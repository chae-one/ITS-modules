<?php
// =====================================================================
// REMINDER DIAGNOSTIC (read-only)
//
// Does NOT send any email and does NOT touch the reminder_*_sent columns.
// Run it the same way you'd run send_reminders.php:
//
//   php debug_reminders.php
//
// or open it in a browser if your host allows that. It reports:
//   1) whether the three tracking columns exist
//   2) every upcoming (or recently past) request, with how many minutes
//      until its start time, its current *_sent flags, and whether each
//      stage would fire on the next real cron run.
// =====================================================================

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db_config.php';

header('Content-Type: text/plain');

echo "Server time right now: " . (new DateTime())->format('Y-m-d H:i:s') . "\n\n";

// ---- 1) Column check ----
echo "=== Column check ===\n";
$requiredCols = ['reminder_1day_sent', 'reminder_1hr_sent', 'reminder_30min_sent'];
$existing = [];
$res = $conn->query("DESCRIBE projector_requests");
if (!$res) {
    die("Could not describe projector_requests: " . $conn->error . "\n");
}
while ($r = $res->fetch_assoc()) {
    $existing[] = $r['Field'];
}
$missing = [];
foreach ($requiredCols as $c) {
    $ok = in_array($c, $existing, true);
    echo str_pad($c, 24) . ($ok ? "OK" : "*** MISSING ***") . "\n";
    if (!$ok) $missing[] = $c;
}
if (!empty($missing)) {
    echo "\n>>> Missing column(s) above are why that stage silently fails on every\n";
    echo ">>> cron run. Add them with the ALTER TABLE from send_reminders.php's\n";
    echo ">>> header comment, then re-run this script.\n";
}

// ---- 2) Per-request eligibility ----
function debug_normalize_time($value) {
    $value = trim((string) $value);
    if ($value === '') return null;
    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
    if (preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d)\s*([AaPp][Mm])$/', $value, $m)) {
        $hour = (int) $m[1];
        $minutes = (int) $m[2];
        $suffix = strtoupper($m[3]);
        if ($suffix === 'AM') { if ($hour === 12) $hour = 0; }
        else { if ($hour !== 12) $hour += 12; }
        return sprintf('%02d:%02d', $hour, $minutes);
    }
    return null;
}

echo "\n=== Requests (from 2 days ago onward) ===\n";

$selectCols = array_merge(['id', 'name', 'date_needed', 'time_from', 'time_to'], array_intersect($requiredCols, $existing));
$sql = "SELECT " . implode(', ', $selectCols) . "
        FROM projector_requests
        WHERE date_needed >= (CURDATE() - INTERVAL 2 DAY)
        ORDER BY date_needed, time_from";
$res = $conn->query($sql);
if (!$res) {
    die("\nQuery failed: " . $conn->error . "\n");
}

if ($res->num_rows === 0) {
    echo "(no requests found in that date range)\n";
}

$now = new DateTime();
$stages = [
    ['label' => '1 day',     'minutes' => 1440, 'column' => 'reminder_1day_sent'],
    ['label' => '1 hour',    'minutes' => 60,   'column' => 'reminder_1hr_sent'],
    ['label' => '30 minutes','minutes' => 30,   'column' => 'reminder_30min_sent'],
];
$lateGrace = 15;

// Mirrors send_reminders.php's hand-off logic: a stage only "would send"
// while the event is still further out than the *next* stage's lead time.
// Once it drops into a nearer stage's window, this one is skipped for
// good (e.g. a request made 5 hours out never shows "WOULD SEND" for
// "1 day" - only "1 hour", and later "30 minutes", light up).
foreach ($stages as $idx => &$s) {
    $s['handoffFloor'] = isset($stages[$idx + 1]) ? $stages[$idx + 1]['minutes'] : null;
}
unset($s);

while ($row = $res->fetch_assoc()) {
    $time24 = debug_normalize_time($row['time_from']);
    echo "\n#{$row['id']} - {$row['name']} | date_needed={$row['date_needed']} time_from='{$row['time_from']}'\n";

    if ($time24 === null) {
        echo "  -> time_from isn't a recognized clock format (free text?). Every stage is SKIPPED for this row.\n";
        continue;
    }

    try {
        $eventStart = new DateTime($row['date_needed'] . ' ' . $time24);
    } catch (Exception $e) {
        echo "  -> could not build a DateTime from date_needed + time_from. Every stage is SKIPPED for this row.\n";
        continue;
    }

    $diff = ($eventStart->getTimestamp() - $now->getTimestamp()) / 60;
    echo "  Event start: {$eventStart->format('Y-m-d H:i')}  |  minutes until event: " . round($diff, 1) . "\n";

    foreach ($stages as $stage) {
        $col = $stage['column'];
        if (!in_array($col, $existing, true)) {
            echo "  [{$stage['label']}] column {$col} does not exist -> stage broken for ALL rows\n";
            continue;
        }
        $sentFlag = (int) $row[$col];
        if ($sentFlag === 1) {
            echo "  [{$stage['label']}] already marked sent (column {$col} = 1)\n";
            continue;
        }
        if ($diff > $stage['minutes']) {
            echo "  [{$stage['label']}] not due yet (needs <= {$stage['minutes']} min out)\n";
        } elseif ($stage['handoffFloor'] === null && $diff < -$lateGrace) {
            echo "  [{$stage['label']}] too late - event passed more than {$lateGrace} min ago, will never send\n";
        } elseif ($stage['handoffFloor'] !== null && $diff <= $stage['handoffFloor']) {
            echo "  [{$stage['label']}] handed off - already within the next stage's window ({$stage['handoffFloor']} min), will never send this stage\n";
        } else {
            echo "  [{$stage['label']}] >>> WOULD SEND on the next cron run <<<\n";
        }
    }
}

$conn->close();