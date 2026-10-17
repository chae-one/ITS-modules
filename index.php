<?php

/* ITS REQUEST PORTAL - DASHBOARD + MANAGEMENT

   One page, four sections (tabs):
    1. Dashboard  - this-week counts, the month calendar, today's
                   agenda, the three request forms and quick links
    2. Request Calendar - full-width month calendar with the colour legend
    3. Email Recipients - the inboxes that receive mailer.php notifications
    4. Settings   - projector inventory cap, per-request cap, daily cap


  Every report can be marked as DONE from its summary popup once the event
  is over (and reopened again). Done reports stay on the calendar with a
  check mark, and drop off the Pending list. The is_done / done_at columns
  are added automatically the first time a report is marked done; run
  done_migration.sql instead if the DB user has no ALTER privilege.

  Marking any request done first asks who fixed / handled it. That name is
  saved in each request table's done_by column (added automatically, same
  as is_done/done_at; run done_by_migration.sql if the DB user has no ALTER
  privilege) and written to the activity log.*/


session_start();

require_once 'db_config.php';
require_once 'settings.php';

// ---- Cron-friendly endpoint for the monthly log archive ----
// A real crontab entry can hit this URL directly (e.g. once a day:
// `curl -s https://.../index.php?cron=archive_logs`) so the archive-and-clear
// does not depend on anyone opening the dashboard. Safe to call as often as
// you like - it only does anything once a month has actually ended.
if (($_GET['cron'] ?? '') === 'archive_logs') {
    $archived = run_monthly_activity_log_archive($conn);
    header('Content-Type: text/plain');
    echo $archived ? ('Archived: ' . implode(', ', $archived)) : 'Nothing to archive.';
    exit;
}

// ---- Manual / automatic PDF download of one month's activity log ----
// Used by the Logs tab's month filter + Download button, and by the
// one-time automatic download that fires right after a month gets archived.
if (isset($_GET['export_logs_pdf'])) {
    export_activity_log_month_pdf($conn, (string) ($_GET['ym'] ?? ''));
    // export_activity_log_month_pdf() exits.
}

// ---- Manual custom date-range export (Logs tab "Generate PDF" popup) ----
// Months already archived get cleared out of the activity_log table (see
// run_monthly_activity_log_archive above), so a date range can only ever
// cover whatever is still live here - it can't reach into an archived
// month's data. Rather than a real PDF, this opens a print-ready page and
// lets the browser's own Print > Save as PDF do the conversion, so it needs
// no extra library and works entirely from this file.
if (isset($_GET['export_logs_range'])) {
    $rangeFrom = (string) ($_GET['from'] ?? '');
    $rangeTo   = (string) ($_GET['to'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeFrom)) $rangeFrom = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeTo))   $rangeTo   = date('Y-m-d');
    if ($rangeFrom > $rangeTo) { $tmp = $rangeFrom; $rangeFrom = $rangeTo; $rangeTo = $tmp; }

    $rangeLogs = array_values(array_filter(get_activity_logs($conn, 100000), function ($log) use ($rangeFrom, $rangeTo) {
        $day = substr((string) $log['created_at'], 0, 10);
        return $day >= $rangeFrom && $day <= $rangeTo;
    }));

    header('Content-Type: text/html; charset=UTF-8');
    ?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Activity Log <?= h($rangeFrom) ?> to <?= h($rangeTo) ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1e293b; margin: 24px; }
  h1 { font-size: 16px; margin: 0 0 4px; }
  p.sub { color: #64748b; margin: 0 0 16px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
  th { background: #f1f5f9; }
  .note { margin-top: 16px; color: #64748b; font-size: 11px; }
  .noprint button { background: #2f6fed; color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font: inherit; font-weight: 700; cursor: pointer; }
  @media print { .noprint { display: none; } }
</style>
</head>
<body>
<p class="noprint"><button onclick="window.print()">Print / Save as PDF</button></p>
<h1>Activity Log</h1>
<p class="sub"><?= h($rangeFrom) ?> to <?= h($rangeTo) ?> - <?= count($rangeLogs) ?> entr<?= count($rangeLogs) === 1 ? 'y' : 'ies' ?></p>
<table>
  <thead><tr><th>When</th><th>Action</th><th>Event</th><th>Requested By</th><th>Details</th></tr></thead>
  <tbody>
  <?php if (!$rangeLogs): ?>
    <tr><td colspan="5">No activity recorded in this range.</td></tr>
  <?php else: foreach ($rangeLogs as $log):
      $rEventType   = trim((string) ($log['event_type'] ?? ''));
      $rRequestedBy = trim((string) ($log['requested_by'] ?? ''));
      $rExtra       = activity_log_detail_text($log['action'], $log['details']);
  ?>
    <tr>
      <td><?= h(format_detail_value('created_at', $log['created_at'])) ?></td>
      <td><?= h(activity_log_action_label($log['action'])) ?></td>
      <td><?= $rEventType !== '' ? h($rEventType) : '—' ?></td>
      <td><?= $rRequestedBy !== '' ? h($rRequestedBy) : '—' ?></td>
      <td><?= h($log['summary']) ?><?= $rExtra !== '' ? '<br><span style="color:#64748b">' . h($rExtra) . '</span>' : '' ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<p class="note noprint">This covers activity still stored on the server. Months already archived and cleared aren't included here - use the month option in the "Generate PDF" popup for those.</p>
</body>
</html>
<?php
    exit;
}

// Runs on every normal page load too, so simply opening the dashboard on or
// after the 1st of the month is enough to trigger the archive even without
// the cron endpoint above. Returns the months (if any) freshly archived by
// THIS call, which the Logs tab uses to auto-download them once.
$freshlyArchivedLogs = run_monthly_activity_log_archive($conn);

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$SITE_NAME = 'ITS Department';
$FORM_TITLE = 'ITS Request Portal';

// Token that proves a POST came from this page's own forms.
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

// ---------------------------------------------------------------------
// POST handling (all actions are CSRF-checked, then Post/Redirect/Get)
// ---------------------------------------------------------------------
$tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($tab, ['dashboard', 'settings', 'calendar', 'mail', 'logs'], true)) $tab = 'dashboard';

$flash = '';
$flashType = 'ok';
$FLASH_MESSAGES = [
    'settings_saved'    => 'Settings saved.',
    'recipient_added'   => 'Recipient added.',
    'recipient_updated' => 'Recipient changes saved.',
    'recipient_deleted' => 'Recipient removed.',
    'recipient_dupe'    => 'That email address is already on the list.',
    'recipient_bad'     => 'Please enter a valid email address.',
    'recipients_partial'=> 'Some changes were saved, but one or more entries were skipped (invalid or duplicate email).',
    'settings_bad'      => 'Those numbers look wrong - check the ranges and try again.',
    'daily_exceeds_total'=> 'Maximum requests per day cannot be higher than the total projectors available.',
    'csrf'              => 'Your form expired. Please try again.',
    'request_cancelled' => 'Request cancelled. The record has been kept.',
    'cancel_notfound'   => 'That request was not found, or it is already cancelled.',
    'cancel_bad'        => 'Could not cancel that request - the details were incomplete.',
    'cancel_setup'      => 'Could not add the cancellation columns to the database. Run cancel_migration.sql, then try again.',
    'request_done'      => 'Report marked as done.',
    'request_reopened'  => 'Report reopened - it is active again.',
    'done_notfound'     => 'That report was not found, is cancelled, or was already updated.',
    'done_bad'          => 'Could not update that report - the details were incomplete.',
    'done_setup'        => 'Could not add the "done" columns to the database. Run done_migration.sql, then try again.',
    'done_future'       => 'That date has not happened yet - it can only be marked done on or after its own date.',
    'done_handler_required' => 'Please say who fixed or handled this request before marking it done.',
    'request_updated'   => 'Report updated.',
    'edit_bad'          => 'Could not save that report - please reopen it and try again.',
];
if (isset($_GET['msg']) && isset($FLASH_MESSAGES[$_GET['msg']])) {
    $flash = $FLASH_MESSAGES[$_GET['msg']];
    $flashType = in_array($_GET['msg'], ['recipient_dupe', 'recipient_bad', 'settings_bad', 'daily_exceeds_total', 'recipients_partial', 'csrf', 'cancel_notfound', 'cancel_bad', 'cancel_setup', 'edit_bad', 'done_notfound', 'done_bad', 'done_setup', 'done_future', 'done_handler_required'], true) ? 'err' : 'ok';
}

function redirect_back($tab, $msg, $ym = '') {
    $url = basename(__FILE__) . '?tab=' . urlencode($tab) . '&msg=' . urlencode($msg);
    if ($ym !== '') $url .= '&ym=' . urlencode($ym);
    $url .= '#manage';
    header('Location: ' . $url);
    exit;
}

$action = $_POST['action'] ?? '';
$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'); // the report editor saves with fetch()

/** JSON reply for the report editor. Drops any stray output first so the JSON always parses. */
function json_reply(array $payload) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if ($action !== '') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        if ($isAjax) json_reply(['ok' => false, 'error' => 'Your form expired. Reload the page and try again.']);
        redirect_back($tab, 'csrf');
    }

    // ---- Settings -------------------------------------------------
    if ($action === 'save_settings') {
        $total   = (int) ($_POST['projector_total_units'] ?? 0);
        $perReq  = (int) ($_POST['max_units_per_request'] ?? 0);
        $perDay  = (int) ($_POST['daily_request_limit'] ?? 0);

        // A per-request cap larger than the whole inventory would be
        // meaningless, so it is clamped rather than rejected.
        if ($total < 1 || $total > 100 || $perReq < 1 || $perDay < 1 || $perDay > 100) {
            redirect_back('settings', 'settings_bad');
        }
        // The daily request cap is capped by the projector inventory itself -
        // there's no point accepting more requests in a day than there are
        // projectors to hand out, so this is rejected rather than clamped.
        if ($perDay > $total) {
            redirect_back('settings', 'daily_exceeds_total');
        }
        $perReq = min($perReq, $total);

        $before = get_settings($conn);
        set_setting($conn, 'projector_total_units', $total);
        set_setting($conn, 'max_units_per_request', $perReq);
        set_setting($conn, 'daily_request_limit',   $perDay);
        log_activity($conn, 'settings_updated', '', '', 'Projector borrowing limits changed', [
            'before' => $before,
            'after'  => [
                'projector_total_units' => $total,
                'max_units_per_request' => $perReq,
                'daily_request_limit'   => $perDay,
            ],
        ]);
        redirect_back('settings', 'settings_saved');
    }

    // ---- Mail recipients ------------------------------------------
    if ($action === 'recipient_add') {
        $email = trim($_POST['email'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_back('mail', 'recipient_bad');
        }
        $stmt = $conn->prepare("INSERT INTO mail_recipients (email, name, is_active) VALUES (?, ?, 1)");
        $stmt->bind_param('ss', $email, $name);
        $ok = $stmt->execute();
        $newId = $ok ? $conn->insert_id : null;
        $stmt->close();
        if ($ok) {
            log_activity($conn, 'recipient_added', '', '', "Added recipient $email", ['email' => $email, 'name' => $name]);
        }
        redirect_back('mail', $ok ? 'recipient_added' : 'recipient_dupe');
    }

    // One submit updates every row on the page at once, so editing two
    // addresses and clicking a single "Save changes" button saves both -
    // there is no longer a per-row Save button to lose the other edits.
    // Fields are posted as email[id], name[id], is_active[id].
    if ($action === 'recipients_bulk_save') {
        $emails    = $_POST['email'] ?? [];
        $names     = $_POST['name'] ?? [];
        $actives   = $_POST['is_active'] ?? [];
        $notifies  = $_POST['notify_new'] ?? [];
        $hasNotify = ensure_mail_recipient_notify_column($conn); // adds the column on first use
        $savedOk   = 0;
        $savedBad  = 0;

        $stmt = $hasNotify
            ? $conn->prepare("UPDATE mail_recipients SET email = ?, name = ?, is_active = ?, notify_new_requests = ? WHERE id = ?")
            : $conn->prepare("UPDATE mail_recipients SET email = ?, name = ?, is_active = ? WHERE id = ?");
        foreach ($emails as $id => $rawEmail) {
            $id = (int) $id;
            if ($id < 1) continue;

            $email  = trim($rawEmail);
            $name   = trim($names[$id] ?? '');
            $active = isset($actives[$id]) ? 1 : 0;
            $notify = isset($notifies[$id]) ? 1 : 0;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $savedBad++;
                continue;
            }

            if ($hasNotify) {
                $stmt->bind_param('ssiii', $email, $name, $active, $notify, $id);
            } else {
                $stmt->bind_param('ssii', $email, $name, $active, $id);
            }
            if ($stmt->execute()) {
                $savedOk++;
            } else {
                // Most likely the unique-email constraint (a duplicate).
                $savedBad++;
            }
        }
        $stmt->close();

        if ($savedOk > 0) {
            $summary = "Updated $savedOk recipient(s)" . ($savedBad > 0 ? ", $savedBad skipped" : '');
            log_activity($conn, 'recipient_updated', '', '', $summary, ['saved' => $savedOk, 'skipped' => $savedBad]);
        }

        if ($savedBad === 0) {
            redirect_back('mail', 'recipient_updated');
        } elseif ($savedOk > 0) {
            redirect_back('mail', 'recipients_partial');
        } else {
            redirect_back('mail', 'recipient_dupe');
        }
    }

    if ($action === 'recipient_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Grab the email first, purely so the log entry can name who was removed.
            $email = null;
            if ($res = $conn->query("SELECT email FROM mail_recipients WHERE id = " . $id)) {
                if ($row = $res->fetch_assoc()) $email = $row['email'];
                $res->free();
            }

            $stmt = $conn->prepare("DELETE FROM mail_recipients WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            if ($deleted > 0) {
                log_activity($conn, 'recipient_deleted', '', '', 'Removed recipient' . ($email ? " $email" : ''), ['email' => $email]);
            }
        }
        redirect_back('mail', 'recipient_deleted');
    }

    // ---- Cancel a request -----------------------------------------
    // Nothing is deleted: the row is flagged (is_cancelled, cancelled_at,
    // cancel_reason) so the full record stays in the database and on the
    // calendar, and only stops counting toward projector availability.
    if ($action === 'cancel_request') {
        $tables = request_tables();
        $post   = function ($k) { return (isset($_POST[$k]) && is_string($_POST[$k])) ? trim($_POST[$k]) : ''; };
        $type   = $post('type');
        $id     = (int) $post('id');
        $reason = $post('reason');
        if (mb_strlen($reason) > 255) $reason = mb_substr($reason, 0, 255);
        $backTab = in_array($post('back_tab'), ['dashboard', 'calendar'], true) ? $post('back_tab') : 'calendar';
        $backYm  = preg_match('/^\d{4}-\d{2}$/', $post('ym')) ? $post('ym') : '';

        if (!isset($tables[$type]) || $id < 1) {
            redirect_back($backTab, 'cancel_bad', $backYm);
        }
        $table = $tables[$type]; // whitelisted above, so safe to use in the query
        if (!ensure_cancel_columns($conn, $table)) {
            redirect_back($backTab, 'cancel_setup', $backYm);
        }
        if ($type === 'projector') ensure_projector_event_columns($conn);

        // A report already marked done has taken place, so it can't be cancelled.
        $notDone = table_has_column($conn, $table, 'is_done') ? ' AND is_done = 0' : '';
        $stmt = $conn->prepare("UPDATE `$table` SET is_cancelled = 1, cancelled_at = NOW(), cancel_reason = ?
                                 WHERE id = ? AND is_cancelled = 0" . $notDone);
        $stmt->bind_param('si', $reason, $id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed > 0) {
            $logRow = [];
            if ($res = @$conn->query("SELECT * FROM `$table` WHERE id = " . (int) $id)) {
                $logRow = $res->fetch_assoc() ?: [];
                $res->free();
            }
            $eventType = tm_log_event($type, $logRow); // projector: its Purpose / Event
            $requester = trim((string) ($logRow['name'] ?? ''));
            $label   = tm_log_label($type, $logRow);
            $summary = "$label cancelled" . ($reason !== '' ? " ($reason)" : '');
            log_activity($conn, 'request_cancelled', $eventType, $requester, $summary,
                         ['reason' => $reason] + tm_log_detail($type, $logRow));
        }
        redirect_back($backTab, $changed > 0 ? 'request_cancelled' : 'cancel_notfound', $backYm);
    }

    // ---- Mark a report done / reopen it -----------------------------
    // For when the event is over. Nothing is deleted or moved: the record is
    // flagged so it stays on the calendar with a check mark and stops
    // showing as pending. state=1 marks it done, state=0 reopens it.
    //
    // A projector request can span several dates, and each date is its own
    // occurrence - marking Tuesday's date done must not touch Thursday's.
    // The first date (date_needed) still uses the row's own is_done/done_at
    // columns; every other date carries its own done/done_at inside its
    // entry in additional_dates. Software and service requests only ever
    // have one date, so they keep working exactly as before.
    //
    // A date that has not happened yet cannot be marked done - except its
    // own day, so a request can still be closed out early if it finishes
    // earlier than planned.
    if ($action === 'set_done') {
        $tables = request_tables();
        $post   = function ($k) { return (isset($_POST[$k]) && is_string($_POST[$k])) ? trim($_POST[$k]) : ''; };
        $type   = $post('type');
        $id     = (int) $post('id');
        $state  = $post('state') === '1' ? 1 : 0;
        $date   = $post('date'); // which occurrence this action applies to (Y-m-d)
        // Who fixed / handled the request - asked for in a popup before any
        // request can be confirmed done.
        $handledBy = mb_substr($post('handled_by'), 0, 150);
        $backTab = in_array($post('back_tab'), ['dashboard', 'calendar'], true) ? $post('back_tab') : 'calendar';
        $backYm  = preg_match('/^\d{4}-\d{2}$/', $post('ym')) ? $post('ym') : '';

        if (!isset($tables[$type]) || $id < 1) {
            redirect_back($backTab, 'done_bad', $backYm);
        }
        if ($state === 1 && $handledBy === '') {
            redirect_back($backTab, 'done_handler_required', $backYm);
        }
        $table = $tables[$type]; // whitelisted above, so safe to use in the query
        // is_cancelled is used below, so both column sets must exist.
        if (!ensure_cancel_columns($conn, $table) || !ensure_done_columns($conn, $table)) {
            redirect_back($backTab, 'done_setup', $backYm);
        }
        if (!ensure_done_by_column($conn, $table)) {
            redirect_back($backTab, 'done_setup', $backYm);
        }

        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? AND is_cancelled = 0");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = ($res = $stmt->get_result()) ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            redirect_back($backTab, 'done_notfound', $backYm);
        }

        // Pin down which date this applies to. A projector request that
        // doesn't recognise the posted date falls back to its first date;
        // software/service requests only ever have the one date they were
        // filed for.
        if (is_booking_type($type)) {
            $occDates = array_column(projector_occurrences($row), 'date');
            if (!in_array($date, $occDates, true)) $date = (string) $row['date_needed'];
        } else {
            $date = (string) ($row['date_requested'] ?? '');
        }

        // Can't close out a date that hasn't arrived yet - today is fine,
        // so an event can still be marked done earlier the same day.
        if ($state === 1 && $date !== '' && $date > date('Y-m-d')) {
            redirect_back($backTab, 'done_future', $backYm);
        }

        if (is_booking_type($type) && $date !== (string) $row['date_needed']) {
            // One of the additional dates - flip just that occurrence inside
            // the JSON, leaving the row's own is_done (and every other date)
            // untouched.
            $extra = json_decode($row['additional_dates'] ?? '', true);
            $changed = 0;
            if (is_array($extra)) {
                foreach ($extra as &$e) {
                    if (!is_array($e) || ($e['date'] ?? '') !== $date) continue;
                    $already = !empty($e['done']);
                    if (($state === 1) === $already) break; // already in the requested state
                    $e['done']    = $state === 1;
                    $e['done_at'] = $state === 1 ? date('Y-m-d H:i:s') : null;
                    $e['done_by'] = $state === 1 ? $handledBy : null;
                    $changed = 1;
                    break;
                }
                unset($e);
            }
            if ($changed) {
                $json = json_encode($extra);
                $stmt = $conn->prepare("UPDATE `$table` SET additional_dates = ? WHERE id = ? AND is_cancelled = 0");
                $stmt->bind_param('si', $json, $id);
                $stmt->execute();
                $changed = $stmt->affected_rows;
                $stmt->close();
            }
        } else {
            if ($state === 1) {
                $stmt = $conn->prepare("UPDATE `$table` SET is_done = 1, done_at = NOW(), done_by = ?
                                         WHERE id = ? AND is_cancelled = 0 AND is_done = 0");
                $stmt->bind_param('si', $handledBy, $id);
            } else {
                $stmt = $conn->prepare("UPDATE `$table` SET is_done = 0, done_at = NULL, done_by = NULL
                                         WHERE id = ? AND is_cancelled = 0 AND is_done = 1");
                $stmt->bind_param('i', $id);
            }
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
        }
        if ($changed) {
            $action_tag = $state === 1 ? 'request_done' : 'request_reopened';
            $verb       = $state === 1 ? 'marked done' : 'reopened';
            $eventType  = tm_log_event($type, $row); // projector: its Purpose / Event
            $requester  = trim((string) ($row['name'] ?? ''));
            $label      = tm_log_label($type, $row);
            $summary    = "$label $verb" . ($date !== '' ? " for $date" : '')
                        . ($state === 1 && $handledBy !== '' ? " - handled by $handledBy" : '');
            $logDetail  = ['date' => $date] + tm_log_detail($type, $row);
            if ($state === 1) $logDetail['handled_by'] = $handledBy;
            log_activity($conn, $action_tag, $eventType, $requester, $summary, $logDetail);
        }
        redirect_back($backTab, $changed > 0 ? ($state === 1 ? 'request_done' : 'request_reopened') : 'done_notfound', $backYm);
    }

    // ---- Edit a request ---------------------------------------------
    // Called with fetch() from the report modal. Replies with JSON so a
    // validation problem can be shown inside the editor without losing
    // what was typed; on success the page reloads with a flash message.
    if ($action === 'edit_request') {
        if (!$isAjax) redirect_back($tab, 'edit_bad');

        $type   = (isset($_POST['type']) && is_string($_POST['type'])) ? trim($_POST['type']) : '';
        $result = apply_request_edit($conn, $type, (int) ($_POST['id'] ?? 0), $_POST);
        if (!$result['ok']) json_reply(['ok' => false, 'error' => $result['error']]);

        $backTab = in_array($_POST['back_tab'] ?? '', ['dashboard', 'calendar'], true) ? $_POST['back_tab'] : 'calendar';
        $backYm  = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['ym'] ?? '')) ? $_POST['ym'] : '';
        $url = basename(__FILE__) . '?tab=' . urlencode($backTab) . '&msg=request_updated'
             . ($backYm !== '' ? '&ym=' . urlencode($backYm) : '') . '#manage';
        json_reply(['ok' => true, 'redirect' => $url]);
    }
}

// ---------------------------------------------------------------------
// Page data
// ---------------------------------------------------------------------
ensure_projector_event_columns($conn); // event_type + participants (no-op once they exist)
$settings   = get_settings($conn);
$totalUnits = (int) $settings['projector_total_units'];
$recipients = get_mail_recipients($conn, false);
// Optional date-range filter for the Logs tab (?log_from=YYYY-MM-DD&log_to=YYYY-MM-DD). Filtering
// happens in SQL, so it reaches back past the latest 200 entries; the result is capped at 1000 rows.
$logFrom = (string) ($_GET['log_from'] ?? '');
$logTo   = (string) ($_GET['log_to'] ?? '');
$validYmd = function ($d) { $dt = DateTime::createFromFormat('Y-m-d', $d); return $dt && $dt->format('Y-m-d') === $d; };
if (!$validYmd($logFrom)) $logFrom = '';
if (!$validYmd($logTo))   $logTo   = '';
if ($logFrom !== '' && $logTo !== '' && $logFrom > $logTo) { $tmp = $logFrom; $logFrom = $logTo; $logTo = $tmp; }
$logFiltered  = ($logFrom !== '' || $logTo !== '');
$activityLogs = $logFiltered
    ? get_activity_logs($conn, 1000, $logFrom !== '' ? $logFrom : null, $logTo !== '' ? $logTo : null)
    : get_activity_logs($conn, 200); // always loaded: switching to the Logs tab is client-side, no page reload
$activityLogMonths   = get_activity_log_month_counts($conn); // months still in the live table (usually just the current one)
$activityLogArchives = list_activity_log_archives($conn);    // past months already exported to PDF and cleared

// ---- Calendar month ----
$ym = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');
$monthStart = $ym . '-01';
$monthTs    = strtotime($monthStart);
$daysInMonth = (int) date('t', $monthTs);
$monthEnd   = date('Y-m-t', $monthTs);
$prevYm     = date('Y-m', strtotime('-1 month', $monthTs));
$nextYm     = date('Y-m', strtotime('+1 month', $monthTs));
$firstWeekday = (int) date('w', $monthTs); // 0 = Sunday

/** 24h "HH:MM" -> "8:30 AM"; anything else is passed through as typed. */
function fmt_time($t) {
    $t = (string) $t; // nullable TIME columns arrive as NULL
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($t), $m)) {
        $hh = (int) $m[1];
        $suffix = $hh >= 12 ? 'PM' : 'AM';
        $h12 = $hh % 12; if ($h12 === 0) $h12 = 12;
        return $h12 . ':' . $m[2] . ' ' . $suffix;
    }
    return trim($t);
}

/** Purpose/software text for a chip label - shows at least 11 characters, then trails off with "..." if longer. */
function chip_purpose($text) {
    $text = trim((string) $text);
    if ($text === '') return 'Request';
    if (mb_strlen($text) <= 11) return $text;
    return mb_substr($text, 0, 11) . '…';
}

/** Turns one raw column value into readable text for the event details modal. */
function format_detail_value($col, $raw) {
    if ($raw === null) return '';
    $raw = trim((string) $raw);
    if ($raw === '') return '';

    if ($col === 'projector_units' || $col === 'teachmint_units') return $raw . ((int) $raw === 1 ? ' unit' : ' units');
    if ($col === 'consent')         return $raw === '1' ? 'Yes' : 'No';

    // JSON lists (licensed software, freeware, problem types, ...) -> "A, B, C"
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $flat = [];
        array_walk_recursive($decoded, function ($v) use (&$flat) {
            $v = trim((string) $v);
            if ($v !== '') $flat[] = $v;
        });
        return implode(', ', $flat);
    }

    // Dates and timestamps -> "September 19, 2026" / "September 19, 2026 2:22 PM"
    if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T](\d{2}:\d{2})(?::\d{2})?)?$/', $raw, $m)) {
        $ts = strtotime($raw);
        if ($ts) return date(empty($m[1]) ? 'F j, Y' : 'F j, Y g:i A', $ts);
    }
    return $raw;
}

/**
 * Every column of a request row as a [label, value] list for the details
 * modal. Known columns come first in a fixed order; any other column in the
 * table (contact number, status, timestamps, ...) is appended automatically,
 * so nothing stored for a request is left out of the popup.
 */
function request_details(array $row) {
    $labels = [
        'name'                => 'Requested By',
        'position'            => 'Position',
        'department'          => 'Department / Office',
        'venue'               => 'Venue',
        'purpose'             => 'Purpose',
        'event_type'          => 'Type of Event',
        'participants'        => 'Number of Participants',
        'projector_units'     => 'Projector Units',
        'teachmint_units'     => 'Teachmint Units',
        'date_requested'      => 'Date Requested',
        'computer_laboratory' => 'Computer Laboratory',
        'licensed_software'   => 'Licensed Software',
        'freeware'            => 'Freeware',
        'supervisor_name'     => 'Supervisor / Dept. Head / Dean',
        'immediate_superior'  => 'Immediate Superior',
        'equipment'           => 'Equipment',
        'remarks'             => 'Remarks',
        'problem_type'        => 'Problem Encountered',
        'description'         => 'Description',
        'consent'             => 'Data Privacy Consent',
        'created_at'          => 'Submitted On',
        'submitted_at'        => 'Submitted On',
        'updated_at'          => 'Last Updated',
        'incident_date'       => 'Date of Incident',
        'incident_time'       => 'Time of Incident',
        'location'            => 'Location / Venue',
        'category'            => 'Incident Category',
        'affected_systems'    => 'Systems / Equipment Affected',
        'action_taken'        => 'Immediate Action Taken',
        'status'              => 'Current Status',
    ];
    $order = ['name', 'position', 'department', 'immediate_superior',
              'venue', 'purpose', 'event_type', 'participants', 'problem_type', 'description', 'equipment',
              'projector_units', 'teachmint_units', 'date_requested', 'computer_laboratory',
              'licensed_software', 'freeware', 'supervisor_name',
              'incident_date', 'incident_time', 'location', 'category', 'affected_systems',
              'action_taken', 'status', 'remarks'];
    // Not repeated as rows: the id (never shown on this page) and the
    // date/time columns (shown together in the Schedule block instead).
    $skip = ['id', 'date_needed', 'time_from', 'time_to', 'additional_dates',
             'is_cancelled', 'cancelled_at', 'cancel_reason',   // cancellation is shown as its own notice
             'is_done', 'done_at', 'done_by',                   // "done" is shown as its own badge + note
             'reminder_1hr_sent', 'reminder_30min_sent'];       // internal flags for send_reminders.php

    $out = [];
    foreach ($order as $col) {
        if (!array_key_exists($col, $row)) continue;
        $val = format_detail_value($col, $row[$col]);
        $out[] = ['label' => $labels[$col], 'value' => $val === '' ? '—' : $val];
    }
    foreach ($row as $col => $raw) {
        if (in_array($col, $order, true) || in_array($col, $skip, true)) continue;
        $val = format_detail_value($col, $raw);
        if ($val === '') continue;
        $out[] = ['label' => $labels[$col] ?? ucwords(str_replace('_', ' ', $col)), 'value' => $val];
    }
    return $out;
}

/**
 * The Logs tab's "Details" column, in one short line, e.g.
 * "Venue: Room 1 → Room 2; Date Needed: 2026-09-20 → 2026-09-22" for a
 * schedule edit, or "Reason: no longer needed" for a cancellation.
 * Returns '' when the log entry's summary already says everything.
 */
function activity_log_detail_text($action, array $details) {
    if (!$details) return '';

    // A new request: the date(s) it is for, then where and when it was filed.
    if ($action === 'request_submitted') {
        $parts = [];
        if (!empty($details['date_needed'])) {
            $slot = $details['date_needed'];
            if (!empty($details['time_from']) || !empty($details['time_to'])) {
                $slot .= ' (' . fmt_time($details['time_from'] ?? '') . ' - ' . fmt_time($details['time_to'] ?? '') . ')';
            }
            $parts[] = 'Date Needed: ' . $slot;
        }
        if (!empty($details['more_dates']) && is_array($details['more_dates'])) {
            $parts[] = 'Also: ' . implode(', ', $details['more_dates']);
        }
        if (!empty($details['venue']))          $parts[] = 'Venue: ' . $details['venue'];
        if (!empty($details['units']))          $parts[] = $details['units'] . ((int) $details['units'] === 1 ? ' unit' : ' units');
        if (!empty($details['date_requested'])) $parts[] = 'Date Requested: ' . $details['date_requested'];
        return implode('; ', $parts);
    }

    if ($action === 'request_updated') {
        $parts = [];
        foreach ($details as $col => $chg) {
            if (!is_array($chg) || !array_key_exists('from', $chg) || !array_key_exists('to', $chg)) continue;
            $label = ucwords(str_replace('_', ' ', $col));
            $from  = ($chg['from'] === null || $chg['from'] === '') ? '—' : $chg['from'];
            $to    = ($chg['to']   === null || $chg['to']   === '') ? '—' : $chg['to'];
            $parts[] = "$label: $from \xE2\x86\x92 $to"; // "\xE2\x86\x92" = →
        }
        return implode('; ', $parts);
    }

    if ($action === 'settings_updated' && isset($details['before'], $details['after']) && is_array($details['after'])) {
        $parts = [];
        foreach ($details['after'] as $key => $val) {
            $old = $details['before'][$key] ?? null;
            if ((string) $old === (string) $val) continue;
            $label = ucwords(str_replace('_', ' ', $key));
            $parts[] = "$label: $old \xE2\x86\x92 $val";
        }
        return implode('; ', $parts);
    }

    if (!empty($details['reason'])) return 'Reason: ' . $details['reason'];
    if (!empty($details['email']))  return (string) $details['email'];
    if (!empty($details['date']) && !empty($details['handled_by'])) {
        return 'Date: ' . $details['date'] . '; Set up/pulled out by: ' . $details['handled_by'];
    }
    if (!empty($details['date']))   return 'Date: ' . $details['date'];
    if (isset($details['saved']))   return $details['saved'] . ' saved' . (!empty($details['skipped']) ? ', ' . $details['skipped'] . ' skipped' : '');

    return '';
}

/** Every date/time a projector request is booked for (first date + additional_dates), earliest first. */
function projector_schedule(array $row) {
    $slot = function ($date, $from, $to) {
        $from = fmt_time($from); $to = fmt_time($to);
        $time = ($from !== '' && $to !== '') ? $from . ' - ' . $to : trim($from . $to);
        return ['date' => $date, 'time' => $time];
    };
    $out = [$slot($row['date_needed'], $row['time_from'], $row['time_to'])];
    $extra = json_decode($row['additional_dates'] ?? '', true);
    if (is_array($extra)) {
        foreach ($extra as $e) {
            if (is_array($e) && !empty($e['date'])) {
                $out[] = $slot($e['date'], $e['time_from'] ?? '', $e['time_to'] ?? '');
            }
        }
    }
    usort($out, function ($a, $b) { return strcmp($a['date'], $b['date']); });
    return $out;
}

/** Cancellation state of a request row (columns may not exist until the first cancel). */
function cancel_fields(array $row) {
    $on = !empty($row['is_cancelled']);
    return [
        'cancelled'     => $on,
        'cancelled_at'  => $on ? format_detail_value('cancelled_at', $row['cancelled_at'] ?? '') : '',
        'cancel_reason' => $on ? trim((string) ($row['cancel_reason'] ?? '')) : '',
    ];
}

/** Adds the cancellation columns to a request table if they are not there yet. */
function ensure_cancel_columns($conn, $table) {
    $wanted = [
        'is_cancelled'  => 'TINYINT(1) NOT NULL DEFAULT 0',
        'cancelled_at'  => 'DATETIME NULL DEFAULT NULL',
        'cancel_reason' => 'VARCHAR(255) NULL DEFAULT NULL',
    ];
    try {
        foreach ($wanted as $col => $definition) {
            $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            $exists = $res && $res->num_rows > 0;
            if ($res instanceof mysqli_result) $res->free();
            if (!$exists && !$conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition")) return false;
        }
    } catch (Throwable $e) {
        return false; // e.g. the DB user has no ALTER privilege
    }
    return true;
}

/** Done state of a request row (columns may not exist until the first report is marked done). */
function done_fields(array $row) {
    $on = !empty($row['is_done']);
    return [
        'done'    => $on,
        'done_at' => $on ? format_detail_value('done_at', $row['done_at'] ?? '') : '',
        'done_by' => $on ? trim((string) ($row['done_by'] ?? '')) : '',
    ];
}

/**
 * Per-date done state for a projector request: 'Y-m-d' => ['done'=>bool, 'done_at'=>string].
 * The first date (date_needed) reads the row's own is_done/done_at; every
 * other date reads the done/done_at kept inside its additional_dates entry.
 * This is what lets one date of a multi-date request be marked done without
 * marking the others done too.
 */
function projector_done_map(array $row) {
    $map = [];
    $on = !empty($row['is_done']);
    $map[(string) $row['date_needed']] = [
        'done'    => $on,
        'done_at' => $on ? format_detail_value('done_at', $row['done_at'] ?? '') : '',
        'done_by' => $on ? trim((string) ($row['done_by'] ?? '')) : '',
    ];
    $extra = json_decode($row['additional_dates'] ?? '', true);
    if (is_array($extra)) {
        foreach ($extra as $e) {
            if (!is_array($e) || empty($e['date'])) continue;
            $eOn = !empty($e['done']);
            $map[(string) $e['date']] = [
                'done'    => $eOn,
                'done_at' => $eOn ? format_detail_value('done_at', $e['done_at'] ?? '') : '',
                'done_by' => $eOn ? trim((string) ($e['done_by'] ?? '')) : '',
            ];
        }
    }
    return $map;
}

/** True once every date on a projector request has been marked done. */
function projector_all_done(array $row) {
    $map = projector_done_map($row);
    if (!$map) return false;
    foreach ($map as $info) {
        if (empty($info['done'])) return false;
    }
    return true;
}

/** True when the table has the given column (checked with SHOW COLUMNS; $table / $col come from code, never from the request). */
function table_has_column($conn, $table, $col) {
    try {
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        $has = $res && $res->num_rows > 0;
        if ($res instanceof mysqli_result) $res->free();
        return $has;
    } catch (Throwable $e) {
        return false;
    }
}

/** Adds the "done" columns to a request table if they are not there yet. */
function ensure_done_columns($conn, $table) {
    $wanted = [
        'is_done' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'done_at' => 'DATETIME NULL DEFAULT NULL',
    ];
    try {
        foreach ($wanted as $col => $definition) {
            if (!table_has_column($conn, $table, $col) && !$conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition")) return false;
        }
    } catch (Throwable $e) {
        return false; // e.g. the DB user has no ALTER privilege
    }
    return true;
}

/**
 * Adds the done_by column to a request table if it is not there yet - the
 * name of whoever fixed / handled the request, captured by the popup shown
 * before any request is confirmed done.
 */
function ensure_done_by_column($conn, $table) {
    try {
        if (!table_has_column($conn, $table, 'done_by') && !$conn->query("ALTER TABLE `$table` ADD COLUMN `done_by` VARCHAR(150) NULL DEFAULT NULL")) return false;
    } catch (Throwable $e) {
        return false; // e.g. the DB user has no ALTER privilege
    }
    return true;
}

/** Database table behind each request type (a whitelist, so safe to use in queries). */
function request_tables() {
    return [
        'projector' => 'projector_requests',
        'teachmint' => 'teachmint_requests',
        'software'  => 'software_request',
        'service'   => 'service_call_requests',
        'incident'  => 'incident_reports',
    ];
}

// ---- Teachmint limit helpers (guarded so the same code can live in both teachmint.php and index.php) ----
if (!function_exists('teachmint_setting_int')) {
    /** Reads an integer from app_settings; returns $default if missing/invalid. */
    function teachmint_setting_int($conn, $key, $default) {
        $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        if (!$stmt) return $default;
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !preg_match('/^\d+$/', trim((string) $row['setting_value']))) return $default;
        return (int) $row['setting_value'];
    }
}

if (!function_exists('teachmint_day_usage')) {
    /**
     * How many (non-cancelled) Teachmint requests and units are already booked
     * on $date, counting both the primary date and any additional_dates entries.
     * @return array ['requests' => int, 'units' => int]
     */
    function teachmint_day_usage($conn, $date) {
        $out = ['requests' => 0, 'units' => 0];
        $like = '%' . $date . '%';
        $stmt = $conn->prepare(
            "SELECT teachmint_units, date_needed, additional_dates
             FROM teachmint_requests
             WHERE is_cancelled = 0 AND (date_needed = ? OR additional_dates LIKE ?)"
        );
        if (!$stmt) return $out;
        $stmt->bind_param('ss', $date, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $hits = ($r['date_needed'] === $date) ? 1 : 0;
            $extra = json_decode($r['additional_dates'] ?? '', true);
            if (is_array($extra)) {
                foreach ($extra as $e) {
                    if (is_array($e) && ($e['date'] ?? '') === $date) $hits++;
                }
            }
            $out['requests'] += $hits;
            $out['units']    += $hits * (int) $r['teachmint_units'];
        }
        $stmt->close();
        return $out;
    }
}

/** Request types that are booked for a date + time range (projector, Teachmint). */
function is_booking_type($type) {
    return $type === 'projector' || $type === 'teachmint';
}

/** Per-type settings for the booking types: units column, limits and day usage. */
function booking_profile($type) {
    if ($type === 'teachmint') {
        return [
            'units_col'  => 'teachmint_units',
            'unit_label' => 'Teachmint',
            'plural'     => 'Teachmint units',
            'total'      => function ($conn) { return teachmint_setting_int($conn, 'teachmint_total_units', 2); },
            'limit'      => function ($conn) { return teachmint_setting_int($conn, 'teachmint_daily_request_limit', 5); },
            'usage'      => function ($conn, $d) { return teachmint_day_usage($conn, $d); },
        ];
    }
    return [
        'units_col'  => 'projector_units',
        'unit_label' => 'Projector',
        'plural'     => 'projectors',
        'total'      => function ($conn) { return get_setting_int($conn, 'projector_total_units'); },
        'limit'      => function ($conn) { return get_setting_int($conn, 'daily_request_limit'); },
        'usage'      => function ($conn, $d) { return projector_day_usage($conn, $d); },
    ];
}

/* Logging wrappers: Teachmint is described here; every other type goes to the
   original helpers in settings.php unchanged. */
function tm_log_event($type, array $row) {
    if ($type === 'teachmint') return (string) ($row['purpose'] ?? '');
    return request_log_event($type, $row);
}
function tm_log_label($type, array $row) {
    if ($type === 'teachmint') {
        $et = trim((string) ($row['event_type'] ?? ''));
        return 'Teachmint request' . ($et !== '' ? " ($et)" : '');
    }
    return request_log_label_from_row($type, $row);
}
function tm_log_detail($type, array $row) {
    if ($type === 'teachmint') return ['type_of_event' => trim((string) ($row['event_type'] ?? ''))];
    return request_log_type_detail($type, $row);
}

/**
 * The columns the report editor may change for each request type, in the
 * order they appear in the form. Anything not listed here (reference number,
 * equipment list, additional dates, cancellation flags, ...) can never be
 * written by an edit. A column that does not exist in the table is skipped.
 *
 * kind: name (letters only) | text | textarea | date | time | int | event
 */
function edit_field_specs($type) {
    $who = [
        'name'       => ['label' => 'Requested By',        'kind' => 'name', 'required' => true],
        'position'   => ['label' => 'Position',            'kind' => 'text', 'required' => true],
        'department' => ['label' => 'Department / Office', 'kind' => 'text', 'required' => true],
    ];
    if (is_booking_type($type)) {
        $uc = booking_profile($type)['units_col'];
        $ul = booking_profile($type)['unit_label'];
        return $who + [
            'purpose'         => ['label' => 'Event Name / Purpose',    'kind' => 'text',  'required' => true, 'wide' => true],
            'event_type'      => ['label' => 'Type of Event',           'kind' => 'event'],
            'participants'    => ['label' => 'Number of Participants',  'kind' => 'int',   'min' => 1, 'max' => 10000],
            'venue'           => ['label' => 'Venue / Room',            'kind' => 'text',  'required' => true],
            $uc               => ['label' => $ul . ' Units',            'kind' => 'int',   'required' => true, 'min' => 0, 'max' => 100],
            'date_needed'     => ['label' => 'Date Needed',             'kind' => 'date',  'required' => true],
            'time_from'       => ['label' => 'Time From',               'kind' => 'time',  'required' => true],
            'time_to'         => ['label' => 'Time To',                 'kind' => 'time',  'required' => true],
            'remarks'         => ['label' => 'Remarks',                 'kind' => 'textarea', 'wide' => true],
        ];
    }
    if ($type === 'service') {
        return $who + [
            'immediate_superior' => ['label' => 'Immediate Superior', 'kind' => 'name'],
            'date_requested'     => ['label' => 'Date Requested',     'kind' => 'date', 'required' => true],
            'description'        => ['label' => 'Description',        'kind' => 'textarea', 'required' => true, 'wide' => true],
        ];
    }
    if ($type === 'incident') {
        return $who + [
            'incident_date' => ['label' => 'Date of Incident',        'kind' => 'date', 'required' => true],
            'location'      => ['label' => 'Location / Venue',        'kind' => 'text', 'required' => true],
            'category'      => ['label' => 'Incident Category',       'kind' => 'text', 'required' => true],
            'description'   => ['label' => 'Description of Incident', 'kind' => 'textarea', 'required' => true, 'wide' => true],
            'action_taken'  => ['label' => 'Immediate Action Taken',  'kind' => 'textarea', 'wide' => true],
            'remarks'       => ['label' => 'Remarks',                 'kind' => 'textarea', 'wide' => true],
        ];
    }
    return $who + [ // software
        'computer_laboratory' => ['label' => 'Computer Laboratory',                'kind' => 'text'],
        'supervisor_name'     => ['label' => 'Supervisor / Dept. Head / Dean',     'kind' => 'text'],
        'date_requested'      => ['label' => 'Date Requested',                     'kind' => 'date', 'required' => true],
        'remarks'             => ['label' => 'Remarks',                            'kind' => 'textarea', 'wide' => true],
    ];
}

/** The editor's form fields for one request row, with the current values filled in. */
/**
 * Normalizes a stored clock value to 24-hour "HH:MM" for <input type="time">
 * and for comparing times: accepts "HH:MM", "H:MM:SS" and "h:mm AM/PM".
 * Returns '' when it isn't a recognized time.
 */
function edit_clock_24($value) {
    $v = trim((string) $value);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/', $v, $m)) {
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($min > 59) return '';
        if (!empty($m[3])) {
            if ($h < 1 || $h > 12) return '';
            $pm = strtoupper($m[3]) === 'PM';
            if ($h === 12) $h = $pm ? 12 : 0; elseif ($pm) $h += 12;
        } elseif ($h > 23) {
            return '';
        }
        return sprintf('%02d:%02d', $h, $min);
    }
    return '';
}

/** Reminder flags kept inside each additional_dates entry (see send_reminders.php). */
function reminder_flag_keys() {
    return ['reminder_1day_sent', 'reminder_1hr_sent', 'reminder_30min_sent'];
}

/** The extra dates of a projector request, shaped for the editor form. idx = position in the saved JSON. */
function edit_extra_dates(array $row) {
    $out = [];
    $extra = json_decode($row['additional_dates'] ?? '', true);
    if (!is_array($extra)) return $out;
    foreach ($extra as $i => $e) {
        if (!is_array($e) || empty($e['date'])) continue;
        $out[] = [
            'idx'       => (int) $i,
            'n'         => (int) $i + 1,
            'date'      => (string) $e['date'],
            'time_from' => edit_clock_24($e['time_from'] ?? ''),
            'time_to'   => edit_clock_24($e['time_to'] ?? ''),
            'done'      => !empty($e['done']),
        ];
    }
    return $out;
}

function edit_fields($type, array $row) {
    $out = [];
    foreach (edit_field_specs($type) as $col => $spec) {
        if (!array_key_exists($col, $row)) continue;
        $val = trim((string) ($row[$col] ?? ''));
        if ($spec['kind'] === 'time' && edit_clock_24($val) !== '') {
            $val = edit_clock_24($val); // "8:30:00" / "1:00 PM" -> 24-hour "HH:MM" for <input type=time>
        }
        $out[] = [
            'col'      => $col,
            'label'    => $spec['label'],
            'kind'     => $spec['kind'],
            'value'    => $val,
            'required' => !empty($spec['required']),
            'wide'     => !empty($spec['wide']),
            'min'      => $spec['min'] ?? null,
            'max'      => $spec['max'] ?? null,
        ];
    }
    return $out;
}

/**
 * Validates and saves an edit from the report modal. Only columns listed in
 * edit_field_specs() are ever written. For a projector request, raising the
 * unit count or moving the date is checked against the same daily limits the
 * public form enforces (this request's own current booking is not counted
 * against itself).
 *
 * @param array $post The raw $_POST (fields arrive as f[column]).
 * @return array ['ok' => bool, 'error' => string]
 */
function apply_request_edit($conn, $type, $id, array $post) {
    $fail = function ($msg) { return ['ok' => false, 'error' => $msg]; };

    $tables = request_tables();
    if (!isset($tables[$type]) || $id < 1) return $fail('That report could not be identified.');
    $table = $tables[$type]; // whitelisted above, so safe to use in the query
    if ($type === 'projector') ensure_projector_event_columns($conn);

    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) return $fail('That report no longer exists.');
    if (!empty($row['is_cancelled'])) return $fail('Cancelled reports can no longer be edited.');

    $input = (isset($post['f']) && is_array($post['f'])) ? $post['f'] : [];
    $clean = [];
    foreach (edit_field_specs($type) as $col => $spec) {
        if (!array_key_exists($col, $row)) continue;
        $label = $spec['label'];
        $kind  = $spec['kind'];
        $raw   = (isset($input[$col]) && is_string($input[$col])) ? trim($input[$col]) : '';

        if ($kind === 'event') {
            $typed = (isset($post['event_type_other']) && is_string($post['event_type_other'])) ? trim($post['event_type_other']) : '';
            if ($raw !== '' && !array_key_exists($raw, event_type_palette())) return $fail('Please choose a valid Type of Event.');
            if ($raw === EVENT_TYPE_OTHER_VALUE && $typed === '') return $fail('Please specify the type of event.');
            $value = resolve_event_type($raw, $typed);
            if (mb_strlen($value) > 60) return $fail('Type of Event must be 60 characters or fewer.');
            $clean[$col] = $value;
            continue;
        }

        if ($raw === '') {
            if (!empty($spec['required'])) return $fail($label . ' is required.');
            $clean[$col] = ($kind === 'int') ? null : '';
            continue;
        }

        switch ($kind) {
            case 'name':
                if (!preg_match('/^[a-zA-Z\s.\'-]+$/', $raw)) return $fail($label . ' must contain letters only (no numbers or special characters).');
                if (mb_strlen($raw) > 150) return $fail($label . ' is too long.');
                $clean[$col] = $raw;
                break;
            case 'text':
                if (mb_strlen($raw) > 150) return $fail($label . ' must be 150 characters or fewer.');
                $clean[$col] = $raw;
                break;
            case 'textarea':
                if (mb_strlen($raw) > 2000) return $fail($label . ' must be 2,000 characters or fewer.');
                $clean[$col] = $raw;
                break;
            case 'date':
                $dt = DateTime::createFromFormat('!Y-m-d', $raw);
                if (!$dt || $dt->format('Y-m-d') !== $raw) return $fail($label . ' is not a valid date.');
                $clean[$col] = $raw;
                break;
            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $m)) return $fail($label . ' is not a valid time.');
                $clean[$col] = $m[1] . ':' . $m[2];
                break;
            case 'int':
                if (!preg_match('/^\d+$/', $raw)) return $fail($label . ' must be a whole number.');
                $n = (int) $raw;
                if ($n < ($spec['min'] ?? 0) || $n > ($spec['max'] ?? PHP_INT_MAX)) {
                    return $fail($label . ' must be between ' . ($spec['min'] ?? 0) . ' and ' . number_format($spec['max']) . '.');
                }
                $clean[$col] = $n;
                break;
        }
    }

    $newExtra = null;            // rewritten additional_dates, only when an extra date actually changed
    $extraDiff = [];             // extra-date changes, for the activity log
    $primaryReschedule = false;  // primary date / start time moved -> reminders start over
    if (is_booking_type($type)) {
        $bp = booking_profile($type);
        $uc = $bp['units_col'];
        if (isset($clean['time_from'], $clean['time_to']) && $clean['time_to'] <= $clean['time_from']) {
            return $fail('Time To must be later than Time From.');
        }

        $total = $bp['total']($conn);
        if (isset($clean[$uc]) && $clean[$uc] > $total) {
            return $fail("{$bp['unit_label']} Units cannot be more than the {$total} {$bp['plural']} available in total.");
        }

        $oldUnits = (int) $row[$uc];
        $newUnits = isset($clean[$uc]) ? (int) $clean[$uc] : $oldUnits;
        $newRow   = $row;
        if (isset($clean['date_needed'])) $newRow['date_needed'] = $clean['date_needed'];

        // ---- Primary date/time moved? Its reminders must start over. ----
        if (isset($clean['date_needed']) && $clean['date_needed'] !== (string) $row['date_needed']) $primaryReschedule = true;
        if (isset($clean['time_from']) && edit_clock_24($clean['time_from']) !== edit_clock_24($row['time_from'])) $primaryReschedule = true;

        // ---- Extra dates: posted as xd[<position>][date|time_from|time_to] ----
        // Existing entries can be edited or removed (xd_remove[]) here, and new ones
        // are added via nd[] below. Each entry keeps its other keys (done, done_at, ...); a
        // changed date or start time clears that entry's three reminder flags.
        $oldExtra = json_decode($row['additional_dates'] ?? '', true);
        $xdIn = (isset($post['xd']) && is_array($post['xd'])) ? $post['xd'] : null;
        $work = is_array($oldExtra) ? $oldExtra : [];

        // ---- Extra dates ticked for removal: posted as xd_remove[<position>] ----
        // A date already marked done is a record of finished work, so it stays.
        $removeIdx = [];
        if (isset($post['xd_remove']) && is_array($post['xd_remove']) && is_array($oldExtra)) {
            foreach (array_keys($post['xd_remove']) as $ri) {
                if (!is_int($ri) && !ctype_digit((string) $ri)) continue;
                $ri = (int) $ri;
                if (!isset($oldExtra[$ri]) || !is_array($oldExtra[$ri]) || empty($oldExtra[$ri]['date'])) continue;
                if (!empty($oldExtra[$ri]['done'])) {
                    return $fail('Extra date ' . ($ri + 1) . ' is already marked done and cannot be removed.');
                }
                $removeIdx[$ri] = true;
            }
        }

        if ($xdIn !== null && is_array($oldExtra) && $oldExtra) {
            foreach ($oldExtra as $i => $e) {
                if (!is_array($e) || empty($e['date'])) continue;
                if (isset($removeIdx[$i])) continue; // being removed - no need to validate its fields
                $in = $xdIn[$i] ?? null;
                if (!is_array($in)) continue; // not sent - leave as is
                $lbl = 'Extra date ' . ($i + 1);
                $d   = isset($in['date'])      && is_string($in['date'])      ? trim($in['date'])      : '';
                $tf  = isset($in['time_from']) && is_string($in['time_from']) ? trim($in['time_from']) : '';
                $tt  = isset($in['time_to'])   && is_string($in['time_to'])   ? trim($in['time_to'])   : '';
                if ($d === '') return $fail($lbl . ': date is required.');
                $dt = DateTime::createFromFormat('!Y-m-d', $d);
                if (!$dt || $dt->format('Y-m-d') !== $d) return $fail($lbl . ' is not a valid date.');
                $tf24 = edit_clock_24($tf);
                $tt24 = edit_clock_24($tt);
                if ($tf24 === '') return $fail($lbl . ': Time From is not a valid time.');
                if ($tt24 === '') return $fail($lbl . ': Time To is not a valid time.');
                if ($tt24 <= $tf24) return $fail($lbl . ': Time To must be later than Time From.');

                $oldD  = (string) $e['date'];
                $oldTf = edit_clock_24($e['time_from'] ?? '');
                $oldTt = edit_clock_24($e['time_to'] ?? '');

                if ($d !== $oldD) {
                    $work[$i]['date'] = $d;
                    $extraDiff['extra_date_' . ($i + 1)] = ['from' => $oldD, 'to' => $d];
                }
                if ($tf24 !== $oldTf) {
                    $work[$i]['time_from'] = $tf24;
                    $extraDiff['extra_time_from_' . ($i + 1)] = ['from' => $oldTf, 'to' => $tf24];
                }
                if ($tt24 !== $oldTt) {
                    $work[$i]['time_to'] = $tt24;
                    $extraDiff['extra_time_to_' . ($i + 1)] = ['from' => $oldTt, 'to' => $tt24];
                }
                if ($d !== $oldD || $tf24 !== $oldTf) {
                    foreach (reminder_flag_keys() as $k) unset($work[$i][$k]); // back to "not sent"
                }
            }
        }

        // Drop the removed entries (the array is re-indexed below with array_values).
        foreach (array_keys($removeIdx) as $ri) {
            $e = $oldExtra[$ri];
            $extraDiff['extra_date_removed_' . ($ri + 1)] = [
                'from' => (string) $e['date'] . ' ' . edit_clock_24($e['time_from'] ?? '') . '-' . edit_clock_24($e['time_to'] ?? ''),
                'to'   => '',
            ];
            unset($work[$ri]);
        }

        // ---- New extra dates: posted as nd[<n>][date|time_from|time_to] ----
        // Added with the editor's "Add date" button. Each one is appended to
        // additional_dates with no reminder flags, so its reminders start fresh.
        $ndIn = (isset($post['nd']) && is_array($post['nd'])) ? $post['nd'] : [];
        if ($ndIn) {
            if (!array_key_exists('additional_dates', $row)) return $fail('This request cannot have extra dates added.');
            $addNo = 0;
            foreach ($ndIn as $in) {
                if (!is_array($in)) continue;
                $d  = isset($in['date'])      && is_string($in['date'])      ? trim($in['date'])      : '';
                $tf = isset($in['time_from']) && is_string($in['time_from']) ? trim($in['time_from']) : '';
                $tt = isset($in['time_to'])   && is_string($in['time_to'])   ? trim($in['time_to'])   : '';
                if ($d === '' && $tf === '' && $tt === '') continue; // an untouched blank row is ignored
                $addNo++;
                $lbl = 'New date ' . $addNo;
                if ($d === '') return $fail($lbl . ': date is required.');
                $dt = DateTime::createFromFormat('!Y-m-d', $d);
                if (!$dt || $dt->format('Y-m-d') !== $d) return $fail($lbl . ' is not a valid date.');
                $tf24 = edit_clock_24($tf);
                $tt24 = edit_clock_24($tt);
                if ($tf24 === '') return $fail($lbl . ': Time From is not a valid time.');
                if ($tt24 === '') return $fail($lbl . ': Time To is not a valid time.');
                if ($tt24 <= $tf24) return $fail($lbl . ': Time To must be later than Time From.');
                $work[] = ['date' => $d, 'time_from' => $tf24, 'time_to' => $tt24];
                $extraDiff['extra_date_added_' . $addNo] = ['from' => '', 'to' => $d . ' ' . $tf24 . '-' . $tt24];
            }
        }

        if ($extraDiff) {
            $newExtra = array_values($work);
            $newRow['additional_dates'] = json_encode($newExtra);
        }

        $oldDates = projector_request_dates($row);
        $newDates = projector_request_dates($newRow);

        // The same day can't be listed twice on one request (the public form
        // enforces this too; done-state is tracked per date).
        if ($newDates !== $oldDates && count($newDates) !== count(array_unique($newDates))) {
            return $fail('The same date cannot be listed twice on one request.');
        }

        if ($newUnits !== $oldUnits || $newDates !== $oldDates) {
            $limit    = $bp['limit']($conn);
            foreach (array_unique($newDates) as $d) {
                $oldHits = count(array_keys($oldDates, $d));
                $newHits = count(array_keys($newDates, $d));
                $usage   = $bp['usage']($conn, $d);
                $othersUnits    = $usage['units'] - $oldHits * $oldUnits;
                $othersRequests = $usage['requests'] - $oldHits;
                $pretty = date('F j, Y', strtotime($d));

                // Only complain when this edit makes the day fuller than it already was.
                if ($newHits > $oldHits && $othersRequests + $newHits > $limit) {
                    return $fail("{$pretty} is already fully booked ({$limit} requests per day maximum).");
                }
                if ($newHits * $newUnits > $oldHits * $oldUnits && $othersUnits + $newHits * $newUnits > $total) {
                    $free = max(0, $total - $othersUnits);
                    return $fail("That would overbook {$pretty}: other requests already hold {$othersUnits} of {$total} {$bp['plural']}, so this request can have at most {$free}.");
                }
            }
        }
    }

    if (!$clean) return $fail('There was nothing to save.');

    $sets = []; $types = ''; $vals = [];
    foreach ($clean as $col => $val) { // $col comes from edit_field_specs(), never from the request
        $sets[]  = "`$col` = ?";
        $types  .= is_int($val) ? 'i' : 's';
        $vals[]  = $val;
    }
    if (is_booking_type($type) && $primaryReschedule) {
        foreach (reminder_flag_keys() as $rc) { // literal column names, no user input
            if (array_key_exists($rc, $row)) $sets[] = "`$rc` = 0";
        }
    }
    if (is_booking_type($type) && $newExtra !== null) {
        $sets[]  = '`additional_dates` = ?';
        $types  .= 's';
        $vals[]  = json_encode($newExtra);
    }
    $types .= 'i';
    $vals[] = $id;

    $stmt = $conn->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->bind_param($types, ...$vals);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) return $fail('The database could not save that change.');

    // Log only what actually changed, with old -> new values. A change to
    // any schedule field (date/time) is called out in the summary so it's
    // easy to spot at a glance in the Logs tab.
    $scheduleCols = ['date_needed', 'time_from', 'time_to', 'date_requested'];
    $diff = [];
    foreach ($clean as $col => $val) {
        $old = $row[$col] ?? null;
        if ((string) $old !== (string) $val) {
            $diff[$col] = ['from' => $old, 'to' => $val];
        }
    }
    foreach ($extraDiff as $k => $chg) $diff[$k] = $chg; // extra-date edits are logged like any other change
    if ($diff) {
        $isSchedule = (bool) array_intersect(array_keys($diff), $scheduleCols) || (bool) $extraDiff;
        $mergedRow  = $clean + $row; // clean's edited values win over the old row
        $eventType  = tm_log_event($type, $mergedRow); // projector: its Purpose / Event
        $requester  = trim((string) ($mergedRow['name'] ?? ''));
        $label      = tm_log_label($type, $mergedRow);
        $summary = "$label updated" . ($isSchedule ? ' (schedule changed)' : '');
        // 'type_of_event' is a plain string, so it never collides with (or gets
        // read as) one of the from/to pairs in $diff.
        log_activity($conn, 'request_updated', $eventType, $requester, $summary,
                     $diff + tm_log_detail($type, $mergedRow));
    }

    return ['ok' => true, 'error' => ''];
}

/** One Teachmint request occurrence -> calendar item (same shape as a projector one). */
function teachmint_item(array $row, array $occ) {
    return projector_item($row, $occ, 'teachmint');
}

/** One projector request occurrence (one date of it) -> calendar item. */
function projector_item(array $row, array $occ, $type = 'projector') {
    $eventType = trim((string) ($row['event_type'] ?? ''));
    $doneMap = projector_done_map($row);
    // This occurrence's own done state (drives the calendar chip / list badge
    // for this specific date); doneByDate is the full map, so the report
    // popup can look up the right state for whichever date it is showing.
    $occDone = $doneMap[(string) $occ['date']] ?? ['done' => false, 'done_at' => ''];
    return [
        'type'         => $type,
        'id'           => (int) $row['id'],
        'name'         => $row['name'],
        'dept'         => $row['department'],
        'venue'        => $row['venue'],
        'what'         => $row['purpose'],
        'time'         => fmt_time($occ['time_from']) . ' - ' . fmt_time($occ['time_to']),
        'units'        => (int) ($row[booking_profile($type)['units_col']] ?? 0),
        'event_type'   => $eventType,
        'participants' => isset($row['participants']) ? (int) $row['participants'] : null,
        'color'        => event_type_color($eventType),
        'details'      => request_details($row),
        'schedule'     => projector_schedule($row),
        'edit'         => edit_fields($type, $row),
        'edit_extra'   => edit_extra_dates($row),
        'doneByDate'   => $doneMap,
    ] + cancel_fields($row) + $occDone;
}

/** One software request row -> calendar item. */
function software_item(array $row) {
    $pkgs = [];
    foreach (['licensed_software', 'freeware'] as $col) {
        $decoded = json_decode($row[$col] ?? '', true);
        if (is_array($decoded)) {
            $pkgs = array_merge($pkgs, $decoded);
        } elseif (trim((string) ($row[$col] ?? '')) !== '') {
            $pkgs[] = trim((string) $row[$col]);
        }
    }
    return [
        'type'     => 'software',
        'id'       => (int) $row['id'],
        'name'     => $row['name'],
        'dept'     => $row['department'],
        'venue'    => $row['computer_laboratory'],
        'what'     => $pkgs ? implode(', ', $pkgs) : 'Software installation',
        'time'     => '',
        'units'    => 0,
        'details'  => request_details($row),
        'schedule' => [],
        'edit'     => edit_fields('software', $row),
    ] + cancel_fields($row) + done_fields($row);
}

/** One service call request row -> calendar item. */
function service_item(array $row) {
    $desc  = trim(preg_replace('/\s+/', ' ', (string) ($row['description'] ?? '')));
    $types = format_detail_value('problem_type', $row['problem_type'] ?? '');
    return [
        'type'     => 'service',
        'id'       => (int) $row['id'],
        'name'     => $row['name'],
        'dept'     => $row['department'],
        'venue'    => '',
        'what'     => $desc !== '' ? mb_strimwidth($desc, 0, 120, '…') : ($types !== '' ? $types : 'Service call'),
        'time'     => '',
        'units'    => 0,
        'details'  => request_details($row),
        'schedule' => [],
        'edit'     => edit_fields('service', $row),
    ] + cancel_fields($row) + done_fields($row);
}

/** One IT incident report row -> calendar item, filed against its own incident_date. */
function incident_item(array $row) {
    $desc = trim(preg_replace('/\s+/', ' ', (string) ($row['description'] ?? '')));
    return [
        'type'     => 'incident',
        'id'       => (int) $row['id'],
        'name'     => $row['name'],
        'dept'     => $row['department'],
        'venue'    => $row['location'] ?? '',
        'what'     => ($row['category'] ?? '') !== '' ? $row['category'] : ($desc !== '' ? mb_strimwidth($desc, 0, 120, '…') : 'Incident report'),
        'time'     => fmt_time($row['incident_time'] ?? ''),
        'units'    => 0,
        'details'  => request_details($row),
        'schedule' => [],
        'edit'     => edit_fields('incident', $row),
    ] + cancel_fields($row) + done_fields($row);
}

/** Inline background for a calendar chip: projector requests take their event-type colour (cancelled ones stay grey). */
function chip_style($it) {
    if (!is_booking_type($it['type'] ?? '') || !empty($it['cancelled']) || empty($it['color'])) return '';
    return ' style="background:' . h($it['color']) . '"';
}

/** Sets --chip (the request's type colour) on a calendar chip so the dot and the title highlight share one colour. */
function chip_var($it) {
    if (!is_booking_type($it['type'] ?? '') || !empty($it['cancelled']) || empty($it['color'])) return '';
    return ' style="--chip:' . h($it['color']) . '"';
}

/** CSS classes for a calendar chip: colour by request type, plus "cancelled" or "done". */
/** "TM: " marker so Teachmint chips can be told apart from projector ones. */
function chip_prefix($it) {
    return '';
}

/** CSS classes for a calendar chip (see above). */
function chip_classes($it) {
    $map = ['projector' => 'proj', 'teachmint' => 'proj', 'software' => 'soft', 'service' => 'svc', 'incident' => 'inc'];
    return ($map[$it['type']] ?? 'proj') . (!empty($it['cancelled']) ? ' cancelled' : '') . (!empty($it['done']) && empty($it['cancelled']) ? ' done' : '');
}

function type_label($type) {
    $map = ['projector' => 'Projector request', 'teachmint' => 'Teachmint request', 'software' => 'Software request', 'service' => 'Service call', 'incident' => 'Incident report'];
    return $map[$type] ?? 'Request';
}

// Projector bookings that touch this month, expanded so each date the
// request occupies becomes its own calendar entry.
$byDate = []; // 'Y-m-d' => ['units'=>int, 'items'=>[...]]
$stmt = $conn->prepare(
    "SELECT *
       FROM projector_requests
      WHERE (date_needed BETWEEN ? AND ?) OR additional_dates LIKE ?
      ORDER BY date_needed ASC, time_from ASC"
);
$likeMonth = '%"date":"' . $ym . '%';
$stmt->bind_param('sss', $monthStart, $monthEnd, $likeMonth);
$stmt->execute();
$res = $stmt->get_result();
while ($res && ($row = $res->fetch_assoc())) {
    $occurrences = [[
        'date'      => $row['date_needed'],
        'time_from' => $row['time_from'],
        'time_to'   => $row['time_to'],
    ]];
    $extra = json_decode($row['additional_dates'] ?? '', true);
    if (is_array($extra)) {
        foreach ($extra as $e) {
            if (is_array($e) && !empty($e['date'])) {
                $occurrences[] = [
                    'date'      => $e['date'],
                    'time_from' => $e['time_from'] ?? '',
                    'time_to'   => $e['time_to'] ?? '',
                ];
            }
        }
    }
    foreach ($occurrences as $occ) {
        $d = $occ['date'];
        if ($d < $monthStart || $d > $monthEnd) continue; // other months
        if (!isset($byDate[$d])) $byDate[$d] = ['units' => 0, 'items' => []];
        if (empty($row['is_cancelled'])) $byDate[$d]['units'] += (int) $row['projector_units'];
        $byDate[$d]['items'][] = projector_item($row, $occ);
    }
}
$stmt->close();

// Teachmint bookings for the month (skipped quietly until teachmint_requests.sql has been run).
$hasTeachmint = false;
if ($__tr = $conn->query("SHOW TABLES LIKE 'teachmint_requests'")) { $hasTeachmint = $__tr->num_rows > 0; }
if ($hasTeachmint) {
    $stmt = $conn->prepare(
        "SELECT * FROM teachmint_requests
          WHERE (date_needed BETWEEN ? AND ?) OR additional_dates LIKE ?
          ORDER BY date_needed ASC, time_from ASC"
    );
    $stmt->bind_param('sss', $monthStart, $monthEnd, $likeMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        foreach (projector_occurrences($row) as $occ) {
            $d = $occ['date'];
            if ($d < $monthStart || $d > $monthEnd) continue;
            if (!isset($byDate[$d])) $byDate[$d] = ['units' => 0, 'items' => []];
            $byDate[$d]['items'][] = teachmint_item($row, $occ); // Teachmint units don't use up projectors
        }
    }
    $stmt->close();
}

// Software requests, filed against the date they were submitted for.
$stmt = $conn->prepare(
    "SELECT *
       FROM software_request
      WHERE date_requested BETWEEN ? AND ?
      ORDER BY date_requested ASC, id ASC"
);
$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$res = $stmt->get_result();
while ($res && ($row = $res->fetch_assoc())) {
    $d = $row['date_requested'];
    if (!isset($byDate[$d])) $byDate[$d] = ['units' => 0, 'items' => []];

    $byDate[$d]['items'][] = software_item($row);
}
$stmt->close();

// Service call requests, filed against the date they were requested for.
$stmt = $conn->prepare(
    "SELECT *
       FROM service_call_requests
      WHERE date_requested BETWEEN ? AND ?
      ORDER BY date_requested ASC, id ASC"
);
$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$res = $stmt->get_result();
while ($res && ($row = $res->fetch_assoc())) {
    $d = $row['date_requested'];
    if (!isset($byDate[$d])) $byDate[$d] = ['units' => 0, 'items' => []];
    $byDate[$d]['items'][] = service_item($row);
}
$stmt->close();

// Incident reports, filed against the date the incident occurred.
$stmt = $conn->prepare(
    "SELECT *
       FROM incident_reports
      WHERE incident_date BETWEEN ? AND ?
      ORDER BY incident_date ASC, id ASC"
);
$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$res = $stmt->get_result();
while ($res && ($row = $res->fetch_assoc())) {
    $d = $row['incident_date'];
    if (!isset($byDate[$d])) $byDate[$d] = ['units' => 0, 'items' => []];
    $byDate[$d]['items'][] = incident_item($row);
}
$stmt->close();

// ---------------------------------------------------------------------
// Dashboard: rolling 7-day window starting today, independent of whatever
// month the Calendar tab happens to be showing.
// ---------------------------------------------------------------------
$weekStart = date('Y-m-d');
$weekEnd   = date('Y-m-d', strtotime('+6 days'));
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[] = date('Y-m-d', strtotime("+{$i} days"));
}

$weekByDate = [];
foreach ($weekDates as $d) $weekByDate[$d] = ['units' => 0, 'items' => []];

// Projector requests: fetched in full (not date-filtered in SQL) since a
// request's additional_dates can fall on either side of a month boundary
// that the 7-day window straddles - simpler and plenty fast for this
// dataset to expand every row in PHP and keep only the days in range.
$res = $conn->query(
    "SELECT * FROM projector_requests"
);
while ($res && ($row = $res->fetch_assoc())) {
    $occurrences = [[
        'date'      => $row['date_needed'],
        'time_from' => $row['time_from'],
        'time_to'   => $row['time_to'],
    ]];
    $extra = json_decode($row['additional_dates'] ?? '', true);
    if (is_array($extra)) {
        foreach ($extra as $e) {
            if (is_array($e) && !empty($e['date'])) {
                $occurrences[] = [
                    'date'      => $e['date'],
                    'time_from' => $e['time_from'] ?? '',
                    'time_to'   => $e['time_to'] ?? '',
                ];
            }
        }
    }
    foreach ($occurrences as $occ) {
        if (!isset($weekByDate[$occ['date']])) continue; // outside the 7-day window
        if (empty($row['is_cancelled'])) $weekByDate[$occ['date']]['units'] += (int) $row['projector_units'];
        $weekByDate[$occ['date']]['items'][] = projector_item($row, $occ);
    }
}

// Teachmint requests in the window.
if ($hasTeachmint) {
    $res = $conn->query("SELECT * FROM teachmint_requests");
    while ($res && ($row = $res->fetch_assoc())) {
        foreach (projector_occurrences($row) as $occ) {
            if (!isset($weekByDate[$occ['date']])) continue;
            $weekByDate[$occ['date']]['items'][] = teachmint_item($row, $occ);
        }
    }
}

// Software requests due in the window.
$res = $conn->query(
    "SELECT * FROM software_request"
);
while ($res && ($row = $res->fetch_assoc())) {
    $d = $row['date_requested'];
    if (!isset($weekByDate[$d])) continue;

    $weekByDate[$d]['items'][] = software_item($row);
}

// Service call requests in the window.
$res = $conn->query("SELECT * FROM service_call_requests");
while ($res && ($row = $res->fetch_assoc())) {
    $d = $row['date_requested'];
    if (!isset($weekByDate[$d])) continue;
    $weekByDate[$d]['items'][] = service_item($row);
}

// Incident reports in the window.
$res = $conn->query("SELECT * FROM incident_reports");
while ($res && ($row = $res->fetch_assoc())) {
    $d = $row['incident_date'];
    if (!isset($weekByDate[$d])) continue;
    $weekByDate[$d]['items'][] = incident_item($row);
}

// Totals for the stat cards, plus which day of the week has the most going on.
// Cancelled requests are still listed, but never counted here.
$weekProjectorCount = 0;
$weekSoftwareCount  = 0;
$weekServiceCount   = 0;
$weekIncidentCount  = 0;
$weekTeachmintCount = 0;
$weekUnitsBooked    = 0;
$busiestDay         = null;
$busiestDayTotal    = 0;
foreach ($weekByDate as $d => $bucket) {
    $pCount = 0;
    $sCount = 0;
    $vCount = 0;
    $iCount = 0;
    $tCount = 0;
    foreach ($bucket['items'] as $it) {
        if (!empty($it['cancelled'])) continue;
        if ($it['type'] === 'projector')    $pCount++;
        elseif ($it['type'] === 'software') $sCount++;
        elseif ($it['type'] === 'incident') $iCount++;
        elseif ($it['type'] === 'teachmint') $tCount++;
        else                                $vCount++;
    }
    $weekProjectorCount += $pCount;
    $weekSoftwareCount  += $sCount;
    $weekServiceCount   += $vCount;
    $weekIncidentCount  += $iCount;
    $weekTeachmintCount += $tCount;
    $weekUnitsBooked    += $bucket['units'];
    if (($pCount + $sCount + $vCount + $iCount + $tCount) > $busiestDayTotal) {
        $busiestDayTotal = $pCount + $sCount + $vCount + $iCount + $tCount;
        $busiestDay = $d;
    }
}
$weekTotalRequests = $weekProjectorCount + $weekTeachmintCount + $weekSoftwareCount + $weekServiceCount + $weekIncidentCount;
$weekUtilPct = ($totalUnits * 7) > 0 ? (int) round(($weekUnitsBooked / ($totalUnits * 7)) * 100) : 0;

// Today only (subset of the week window) - for the highlight strip at the
// top of the dashboard.
$todayStr    = date('Y-m-d');
$todayBucket = $weekByDate[$todayStr] ?? ['units' => 0, 'items' => []];
$todayUsed   = (int) $todayBucket['units'];
$todayFree   = max(0, $totalUnits - $todayUsed);
$todayItems  = $todayBucket['items'];
$todayActiveCount = count(array_filter($todayItems, function ($it) { return empty($it['cancelled']); }));
$todayUtilPct = $totalUnits > 0 ? (int) min(100, round(($todayUsed / $totalUnits) * 100)) : 0;

// ---------------------------------------------------------------------
// Pending requests (the "Pending" card): every active request that is still
// waiting to be handled, across all three request types, whatever month or
// week it falls in. A table with its own `status` column is trusted: empty or
// "pending" counts. Without one, a request is pending while it is not
// cancelled and its (last) date has not passed yet.
// ---------------------------------------------------------------------
function projector_occurrences(array $row) {
    $occ = [['date' => (string) $row['date_needed'], 'time_from' => $row['time_from'], 'time_to' => $row['time_to']]];
    $extra = json_decode($row['additional_dates'] ?? '', true);
    if (is_array($extra)) {
        foreach ($extra as $e) {
            if (is_array($e) && !empty($e['date'])) {
                $occ[] = ['date' => (string) $e['date'], 'time_from' => $e['time_from'] ?? '', 'time_to' => $e['time_to'] ?? ''];
            }
        }
    }
    return $occ;
}

function request_is_pending(array $row, $lastDate, $today, $doneOverride = null) {
    if (!empty($row['is_cancelled'])) return false;
    // A projector request with several dates isn't finished until every
    // date on it has been marked done, not just the first one.
    $isDone = $doneOverride !== null ? $doneOverride : !empty($row['is_done']);
    if ($isDone) return false; // finished - nothing left to handle
    if (array_key_exists('status', $row)) {
        $st = strtolower(trim((string) $row['status']));
        // Service calls: pending unless 'Accomplished'. Incident reports:
        // pending (Ongoing / Under Investigation) unless 'Resolved'.
        return !in_array($st, ['accomplished', 'resolved'], true);
    }
    return $lastDate !== '' && $lastDate >= $today;
}

$pendToday   = date('Y-m-d');
$pendingRaw  = []; // [item, date] - date is the one shown next to it in the list

$res = $conn->query("SELECT * FROM projector_requests");
while ($res && ($row = $res->fetch_assoc())) {
    $occs    = projector_occurrences($row);
    $dates   = array_column($occs, 'date');
    $doneMap = projector_done_map($row);
    if (!request_is_pending($row, max($dates), $pendToday, projector_all_done($row))) continue;

    // Pick the date to show next to this request: the soonest date that
    // still needs handling (not yet done), preferring one that hasn't
    // happened yet, else falling back to the first undone date.
    $undone = array_values(array_filter($occs, function ($o) use ($doneMap) {
        return empty($doneMap[$o['date']]['done']);
    }));
    if (!$undone) $undone = $occs; // shouldn't happen (all-done rows are skipped above), but stay safe
    $pick = $undone[0];
    foreach ($undone as $o) {
        if ($o['date'] >= $pendToday && ($pick['date'] < $pendToday || $o['date'] < $pick['date'])) $pick = $o;
    }
    $pendingRaw[] = [projector_item($row, $pick), $pick['date']];
}
if ($hasTeachmint) {
    $res = $conn->query("SELECT * FROM teachmint_requests");
    while ($res && ($row = $res->fetch_assoc())) {
        $occs    = projector_occurrences($row);
        $dates   = array_column($occs, 'date');
        $doneMap = projector_done_map($row);
        if (!request_is_pending($row, max($dates), $pendToday, projector_all_done($row))) continue;
        $undone = array_values(array_filter($occs, function ($o) use ($doneMap) {
            return empty($doneMap[$o['date']]['done']);
        }));
        if (!$undone) $undone = $occs;
        $pick = $undone[0];
        foreach ($undone as $o) {
            if ($o['date'] >= $pendToday && ($pick['date'] < $pendToday || $o['date'] < $pick['date'])) $pick = $o;
        }
        $pendingRaw[] = [teachmint_item($row, $pick), $pick['date']];
    }
}
$res = $conn->query("SELECT * FROM software_request");
while ($res && ($row = $res->fetch_assoc())) {
    $d = (string) $row['date_requested'];
    if (request_is_pending($row, $d, $pendToday)) $pendingRaw[] = [software_item($row), $d];
}
$res = $conn->query("SELECT * FROM service_call_requests");
while ($res && ($row = $res->fetch_assoc())) {
    $d = (string) $row['date_requested'];
    if (request_is_pending($row, $d, $pendToday)) $pendingRaw[] = [service_item($row), $d];
}
$res = $conn->query("SELECT * FROM incident_reports");
while ($res && ($row = $res->fetch_assoc())) {
    $d = (string) $row['incident_date'];
    if (request_is_pending($row, $d, $pendToday)) $pendingRaw[] = [incident_item($row), $d];
}
usort($pendingRaw, function ($a, $b) {
    return [$a[1], agenda_start_minutes($a[0]['time'])] <=> [$b[1], agenda_start_minutes($b[0]['time'])];
});
$pendingCount = count($pendingRaw);

// Flat lookup of every request shown anywhere on the page, keyed by
// "type|id" - lets one event card be clicked to pull up that one request's
// summary, regardless of which list it's shown in. The id is the database
// row id; reference numbers are no longer used on this page.
//
// $dayIndex lists, per date, the requests on that date (with the time for
// that particular date, since a projector request can span several days) so
// the modal can show every report name for a day.
$eventIndex = [];
$dayIndex   = [];
foreach ([$byDate, $weekByDate] as $bucketSet) {
    foreach ($bucketSet as $d => $bucket) {
        $firstSeen = !isset($dayIndex[$d]);
        if ($firstSeen) $dayIndex[$d] = ['units' => (int) $bucket['units'], 'items' => []];
        foreach ($bucket['items'] as $it) {
            $key = $it['type'] . '|' . $it['id'];
            if (!isset($eventIndex[$key])) $eventIndex[$key] = $it;
            if ($firstSeen) $dayIndex[$d]['items'][] = ['key' => $key, 'time' => $it['time'], 'date' => $d];
        }
    }
}

// Pending requests open the same summary popup, so they need to be in the lookup too.
$pendingList = [];
foreach ($pendingRaw as $pr) {
    $key = $pr[0]['type'] . '|' . $pr[0]['id'];
    if (!isset($eventIndex[$key])) $eventIndex[$key] = $pr[0];
    $pendingList[] = ['key' => $key, 'date' => $pr[1], 'time' => $pr[0]['time']];
}

$csrf = $_SESSION['csrf'];


// ---------------------------------------------------------------------
// Dashboard extras: agenda order and the calendar renderer
// (used twice - the dashboard's compact calendar and the full-width
// Request Calendar tab).
// ---------------------------------------------------------------------
/** Minutes after midnight for the start of a "8:30 AM - 10:00 AM" string; requests without a time sort last. */
function agenda_start_minutes($time) {
    $start = trim(explode(' - ', (string) $time)[0]);
    $ts    = $start === '' ? false : strtotime($start);
    return $ts === false ? PHP_INT_MAX : ((int) date('G', $ts)) * 60 + (int) date('i', $ts);
}

// Today's agenda, earliest first (cancelled ones stay listed, struck through).
$agendaItems = [];
foreach ($todayItems as $i => $it) $agendaItems[] = [agenda_start_minutes($it['time']), $i, $it];
usort($agendaItems, function ($a, $b) { return [$a[0], $a[1]] <=> [$b[0], $b[1]]; });
$agendaItems = array_column($agendaItems, 2);

/** free / low / none - colour of the "N free" badge on a date. */
function avail_class($free, $total) {
    return $free <= 0 ? 'none' : ($free <= max(1, (int) floor($total * 0.3)) ? 'low' : 'free');
}

/** One request on a calendar date: a coloured dot and a short label. Clicking it opens the report. */
function event_line($it, $date) {
    return '<span class="mg-ev mg-event ' . h(chip_classes($it)) . '" role="button" tabindex="0"' . chip_var($it)
         . ' title="' . h($it['what']) . '"'
         . ' data-event-key="' . h($it['type'] . '|' . $it['id']) . '" data-event-date="' . h($date) . '">'
         . '<i class="mg-ev-dot"' . chip_style($it) . '></i>'
         . '<span class="mg-ev-txt">' . h(chip_prefix($it) . chip_purpose($it['what'])) . '</span></span>';
}

$cal = [
    'ym'           => $ym,
    'monthTs'      => $monthTs,
    'prevYm'       => $prevYm,
    'nextYm'       => $nextYm,
    'firstWeekday' => $firstWeekday,
    'daysInMonth'  => $daysInMonth,
    'byDate'       => $byDate,
    'totalUnits'   => $totalUnits,
];

/**
 * The month calendar card.
 *
 * @param array  $c       The $cal array above.
 * @param string $tabName Tab the month arrows return to ('dashboard' or 'calendar').
 * @param bool   $wide    true for the full-width tab: taller cells, three requests per date.
 */
function render_calendar(array $c, $tabName, $wide) {
    $todayDate = date('Y-m-d');
    $maxShow   = $wide ? 3 : 2;
    $nav       = '?tab=' . $tabName . '&amp;ym=';
    $total     = (int) $c['totalUnits'];
    ?>
<div class="mg-card mg-calwrap<?= $wide ? ' cal-wide' : '' ?>">
  <div class="mg-card-header">
    <span class="hd-title"><svg class="ic" aria-hidden="true"><use href="#i-cal"/></svg> Request Calendar</span>
    <div class="cal-ctrl">
      <div class="cal-nav">
        <a class="mg-nav-arrow" href="<?= $nav . h($c['prevYm']) ?>#manage" aria-label="Previous month"><svg class="ic" aria-hidden="true"><use href="#i-chev-l"/></svg></a>
        <span class="cal-month"><?= h(date('F Y', $c['monthTs'])) ?></span>
        <a class="mg-nav-arrow" href="<?= $nav . h($c['nextYm']) ?>#manage" aria-label="Next month"><svg class="ic" aria-hidden="true"><use href="#i-chev-r"/></svg></a>
      </div>
      <div class="mg-view-toggle" role="group" aria-label="Calendar view">
        <button type="button" class="mg-view-btn on" data-view="month">Month</button>
        <button type="button" class="mg-view-btn" data-view="week">Week</button>
        <button type="button" class="mg-view-btn" data-view="day">Day</button>
      </div>
    </div>
  </div>
  <div class="mg-card-body">
    <div class="mg-cal-legend">
      <span class="mg-legend-label">Projector:</span>
<?php foreach (event_type_palette() as $evLabel => $evColor): ?>
      <span><i class="mg-dot" style="background:<?= h($evColor) ?>"></i> <?= h($evLabel) ?></span>
<?php endforeach; ?>
      <span><i class="mg-dot"></i> No event type</span>
      <span class="mg-legend-label">Other:</span>
      <span><i class="mg-dot soft"></i> Software request</span>
      <span><i class="mg-dot svc"></i> Service call</span>
      <span><i class="mg-dot inc"></i> Incident report</span>
      <span><i class="mg-dot off"></i> <s>Cancelled</s></span>
    </div>
    <div class="mg-cal">
<?php
    $cellIndex = 0;
    foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow) {
        echo '<div class="mg-dow">' . $dow . '</div>';
    }
    for ($i = 0; $i < $c['firstWeekday']; $i++, $cellIndex++) {
        echo '<div class="mg-cell empty" data-week="' . (int) floor($cellIndex / 7) . '"></div>';
    }
    for ($day = 1; $day <= $c['daysInMonth']; $day++, $cellIndex++) {
        $dateStr = sprintf('%s-%02d', $c['ym'], $day);
        $bucket  = $c['byDate'][$dateStr] ?? ['units' => 0, 'items' => []];
        $free    = max(0, $total - (int) $bucket['units']);
        $items   = $bucket['items'];
        $cls     = 'mg-cell' . ($items ? ' has' : '') . ($dateStr === $todayDate ? ' today' : '');
        echo '<div class="' . $cls . '" data-week="' . (int) floor($cellIndex / 7) . '" data-date="' . h($dateStr) . '"' . ($items ? ' tabindex="0"' : '') . '>';
        echo '<div class="mg-cell-top"><span class="d">' . $day . '</span>'
           . '<span class="mg-avail ' . avail_class($free, $total) . '" title="Projectors still free">' . $free . ' free</span></div>';
        foreach (array_slice($items, 0, $maxShow) as $it) echo event_line($it, $dateStr);
        if (count($items) > $maxShow) echo '<span class="mg-more">+' . (count($items) - $maxShow) . ' more</span>';
        echo '</div>';
    }
    // Pad the last row so the grid closes cleanly.
    while ($cellIndex % 7 !== 0) {
        echo '<div class="mg-cell empty" data-week="' . (int) floor($cellIndex / 7) . '"></div>';
        $cellIndex++;
    }
?>
    </div>
  </div>
</div>
<?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($SITE_NAME) ?> - <?= h($FORM_TITLE) ?></title>
<style>
:root {
  --brand: #2b4a9e;        
  --brand-dark: #213a80;
  --gold: #8fa3e0;         
  --gold-soft: #c9d3f2;
  --gold-ink: #2f4f8f;     
  --tint-50: #f5f7fc;
  --tint-100: #e9edf9;
  --tint-200: #d5ddf3;
  --tint-300: #b7c4ea;
  --bg: #f1f4fa;
  --white: #ffffff;
  --line: #dde2ef;
  --line-soft: #e9edf9;
  --ink: #111318;
  --muted: #5b6072;
  --ok: #107c10;
  --ok-bg: #dff6dd;
  --warn: #8a6d00;
  --warn-bg: #fff4ce;
  --err: #a4262c;
  --err-bg: #fde7e9;
  --red: #2b4a9e;   
  --shadow: 0 1px 2px rgba(20,35,90,.05), 0 4px 14px rgba(20,35,90,.06);
  --site-max: 1500px;
}
* { box-sizing: border-box; }
body {
  margin: 0; display: flex; flex-direction: column;
  font-family: "Roboto", "Segoe UI", system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
  font-size: 14px; color: var(--ink); background: var(--bg);
}
a { color: inherit; }
:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

.sprite { position: absolute; width: 0; height: 0; overflow: hidden; }
.ic { width: 1em; height: 1em; flex: none; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* ---------- Header ---------- */
.topbar { background: var(--white); border-bottom: 4px solid var(--red); }
.topbar-inner { max-width: var(--site-max); margin: 0 auto; padding: 10px 24px; display: flex; align-items: center; gap: 16px; }
.brand-badge img { display: block; height: 58px; width: auto; }
.brand-text h1 { margin: 0; font-size: 1.08rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: var(--brand); line-height: 1.15; }
.brand-text p { margin: 3px 0 0; font-size: .78rem; font-weight: 600; color: var(--gold-ink); }

/* ---------- Section nav ---------- */
.nav-wrap { background: var(--white); border-bottom: 1px solid var(--line); }
.nav-inner { max-width: var(--site-max); margin: 0 auto; padding: 9px 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.mg-tabs { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; }
.mg-tabs::-webkit-scrollbar { display: none; }
.mg-tabs a {
  display: inline-flex; align-items: center; gap: 9px; padding: 9px 16px; border-radius: 9px;
  color: #2b2f40; font-weight: 600; font-size: .86rem; text-decoration: none; white-space: nowrap;
  border-bottom: 3px solid transparent;
  transition: background-color .15s ease, color .15s ease, border-color .15s ease;
}
.mg-tabs a .ic { width: 17px; height: 17px; color: var(--brand); }
.mg-tabs a:hover { background: var(--tint-100); }
.mg-tabs a.on { background: var(--tint-100); color: var(--brand); font-weight: 700; border-bottom-color: var(--gold); }
.nav-date {
  display: inline-flex; align-items: center; gap: 9px; padding: 8px 14px; border: 1px solid var(--line);
  border-radius: 9px; font-weight: 600; font-size: .84rem; white-space: nowrap; background: var(--white);
}
.nav-date .ic { width: 17px; height: 17px; color: var(--brand); }
.nav-right { display: flex; align-items: center; gap: 8px; flex: none; }
.nav-refresh {
  display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border: 1px solid var(--line);
  border-radius: 9px; font: inherit; font-weight: 600; font-size: .8rem; white-space: nowrap;
  background: var(--white); color: var(--muted); cursor: pointer;
  transition: background-color .15s ease, border-color .15s ease, color .15s ease;
}
.nav-refresh .ic { width: 15px; height: 15px; }
.nav-refresh:hover { background: var(--tint-50); }
.nav-refresh.on { color: #1f8a4c; border-color: #cfe9d8; background: #edf8f1; }
.nav-refresh.spin .ic { animation: navRefreshSpin .8s linear infinite; }
@keyframes navRefreshSpin { to { transform: rotate(360deg); } }

/* ---------- Page shell ---------- */
.shell { width: 100%; max-width: var(--site-max); margin: 0 auto; padding: 18px 24px 36px; }
.mg-flash {
  position: fixed; top: 24px; left: 50%; right: auto; bottom: auto; z-index: 1000;
  transform: translateX(-50%);
  display: flex; align-items: center; gap: 14px;
  max-width: min(460px, calc(100vw - 36px));
  padding: 18px 20px; border-radius: 12px;
  font-size: 1.05rem; font-weight: 600; line-height: 1.4;
  box-shadow: 0 10px 28px rgba(20, 30, 20, .18);
  animation: mg-flash-in .22s ease-out;
}
.mg-flash.ok  { background: var(--ok-bg);  color: var(--ok);  border: 1px solid #b6e0b2; }
.mg-flash.err { background: var(--err-bg); color: var(--err); border: 1px solid #f3c8cc; }
.mg-flash.hide { animation: mg-flash-out .18s ease-in forwards; }
.mg-flash-close {
  flex: 0 0 auto; background: none; border: none; cursor: pointer;
  font-size: 1.4rem; line-height: 1; color: inherit; opacity: .55; padding: 0 0 0 4px;
}
.mg-flash-close:hover { opacity: 1; }
@keyframes mg-flash-in  { from { opacity: 0; transform: translate(-50%, -10px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes mg-flash-out { from { opacity: 1; transform: translate(-50%, 0); } to { opacity: 0; transform: translate(-50%, -10px); } }

/* Tab panels share one grid cell so switching tabs never changes the page height. */
.mg-panels { display: grid; grid-template-columns: minmax(0, 1fr); }
.mg-panel { grid-column: 1; grid-row: 1; visibility: hidden; opacity: 0; pointer-events: none; min-width: 0; }
.mg-panel.active { visibility: visible; opacity: 1; pointer-events: auto; animation: panelIn .22s ease both; }
@keyframes panelIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .mg-panel.active { animation: none; } }

/* ---------- Dashboard layout ---------- */
.dash { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; align-items: start; }
.dash-main, .dash-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.dash .mg-card { margin-bottom: 0; }
.cal-row { display: grid; grid-template-columns: minmax(0, 4fr) minmax(240px, 1fr); gap: 16px; align-items: stretch; }

/* ---------- Stat cards ---------- */
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.stat {
  display: flex; align-items: center; gap: 12px; padding: 16px; min-width: 0;
  background: var(--st-bg); border: 1px solid var(--st-line); border-radius: 14px;
}
.stat.brand  { --st-bg: #e9edf9; --st-line: #d5ddf3; --st-ic: #c9d3f2; --st-fg: #2b4a9e; --st-num: #213a80; }
.stat.sky    { --st-bg: #ebf7fc; --st-line: #cdeaf5; --st-ic: #c9e8f6; --st-fg: #0284c7; --st-num: #0b3d5c; }
.stat.teal   { --st-bg: #e8f7f5; --st-line: #c6e8e3; --st-ic: #c2e9e3; --st-fg: #0f8f82; --st-num: #0a4a43; }
.stat.green  { --st-bg: #edf8f1; --st-line: #cfe9d8; --st-ic: #cbeed7; --st-fg: #1f8a4c; --st-num: #14472a; }
.stat.amber  { --st-bg: #fff6e5; --st-line: #f6e0b4; --st-ic: #fbe3ae; --st-fg: #b06a00; --st-num: #6b4200; }
.stat.purple { --st-bg: #f4eefc; --st-line: #e1d5f4; --st-ic: #e1d3f8; --st-fg: #6a3fb8; --st-num: #3b2170; }
.stat.red    { --st-bg: #fdecec; --st-line: #f3c6c6; --st-ic: #f7cccc; --st-fg: #b3261e; --st-num: #6e120c; }
.stat-click { font: inherit; color: inherit; text-align: left; width: 100%; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; }
.stat-click:hover { transform: translateY(-1px); box-shadow: var(--shadow); }
.stat-ic { flex: none; width: 44px; height: 44px; border-radius: 50%; background: var(--st-ic); color: var(--st-fg); display: grid; place-items: center; }
.stat-ic .ic { width: 23px; height: 23px; }
.stat-body { min-width: 0; }
.stat-lbl { display: block; font-size: .83rem; font-weight: 700; line-height: 1.25; }
.stat-num { display: block; margin-top: 2px; font-size: 2.1rem; font-weight: 700; line-height: 1.1; color: var(--st-num); }
.stat-sub { margin-top: 4px; display: flex; align-items: center; gap: 5px; font-size: .72rem; color: var(--muted); }
.stat-sub .ic { width: 12px; height: 12px; color: var(--st-fg); }

/* ---------- Cards ---------- */
.mg-card { background: var(--white); border: 1px solid var(--line); border-top: 4px solid var(--brand); border-radius: 14px; box-shadow: var(--shadow); overflow: hidden; margin-bottom: 16px; }
.mg-card-header {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 16px 18px 12px; font-size: 1rem; font-weight: 700; color: var(--brand);
}
.mg-card-body { padding: 4px 16px 16px; }
.hd-title { display: inline-flex; align-items: center; gap: 10px; margin: 0; font-size: 1rem; font-weight: 700; color: var(--brand); }
.hd-title .ic { width: 22px; height: 22px; }

/* ---------- Calendar ---------- */
.cal-ctrl { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.cal-nav { display: flex; align-items: center; gap: 6px; }
.cal-month { padding: 6px 14px; border: 1px solid var(--line); border-radius: 8px; font-size: 1rem; font-weight: 700; color: var(--ink); background: var(--white); white-space: nowrap; }
.mg-nav-arrow {
  width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid var(--line); background: var(--white); color: var(--brand); text-decoration: none;
  transition: background-color .15s ease, border-color .15s ease;
}
.mg-nav-arrow:hover { background: var(--tint-50); border-color: var(--tint-300); }
.mg-nav-arrow .ic { width: 14px; height: 14px; stroke-width: 2.2; }
.mg-view-toggle { display: flex; gap: 2px; background: var(--tint-100); border-radius: 10px; padding: 3px; }
.mg-view-btn { border: 0; background: transparent; color: var(--ink); font: inherit; font-size: .78rem; font-weight: 700; padding: 6px 13px; border-radius: 8px; cursor: pointer; transition: background-color .15s ease, color .15s ease; }
.mg-view-btn:hover { background: rgba(43,74,158,.09); }
.mg-view-btn.on { background: var(--brand); color: #fff; }

.mg-cal-legend { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: center; margin: 0 2px 12px; font-size: .76rem; color: var(--muted); }
.mg-cal-legend span { display: inline-flex; align-items: center; gap: 6px; }
.mg-cal-legend .mg-legend-label { font-weight: 700; color: var(--brand); }
.mg-cal-legend .mg-badge-note { border: 1px solid var(--tint-200); background: var(--tint-50); color: var(--brand); padding: 3px 10px; border-radius: 12px; font-weight: 600; font-size: .72rem; }
.mg-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; background: var(--brand); }
.mg-dot.soft { background: #1f8a4c; }
.mg-dot.svc  { background: #6a3fb8; }
.mg-dot.inc  { background: #b3261e; }
.mg-dot.off  { background: #8d93a8; }

.mg-cal {
  display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 1px;
  background: var(--line); border: 1px solid var(--line); border-radius: 10px; overflow: hidden;
}
.mg-cal.view-day { grid-template-columns: minmax(0, 1fr); }
.mg-dow { background: var(--white); padding: 10px 0; text-align: center; font-size: .76rem; font-weight: 600; color: #2b2f40; }
.mg-cell { background: var(--white); min-height: 86px; padding: 6px 7px; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.mg-cell.empty { background: #fafbff; }
.mg-cell.has { cursor: pointer; }
.mg-cell.has:hover { background: var(--tint-50); }
.mg-cell.today { background: linear-gradient(180deg, #f7e6ae 0%, #fbf3d9 100%); box-shadow: inset 0 0 0 1.5px var(--gold); }
.mg-cell.today .d { color: var(--brand); }
.mg-cal.view-day .mg-cell { min-height: 170px; }
.cal-wide .mg-cell { min-height: 112px; }
.mg-cell-top { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
.mg-cell .d { font-size: .78rem; font-weight: 700; }
.mg-avail { font-size: .62rem; font-weight: 700; padding: 1px 6px; border-radius: 9px; white-space: nowrap; }
.mg-avail.free { padding: 0; background: transparent; color: #7a8096; font-weight: 600; }
.mg-avail.low  { background: var(--warn-bg); color: var(--warn); }
.mg-avail.none { background: var(--err-bg); color: var(--err); }

.mg-event { cursor: pointer; }
/* The dot's colour (--chip) also tints the whole title, so the report type reads at a glance. */
.mg-ev { --chip: var(--brand); display: flex; align-items: center; gap: 5px; min-width: 0; padding: 2px 5px; margin: 0 -3px; border-radius: 5px; font-size: .68rem; font-weight: 600;
  background: color-mix(in srgb, var(--chip) 16%, #fff); color: color-mix(in srgb, var(--chip) 55%, #1a1214); }
.mg-ev.soft { --chip: #1f8a4c; }
.mg-ev.svc  { --chip: #6a3fb8; }
.mg-ev.inc  { --chip: #b3261e; }
.mg-ev.cancelled { --chip: #8d93a8; }
.mg-ev:hover { background: color-mix(in srgb, var(--chip) 28%, #fff); }
.cal-wide .mg-ev { font-size: .74rem; }
.mg-ev-dot, .ag-dot { flex: none; border-radius: 50%; background: var(--brand); }
.mg-ev-dot { width: 7px; height: 7px; background: var(--chip); }
.soft .ag-dot { background: #1f8a4c; }
.svc  .ag-dot { background: #6a3fb8; }
.inc  .ag-dot { background: #b3261e; }
.cancelled .ag-dot { background: #8d93a8; }
.mg-ev-txt { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mg-ev.cancelled .mg-ev-txt { text-decoration: line-through; color: #8d93a8; }
/* Done reports: a check mark and a softer chip, so finished events read as finished. */
.mg-ev.done { opacity: .72; }
.mg-ev.done .mg-ev-txt::before { content: '\2713\00a0'; font-weight: 800; }
.mg-more { font-size: .66rem; font-weight: 700; color: var(--muted); }

/* ---------- Today's agenda ---------- */
.agenda { display: flex; flex-direction: column; }
.ag-head { padding: 16px 18px 6px; }
.ag-head p { margin: 8px 0 0; font-size: .95rem; font-weight: 500; color: var(--brand); }
.ag-list { padding: 8px 18px; max-height: 430px; overflow-y: auto; }
.ag-item { display: flex; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--line-soft); border-radius: 6px; }
.ag-item:last-child { border-bottom: 0; }
.ag-item:hover { background: var(--tint-50); }
.ag-dot { width: 9px; height: 9px; margin-top: 4px; }
.ag-body { min-width: 0; }
.ag-time { font-size: .78rem; color: var(--muted); }
.ag-title { margin-top: 3px; font-size: .86rem; font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
.ag-item.cancelled .ag-title { text-decoration: line-through; opacity: .6; }
.ag-item.done .ag-title::before { content: '\2713\00a0'; color: var(--ok); font-weight: 800; }
.ag-place { margin-top: 4px; display: flex; align-items: center; gap: 4px; font-size: .78rem; color: var(--brand); }
.ag-place .ic { width: 14px; height: 14px; }
.ag-empty { margin: 6px 0; font-size: .84rem; color: var(--muted); }
.ag-foot-wrap { margin-top: auto; padding: 8px 18px 18px; }
.ag-foot { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 12px; border-radius: 10px; background: var(--tint-100); color: var(--brand); font-size: .8rem; font-weight: 700; }
.ag-foot .ic { width: 18px; height: 18px; }

/* ---------- Sidebar ---------- */
.side-head {
  display: flex; align-items: center; gap: 10px; margin: 0; padding: 15px 18px;
  background: var(--brand); color: #fff; font-size: 1rem; font-weight: 700;
  border-bottom: 3px solid var(--gold);
}
.side-head .ic { width: 20px; height: 20px; }
.side-head.plain { background: transparent; color: var(--brand); padding-bottom: 8px; }
.side-body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 10px; }
.rq {
  --rq-bg: #e9edf9; --rq-line: #d5ddf3; --rq-ic: #c9d3f2; --rq-fg: #2b4a9e;
  display: flex; align-items: center; gap: 12px; padding: 12px; text-decoration: none;
  background: var(--rq-bg); border: 1px solid var(--rq-line); border-radius: 12px;
  transition: box-shadow .15s ease, border-color .15s ease;
}
.rq:hover { box-shadow: 0 4px 14px rgba(20,35,90,.1); border-color: var(--rq-fg); }
.rq.amber { --rq-bg: #fff6e7; --rq-line: #f6e0b6; --rq-ic: #fbe3b0; --rq-fg: #c27a10; }
.rq.green { --rq-bg: #edf8f1; --rq-line: #cfe9d8; --rq-ic: #cbeed7; --rq-fg: #1f8a4c; --rq-ink: #14472a; }
/* Request cards use the same palette as the stat cards above them: Software = green, LCD Projector = sky, Service Call = purple. */
.rq.sky    { --rq-bg: #ebf7fc; --rq-line: #cdeaf5; --rq-ic: #c9e8f6; --rq-fg: #0284c7; --rq-ink: #0b3d5c; }
.rq.purple { --rq-bg: #f4eefc; --rq-line: #e1d5f4; --rq-ic: #e1d3f8; --rq-fg: #6a3fb8; --rq-ink: #3b2170; }
.rq.red    { --rq-bg: #fdecec; --rq-line: #f3c6c6; --rq-ic: #f7cccc; --rq-fg: #b3261e; --rq-ink: #6e120c; }
.rq-ic { flex: none; width: 40px; height: 40px; border-radius: 50%; background: var(--rq-ic); color: var(--rq-fg); display: grid; place-items: center; }
.rq-ic .ic { width: 20px; height: 20px; }
.rq-txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.rq-txt strong { font-size: .84rem; color: var(--rq-ink, var(--brand)); line-height: 1.3; }
.rq-txt span { font-size: .72rem; color: var(--muted); line-height: 1.45; }
.rq-chev { flex: none; width: 15px; height: 15px; color: var(--ink); }
.ql {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px; text-decoration: none;
  background: var(--white); border: 1px solid var(--line); border-radius: 10px; color: var(--brand); font-weight: 700; font-size: .84rem;
  transition: background-color .15s ease, border-color .15s ease;
}
.ql:hover { background: var(--tint-50); border-color: var(--tint-300); }
.ql > .ic:first-child { width: 18px; height: 18px; }
.ql span { flex: 1; }
.ql .rq-chev { color: var(--brand); }

/* ---------- Forms, tables (Settings + Email recipients) ---------- */
label { display: block; font-size: .82rem; font-weight: 700; margin-bottom: 5px; }
input[type=text], input[type=email], input[type=number], input[type=date], input[type=time], select, textarea {
  width: 100%; padding: 9px 11px; border: 1px solid #c9cfe0; border-radius: 8px; font: inherit; font-size: .88rem; background: var(--white); color: var(--ink);
}
input[type=checkbox] { accent-color: var(--brand); }
input:focus, select:focus, textarea:focus { outline: 2px solid var(--brand); outline-offset: -1px; border-color: transparent; }
.mg-hint { font-size: .78rem; color: var(--muted); font-weight: 400; margin: 5px 0 0; line-height: 1.5; }
.mg-btn { background: var(--brand); color: #fff; border: 0; border-radius: 8px; padding: 9px 18px; font: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; transition: background-color .15s ease; }
.mg-btn:hover { background: var(--brand-dark); }
.mg-btn:disabled { opacity: .6; cursor: default; }
.mg-btn-sm { padding: 6px 13px; font-size: .78rem; }
.mg-btn-ghost { background: transparent; color: var(--brand); border: 1.5px solid var(--brand); }
.mg-btn-ghost:hover { background: var(--brand); color: #fff; }
a.mg-btn { display: inline-block; text-decoration: none; }
.mg-logfilter { background: var(--tint-50); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; display: flex; flex-direction: column; gap: 12px; }
.mg-logfilter.is-active { border-color: var(--gold); box-shadow: inset 4px 0 0 var(--brand); }
.mg-lf-main { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px 12px; }
.mg-lf-field { display: flex; flex-direction: column; gap: 4px; }
.mg-lf-field label { margin: 0; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.mg-lf-field input[type=date] { width: auto; min-width: 150px; background: #fff; }
.mg-lf-arrow { color: var(--muted); font-size: 1.1rem; padding-bottom: 9px; }
.mg-lf-actions { display: flex; gap: 8px; margin-left: auto; }
.mg-lf-presets { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.mg-lf-presets-label { font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-right: 2px; }
.mg-lf-chip { font: inherit; font-size: .76rem; font-weight: 600; padding: 5px 12px; border-radius: 999px; border: 1.5px solid var(--tint-200); background: #fff; color: var(--brand); cursor: pointer; transition: background-color .15s ease, color .15s ease, border-color .15s ease; }
.mg-lf-chip:hover { background: var(--tint-100); border-color: var(--gold); }
.mg-lf-chip.on { background: var(--brand); border-color: var(--brand); color: #fff; }
.mg-lf-result { align-self: flex-start; font-size: .8rem; color: var(--gold-ink); background: var(--tint-100); border-radius: 999px; padding: 5px 14px; }
@media (max-width: 560px) { .mg-lf-arrow { display: none; } .mg-lf-actions { margin-left: 0; width: 100%; } .mg-lf-field { flex: 1; min-width: 140px; } .mg-lf-field input[type=date] { width: 100%; } }
.mg-btn-danger { background: transparent; color: var(--err); border: 1.5px solid var(--err); }
.mg-btn-danger:hover { background: var(--err); color: #fff; }
.mg-btn-danger-solid { background: var(--err); color: #fff; border: 0; }
.mg-btn-danger-solid:hover { background: #7e1d22; }
.mg-btn-done { background: var(--ok); color: #fff; border: 0; }
.mg-btn-done:hover { background: #0b5c0b; }

/* Logs tab pagination - keeps only one page of rows in the DOM at a time,
   so the huge Activity Log table can't inflate the shared tab-panel grid
   cell (see ".mg-panels" above) and drag Dashboard/Calendar/etc. along with it. */
.mg-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
.mg-pager-size { display: flex; align-items: center; gap: 6px; }
.mg-pager-size select { font: inherit; font-size: .8rem; padding: 5px 8px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--ink); }
.mg-pager-btns { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.mg-pager-btns .mg-btn-sm { padding: 6px 11px; }
.mg-pager-ellipsis { padding: 0 4px; color: var(--ink); opacity: .5; }
.mg-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; margin-bottom: 18px; }
table.mg-list { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
table.mg-list th { text-align: left; font-size: .76rem; color: var(--muted); border-bottom: 2px solid var(--line); padding: 8px 10px; }
table.mg-list td { border-bottom: 1px solid var(--line-soft); padding: 9px 10px; vertical-align: middle; }
.mg-pill { display: inline-block; font-size: .7rem; font-weight: 700; padding: 3px 9px; border-radius: 12px; }
.mg-pill.on  { background: var(--ok-bg); color: var(--ok); }
.mg-pill.off { background: #eceef5; color: var(--muted); }
.mg-row-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.mg-row-form input[type=email] { min-width: 210px; flex: 1; }
.mg-row-form input[type=text] { min-width: 150px; flex: 1; }
.mg-chk { display: flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 600; white-space: nowrap; margin: 0; }
.mg-chk input { width: auto; }
.mg-switch { display: inline-flex; align-items: center; gap: 8px; font-size: .78rem; font-weight: 600; white-space: nowrap; margin: 0; cursor: pointer; position: relative; }
.mg-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.mg-slider { position: relative; flex: none; width: 40px; height: 22px; border-radius: 22px; background: #c3c8d6; transition: background .2s; }
.mg-slider::before { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .2s; }
.mg-switch input:checked + .mg-slider { background: var(--brand); }
.mg-switch input:checked + .mg-slider::before { transform: translateX(18px); }
.mg-switch input:focus-visible + .mg-slider { outline: 2px solid var(--brand); outline-offset: 2px; }
.mg-switch-text { min-width: 44px; color: var(--muted); }

/* ---------- Report popup (day list, summary, editor) ---------- */
.mg-overlay { position: fixed; inset: 0; background: rgba(12,20,55,.55); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; padding: 18px; z-index: 60; }
.mg-overlay.show { display: flex; }
.mg-modal { background: var(--white); border-radius: 16px; max-width: 660px; width: 100%; max-height: 88vh; overflow: auto; box-shadow: 0 24px 60px rgba(12,20,55,.35); }
.mg-mh { position: sticky; top: 0; z-index: 1; background: var(--brand); color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; font-weight: 700; font-size: 1rem; }
.mg-mh button { flex: none; background: rgba(255,255,255,.16); border: 0; color: #fff; width: 30px; height: 30px; border-radius: 50%; font-size: 1.2rem; line-height: 1; cursor: pointer; }
.mg-mh button:hover { background: rgba(255,255,255,.28); }
.mg-mb { padding: 18px 20px 20px; }
.mg-summary { background: var(--tint-50); border: 1px solid var(--line); border-radius: 10px; padding: 10px 13px; font-size: .82rem; margin-bottom: 14px; font-weight: 600; color: var(--brand); }
.mg-detail { border: 1px solid var(--line); border-left: 4px solid var(--brand); border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; }
.mg-detail.soft { border-left-color: #1f8a4c; }
.mg-detail.svc { border-left-color: #6a3fb8; }
.mg-detail.inc { border-left-color: #b3261e; }
.mg-detail.cancelled { border-left-color: #8d93a8; background: #f7f8fb; }
.mg-dhead { font-weight: 700; font-size: .86rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mg-dl { margin: 8px 0 0; padding: 0; }
.mg-row { display: grid; grid-template-columns: minmax(110px, 34%) 1fr; gap: 12px; padding: 6px 0; border-top: 1px solid var(--line-soft); font-size: .82rem; line-height: 1.5; }
.mg-row:first-child { border-top: 0; }
.mg-row dt { color: var(--muted); font-weight: 600; }
.mg-row dd { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
.mg-sched { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--line); }
.mg-sched-title { font-size: .78rem; font-weight: 700; color: var(--brand); margin-bottom: 6px; }
.mg-sched ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.mg-sched li { display: flex; justify-content: space-between; gap: 12px; font-size: .8rem; padding: 6px 9px; border-radius: 6px; background: var(--tint-50); color: var(--muted); }
.mg-sched li.on { background: var(--tint-100); color: var(--ink); font-weight: 700; box-shadow: inset 3px 0 0 var(--brand); }
.mg-type-pill { display: inline-block; font-size: .68rem; font-weight: 700; padding: 2px 9px; border-radius: 10px; color: #fff; background: var(--brand); }
.mg-tag-cancel, .mg-badge-cancel { display: inline-block; font-size: .66rem; font-weight: 700; padding: 1px 8px; border-radius: 10px; background: var(--err-bg); color: var(--err); vertical-align: middle; }
.mg-badge-cancel { margin-left: 8px; }
.mg-tag-done, .mg-badge-done { display: inline-block; font-size: .66rem; font-weight: 700; padding: 1px 8px; border-radius: 10px; background: var(--ok-bg); color: var(--ok); vertical-align: middle; }
.mg-badge-done { margin-left: 8px; }
.mg-done-note { margin-top: 8px; padding: 8px 10px; border-radius: 6px; background: var(--ok-bg); color: var(--ok); font-size: .8rem; line-height: 1.5; }
.mg-cancel-note { margin-top: 8px; padding: 8px 10px; border-radius: 6px; background: var(--err-bg); color: var(--err); font-size: .8rem; line-height: 1.5; }
.mg-actions { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--line); display: flex; gap: 8px; flex-wrap: wrap; }
.mg-cancel-box { display: none; margin-top: 12px; padding: 10px 12px; border: 1px solid #f3c8cc; background: #fff8f8; border-radius: 8px; }
.mg-detail.confirming .mg-cancel-box { display: block; }
.mg-detail.confirming .mg-actions { display: none; }
.mg-cancel-box p { margin: 0 0 8px; font-size: .8rem; line-height: 1.5; }
.mg-cancel-reason { resize: vertical; margin-bottom: 8px; font-size: .82rem; }
.mg-cancel-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.mg-done-box { display: none; margin-top: 12px; padding: 10px 12px; border: 1px solid #bfe3c9; background: var(--ok-bg); border-radius: 8px; }
.mg-detail.confirming-done .mg-done-box { display: block; }
.mg-detail.confirming-done .mg-actions { display: none; }
.mg-done-box p { margin: 0 0 8px; font-size: .8rem; line-height: 1.5; }
.mg-done-handler { width: 100%; margin-bottom: 4px; font-size: .82rem; }
.mg-done-err { display: none; margin: 0 0 8px; font-size: .76rem; color: var(--err); }
.mg-done-box.err .mg-done-err { display: block; }
.mg-rep-head { font-size: .76rem; font-weight: 700; color: var(--muted); margin: 0 0 8px; }
.mg-rep-list { display: flex; flex-direction: column; gap: 8px; }
.mg-rep-row {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; text-align: left;
  background: var(--white); border: 1px solid var(--line); border-left: 5px solid var(--brand); border-radius: 10px;
  padding: 10px 12px; cursor: pointer; font-family: inherit; color: var(--ink); transition: background-color .12s ease, box-shadow .12s ease;
}
.mg-rep-row:hover { background: var(--tint-50); box-shadow: 0 2px 8px rgba(43,74,158,.12); }
.mg-rep-row.soft { border-left-color: #1f8a4c; }
.mg-rep-row.svc { border-left-color: #6a3fb8; }
.mg-rep-row.inc { border-left-color: #b3261e; }
.mg-rep-row.cancelled { border-left-color: #8d93a8; background: #f7f8fb; }
.mg-rep-row.cancelled .mg-rep-title { text-decoration: line-through; opacity: .7; }
.mg-rep-row.done { background: #f6fbf5; }
.mg-rep-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mg-rep-title { font-weight: 700; font-size: .9rem; color: var(--brand); overflow-wrap: anywhere; }
.mg-rep-sub { font-size: .76rem; color: var(--muted); }
.mg-rep-chev { flex: none; font-size: 1.4rem; color: var(--muted); line-height: 1; }
.mg-back { background: transparent; border: 0; color: var(--brand); font: inherit; font-size: .8rem; font-weight: 700; cursor: pointer; padding: 0 0 10px; }
.mg-back:hover { text-decoration: underline; }
.mg-edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; margin-top: 10px; }
.mg-edit-field.full { grid-column: 1 / -1; }
.mg-edit-field label { margin-bottom: 4px; }
.mg-edit-field textarea { resize: vertical; }
.mg-edit-other { margin-top: 6px; }
.mg-req { color: var(--err); }
.mg-ev-dot-pick { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-left: 6px; vertical-align: middle; background: transparent; }
.mg-edit-error { display: none; margin-top: 12px; padding: 8px 10px; border-radius: 6px; background: var(--err-bg); color: var(--err); font-size: .8rem; line-height: 1.5; font-weight: 600; }
.mg-edit-error.show { display: block; }
.mg-edit-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px solid var(--line); }

/* ---------- Footer ---------- */
.page-footer { margin-top: 32px; padding: 14px 20px; text-align: center; font-size: .76rem; color: rgba(255,255,255,.88); background: var(--brand); }

/* ---------- Responsive ---------- */
@media (max-width: 1280px) {
  .stat { padding: 14px; gap: 10px; }
  .stat-ic { width: 40px; height: 40px; }
  .stat-ic .ic { width: 20px; height: 20px; }
  .stat-num { font-size: 1.85rem; }
}
@media (max-width: 1180px) {
  .dash { grid-template-columns: minmax(0, 1fr); }
  .dash-side { display: grid; grid-template-columns: 1fr 1fr; align-items: start; }
}
@media (max-width: 980px) {
  .cal-row { grid-template-columns: minmax(0, 1fr); }
  .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .stats .stat:last-child:nth-child(odd) { grid-column: 1 / -1; }
}
@media (max-width: 720px) {
  .shell { padding: 14px 14px 28px; }
  .dash-side { grid-template-columns: minmax(0, 1fr); }
  .mg-cal { border-radius: 8px; }
  .mg-cell { min-height: 62px; padding: 4px; flex-direction: row; flex-wrap: wrap; align-content: flex-start; gap: 4px; }
  .mg-cell-top { width: 100%; }
  .mg-avail.free { display: none; }
  .mg-ev { margin: 0; padding: 0; background: none; }
  .mg-ev-txt, .mg-more { display: none; }
  .mg-ev-dot { width: 8px; height: 8px; }
  .mg-dow { font-size: .66rem; padding: 8px 0; }
  .mg-edit-grid { grid-template-columns: 1fr; }
  .mg-row { grid-template-columns: 1fr; gap: 1px; }
  .mg-sched li { flex-direction: column; gap: 0; }
}
@media (max-width: 640px) {
  .nav-right { display: none; }
  .mg-tabs { width: 100%; }
  .mg-tabs a { flex: 0 0 auto; padding: 9px 12px; }
  .mg-tabs a:not(.on) .tab-label { display: none; }
  .mg-tabs a.on { flex: 1; justify-content: center; }
  .topbar-inner, .nav-inner { padding-left: 14px; padding-right: 14px; }
  .brand-badge img { height: 46px; }
}
@media (max-width: 440px) {
  .stat { flex-direction: column; align-items: flex-start; gap: 8px; }
}
</style>
</head>
<body>

<!-- Icon sprite: every icon on the page is a <use href="#i-..."> of one of these. -->
<svg class="sprite" aria-hidden="true" focusable="false">
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11.2 12 4l9 7.2V20a1 1 0 0 1-1 1h-4.5v-6h-7v6H4a1 1 0 0 1-1-1z"/></symbol>
  <symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 6.5 8 6.5 8-6.5"/></symbol>
  <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V19.5a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1.04H4.5a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.56-1.04 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H10.5a1.7 1.7 0 0 0 1.04-1.56V4.5a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V10.5a1.7 1.7 0 0 0 1.56 1.04h.09a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.56 1.04z"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
  <symbol id="i-doc" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></symbol>
  <symbol id="i-proj" viewBox="0 0 24 24"><rect x="2.5" y="7" width="14" height="9" rx="1.8"/><circle cx="6.2" cy="11.5" r="1.6"/><path d="m16.5 10.2 4.5-2.4v9l-4.5-2.4M9.5 19.5h3"/></symbol>
  <symbol id="i-soft" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="1.6"/><path d="M8 21h8M12 17v4M7.5 9.5l2.2 2.2 4.8-4.8"/></symbol>
  <symbol id="i-wrench" viewBox="0 0 24 24"><path d="M14.7 6.3a3.5 3.5 0 0 0-4.6 4.1L4 16.5V20h3.5l6.1-6.1a3.5 3.5 0 0 0 4.1-4.6l-2.6 2.6-2-2 2.6-2.6z"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3.2 2.5 20.5h19z"/><path d="M12 9.5v5M12 17.3v.01"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></symbol>
  <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></symbol>
  <symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.5"/><circle cx="3.5" cy="12" r="1.5"/><circle cx="3.5" cy="18" r="1.5"/></symbol>
  <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20.5 12a8.5 8.5 0 1 1-2.5-6M20.5 4v4.5H16"/></symbol>
  <symbol id="i-chev-l" viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></symbol>
  <symbol id="i-chev-r" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
</svg>

<header class="topbar">
  <div class="topbar-inner">
    <div class="brand-badge"><img src="img/logo.png" alt="ITS - Information Technology Services"></div>
    <div class="brand-text">
      <h1>ITS Department</h1>
      <p>Information Technology Services</p>
    </div>
  </div>
</header>

<div class="nav-wrap" id="manage">
  <div class="nav-inner">
    <nav class="mg-tabs" aria-label="Sections">
      <a href="?tab=dashboard#manage" data-tab="dashboard" aria-label="Dashboard" class="<?= $tab === 'dashboard' ? 'on' : '' ?>"<?= $tab === 'dashboard' ? ' aria-current="page"' : '' ?>>
        <svg class="ic" aria-hidden="true"><use href="#i-home"/></svg> <span class="tab-label">Dashboard</span>
      </a>
      <a href="?tab=calendar#manage" data-tab="calendar" aria-label="Request Calendar" class="<?= $tab === 'calendar' ? 'on' : '' ?>"<?= $tab === 'calendar' ? ' aria-current="page"' : '' ?>>
        <svg class="ic" aria-hidden="true"><use href="#i-cal"/></svg> <span class="tab-label">Request Calendar</span>
      </a>
      <a href="?tab=mail#manage" data-tab="mail" aria-label="Email Recipients" class="<?= $tab === 'mail' ? 'on' : '' ?>"<?= $tab === 'mail' ? ' aria-current="page"' : '' ?>>
        <svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg> <span class="tab-label">Email Recipients</span>
      </a>
      <a href="?tab=settings#manage" data-tab="settings" aria-label="Settings" class="<?= $tab === 'settings' ? 'on' : '' ?>"<?= $tab === 'settings' ? ' aria-current="page"' : '' ?>>
        <svg class="ic" aria-hidden="true"><use href="#i-gear"/></svg> <span class="tab-label">Settings</span>
      </a>
      <a href="?tab=logs#manage" data-tab="logs" aria-label="Logs" class="<?= $tab === 'logs' ? 'on' : '' ?>"<?= $tab === 'logs' ? ' aria-current="page"' : '' ?>>
        <svg class="ic" aria-hidden="true"><use href="#i-list"/></svg> <span class="tab-label">Logs</span>
      </a>
    </nav>
    <div class="nav-right">
      <button type="button" class="nav-refresh on" id="autoRefreshBtn" aria-pressed="true">
        <svg class="ic" aria-hidden="true"><use href="#i-refresh"/></svg> <span id="autoRefreshTxt">Auto-refresh on</span>
      </button>
      <div class="nav-date"><svg class="ic" aria-hidden="true"><use href="#i-cal"/></svg> <?= h(date('D, M j, Y')) ?></div>
    </div>
  </div>
</div>

<main class="shell">

  <?php if ($flash): ?>
    <div class="mg-flash <?= h($flashType) ?>" role="status" id="flashToast">
      <span><?= h($flash) ?></span>
      <button type="button" class="mg-flash-close" id="flashClose" aria-label="Dismiss">&times;</button>
    </div>
    <script>
    (function () {
      var el = document.getElementById('flashToast');
      var btn = document.getElementById('flashClose');
      if (!el) return;
      function dismiss() {
        el.classList.add('hide');
        setTimeout(function () { el.remove(); }, 200);
      }
      var timer = setTimeout(dismiss, 4000); // auto-close
      if (btn) btn.addEventListener('click', function () { clearTimeout(timer); dismiss(); });
    })();
    </script>
  <?php endif; ?>

<div class="mg-panels">

<!-- ================= DASHBOARD ================= -->
<div class="mg-panel <?= $tab === 'dashboard' ? 'active' : '' ?>" id="panel-dashboard">

  <div class="dash">
    <div class="dash-main">

      <?php
      // Counts cover the rolling 7-day window that starts today.
      $stats = [
          ['sky',    'i-proj',   'Projector Setups',  $weekProjectorCount],
          ['teal',   'i-proj',   'Teachmint Setups',  $weekTeachmintCount],
          ['green',  'i-soft',   'Software Installs', $weekSoftwareCount],
          ['purple', 'i-wrench', 'Service Calls',     $weekServiceCount],
          ['red',    'i-alert',  'Incident Reports',  $weekIncidentCount],
      ];
      ?>
      <div class="stats">
        <?php foreach ($stats as $s): ?>
          <div class="stat <?= $s[0] ?>" title="<?= h(date('M j', strtotime($weekStart)) . ' – ' . date('M j', strtotime($weekEnd))) ?>">
            <span class="stat-ic"><svg class="ic" aria-hidden="true"><use href="#<?= $s[1] ?>"/></svg></span>
            <div class="stat-body">
              <span class="stat-lbl"><?= h($s[2]) ?></span>
              <span class="stat-num"><?= (int) $s[3] ?></span>
              <span class="stat-sub"><svg class="ic" aria-hidden="true"><use href="#i-cal"/></svg> Next 7 days</span>
            </div>
          </div>
        <?php endforeach; ?>
        <button type="button" class="stat amber stat-click" id="pendingCard" title="Show all pending requests">
          <span class="stat-ic"><svg class="ic" aria-hidden="true"><use href="#i-clock"/></svg></span>
          <span class="stat-body">
            <span class="stat-lbl">Pending</span>
            <span class="stat-num"><?= (int) $pendingCount ?></span>
            <span class="stat-sub"><svg class="ic" aria-hidden="true"><use href="#i-doc"/></svg> Click to view all</span>
          </span>
        </button>
      </div>

      <div class="cal-row">
        <?php render_calendar($cal, 'dashboard', false); ?>

        <section class="mg-card agenda" aria-labelledby="agTitle">
          <div class="ag-head">
            <h3 class="hd-title" id="agTitle"><svg class="ic" aria-hidden="true"><use href="#i-cal"/></svg> Today&rsquo;s Agenda</h3>
            <p><?= h(date('l, M j, Y', strtotime($todayStr))) ?></p>
          </div>
          <div class="ag-list">
            <?php if (empty($agendaItems)): ?>
              <p class="ag-empty">Nothing scheduled for today.</p>
            <?php else: ?>
              <?php foreach ($agendaItems as $it):
                $when  = $it['time'] !== '' ? str_replace(' - ', ' – ', $it['time']) : type_label($it['type']);
                $where = $it['venue'] ?: ($it['dept'] ?: '');
              ?>
                <div class="ag-item mg-event <?= h(chip_classes($it)) ?>" role="button" tabindex="0"
                     data-event-key="<?= h($it['type'] . '|' . $it['id']) ?>" data-event-date="<?= h($todayStr) ?>">
                  <i class="ag-dot"<?= chip_style($it) ?>></i>
                  <div class="ag-body">
                    <div class="ag-time"><?= h($when) ?><?= !empty($it['cancelled']) ? ' <span class="mg-tag-cancel">Cancelled</span>' : (!empty($it['done']) ? ' <span class="mg-tag-done">Done</span>' : '') ?></div>
                    <div class="ag-title"><?= h(chip_prefix($it) . ($it['what'] ?: type_label($it['type']))) ?></div>
                    <?php if ($where !== ''): ?>
                      <div class="ag-place"><svg class="ic" aria-hidden="true"><use href="#i-pin"/></svg> <?= h($where) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="ag-foot-wrap">
            <div class="ag-foot"><svg class="ic" aria-hidden="true"><use href="#i-proj"/></svg> <?= (int) $todayFree ?> of <?= (int) $totalUnits ?> projectors free today</div>
          </div>
        </section>
      </div>

    </div>

    <aside class="dash-side">
      <section class="mg-card">
        <h3 class="side-head"><svg class="ic" aria-hidden="true"><use href="#i-send"/></svg> Request</h3>
        <div class="side-body">
          <a class="rq green" href="software.php">
            <span class="rq-ic"><svg class="ic" aria-hidden="true"><use href="#i-soft"/></svg></span>
            <span class="rq-txt"><strong>Software Installation Request</strong><span>Request licensed software or freeware to be installed on a computer laboratory unit.</span></span>
            <svg class="ic rq-chev" aria-hidden="true"><use href="#i-chev-r"/></svg>
          </a>
          <a class="rq sky" href="projector.php">
            <span class="rq-ic"><svg class="ic" aria-hidden="true"><use href="#i-proj"/></svg></span>
            <span class="rq-txt"><strong>LCD Projector Setup Request</strong><span>Request an LCD projector to be set up for a class, event, or meeting.</span></span>
            <svg class="ic rq-chev" aria-hidden="true"><use href="#i-chev-r"/></svg>
          </a>
          <a class="rq amber" href="teachmint.php">
            <span class="rq-ic"><svg class="ic" aria-hidden="true"><use href="#i-proj"/></svg></span>
            <span class="rq-txt"><strong>Teachmint Setup Request</strong><span>Request a Teachmint setup for a class, event, or meeting.</span></span>
            <svg class="ic rq-chev" aria-hidden="true"><use href="#i-chev-r"/></svg>
          </a>
          <a class="rq purple" href="service_call.php">
            <span class="rq-ic"><svg class="ic" aria-hidden="true"><use href="#i-wrench"/></svg></span>
            <span class="rq-txt"><strong>Service Call Request</strong><span>Report a software or hardware issue and send a service request to the ITS team for assistance.</span></span>
            <svg class="ic rq-chev" aria-hidden="true"><use href="#i-chev-r"/></svg>
          </a>
          <a class="rq red" href="incident_report.php">
            <span class="rq-ic"><svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg></span>
            <span class="rq-txt"><strong>IT Incident Report</strong><span>Report an IT incident - outage, security issue, data loss, or equipment failure - for the ITS team to document and resolve.</span></span>
            <svg class="ic rq-chev" aria-hidden="true"><use href="#i-chev-r"/></svg>
          </a>
        </div>
      </section>
    </aside>
  </div>
</div>

<!-- ================= REQUEST CALENDAR (full width) ================= -->
<div class="mg-panel <?= $tab === 'calendar' ? 'active' : '' ?>" id="panel-calendar">
  <?php render_calendar($cal, 'calendar', true); ?>
</div>

<!-- ================= MAIL RECIPIENTS ================= -->
<div class="mg-panel <?= $tab === 'mail' ? 'active' : '' ?>" id="panel-mail">
  <div class="mg-card">
    <div class="mg-card-header">Add a Recipient</div>
    <div class="mg-card-body">
      <form method="post" class="mg-row-form">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="recipient_add">
        <input type="email" name="email" placeholder="name@example.com" aria-label="Email address" required>
        <input type="text" name="name" placeholder="Display name (optional)" aria-label="Display name">
        <button class="mg-btn" type="submit">Add recipient</button>
      </form>
      <p class="mg-hint">Every <strong>active</strong> address below always receives the event reminders (1 day / 1 hour / 30 minutes). The <strong>New-request emails</strong> switch lets an active address opt in or out of the email sent when a new request is submitted.</p>
    </div>
  </div>

  <div class="mg-card">
    <div class="mg-card-header">Recipients (<?= count($recipients) ?>)</div>
    <div class="mg-card-body">
      <?php if (empty($recipients)): ?>
        <p class="mg-hint">No recipients yet. Until one is added, mail falls back to the address in <code>mail_config.php</code>.</p>
      <?php else: ?>
        <form method="post" id="rcptBulkForm">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="recipients_bulk_save">
          <table class="mg-list">
            <thead>
              <tr><th>Email</th><th>Name</th><th>Status</th><th>New-request emails</th><th style="width:1%"></th></tr>
            </thead>
            <tbody>
            <?php foreach ($recipients as $r): $rid = (int) $r['id']; ?>
              <tr>
                <td><input type="email" name="email[<?= $rid ?>]" value="<?= h($r['email']) ?>" aria-label="Email address" required></td>
                <td><input type="text" name="name[<?= $rid ?>]" value="<?= h($r['name']) ?>" aria-label="Display name"></td>
                <td>
                  <label class="mg-switch" title="Paused inboxes get no emails at all. Active inboxes always get event reminders.">
                    <input type="checkbox" name="is_active[<?= $rid ?>]" value="1" <?= $r['is_active'] ? 'checked' : '' ?>
                           onchange="document.getElementById('rcpt-status-<?= $rid ?>').textContent = this.checked ? 'Active' : 'Paused';">
                    <span class="mg-slider"></span>
                    <span class="mg-switch-text" id="rcpt-status-<?= $rid ?>"><?= $r['is_active'] ? 'Active' : 'Paused' ?></span>
                  </label>
                </td>
                <td>
                  <label class="mg-switch" title="Opt in or out of the email sent when a new request is submitted. Reminders are not affected.">
                    <input type="checkbox" name="notify_new[<?= $rid ?>]" value="1" <?= !empty($r['notify_new_requests']) ? 'checked' : '' ?>
                           onchange="document.getElementById('rcpt-notify-<?= $rid ?>').textContent = this.checked ? 'On' : 'Off';">
                    <span class="mg-slider"></span>
                    <span class="mg-switch-text" id="rcpt-notify-<?= $rid ?>"><?= !empty($r['notify_new_requests']) ? 'On' : 'Off' ?></span>
                  </label>
                </td>
                <td style="white-space:nowrap">
                  <button class="mg-btn mg-btn-sm mg-btn-danger" type="submit" form="rcpt-del-<?= $rid ?>">Remove</button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <button class="mg-btn" type="submit">Save changes</button>
        </form>
        <?php foreach ($recipients as $r): $rid = (int) $r['id']; ?>
          <form id="rcpt-del-<?= $rid ?>" method="post" hidden
                onsubmit="return confirm('Remove <?= h($r['email']) ?> from the notification list?');">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="recipient_delete">
            <input type="hidden" name="id" value="<?= $rid ?>">
          </form>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ================= SETTINGS ================= -->
<div class="mg-panel <?= $tab === 'settings' ? 'active' : '' ?>" id="panel-settings">
  <div class="mg-card">
    <div class="mg-card-header">Projector Borrowing Limits</div>
    <div class="mg-card-body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="mg-settings-grid">
          <div>
            <label for="total">Total projectors available</label>
            <input type="number" id="total" name="projector_total_units" min="1" max="100" required
                   value="<?= (int) $settings['projector_total_units'] ?>">
            <p class="mg-hint">How many units the ITS office can lend out on any single day. The calendar counts down from this number.</p>
          </div>
          <div>
            <label for="perreq">Maximum units per request</label>
            <input type="number" id="perreq" name="max_units_per_request" min="1" max="100" required
                   value="<?= (int) $settings['max_units_per_request'] ?>">
            <p class="mg-hint">The highest number a single requester may ask for on the projector form. Clamped to the total above if set higher.</p>
          </div>
          <div>
            <label for="perday">Maximum requests per day</label>
            <input type="number" id="perday" name="daily_request_limit" min="1"
                   max="<?= (int) $settings['projector_total_units'] ?>" required
                   value="<?= (int) $settings['daily_request_limit'] ?>">
            <p class="mg-hint">How many separate projector requests a single calendar date will accept, regardless of units. Cannot be set higher than the total projectors available above.</p>
          </div>
        </div>
        <button class="mg-btn" type="submit">Save settings</button>
      </form>
    </div>
  </div>
</div>

<!-- ================= LOGS ================= -->
<div class="mg-panel <?= $tab === 'logs' ? 'active' : '' ?>" id="panel-logs">
  <div class="mg-card">
    <div class="mg-card-header">Activity Log<?= $activityLogs ? ' (' . count($activityLogs) . ')' : '' ?><?= $logFiltered ? ' - filtered' : '' ?></div>
    <div class="mg-card-body">
      

      <?php
        // Every month worth offering in the "Download month" picker: months
        // still live in the table, plus months already archived to PDF.
        $downloadMonths = [];
        foreach ($activityLogMonths as $m) $downloadMonths[$m['ym']] = ['ym' => $m['ym'], 'count' => $m['count'], 'archived' => false];
        foreach ($activityLogArchives as $a) {
            if (!isset($downloadMonths[$a['ym']])) $downloadMonths[$a['ym']] = ['ym' => $a['ym'], 'count' => (int) $a['row_count'], 'archived' => true];
        }
        krsort($downloadMonths);
      ?>

      <form method="get" action="<?= h(strtok($_SERVER['REQUEST_URI'], '?')) ?>#manage" class="mg-logfilter<?= $logFiltered ? ' is-active' : '' ?>" id="logsFilterForm">
        <input type="hidden" name="tab" value="logs">
        <div class="mg-lf-main">
          <div class="mg-lf-field">
            <label for="logFrom">From</label>
            <input type="date" id="logFrom" name="log_from" value="<?= h($logFrom) ?>" max="<?= h(date('Y-m-d')) ?>">
          </div>
          <span class="mg-lf-arrow" aria-hidden="true">&rarr;</span>
          <div class="mg-lf-field">
            <label for="logTo">To</label>
            <input type="date" id="logTo" name="log_to" value="<?= h($logTo) ?>" max="<?= h(date('Y-m-d')) ?>">
          </div>
          <div class="mg-lf-actions">
            <button class="mg-btn" type="submit">Apply</button>
            <?php if ($logFiltered): ?><a class="mg-btn mg-btn-ghost" href="?tab=logs#manage">Clear</a><?php endif; ?>
          </div>
        </div>
        <div class="mg-lf-presets">
          <span class="mg-lf-presets-label">Quick range</span>
          <button type="button" class="mg-lf-chip" data-preset="today">Today</button>
          <button type="button" class="mg-lf-chip" data-preset="7">Last 7 days</button>
          <button type="button" class="mg-lf-chip" data-preset="30">Last 30 days</button>
          <button type="button" class="mg-lf-chip" data-preset="month">This month</button>
        </div>
        <?php if ($logFiltered): ?>
          <div class="mg-lf-result">
            <strong><?= count($activityLogs) ?></strong> entr<?= count($activityLogs) === 1 ? 'y' : 'ies' ?>
            <?php if ($logFrom !== '' && $logTo !== ''): ?>
              from <strong><?= h($logFrom) ?></strong> to <strong><?= h($logTo) ?></strong>
            <?php elseif ($logFrom !== ''): ?>
              from <strong><?= h($logFrom) ?></strong> onward
            <?php else: ?>
              up to <strong><?= h($logTo) ?></strong>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </form>

      <?php if (empty($activityLogs)): ?>
        <p class="mg-hint"><?= $logFiltered ? 'No activity found in that date range.' : 'No activity recorded yet this month.' ?></p>
        <button type="button" class="mg-btn" id="logsPdfOpenBtn" <?= $downloadMonths ? '' : 'disabled' ?> style="margin-top:8px">Generate PDF</button>
      <?php else: ?>
        <div style="overflow-x:auto">
        <table class="mg-list">
          <thead>
            <tr>
              <th style="width:1%; white-space:nowrap">When</th>
              <th style="width:1%; white-space:nowrap">Action</th>
              <th style="width:1%; white-space:nowrap">Event</th>
              <th style="width:1%; white-space:nowrap">Requested By</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody id="logsTbody">
          <?php foreach ($activityLogs as $log):
              $when        = format_detail_value('created_at', $log['created_at']);
              $extra       = activity_log_detail_text($log['action'], $log['details']);
              $eventType   = trim((string) ($log['event_type'] ?? ''));
              $requestedBy = trim((string) ($log['requested_by'] ?? ''));
              $logYm       = substr((string) $log['created_at'], 0, 7);
              // The chip shows the Purpose / Event but keeps the colour of the request's Type of Event
              // (stored in the details). Older entries stored the type itself, so it colours itself.
              $chipColorKey = array_key_exists('type_of_event', $log['details']) ? $log['details']['type_of_event'] : $eventType;
          ?>
            <tr data-ym="<?= h($logYm) ?>">
              <td style="white-space:nowrap"><?= h($when) ?></td>
              <td style="white-space:nowrap">
                <span class="mg-pill" style="background:<?= h(activity_log_action_color($log['action'])) ?>; color:#fff">
                  <?= h(activity_log_action_label($log['action'])) ?>
                </span>
              </td>
              <td style="white-space:nowrap">
                <?php if ($eventType !== ''): ?>
                  <span class="mg-pill" title="<?= h($eventType) ?>" style="background:<?= h(event_type_color($chipColorKey)) ?>; color:#fff; display:inline-block; max-width:260px; overflow:hidden; text-overflow:ellipsis; vertical-align:middle"><?= h($eventType) ?></span>
                <?php else: ?>
                  <span class="mg-hint">—</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap"><?= $requestedBy !== '' ? h($requestedBy) : '<span class="mg-hint">—</span>' ?></td>
              <td>
                <?= h($log['summary']) ?>
                <?php if ($extra !== ''): ?><div class="mg-hint" style="margin:2px 0 0"><?= h($extra) ?></div><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <div class="mg-pager" id="logsPager">
          <div class="mg-pager-size">
            <span class="mg-hint" id="logsPagerInfo"></span>
            <label class="mg-hint" for="logsPageSize" style="margin-left:8px">Rows per page</label>
            <select id="logsPageSize">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <button type="button" class="mg-btn" id="logsPdfOpenBtn" <?= $downloadMonths ? '' : 'disabled' ?> style="margin-left:8px">Generate PDF</button>
          </div>
          <div class="mg-pager-btns" id="logsPagerBtns"></div>
        </div>
      <?php endif; ?>

      <?php if ($activityLogArchives): ?>
        <div class="mg-card-header" style="margin-top:24px">Archived Monthly Reports</div>
        <p class="mg-hint">These months have already been exported and cleared from the table above. Each is still downloadable any time.</p>
        <div style="overflow-x:auto">
        <table class="mg-list">
          <thead>
            <tr>
              <th style="width:1%; white-space:nowrap">Month</th>
              <th style="width:1%; white-space:nowrap">Entries</th>
              <th style="width:1%; white-space:nowrap">Archived on</th>
              <th style="width:1%; white-space:nowrap"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activityLogArchives as $a): ?>
              <tr>
                <td style="white-space:nowrap"><?= h(activity_log_month_label($a['ym'])) ?></td>
                <td style="white-space:nowrap"><?= (int) $a['row_count'] ?></td>
                <td style="white-space:nowrap"><?= h(format_detail_value('created_at', $a['created_at'])) ?></td>
                <td style="white-space:nowrap">
                  <a class="mg-btn mg-btn-sm mg-btn-ghost" href="?export_logs_pdf=1&amp;ym=<?= urlencode($a['ym']) ?>">Download PDF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</div><!-- /.mg-panels -->
</main>

<!-- Report popup: day list, one report's summary, and the editor share this shell. -->
<div class="mg-overlay" id="dayOverlay">
  <div class="mg-modal" role="dialog" aria-modal="true" aria-labelledby="dayTitle">
    <div class="mg-mh"><span id="dayTitle">Day</span><button type="button" id="dayClose" aria-label="Close">&times;</button></div>
    <div class="mg-mb">
      <div class="mg-summary" id="daySummary"></div>
      <div id="dayItems"></div>
    </div>
  </div>
</div>

<!-- PDF export popup: pick a month (real PDF) or a custom date range (print-to-PDF). -->
<div class="mg-overlay" id="pdfOverlay">
  <div class="mg-modal" role="dialog" aria-modal="true" aria-labelledby="pdfTitle" style="max-width:440px">
    <div class="mg-mh"><span id="pdfTitle">Generate PDF</span><button type="button" id="pdfClose" aria-label="Close">&times;</button></div>
    <div class="mg-mb">
      <div class="mg-seg" style="display:flex; gap:8px; margin-bottom:16px">
        <button type="button" class="mg-btn mg-btn-sm" id="pdfModeMonthBtn">By month</button>
        <button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" id="pdfModeRangeBtn">By date range</button>
      </div>

      <div id="pdfMonthPanel">
        <label for="pdfMonthSelect" style="display:block">Month</label>
        <select id="pdfMonthSelect" style="width:100%">
          <?php foreach ($downloadMonths as $m): ?>
            <option value="<?= h($m['ym']) ?>">
              <?= h(activity_log_month_label($m['ym'])) ?> (<?= (int) $m['count'] ?><?= $m['archived'] ? ', archived' : '' ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <p class="mg-hint" style="margin-top:8px">Downloads a proper PDF for the whole month.</p>
      </div>

      <div id="pdfRangePanel" style="display:none">
        <div style="display:flex; gap:12px; flex-wrap:wrap">
          <div style="flex:1; min-width:140px">
            <label for="pdfFrom" style="display:block">From</label>
            <input type="date" id="pdfFrom" style="width:100%">
          </div>
          <div style="flex:1; min-width:140px">
            <label for="pdfTo" style="display:block">To</label>
            <input type="date" id="pdfTo" style="width:100%">
          </div>
        </div>
        <p class="mg-hint" style="margin-top:8px">Opens a print-ready page for just that range - use your browser's Print dialog and choose "Save as PDF". Only covers activity still on this page (not months already archived and cleared).</p>
      </div>

      <button type="button" class="mg-btn" id="pdfGenerateBtn" style="margin-top:6px">Generate PDF</button>
    </div>
  </div>
</div>

<script>
var DAY_DATA    = <?= json_encode($dayIndex, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
var PENDING_DATA = <?= json_encode($pendingList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
var EVENT_DATA  = <?= json_encode($eventIndex, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
var EVENT_TYPES = <?= json_encode(event_type_palette(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
var EVENT_OTHER = <?= json_encode(EVENT_TYPE_OTHER_VALUE) ?>;
var TOTAL_UNITS = <?= (int) $totalUnits ?>;
var CSRF   = '<?= h($csrf) ?>';
var CAL_YM = '<?= h($ym) ?>';
// Months the automatic archive job just cleared out of the Logs table on THIS
// page load (normally empty) - triggers the one-time automatic PDF download.
var AUTO_DOWNLOAD_LOGS = <?= json_encode(array_values($freshlyArchivedLogs), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var TODAY_STR = '<?= h(date('Y-m-d')) ?>'; // server's "today", Y-m-d

// The report modal has three views:
//   list    - every report on a date, by name          (showList)
//   summary - one report's details, with Edit / Mark as done / Cancel  (showReport)
//   editor  - the report's fields as a form             (showEdit)
// Reports are looked up by "type|id" (the database row id); reference numbers
// are not used or shown here.
(function () {
  var overlay = document.getElementById('dayOverlay');
  var modal   = overlay.querySelector('.mg-modal');
  var title   = document.getElementById('dayTitle');
  var summary = document.getElementById('daySummary');
  var list    = document.getElementById('dayItems');

  var currentDate = null;    // Y-m-d the modal is showing
  var currentKey  = null;    // "type|id" of the report open in the summary / editor
  var fromList    = false;   // whether the open report was reached from the list
  var inPending   = false;   // whether the list being shown is the Pending card's list

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function toDate(dateStr) {
    var parts = dateStr.split('-');
    return new Date(+parts[0], +parts[1] - 1, +parts[2]);
  }
  function fmtDate(dateStr) {
    return toDate(dateStr).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }
  function fmtShort(dateStr) {
    return toDate(dateStr).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  var TYPE_LABEL = { projector: 'Projector request', teachmint: 'Teachmint request', software: 'Software request', service: 'Service call', incident: 'Incident report' };
  var TYPE_CLASS = { projector: '', software: 'soft', service: 'svc', incident: 'inc' };

  // A projector request can have several dates, each done independently
  // (it.doneByDate); everything else only ever has the one date it was
  // filed for, so it.done / it.done_at already describe it directly.
  function doneInfoFor(it, date) {
    if (date && it.doneByDate && it.doneByDate[date]) return it.doneByDate[date];
    return { done: !!it.done, done_at: it.done_at || '', done_by: it.done_by || '' };
  }

  function reportName(it) { return it.what || TYPE_LABEL[it.type] || 'Report'; }
  // Event-type colour for an active projector request; '' for everything else.
  function isBooking(it) { return it.type === 'projector' || it.type === 'teachmint'; }
  function activeColor(it) { return (isBooking(it) && !it.cancelled && it.color) ? it.color : ''; }
  // "Meeting" for a projector request with an event type, otherwise the request type.
  function kindLabel(it) { return (isBooking(it) && it.event_type) ? it.event_type : (TYPE_LABEL[it.type] || 'Report'); }
  function reveal() { overlay.classList.add('show'); modal.scrollTop = 0; }

  // ---------------------------------------------------------------- list view
  function reportRow(entry) {
    var it = EVENT_DATA[entry.key];
    var color = activeColor(it);
    var sub = [esc(kindLabel(it))];
    if (inPending && entry.date) sub.push(esc(fmtShort(entry.date)));
    if (entry.time) sub.push(esc(entry.time));
    if (it.name) sub.push(esc(it.name));
    var done = !!doneInfoFor(it, entry.date).done && !it.cancelled;
    return '<button type="button" class="mg-rep-row ' + (TYPE_CLASS[it.type] || '') + (it.cancelled ? ' cancelled' : '') + (done ? ' done' : '') + '"'
         + (color ? ' style="border-left-color:' + esc(color) + '"' : '')
         + ' data-act="open-report" data-key="' + esc(entry.key) + '" data-date="' + esc(entry.date || '') + '">'
         + '<span class="mg-rep-main"><span class="mg-rep-title">' + esc(reportName(it)) + '</span>'
         + '<span class="mg-rep-sub">' + sub.join(' &middot; ') + (it.cancelled ? ' &middot; <span class="mg-tag-cancel">Cancelled</span>' : (done ? ' &middot; <span class="mg-tag-done">Done</span>' : '')) + '</span></span>'
         + '<span class="mg-rep-chev" aria-hidden="true">&rsaquo;</span></button>';
  }

  function showList(dateStr) {
    var day = DAY_DATA[dateStr];
    if (!day || !day.items.length) return;
    currentDate = dateStr;
    currentKey = null;
    inPending = false;

    var entries = day.items.filter(function (d) { return EVENT_DATA[d.key]; });
    var gone = entries.filter(function (d) { return EVENT_DATA[d.key].cancelled; }).length;
    var used = day.units || 0;
    var free = Math.max(0, TOTAL_UNITS - used);

    title.textContent = fmtDate(dateStr);
    summary.textContent = (entries.length - gone) + ' active report(s) on this date'
      + (gone ? ' (' + gone + ' cancelled)' : '') + ' - '
      + used + ' of ' + TOTAL_UNITS + ' projectors booked, ' + free + ' still available.';

    list.innerHTML = '<div class="mg-rep-head">Reports &middot; select one to view its summary</div>'
      + '<div class="mg-rep-list">' + entries.map(reportRow).join('') + '</div>';
    reveal();
  }

  // Every pending request, soonest first (the Pending card).
  function showPending() {
    inPending = true;
    currentDate = null;
    currentKey = null;
    var entries = PENDING_DATA.filter(function (d) { return EVENT_DATA[d.key]; });

    title.textContent = 'Pending requests';
    summary.textContent = entries.length + ' pending request(s), soonest first.';
    list.innerHTML = entries.length
      ? '<div class="mg-rep-head">Select one to view its summary</div><div class="mg-rep-list">' + entries.map(reportRow).join('') + '</div>'
      : '<div class="mg-rep-head">No pending requests right now.</div>';
    reveal();
  }

  // ------------------------------------------------------------- summary view
  // opts.dateKey (Y-m-d) marks which of a projector request's dates was clicked.
  function detailBlock(it, opts) {
    opts = opts || {};
    var cancelled = !!it.cancelled;
    var occDate = opts.dateKey || '';
    var doneInfo = doneInfoFor(it, occDate);
    var done = !!doneInfo.done && !cancelled;
    // A date that hasn't arrived yet can't be marked done - except its own
    // day, so an event can still be closed out early the same day it happens.
    var isFuture = !cancelled && !done && occDate !== '' && occDate > TODAY_STR;
    var color = activeColor(it);

    var head;
    if (isBooking(it) && it.event_type) {
      head = '<span class="mg-type-pill" style="background:' + esc(color || '#8d93a8') + '">' + esc(it.event_type) + '</span>';
    } else {
      head = '<span>' + esc(TYPE_LABEL[it.type] || 'Report') + '</span>';
    }
    if (it.type === 'teachmint') head += '<span>&middot; Teachmint</span>';
    if (isBooking(it)) head += '<span>&middot; ' + esc(it.units) + ' unit(s)</span>';
    if (cancelled) head += '<span class="mg-badge-cancel">Cancelled</span>';
    else if (done) head += '<span class="mg-badge-done">Done</span>';

    // Every stored field, one row each.
    var rows = (it.details || []).map(function (d) {
      return '<div class="mg-row"><dt>' + esc(d.label) + '</dt><dd>' + esc(d.value) + '</dd></div>';
    }).join('');

    // Projector requests can span several dates - list them all.
    var sched = '';
    if (it.schedule && it.schedule.length) {
      sched = '<div class="mg-sched"><div class="mg-sched-title">Schedule</div><ul>'
        + it.schedule.map(function (s) {
            var on = opts.dateKey && s.date === opts.dateKey;
            return '<li' + (on ? ' class="on"' : '') + '><span>' + esc(fmtDate(s.date)) + '</span>'
                 + '<span>' + esc(s.time || '') + '</span></li>';
          }).join('')
        + '</ul></div>';
    }

    var note = '', actions = '';
    if (cancelled) {
      note = '<div class="mg-cancel-note"><strong>Cancelled'
           + (it.cancelled_at ? ' on ' + esc(it.cancelled_at) : '') + '.</strong>'
           + (it.cancel_reason ? '<br>Reason: ' + esc(it.cancel_reason) : '')
           + '<br>This request is kept on record and no longer counts toward projector availability.</div>';
    } else {
      var dates = (it.schedule || []).length;
      if (done) {
        note = '<div class="mg-done-note"><strong>Marked as done'
             + (doneInfo.done_at ? ' on ' + esc(doneInfo.done_at) : '') + '.</strong>'
             + (doneInfo.done_by ? '<br>Handled by: ' + esc(doneInfo.done_by) : '')
             + '<br>The event is finished. Use Reopen if it was marked by mistake.</div>';
      } else if (isFuture) {
        note = '<div class="mg-hint">This date hasn\'t happened yet - it can be marked done starting ' + esc(fmtShort(occDate)) + '.</div>';
      }
      actions = '<div class="mg-actions">'
        + ((it.edit && it.edit.length) ? '<button type="button" class="mg-btn mg-btn-sm" data-act="edit-open">Edit</button>' : '')
        + (done
            ? '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="reopen">Reopen</button>'
            : (isFuture
                ? '<button type="button" class="mg-btn mg-btn-sm" disabled title="This date hasn\'t happened yet">Mark as done</button>'
                : '<button type="button" class="mg-btn mg-btn-sm mg-btn-done" data-act="done-open" title="Use this once the event is finished">Mark as done</button>')
              + '<button type="button" class="mg-btn mg-btn-sm mg-btn-danger" data-act="cancel-open">Cancel this request</button>')
        + '</div>'
        + (done ? '' :
            '<div class="mg-cancel-box">'
          + '<p>This cancels the whole request' + (dates > 1 ? ', including all ' + dates + ' scheduled dates' : '')
          + '. The record is kept and stays on the calendar, marked as cancelled.</p>'
          + '<textarea class="mg-cancel-reason" rows="2" maxlength="255" placeholder="Reason for cancelling (optional)"></textarea>'
          + '<div class="mg-cancel-btns">'
          + '<button type="button" class="mg-btn mg-btn-sm mg-btn-danger-solid" data-act="cancel-confirm">Confirm cancellation</button>'
          + '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="cancel-close">Keep request</button>'
          + '</div></div>')
        + (done ? '' :
            '<div class="mg-done-box">'
          + '<p>Before this is marked done - who fixed / handled this request?</p>'
          + '<input type="text" class="mg-done-handler" maxlength="150" placeholder="Name of the ITS staff who handled this">'
          + '<div class="mg-done-err">Please enter a name.</div>'
          + '<div class="mg-cancel-btns">'
          + '<button type="button" class="mg-btn mg-btn-sm mg-btn-done" data-act="done-confirm">Confirm - mark as done</button>'
          + '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="done-close">Cancel</button>'
          + '</div></div>');
    }

    return '<div class="mg-detail ' + (TYPE_CLASS[it.type] || '') + (cancelled ? ' cancelled' : '') + (done ? ' done' : '')
         + '" data-type="' + esc(it.type) + '" data-id="' + esc(it.id) + '" data-date="' + esc(occDate) + '"'
         + (color ? ' style="border-left-color:' + esc(color) + '"' : '') + '>'
         + '<div class="mg-dhead">' + head + '</div>'
         + note
         + '<dl class="mg-dl">' + rows + '</dl>'
         + sched
         + actions
         + '</div>';
  }

  // Shows one report's summary. Reached from a calendar chip / agenda row
  // (dateStr = that chip's date) or from a name in the list view.
  function showReport(key, dateStr, viaList) {
    var it = EVENT_DATA[key];
    if (!it) return;
    currentKey = key;
    currentDate = dateStr || currentDate;
    fromList = !!viaList;

    title.textContent = reportName(it);
    summary.textContent = kindLabel(it) + (currentDate ? ' \u00b7 ' + fmtDate(currentDate) : '');

    var day = currentDate ? DAY_DATA[currentDate] : null;
    var back = inPending
      ? '<button type="button" class="mg-back" data-act="back-pending">&larr; All pending requests</button>'
      : (day && (day.items.length > 1 || fromList))
      ? '<button type="button" class="mg-back" data-act="back-list" data-date="' + esc(currentDate) + '">&larr; All reports on ' + esc(fmtShort(currentDate)) + '</button>'
      : '';
    list.innerHTML = back + detailBlock(it, { dateKey: currentDate });
    reveal();
  }

  // -------------------------------------------------------------- editor view
  function fieldHtml(f) {
    var id = 'ef_' + f.col;
    var name = 'f[' + f.col + ']';
    var req = f.required ? ' required' : '';
    var label = '<label for="' + id + '">' + esc(f.label) + (f.required ? ' <span class="mg-req">*</span>' : '')
              + (f.kind === 'event' ? '<i class="mg-ev-dot-pick" id="efDot"></i>' : '') + '</label>';
    var input;

    if (f.kind === 'textarea') {
      input = '<textarea id="' + id + '" name="' + name + '" rows="3" maxlength="2000"' + req + '>' + esc(f.value) + '</textarea>';
    } else if (f.kind === 'event') {
      var cur = f.value || '';
      var known = Object.keys(EVENT_TYPES).filter(function (t) { return t.toLowerCase() === cur.toLowerCase(); })[0];
      var custom = cur !== '' && !known;          // a type someone typed under "Others"
      var picked = known || (custom ? EVENT_OTHER : '');
      var opts = '<option value="">-- Not set --</option>' + Object.keys(EVENT_TYPES).map(function (t) {
        return '<option value="' + esc(t) + '"' + (t === picked ? ' selected' : '') + '>'
             + esc(t === EVENT_OTHER ? 'Others (please specify)' : t) + '</option>';
      }).join('');
      input = '<select id="' + id + '" name="' + name + '" data-role="event-select">' + opts + '</select>'
            + '<input type="text" class="mg-edit-other" name="event_type_other" maxlength="60" placeholder="Please specify the type of event"'
            + ' value="' + esc(custom ? cur : '') + '"' + (picked === EVENT_OTHER ? '' : ' style="display:none"') + '>';
    } else {
      var type = f.kind === 'int' ? 'number' : (f.kind === 'date' ? 'date' : (f.kind === 'time' ? 'time' : 'text'));
      var extra = '';
      if (f.kind === 'int') {
        extra = ' step="1" inputmode="numeric"' + (f.min != null ? ' min="' + f.min + '"' : '') + (f.max != null ? ' max="' + f.max + '"' : '');
      } else if (type === 'text') {
        extra = ' maxlength="150"';
      }
      input = '<input type="' + type + '" id="' + id + '" name="' + name + '" value="' + esc(f.value) + '"' + extra + req + '>';
    }
    return '<div class="mg-edit-field' + (f.wide ? ' full' : '') + '">' + label + input + '</div>';
  }

  // "Others" reveals the text box; the dot previews the calendar colour.
  function syncEventField(select) {
    var form = select.closest('form');
    var other = form.querySelector('input[name="event_type_other"]');
    var isOther = select.value === EVENT_OTHER;
    if (other) {
      other.style.display = isOther ? '' : 'none';
      other.required = isOther;
      if (!isOther) other.value = '';
    }
    var dot = form.querySelector('#efDot');
    if (dot) dot.style.background = EVENT_TYPES[select.value] || 'transparent';
  }

  // Editable extra dates (projector only). Existing ones post as xd[position][...];
  // dates added with "Add date" post as nd[n][...] and are appended on save.
  function extraDatesHtml(list) {
    var rows = (list || []).map(function (x) {
      var n = 'xd[' + x.idx + ']';
      return '<div class="mg-edit-field full" data-xd="' + esc(String(x.idx)) + '"><label>Extra date ' + esc(String(x.n)) + ' <span class="mg-req">*</span>'
        + (x.done ? ' <span class="mg-hint">(marked done - cannot be removed)</span>' : '') + '</label>'
        + '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
        + '<input type="date" name="' + n + '[date]" value="' + esc(x.date) + '" required style="flex:2 1 150px">'
        + '<input type="time" name="' + n + '[time_from]" value="' + esc(x.time_from) + '" required aria-label="Time From" style="flex:1 1 110px">'
        + '<input type="time" name="' + n + '[time_to]" value="' + esc(x.time_to) + '" required aria-label="Time To" style="flex:1 1 110px">'
        + (x.done ? '' : '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="remove-existing" aria-label="Remove this date">Remove</button>')
        + '</div></div>';
    }).join('');
    return '<div class="mg-dhead" style="margin-top:14px">Extra dates</div>'
      + '<div class="mg-edit-grid" id="extraDateRows">' + rows + '</div>'
      + '<div style="margin-top:10px"><button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="add-date">+ Add date</button></div>';
  }

  // Marks an existing extra date for removal (or undoes it). Nothing is deleted
  // until "Save changes"; the row is greyed out and sent as xd_remove[idx].
  function toggleRemoveExisting(row, btn) {
    var idx = row.getAttribute('data-xd');
    var marker = row.querySelector('input[data-removemark]');
    var inputs = row.querySelectorAll('input[type="date"],input[type="time"]');
    var i;
    if (marker) {
      marker.remove();
      for (i = 0; i < inputs.length; i++) inputs[i].disabled = false;
      row.style.opacity = '';
      btn.textContent = 'Remove';
    } else {
      marker = document.createElement('input');
      marker.type = 'hidden';
      marker.name = 'xd_remove[' + idx + ']';
      marker.value = '1';
      marker.setAttribute('data-removemark', '1');
      row.appendChild(marker);
      for (i = 0; i < inputs.length; i++) inputs[i].disabled = true;
      row.style.opacity = '.5';
      btn.textContent = 'Undo';
    }
  }

  var newDateSeq = 0;
  // Appends a blank date / time row to the editor; it is saved with the rest of the form.
  function addDateRow(form) {
    var box = form.querySelector('#extraDateRows');
    if (!box) return;
    var k = newDateSeq++;
    var n = 'nd[' + k + ']';
    var row = document.createElement('div');
    row.className = 'mg-edit-field full';
    row.setAttribute('data-newdate', '1');
    row.innerHTML = '<label>New date <span class="mg-req">*</span></label>'
      + '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
      + '<input type="date" name="' + n + '[date]" required style="flex:2 1 150px">'
      + '<input type="time" name="' + n + '[time_from]" required aria-label="Time From" style="flex:1 1 110px">'
      + '<input type="time" name="' + n + '[time_to]" required aria-label="Time To" style="flex:1 1 110px">'
      + '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="remove-date" aria-label="Remove this date">Remove</button>'
      + '</div>';
    box.appendChild(row);
    var first = row.querySelector('input');
    if (first) first.focus();
  }

  function showEdit(key) {
    var it = EVENT_DATA[key];
    if (!it || it.cancelled || !it.edit || !it.edit.length) return;
    currentKey = key;

    title.textContent = 'Edit report';
    summary.textContent = reportName(it) + ' \u00b7 changes show on the calendar as soon as you save.';

    list.innerHTML = '<form id="editForm" class="mg-detail" data-type="' + esc(it.type) + '" data-id="' + esc(it.id) + '">'
      + '<div class="mg-dhead">Editing this report</div>'
      + '<div class="mg-edit-grid">' + it.edit.map(fieldHtml).join('') + '</div>'
      + (isBooking(it) ? extraDatesHtml(it.edit_extra || []) : '')
      + (isBooking(it) ? '<p class="mg-hint">Moving a date or start time restarts that date\'s reminder emails. Use "Add date" to book more days, or "Remove" to drop an extra date (it is deleted when you save). The equipment list cannot be changed here.</p>' : '')
      + '<div class="mg-edit-error" id="editError" role="alert"></div>'
      + '<div class="mg-edit-btns">'
      + '<button type="submit" class="mg-btn mg-btn-sm">Save changes</button>'
      + '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-act="edit-cancel">Discard changes</button>'
      + '</div></form>';

    var sel = list.querySelector('[data-role="event-select"]');
    if (sel) syncEventField(sel);
    reveal();
    var first = list.querySelector('input,select,textarea');
    if (first) first.focus();
  }

  // Saves with fetch(); the server answers with JSON so a validation problem
  // shows inside the editor and nothing that was typed is lost. On success the
  // page reloads (same tab and month) with a "Report updated" message.
  function submitEdit(form) {
    var it = EVENT_DATA[currentKey];
    if (!it) return;
    var errBox = document.getElementById('editError');
    var btn = form.querySelector('[type="submit"]');
    var tabLink = document.querySelector('.mg-tabs a.on');

    function fail(msg) {
      errBox.textContent = msg || 'Could not save this report. Please try again.';
      errBox.classList.add('show');
      btn.disabled = false;
      btn.textContent = 'Save changes';
    }

    var fd = new FormData(form);
    fd.append('csrf', CSRF);
    fd.append('action', 'edit_request');
    fd.append('type', it.type);
    fd.append('id', it.id);
    fd.append('back_tab', tabLink ? tabLink.getAttribute('data-tab') : 'calendar');
    fd.append('ym', CAL_YM);

    errBox.classList.remove('show');
    btn.disabled = true;
    btn.textContent = 'Saving\u2026';

    fetch(window.location.pathname + window.location.search, {
      method: 'POST', body: fd, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }
    }).then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) { window.location.href = res.redirect; }
        else { fail(res && res.error); }
      })
      .catch(function () { fail('Could not reach the server. Check your connection and try again.'); });
  }

  // ------------------------------------------------------------ cancel / done
  // Both post a plain form to this page (CSRF-checked server side), which
  // updates the record and reloads back on the same tab and month.
  function postAction(fields) {
    var tabLink = document.querySelector('.mg-tabs a.on');
    fields.csrf = CSRF;
    fields.back_tab = tabLink ? tabLink.getAttribute('data-tab') : 'calendar';
    fields.ym = CAL_YM;
    var f = document.createElement('form');
    f.method = 'post';
    Object.keys(fields).forEach(function (k) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = fields[k];
      f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  }

  // Flags the record as cancelled.
  function submitCancel(type, id, reason) {
    postAction({ action: 'cancel_request', type: type, id: id, reason: reason });
  }

  // Marks a finished report as done (state 1), or reopens it (state 0).
  // date is the specific occurrence (Y-m-d) this applies to - a projector
  // request can have several dates, and only that one date is affected.
  // handledBy is who fixed / handled the request - asked for in a popup
  // before any request is confirmed done; empty when reopening.
  function submitDone(type, id, state, date, handledBy) {
    postAction({ action: 'set_done', type: type, id: id, state: state, date: date || '', handled_by: handledBy || '' });
  }

  // ---------------------------------------------------------------- wiring
  // One set of listeners for everything inside the modal (its content is rebuilt on each view).
  list.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-act]') : null;
    if (!btn) return;
    var act = btn.getAttribute('data-act');

    if (act === 'open-report') { showReport(btn.getAttribute('data-key'), inPending ? btn.getAttribute('data-date') : currentDate, true); return; }
    if (act === 'back-list')   { showList(btn.getAttribute('data-date')); return; }
    if (act === 'back-pending') { showPending(); return; }
    if (act === 'edit-open')   { showEdit(currentKey); return; }
    if (act === 'edit-cancel') { showReport(currentKey, currentDate, fromList); return; }
    if (act === 'add-date')    { var ef = btn.closest('form'); if (ef) addDateRow(ef); return; }
    if (act === 'remove-existing') { var xr = btn.closest('[data-xd]'); if (xr) toggleRemoveExisting(xr, btn); return; }
    if (act === 'remove-date') { var nr = btn.closest('[data-newdate]'); if (nr) nr.remove(); return; }

    var block = btn.closest('.mg-detail');
    if (!block) return;
    if (act === 'cancel-open') {
      block.classList.add('confirming');
      var ta = block.querySelector('.mg-cancel-reason');
      if (ta) ta.focus();
    } else if (act === 'cancel-close') {
      block.classList.remove('confirming');
    } else if (act === 'done-open') {
      // Every request type asks who fixed / handled it first.
      block.classList.add('confirming-done');
      var hi = block.querySelector('.mg-done-handler');
      if (hi) hi.focus();
    } else if (act === 'done-close') {
      block.classList.remove('confirming-done');
      var box = block.querySelector('.mg-done-box');
      if (box) box.classList.remove('err');
    } else if (act === 'done-confirm') {
      var handlerInput = block.querySelector('.mg-done-handler');
      var handledBy = handlerInput ? handlerInput.value.trim() : '';
      if (!handledBy) {
        var doneBox = block.querySelector('.mg-done-box');
        if (doneBox) doneBox.classList.add('err');
        if (handlerInput) handlerInput.focus();
        return;
      }
      btn.disabled = true; // guard against a double submit
      submitDone(block.getAttribute('data-type'), block.getAttribute('data-id'), 1, block.getAttribute('data-date'), handledBy);
    } else if (act === 'reopen') {
      btn.disabled = true; // guard against a double submit
      submitDone(block.getAttribute('data-type'), block.getAttribute('data-id'), 0, block.getAttribute('data-date'));
    } else if (act === 'cancel-confirm') {
      btn.disabled = true; // guard against a double submit
      var reason = block.querySelector('.mg-cancel-reason');
      submitCancel(block.getAttribute('data-type'), block.getAttribute('data-id'), reason ? reason.value : '');
    }
  });

  list.addEventListener('change', function (e) {
    if (e.target && e.target.getAttribute && e.target.getAttribute('data-role') === 'event-select') syncEventField(e.target);
  });

  list.addEventListener('input', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('mg-done-handler')) {
      var box = e.target.closest('.mg-done-box');
      if (box) box.classList.remove('err');
    }
  });

  list.addEventListener('submit', function (e) {
    if (!e.target || e.target.id !== 'editForm') return;
    e.preventDefault();
    submitEdit(e.target);
  });

  // A calendar day opens the list of every report on that date...
  document.querySelectorAll('.mg-cell[data-date]').forEach(function (cell) {
    cell.addEventListener('click', function () { showList(cell.getAttribute('data-date')); });
  });

  // The Pending card opens the list of every pending request.
  var pendingCard = document.getElementById('pendingCard');
  if (pendingCard) pendingCard.addEventListener('click', showPending);

  // ...and an event name (chip or agenda row) opens that report's summary.
  document.querySelectorAll('.mg-event[data-event-key]').forEach(function (card) {
    card.addEventListener('click', function (e) {
      e.stopPropagation(); // don't also trigger a parent calendar cell's list
      inPending = false;
      showReport(card.getAttribute('data-event-key'), card.getAttribute('data-event-date'), false);
    });
  });

  // Keyboard: Enter / Space opens the focused day or event, like a click.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var t = e.target;
    if (t && t.classList && (t.classList.contains('mg-event') || (t.classList.contains('mg-cell') && t.hasAttribute('data-date')))) {
      e.preventDefault();
      t.click();
    }
  });

  document.getElementById('dayClose').addEventListener('click', function () { overlay.classList.remove('show'); });
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.classList.remove('show'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') overlay.classList.remove('show'); });
})();
</script>

<script>
// ---- Settings: keep "max requests per day" from exceeding the total ----
(function () {
  var total = document.getElementById('total');
  var perday = document.getElementById('perday');
  if (!total || !perday) return;
  function sync() {
    var t = parseInt(total.value, 10) || 1;
    perday.max = t;
    if (parseInt(perday.value, 10) > t) perday.value = t;
  }
  total.addEventListener('input', sync);
})();

// ---- Month / Week / Day toggle (each calendar card works on its own) ----
(function () {
  var todayStr = '<?= h(date('Y-m-d')) ?>';

  document.querySelectorAll('.mg-calwrap').forEach(function (wrap) {
    var toggle = wrap.querySelector('.mg-view-toggle');
    var grid   = wrap.querySelector('.mg-cal');
    if (!toggle || !grid) return;

    var cells = Array.prototype.slice.call(grid.querySelectorAll('.mg-cell'));
    var dows  = Array.prototype.slice.call(grid.querySelectorAll('.mg-dow'));

    // Which week row (0-based) holds today, if today is in the month being shown.
    var todayCell = grid.querySelector('.mg-cell[data-date="' + todayStr + '"]');
    var focusWeek = todayCell ? parseInt(todayCell.dataset.week, 10) : 0;

    function setView(view) {
      toggle.querySelectorAll('.mg-view-btn').forEach(function (b) {
        b.classList.toggle('on', b.dataset.view === view);
      });
      grid.classList.toggle('view-day', view === 'day');
      grid.classList.toggle('view-week', view === 'week');
      dows.forEach(function (d) { d.style.display = view === 'day' ? 'none' : ''; });

      if (view === 'month') {
        cells.forEach(function (c) { c.style.display = ''; });
      } else if (view === 'week') {
        cells.forEach(function (c) { c.style.display = (parseInt(c.dataset.week, 10) === focusWeek) ? '' : 'none'; });
      } else {
        // Day view: today's cell (or the 1st when today is in another month), full width.
        var target = todayCell || grid.querySelector('.mg-cell[data-date]');
        cells.forEach(function (c) { c.style.display = (c === target) ? '' : 'none'; });
      }
    }

    toggle.querySelectorAll('.mg-view-btn').forEach(function (btn) {
      btn.addEventListener('click', function () { setView(btn.dataset.view); });
    });
  });
})();

// ---- Tabs and quick links: switch panels without a full page reload ----
(function () {
  var links  = document.querySelectorAll('.mg-tabs a[data-tab]');
  var panels = document.querySelectorAll('.mg-panel');
  if (!links.length || !panels.length) return;

  function activate(tabName) {
    links.forEach(function (a) {
      var on = a.dataset.tab === tabName;
      a.classList.toggle('on', on);
      if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    panels.forEach(function (p) { p.classList.toggle('active', p.id === 'panel-' + tabName); });
  }

  function wire(a, tabName) {
    a.addEventListener('click', function (e) {
      if (!tabName || !document.getElementById('panel-' + tabName)) return; // let it navigate normally
      e.preventDefault();
      activate(tabName);
      history.replaceState(null, '', a.getAttribute('href'));
      var nav = document.getElementById('manage');
      if (nav && nav.getBoundingClientRect().top < 0) nav.scrollIntoView();
    });
  }

  links.forEach(function (a) { wire(a, a.dataset.tab); });
  document.querySelectorAll('[data-goto]').forEach(function (a) { wire(a, a.dataset.goto); });
})();

// ---- Logs tab pagination: only one page of rows stays in the DOM at a time ----
// The Logs tab can hold up to 200 rows, and every tab panel shares one grid cell
// (see ".mg-panels" in the CSS) so its own height never changes when you switch
// tabs. Without paging, that shared cell grows to fit ALL 200 rows, which makes
// every other tab (Dashboard included) scroll just as far. Hiding rows outside
// the current page with display:none removes them from layout entirely, so the
// shared cell only ever has to fit one page's worth of rows.
(function () {
  var tbody = document.getElementById('logsTbody');
  var pager = document.getElementById('logsPager');
  if (!tbody || !pager) return;

  var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
  if (!allRows.length) return;

  var sizeSelect  = document.getElementById('logsPageSize');
  var infoEl      = document.getElementById('logsPagerInfo');
  var btnsEl      = document.getElementById('logsPagerBtns');

  function readSize() {
    var saved = null;
    try { saved = window.localStorage.getItem('itsLogsPageSize'); } catch (e) {}
    var n = parseInt(saved, 10);
    return (n === 10 || n === 25 || n === 50 || n === 100) ? n : 10;
  }
  function saveSize(n) {
    try { window.localStorage.setItem('itsLogsPageSize', String(n)); } catch (e) {}
  }

  var pageSize = readSize();
  var page = 1;
  var rows = allRows;

  function totalPages() { return Math.max(1, Math.ceil(rows.length / pageSize)); }

  function render() {
    var tp = totalPages();
    if (page > tp) page = tp;
    if (page < 1) page = 1;

    var start = (page - 1) * pageSize;
    var end   = Math.min(start + pageSize, rows.length);
    rows.forEach(function (r, i) { r.style.display = (i >= start && i < end) ? '' : 'none'; });

    infoEl.textContent = 'Showing ' + (rows.length ? (start + 1) : 0) + '\u2013' + end + ' of ' + rows.length;

    // Sliding window of page-number buttons: first, last, and a few around
    // the current page, with an ellipsis for any gap - so 20 pages doesn't
    // mean 20 buttons.
    var html = '';
    html += '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-pg="prev"' + (page === 1 ? ' disabled' : '') + '>Prev</button>';

    var windowSize = 2;
    var shown = [];
    for (var p = 1; p <= tp; p++) {
      if (p === 1 || p === tp || (p >= page - windowSize && p <= page + windowSize)) shown.push(p);
    }
    var last = 0;
    shown.forEach(function (p) {
      if (last && p - last > 1) html += '<span class="mg-pager-ellipsis">\u2026</span>';
      html += '<button type="button" class="mg-btn mg-btn-sm' + (p === page ? '' : ' mg-btn-ghost') + '" data-pg="' + p + '">' + p + '</button>';
      last = p;
    });

    html += '<button type="button" class="mg-btn mg-btn-sm mg-btn-ghost" data-pg="next"' + (page === tp ? ' disabled' : '') + '>Next</button>';
    btnsEl.innerHTML = html;
  }

  if (sizeSelect) {
    sizeSelect.value = String(pageSize);
    sizeSelect.addEventListener('change', function () {
      pageSize = parseInt(sizeSelect.value, 10) || 10;
      saveSize(pageSize);
      page = 1;
      render();
    });
  }

  btnsEl.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-pg]');
    if (!btn || btn.disabled) return;
    if (btn.dataset.pg === 'prev') page = Math.max(1, page - 1);
    else if (btn.dataset.pg === 'next') page = Math.min(totalPages(), page + 1);
    else page = parseInt(btn.dataset.pg, 10);
    render();
    var card = pager.closest('.mg-card');
    if (card) card.scrollIntoView({ block: 'start' });
  });

  render();
})();

// ---- Logs tab: date-range filter (quick presets + keeps From/To consistent) ----
(function () {
  var form = document.getElementById('logsFilterForm');
  if (!form) return;
  var from = document.getElementById('logFrom');
  var to   = document.getElementById('logTo');
  var chips = form.querySelectorAll('[data-preset]');

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function daysAgo(n) { var d = new Date(); d.setDate(d.getDate() - n); return ymd(d); }
  function presetRange(key) {
    var today = ymd(new Date());
    if (key === 'today') return [today, today];
    if (key === '7')     return [daysAgo(6), today];
    if (key === '30')    return [daysAgo(29), today];
    if (key === 'month') { var d = new Date(); return [d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-01', today]; }
    return null;
  }

  // From can't be after To, and To can't be before From.
  function syncLimits() {
    to.min = from.value || '';
    if (to.value) from.max = to.value;
  }

  // Highlight the preset that matches the range currently applied.
  function markActive() {
    chips.forEach(function (c) {
      var r = presetRange(c.dataset.preset);
      c.classList.toggle('on', !!r && r[0] === from.value && r[1] === to.value);
    });
  }

  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      var r = presetRange(c.dataset.preset);
      if (!r) return;
      from.value = r[0];
      to.value = r[1];
      form.submit();
    });
  });
  from.addEventListener('change', syncLimits);
  to.addEventListener('change', syncLimits);
  syncLimits();
  markActive();
})();

// ---- Logs tab: "Generate PDF" popup (by month, or by custom date range) ----
(function () {
  var openBtn  = document.getElementById('logsPdfOpenBtn');
  var overlay  = document.getElementById('pdfOverlay');
  if (!openBtn || !overlay) return;

  var modal        = overlay.querySelector('.mg-modal');
  var closeBtn      = document.getElementById('pdfClose');
  var modeMonthBtn  = document.getElementById('pdfModeMonthBtn');
  var modeRangeBtn  = document.getElementById('pdfModeRangeBtn');
  var monthPanel    = document.getElementById('pdfMonthPanel');
  var rangePanel     = document.getElementById('pdfRangePanel');
  var monthSelect    = document.getElementById('pdfMonthSelect');
  var fromInput      = document.getElementById('pdfFrom');
  var toInput        = document.getElementById('pdfTo');
  var generateBtn    = document.getElementById('pdfGenerateBtn');
  var today           = '<?= h(date('Y-m-d')) ?>';
  var mode             = 'month';

  function setMode(next) {
    mode = next;
    var isMonth = mode === 'month';
    monthPanel.style.display = isMonth ? '' : 'none';
    rangePanel.style.display = isMonth ? 'none' : '';
    modeMonthBtn.classList.toggle('mg-btn-ghost', !isMonth);
    modeRangeBtn.classList.toggle('mg-btn-ghost', isMonth);
  }

  function open() {
    if (!fromInput.value) fromInput.value = today.slice(0, 8) + '01';
    if (!toInput.value) toInput.value = today;
    setMode('month');
    overlay.classList.add('show');
    modal.scrollTop = 0;
  }
  function close() { overlay.classList.remove('show'); }

  openBtn.addEventListener('click', open);
  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('show')) close(); });

  modeMonthBtn.addEventListener('click', function () { setMode('month'); });
  modeRangeBtn.addEventListener('click', function () { setMode('range'); });

  generateBtn.addEventListener('click', function () {
    if (mode === 'month') {
      var ym = monthSelect.value || '<?= h(date('Y-m')) ?>';
      window.location.href = '?export_logs_pdf=1&ym=' + encodeURIComponent(ym);
    } else {
      var from = fromInput.value || today;
      var to   = toInput.value || today;
      if (from > to) { var t = from; from = to; to = t; }
      window.open('?export_logs_range=1&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to), '_blank');
    }
    close();
  });
})();

// ---- Logs tab: automatic download right after the monthly archive job runs ----
// AUTO_DOWNLOAD_LOGS is only non-empty on the one page load where a month
// actually got archived and cleared, so this fires exactly once per month.
(function () {
  if (!window.AUTO_DOWNLOAD_LOGS || !AUTO_DOWNLOAD_LOGS.length) return;
  AUTO_DOWNLOAD_LOGS.forEach(function (ym) {
    var a = document.createElement('a');
    a.href = '?export_logs_pdf=1&ym=' + encodeURIComponent(ym);
    a.download = 'activity-log-' + ym + '.pdf';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { a.remove(); }, 1000);
  });
})();

// ---- Auto-refresh: reloads the page every minute so new requests, log entries and counts show up ----
// It never interrupts work: it waits while the tab is in the background, while a popup (report,
// editor, pending list) is open, while a field is focused, and while any form has unsaved changes.
// The calendar view (Month / Week / Day) and the scroll position are carried across the reload.
// The on/off choice is remembered in this browser.
(function () {
  var INTERVAL = 60 * 1000;
  var btn = document.getElementById('autoRefreshBtn');
  var txt = document.getElementById('autoRefreshTxt');
  if (!btn || !txt) return;

  function local(key, val) {   // val undefined = read, null = remove, string = write
    try {
      if (val === undefined) return window.localStorage.getItem(key);
      if (val === null) window.localStorage.removeItem(key); else window.localStorage.setItem(key, val);
    } catch (e) {}
    return null;
  }
  function session(key, val) {
    try {
      if (val === undefined) return window.sessionStorage.getItem(key);
      if (val === null) window.sessionStorage.removeItem(key); else window.sessionStorage.setItem(key, val);
    } catch (e) {}
    return null;
  }

  var on = local('itsAutoRefresh') !== 'off';
  var loadedAt = Date.now();
  var loadedClock = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  var checking = false;

  function render() {
    btn.classList.toggle('on', on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    txt.textContent = on ? 'Auto-refresh on' : 'Auto-refresh off';
    btn.title = 'Last updated ' + loadedClock + (on ? ' - refreshes every minute. Click to turn off.' : ' - click to turn on.');
  }

  // Put back what the previous auto-refresh saved (calendar view, scroll position).
  (function restore() {
    if (session('itsAutoReloaded') !== '1') return;
    session('itsAutoReloaded', null);
    try {
      var views = JSON.parse(session('itsCalViews') || '[]');
      document.querySelectorAll('.mg-calwrap').forEach(function (wrap, i) {
        var b = views[i] && wrap.querySelector('.mg-view-btn[data-view="' + views[i] + '"]');
        if (b) b.click();
      });
    } catch (e) {}
    var y = parseInt(session('itsScrollY'), 10);
    if (!isNaN(y)) window.scrollTo(0, y);
  })();

  // True when refreshing right now could lose something the person is doing.
  function busy() {
    if (document.hidden) return true;
    if (document.querySelector('.mg-overlay.show')) return true;
    var a = document.activeElement;
    if (a && a !== document.body && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable)) return true;

    var fields = document.querySelectorAll('input, textarea, select');
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      if (f.closest('.mg-overlay')) continue; // popups are covered by the check above
      if (f.tagName === 'SELECT') {
        for (var j = 0; j < f.options.length; j++) if (f.options[j].selected !== f.options[j].defaultSelected) return true;
      } else if (/^(hidden|submit|button|reset|image|file)$/.test(f.type)) {
        continue;
      } else if (f.type === 'checkbox' || f.type === 'radio') {
        if (f.checked !== f.defaultChecked) return true;
      } else if (f.value !== f.defaultValue) {
        return true;
      }
    }
    return false;
  }

  function refreshNow() {
    var views = [];
    document.querySelectorAll('.mg-calwrap').forEach(function (wrap) {
      var b = wrap.querySelector('.mg-view-btn.on');
      views.push(b ? b.getAttribute('data-view') : 'month');
    });
    session('itsCalViews', JSON.stringify(views));
    session('itsScrollY', String(window.scrollY || 0));
    session('itsAutoReloaded', '1');

    // Don't re-show a "saved" / "cancelled" toast that was still in the address bar.
    try {
      var u = new URL(window.location.href);
      if (u.searchParams.has('msg')) {
        u.searchParams.delete('msg');
        history.replaceState(null, '', u.toString());
      }
    } catch (e) {}
    window.location.reload();
  }

  function retrySoon() {
    checking = false;
    btn.classList.remove('spin');
    loadedAt = Date.now() - INTERVAL + 15000; // try again in 15 seconds
  }

  function tick() {
    if (!on || checking || Date.now() - loadedAt < INTERVAL || busy()) return;
    checking = true;
    btn.classList.add('spin');
    // Make sure the server answers first, so an unattended screen never ends up on a browser error page.
    fetch(window.location.pathname + window.location.search, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin' })
      .then(function (res) { if (res && res.ok) refreshNow(); else retrySoon(); })
      .catch(retrySoon);
  }

  btn.addEventListener('click', function () {
    on = !on;
    local('itsAutoRefresh', on ? null : 'off');
    if (on) loadedAt = Date.now();
    render();
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
  setInterval(tick, 5000);
  render();
})();
</script>

<footer class="page-footer">Information Technology Services Department</footer>

</body>
</html>