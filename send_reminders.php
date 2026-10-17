<?php
// Force PHP's internal clock to local time (see original note: reminder
// windows are compared against date/time staff typed in local wall-clock time).
date_default_timezone_set('Asia/Manila');

// =====================================================================
// EVENT REMINDER CRON JOB  (multi-date aware; projector + Teachmint)
//
// Sends the ITS inbox three reminders for EVERY date of a projector
// or Teachmint request: ~1 day, ~1 hour and ~30 minutes before that date's start time.
//
// A request's dates are:
//   - the primary date  -> date_needed / time_from / time_to
//                          (sent-flags live in the reminder_*_sent columns,
//                           done state in is_done)
//   - extra dates       -> entries in the additional_dates JSON column
//                          (sent-flags live INSIDE each entry, next to its
//                           own "done" flag - index.php's set_done only
//                           touches done/done_at/done_by, so these survive)
//
// Dates already marked done, and cancelled requests, get no reminders.
//
// SETUP: no new columns needed beyond the three reminder_*_sent columns
// already on projector_requests and teachmint_requests (see
// teachmint_requests.sql for the Teachmint table). Cron, every minute:
//   * * * * * /usr/bin/php /full/path/to/send_reminders.php >> /full/path/to/reminders.log 2>&1
// =====================================================================

// Minutes before the exact mark during which a stage is allowed to fire.
// Keep >= your cron interval.
define('WINDOW_MARGIN_MIN', 5);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

/** Normalizes "HH:MM" or "h:mm AM/PM" to 24-hour "HH:MM"; null if unrecognized (e.g. "Whole day"). */
function send_reminders_normalize_time($value) {
    $value = trim((string) $value);
    if ($value === '') return null;

    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    if (preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d)\s*([AaPp][Mm])$/', $value, $m)) {
        $hour = (int) $m[1];
        $minutes = (int) $m[2];
        $suffix = strtoupper($m[3]);
        if ($suffix === 'AM') {
            if ($hour === 12) $hour = 0;
        } else {
            if ($hour !== 12) $hour += 12;
        }
        return sprintf('%02d:%02d', $hour, $minutes);
    }

    return null;
}

/**
 * Records that a stage was sent for one date of a request.
 * Primary date -> its column. Extra date -> the flag inside its JSON entry,
 * updated under a row lock so it can't clobber a concurrent "mark done".
 */
function send_reminders_mark_sent($conn, $table, $requestId, $isPrimary, $date, $column) {
    if ($isPrimary) {
        $upd = $conn->prepare("UPDATE `{$table}` SET `{$column}` = 1 WHERE id = ?");
        $upd->bind_param('i', $requestId);
        $ok = $upd->execute();
        $upd->close();
        return $ok;
    }

    try {
        $conn->begin_transaction();
        $sel = $conn->prepare("SELECT additional_dates FROM `{$table}` WHERE id = ? FOR UPDATE");
        $sel->bind_param('i', $requestId);
        $sel->execute();
        $res = $sel->get_result()->fetch_assoc();
        $sel->close();

        $extra = json_decode($res['additional_dates'] ?? '', true);
        $found = false;
        if (is_array($extra)) {
            foreach ($extra as &$e) {
                if (is_array($e) && ($e['date'] ?? '') === $date) {
                    $e[$column] = 1;
                    $found = true;
                    break;
                }
            }
            unset($e);
        }
        if (!$found) { $conn->rollback(); return false; }

        $json = json_encode($extra);
        $upd = $conn->prepare("UPDATE `{$table}` SET additional_dates = ? WHERE id = ?");
        $upd->bind_param('si', $json, $requestId);
        $upd->execute();
        $upd->close();
        $conn->commit();
        return true;
    } catch (Throwable $t) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        error_log('send_reminders: could not save sent-flag for ' . $table . ' request #' . $requestId . ' - ' . $t->getMessage());
        return false;
    }
}

$REMINDER_STAGES = [
    ['minutes' => 1440, 'column' => 'reminder_1day_sent',  'label' => '1 day'],
    ['minutes' => 60,   'column' => 'reminder_1hr_sent',   'label' => '1 hour'],
    ['minutes' => 30,   'column' => 'reminder_30min_sent', 'label' => '30 minutes'],
];


/**
 * Sends due reminders for every active request in $cfg['table'].
 * $cfg: table, label (used in mail text), units_col, active_filter (SQL).
 */
function send_reminders_run($conn, array $cfg, array $REMINDER_STAGES, DateTime $now) {
    $table  = $cfg['table'];
    $label_ = $cfg['label'];
    $cutoff = date('Y-m-d', strtotime('-1 day'));

    $sql = "SELECT id, name, position, department, venue, purpose,
                   date_needed, time_from, time_to, `{$cfg['units_col']}` AS units,
                   is_done, additional_dates,
                   reminder_1day_sent, reminder_1hr_sent, reminder_30min_sent
            FROM `{$table}`
            WHERE (date_needed >= '{$cutoff}'
                   OR (additional_dates IS NOT NULL AND additional_dates NOT IN ('', '[]')))"
         . $cfg['active_filter'];
    $result = $conn->query($sql);

    if (!$result) {
        error_log("send_reminders: query on {$table} failed - " . $conn->error);
        return;
    }


    while ($row = $result->fetch_assoc()) {
        // ---- Build this request's occurrences: primary date + each extra date ----
        $occurrences = [[
            'primary'   => true,
            'date'      => $row['date_needed'],
            'time_from' => $row['time_from'],
            'time_to'   => $row['time_to'],
            'done'      => !empty($row['is_done']),
            'sent'      => [
                'reminder_1day_sent'  => !empty($row['reminder_1day_sent']),
                'reminder_1hr_sent'   => !empty($row['reminder_1hr_sent']),
                'reminder_30min_sent' => !empty($row['reminder_30min_sent']),
            ],
        ]];

        $extra = json_decode($row['additional_dates'] ?? '', true);
        if (is_array($extra)) {
            foreach ($extra as $e) {
                if (!is_array($e) || empty($e['date'])) continue;
                $occurrences[] = [
                    'primary'   => false,
                    'date'      => (string) $e['date'],
                    'time_from' => $e['time_from'] ?? '',
                    'time_to'   => $e['time_to'] ?? '',
                    'done'      => !empty($e['done']),
                    'sent'      => [
                        'reminder_1day_sent'  => !empty($e['reminder_1day_sent']),
                        'reminder_1hr_sent'   => !empty($e['reminder_1hr_sent']),
                        'reminder_30min_sent' => !empty($e['reminder_30min_sent']),
                    ],
                ];
            }
        }
        $total = count($occurrences);

        foreach ($occurrences as $idx => $occ) {
            if ($occ['done']) continue; // already closed out - no reminders

            $time24 = send_reminders_normalize_time($occ['time_from']);
            if ($time24 === null) continue; // free text like "Whole day"

            try {
                $eventStart = new DateTime($occ['date'] . ' ' . $time24);
            } catch (Exception $e) {
                continue;
            }

            foreach ($REMINDER_STAGES as $stage) {
                $column = $stage['column'];
                $label  = $stage['label'];
                if ($occ['sent'][$column]) continue;

                $windowStart = (clone $now)->modify('+' . max(0, $stage['minutes'] - WINDOW_MARGIN_MIN) . ' minutes');
                $windowEnd   = (clone $now)->modify('+' . $stage['minutes'] . ' minutes');
                if ($eventStart < $windowStart || $eventStart > $windowEnd) continue;

                $rows = mail_row('Requested By', $row['name'])
                      . mail_row('Position', $row['position'])
                      . mail_row('Department / Office', $row['department'])
                      . mail_row('Venue / Room', $row['venue'])
                      . mail_row('Purpose / Event', $row['purpose'])
                      . mail_row('Date Needed', $occ['date'])
                      . ($total > 1 ? mail_row('Date', 'Day ' . ($idx + 1) . ' of ' . $total) : '')
                      . mail_row('Time', $occ['time_from'] . ' to ' . $occ['time_to'])
                      . mail_row('Number of Units', (string) $row['units']);

                $htmlBody = mail_wrap_table(
                    'Reminder: ' . ucfirst($label_) . ' Setup Starting Soon',
                    $rows,
                    'This is a reminder that the ' . $label_ . ' setup below is scheduled to begin in about ' . $label
                        . '. Please prepare the equipment and confirm the venue is ready.'
                );
                $plainBody = "Reminder - {$label_} setup starting in about {$label}\n"
                    . "Date: {$occ['date']}" . ($total > 1 ? ' (Day ' . ($idx + 1) . " of {$total})" : '') . "\n"
                    . "Venue: {$row['venue']}\n"
                    . "Time: {$occ['time_from']} to {$occ['time_to']}\n"
                    . "Requested by: {$row['name']} ({$row['department']})\n";

                $sent = send_request_reminder(
                    "Reminder ({$label}): " . ucfirst($label_) . " Setup Starting Soon",
                    $htmlBody,
                    $plainBody
                );

                if ($sent) {
                    if (!send_reminders_mark_sent($conn, $table, (int) $row['id'], $occ['primary'], (string) $occ['date'], $column)) {
                        error_log("send_reminders: sent {$label} reminder for {$table} request #{$row['id']} ({$occ['date']}) but could not record it - it may repeat");
                    }
                } else {
                    error_log("send_reminders: failed to send {$label} reminder for {$table} request #{$row['id']} ({$occ['date']})");
                }
            }
        }
    }
}

$now = new DateTime();

send_reminders_run($conn, [
    'table'         => 'projector_requests',
    'label'         => 'LCD projector',
    'units_col'     => 'projector_units',
    'active_filter' => projector_active_filter($conn),
], $REMINDER_STAGES, $now);

// Teachmint requests (table created by teachmint_requests.sql). Skipped
// quietly if the table has not been created yet.
$tmTable = $conn->query("SHOW TABLES LIKE 'teachmint_requests'");
if ($tmTable && $tmTable->num_rows > 0) {
    send_reminders_run($conn, [
        'table'         => 'teachmint_requests',
        'label'         => 'Teachmint',
        'units_col'     => 'teachmint_units',
        'active_filter' => ' AND is_cancelled = 0',
    ], $REMINDER_STAGES, $now);
}

$conn->close();