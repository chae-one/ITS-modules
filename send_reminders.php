<?php
// =====================================================================
// EVENT REMINDER CRON JOB
//
// Sends the ITS admin inbox two reminder emails for each LCD projector
// request: one ~1 hour before the scheduled start time, and another
// ~30 minutes before. (A separate "request received" confirmation is
// already sent immediately on submission, from inside projector.php.)
//
// SETUP
// -----
// 1) Add the two tracking columns this script needs (run once).
//
//    If you already ran the OLD version of this ALTER TABLE (the one
//    that added a single "reminder_sent" column), drop it first:
//
//      ALTER TABLE projector_requests DROP COLUMN reminder_sent;
//
//    Then add the two new columns:
//
//      ALTER TABLE projector_requests
//        ADD COLUMN reminder_1hr_sent TINYINT(1) NOT NULL DEFAULT 0,
//        ADD COLUMN reminder_30min_sent TINYINT(1) NOT NULL DEFAULT 0;
//
// 2) Schedule this file to run every few minutes via cron, e.g.:
//
//      */5 * * * * /usr/bin/php /full/path/to/send_reminders.php >> /full/path/to/reminders.log 2>&1
//
//    Any interval under 30 minutes works - each stage is only ever sent
//    once per request (its own *_sent column flips to 1 right after a
//    successful send), so more frequent runs just make reminders more
//    punctual.
// =====================================================================

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

// Each reminder stage: how far ahead of the event it fires, which column
// tracks whether it's already been sent, and the wording used in the email.
$REMINDER_STAGES = [
    [
        'minutes' => 60,
        'column'  => 'reminder_1hr_sent',
        'label'   => '1 hour',
    ],
    [
        'minutes' => 30,
        'column'  => 'reminder_30min_sent',
        'label'   => '30 minutes',
    ],
];

$now = new DateTime();

foreach ($REMINDER_STAGES as $stage) {
    $column    = $stage['column'];
    $minutes   = $stage['minutes'];
    $label     = $stage['label'];
    $windowEnd = (clone $now)->modify('+' . $minutes . ' minutes');

    $sql = "SELECT id, name, position, department, contact_number,
                   venue, purpose, date_needed, time_from, time_to, projector_units
            FROM projector_requests
            WHERE `{$column}` = 0" . projector_active_filter($conn); // skip cancelled requests
    $result = $conn->query($sql);

    if (!$result) {
        error_log("send_reminders: query failed for {$column} - " . $conn->error);
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        // Only clock-formatted times (HH:MM, e.g. "08:00") can be reliably
        // parsed into a real datetime. Free-typed times (e.g. "8 AM") are
        // skipped here and won't get an automatic reminder.
        if (!preg_match('/^\d{2}:\d{2}$/', $row['time_from'])) {
            continue;
        }

        try {
            $eventStart = new DateTime($row['date_needed'] . ' ' . $row['time_from']);
        } catch (Exception $e) {
            continue;
        }

        // Not due yet for this stage, or we've already sailed past its window.
        if ($eventStart < $now || $eventStart > $windowEnd) {
            continue;
        }

        $rows = mail_row('Requested By', $row['name'])
              . mail_row('Position', $row['position'])
              . mail_row('Department / Office', $row['department'])
              . mail_row('Contact Number', $row['contact_number'])
              . mail_row('Venue / Room', $row['venue'])
              . mail_row('Purpose / Event', $row['purpose'])
              . mail_row('Date Needed', $row['date_needed'])
              . mail_row('Time', $row['time_from'] . ' to ' . $row['time_to'])
              . mail_row('Number of Units', (string) $row['projector_units']);

        $htmlBody = mail_wrap_table(
            'Reminder: Projector Setup Starting Soon',
            $rows,
            'This is a reminder that the LCD projector setup below is scheduled to begin in about ' . $label . '. Please prepare the equipment and confirm the venue is ready.'
        );
        $plainBody = "Reminder - Projector setup starting in about {$label}\n"
            . "Venue: {$row['venue']}\n"
            . "Time: {$row['time_from']} to {$row['time_to']}\n"
            . "Requested by: {$row['name']} ({$row['department']})\n";

        $sent = send_request_reminder(
            "Reminder ({$label}): Projector Setup Starting Soon",
            $htmlBody,
            $plainBody
        );

        if ($sent) {
            $upd = $conn->prepare("UPDATE projector_requests SET `{$column}` = 1 WHERE id = ?");
            $upd->bind_param('i', $row['id']);
            $upd->execute();
            $upd->close();
        } else {
            error_log("send_reminders: failed to send {$label} reminder for request #{$row['id']}");
        }
    }
}

$conn->close();