<?php
require_once 'db_config.php';
require_once 'settings.php';
require_once 'mailer.php';

// ---------- Software dropdown options are loaded from the software_options table ----------
function load_software_options($conn, $category) {
    $options = [];
    $stmt = $conn->prepare("SELECT name FROM software_options WHERE category = ? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $options[] = $row['name'];
    }
    $stmt->close();
    return $options;
}

// ---------- Department/Office tree is loaded from the dropdown_option table ----------
function load_department_tree($conn) {
    $stmt = $conn->prepare(
        "SELECT id, parent_id, option_value
         FROM dropdown_option
         WHERE is_active = 1
         ORDER BY parent_id ASC, sort_order ASC, id ASC"
    );
    $stmt->execute();
    $res = $stmt->get_result();

    $byId = [];
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
        $byId[$row['id']] = ['name' => $row['option_value'], 'children' => []];
    }
    $stmt->close();

    $tree = [];
    foreach ($rows as $row) {
        $node = &$byId[$row['id']];
        if ($row['parent_id'] === null) {
            $tree[] = &$node;
        } else if (isset($byId[$row['parent_id']])) {
            $byId[$row['parent_id']]['children'][] = &$node;
        }
        unset($node);
    }

    // Drop empty "children" keys so leaf nodes match the old {name: "..."} shape
    $strip = function ($nodes) use (&$strip) {
        $out = [];
        foreach ($nodes as $n) {
            $item = ['name' => $n['name']];
            if (!empty($n['children'])) {
                $item['children'] = $strip($n['children']);
            }
            $out[] = $item;
        }
        return $out;
    };

    return $strip($tree);
}

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Parses a clock-time string into minutes-since-midnight so two times can be
// compared for ordering. Handles both formats this form can produce:
//   - 24-hour "H:MM" / "HH:MM" (rarely typed manually)
//   - 12-hour "h:mm AM/PM" (what the time picker fills in, e.g. "8:00 AM")
// Returns null for anything else (e.g. free text like "Whole day"), so
// callers can skip the ordering check when a time isn't in a recognized
// clock format.
function parse_clock_time_to_minutes($value) {
    $value = trim((string) $value);
    if ($value === '') return null;

    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
        return ((int) $m[1]) * 60 + (int) $m[2];
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
        return $hour * 60 + $minutes;
    }

    return null;
}

// Formats a 24-hour "HH:MM" value (what the native time picker submits) as
// a friendly 12-hour "h:mm AM/PM" string for emails and confirmations.
// Returns the original value unchanged if it isn't in that format.
function format_clock_time_for_display($value) {
    $value = trim((string) $value);
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
        return $value;
    }
    $hour = (int) $m[1];
    $minutes = $m[2];
    $suffix = $hour >= 12 ? 'PM' : 'AM';
    $hour12 = $hour % 12;
    if ($hour12 === 0) $hour12 = 12;
    return $hour12 . ':' . $minutes . ' ' . $suffix;
}

// Whether a minutes-since-midnight value (from parse_clock_time_to_minutes)
// falls within the allowed TIME_SELECTION_MIN..TIME_SELECTION_MAX window.
function is_time_in_selection_window($minutes) {
    if ($minutes === null) return true; // not a recognized clock value, e.g. free text
    static $min = null, $max = null;
    if ($min === null) {
        $min = parse_clock_time_to_minutes(TIME_SELECTION_MIN);
        $max = parse_clock_time_to_minutes(TIME_SELECTION_MAX);
    }
    return $minutes >= $min && $minutes <= $max;
}

$SITE_NAME = 'ITS Department';

$FORM_TITLE = 'LCD Projector Setup Request Form';

const OTHER_OPTION_VALUE = 'Other';
const DEPARTMENT_OTHER_VALUE = 'Others';

// ---------- Shared dropdown lists (loaded from the software_options table) ----------
$LICENSED_SOFTWARE_OPTIONS = load_software_options($conn, 'licensed');
$FREEWARE_OPTIONS = load_software_options($conn, 'freeware');
$DEPARTMENT_TREE_DATA = load_department_tree($conn);
// Add an "Others" leaf so a requester can type a department/office by hand
// when it isn't in the list.
$DEPARTMENT_TREE_DATA[] = ['name' => DEPARTMENT_OTHER_VALUE, 'is_other' => true];

// A row can pick any named option once, plus "Other (please specify)" - so the
// row limit is simply the option count + 1. Keeps the cap in sync with the DB.
$LICENSED_SOFTWARE_MAX_ROWS = count($LICENSED_SOFTWARE_OPTIONS) + 1; // currently 3
$FREEWARE_MAX_ROWS = count($FREEWARE_OPTIONS) + 1; // currently 14

$EQUIPMENT_OPTIONS = [
    'screen'    => 'Projector Screen',
    'extension' => 'Extension Cord',
    'hdmi_vga'  => 'HDMI / VGA Cable',
    'laptop'    => 'Laptop',
    'other'     => 'Other',
];

// Cap on how many dates (Date Needed + any additional dates) a single
// request can carry.
const DATES_NEEDED_MAX_ROWS = 6;

// Borrowing limits, all editable from admin.php (Settings tab). They are
// read fresh on every page load, so a change there applies immediately.
$PROJECTOR_TOTAL_UNITS = get_setting_int($conn, 'projector_total_units');
$DAILY_REQUEST_LIMIT   = get_setting_int($conn, 'daily_request_limit');
$MAX_UNITS_PER_REQUEST = min(get_setting_int($conn, 'max_units_per_request'), $PROJECTOR_TOTAL_UNITS);
if ($MAX_UNITS_PER_REQUEST < 1) $MAX_UNITS_PER_REQUEST = 1;

// Allowed window for any Time From / Time To selection (24-hour "HH:MM",
// used both for the native time picker's min/max and for server-side range
// validation).
const TIME_SELECTION_MIN = '06:00';
const TIME_SELECTION_MAX = '22:00';

function render_options($options, $selected) {
    $html = '<option value="">-- Select --</option>';
    foreach ($options as $opt) {
        $sel = ($selected === $opt) ? 'selected' : '';
        $html .= '<option value="' . h($opt) . '" ' . $sel . '>' . h($opt) . '</option>';
    }
    $sel = ($selected === OTHER_OPTION_VALUE) ? 'selected' : '';
    $html .= '<option value="' . OTHER_OPTION_VALUE . '" ' . $sel . '>Other (please specify)</option>';
    return $html;
}

function resolve_other_values($values, $others) {
    $out = [];
    foreach ($values as $i => $v) {
        if ($v === OTHER_OPTION_VALUE) {
            $out[] = trim($others[$i] ?? '');
        } else {
            $out[] = $v;
        }
    }
    return $out;
}

function resolve_position_value($position, $positionOther) {
    if ($position === OTHER_OPTION_VALUE) {
        return trim($positionOther);
    }
    return $position;
}

$errors = [];
// After a successful submit we redirect to ?success=1 (Post/Redirect/Get)
// so refreshing the page never re-submits the form. Only the exact value
// "1" is accepted, so a tampered query string just falls back to showing
// no confirmation instead of arbitrary text.
$success = null;
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = true;
}

$MIN_DATE = date('Y-m-d');
$MAX_DATE = date('Y-m-d', strtotime('+6 months'));

// =====================================================================
// LCD PROJECTOR SETUP REQUEST - state + submit handling
// =====================================================================
$pOld = [
    'date_requested' => date('Y-m-d'),
    'name' => '',
    'position' => '',
    'position_other' => '',
    'department' => '',
    'department_other' => '',
    'venue' => '',
    'purpose' => '',
    'event_type' => '',
    'event_type_other' => '',
    'participants' => '',
    'date_needed' => '',
    'time_from' => '',
    'time_to' => '',
    'projector_units' => 1,
    'equipment' => [],
    'equipment_other_text' => '',
    'remarks' => '',
    'consent' => 0,
    // Every date/time the requester needs the projector for. The first row
    // is the primary "Date Needed" entry; any rows after it are the "extra"
    // dates. Each row: ['date'=>'', 'time_from'=>'', 'time_to'=>''].
    'dates_needed' => [],
    'additional_dates' => [], // rows 2+ of dates_needed, kept for DB/email compatibility
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pOld['date_requested'] = trim($_POST['date_requested'] ?? '');
    $pOld['name'] = trim($_POST['name'] ?? '');
    $pOld['position'] = trim($_POST['position'] ?? '');
    $pOld['position_other'] = trim($_POST['position_other'] ?? '');
    $pOld['department'] = trim($_POST['department'] ?? '');
    $pOld['department_other'] = trim($_POST['department_other'] ?? '');
    $pOld['venue'] = trim($_POST['venue'] ?? '');
    $pOld['purpose'] = trim($_POST['purpose'] ?? '');
    $pOld['event_type'] = trim($_POST['event_type'] ?? '');
    $pOld['event_type_other'] = trim($_POST['event_type_other'] ?? '');
    $pOld['participants'] = preg_replace('/\D/', '', (string) ($_POST['participants'] ?? ''));
    $pOld['projector_units'] = max(0, (int)($_POST['projector_units'] ?? 1));
    $pOld['equipment'] = array_intersect($_POST['equipment'] ?? [], array_keys($EQUIPMENT_OPTIONS));
    $pOld['equipment_other_text'] = trim($_POST['equipment_other_text'] ?? '');
    if ($pOld['equipment_other_text'] !== '') {
        $pOld['equipment'][] = 'other';
    }
    $pOld['remarks'] = trim($_POST['remarks'] ?? '');
    $pOld['consent'] = isset($_POST['consent']) ? 1 : 0;

    // ---------- Dates Needed (each added as a bubble/chip, with its own time) ----------
    $dnDates = $_POST['dn_date'] ?? [];
    $dnTimeFrom = $_POST['dn_time_from'] ?? [];
    $dnTimeTo = $_POST['dn_time_to'] ?? [];
    $rowCount = max(count($dnDates), count($dnTimeFrom), count($dnTimeTo));
    $rowCount = min($rowCount, DATES_NEEDED_MAX_ROWS);
    $pOld['dates_needed'] = [];
    for ($i = 0; $i < $rowCount; $i++) {
        $d  = trim($dnDates[$i] ?? '');
        $tf = trim($dnTimeFrom[$i] ?? '');
        $tt = trim($dnTimeTo[$i] ?? '');
        if ($d === '' && $tf === '' && $tt === '') {
            continue; // skip a fully blank row
        }
        $pOld['dates_needed'][] = ['date' => $d, 'time_from' => $tf, 'time_to' => $tt];
    }

    $pResolvedPosition = resolve_position_value($pOld['position'], $pOld['position_other']);

    if ($pOld['name'] === '') {
        $errors[] = 'Name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s.\'-]+$/', $pOld['name'])) {
        $errors[] = 'Name must contain letters only (no numbers or special characters).';
    }
    if ($pOld['position'] === '') $errors[] = 'Position is required.';
    if ($pOld['position'] === OTHER_OPTION_VALUE && $pOld['position_other'] === '') {
        $errors[] = 'Please specify your position.';
    }
    if ($pOld['department'] === '') $errors[] = 'Department is required.';
    if ($pOld['department'] === DEPARTMENT_OTHER_VALUE && $pOld['department_other'] === '') {
        $errors[] = 'Please specify your department / office.';
    }
    $pResolvedDepartment = ($pOld['department'] === DEPARTMENT_OTHER_VALUE)
        ? $pOld['department_other']
        : $pOld['department'];

    if ($pOld['venue'] === '') $errors[] = 'Venue / Room is required.';
    if ($pOld['purpose'] === '') $errors[] = 'Purpose / Event is required.';

    // Type of Event: one of the fixed choices, or "Others" plus typed text.
    $pResolvedEventType = resolve_event_type($pOld['event_type'], $pOld['event_type_other']);
    if ($pOld['event_type'] === '') {
        $errors[] = 'Type of Event is required.';
    } elseif (!array_key_exists($pOld['event_type'], event_type_palette())) {
        $errors[] = 'Please choose a valid Type of Event.';
    } elseif ($pOld['event_type'] === EVENT_TYPE_OTHER_VALUE && $pOld['event_type_other'] === '') {
        $errors[] = 'Please specify the type of event.';
    } elseif (mb_strlen($pResolvedEventType) > 60) {
        $errors[] = 'Type of Event must be 60 characters or fewer.';
    }

    if ($pOld['participants'] === '' || (int) $pOld['participants'] < 1) {
        $errors[] = 'Number of Participants is required (at least 1).';
    } elseif ((int) $pOld['participants'] > 10000) {
        $errors[] = 'Number of Participants cannot be more than 10,000.';
    }

    if ($pOld['date_requested'] === '') {
        $errors[] = 'Date Requested is required.';
    } elseif ($pOld['date_requested'] > $MAX_DATE) {
        $errors[] = 'Date Requested cannot be more than 6 months from today.';
    }

    // ---------- Validate each date-needed row ----------
    if (empty($pOld['dates_needed'])) {
        $errors[] = 'Please add at least one Date Needed.';
    }
    foreach ($pOld['dates_needed'] as $idx => $row) {
        $n = $idx + 1;
        $label = $n === 1 ? 'Date Needed' : "Date Needed #{$n}";
        if ($row['date'] === '') {
            $errors[] = "{$label}: date is required.";
        } elseif ($row['date'] > $MAX_DATE) {
            $errors[] = "{$label}: cannot be more than 6 months from today.";
        }
        if ($row['time_from'] === '') $errors[] = "{$label}: Time From is required.";
        if ($row['time_to'] === '') $errors[] = "{$label}: Time To is required.";
        $rowFromMinutes = parse_clock_time_to_minutes($row['time_from']);
        $rowToMinutes = parse_clock_time_to_minutes($row['time_to']);
        if ($rowFromMinutes !== null && $rowToMinutes !== null && $rowToMinutes <= $rowFromMinutes) {
            $errors[] = "{$label}: Time To must be later than Time From.";
        }
        if (!is_time_in_selection_window($rowFromMinutes)) {
            $errors[] = "{$label}: Time From must be between 6:00 AM and 10:00 PM.";
        }
        if (!is_time_in_selection_window($rowToMinutes)) {
            $errors[] = "{$label}: Time To must be between 6:00 AM and 10:00 PM.";
        }
    }

    if ($pOld['projector_units'] < 0 || $pOld['projector_units'] > $MAX_UNITS_PER_REQUEST) {
        $errors[] = 'Number of projector units must be between 0 and ' . $MAX_UNITS_PER_REQUEST . '.';
    }

    if (!$pOld['consent']) {
        $errors[] = 'You must agree to the data privacy notice before submitting.';
    }

    // ---------- Daily limits ----------
    // Two separate caps, both set in admin.php:
    //   * daily_request_limit   - how many requests one date accepts
    //   * projector_total_units - how many projectors exist to lend that day
    // Counting starts from what is already saved for the date, then adds the
    // dates in this submission, so one request listing the same day twice
    // consumes two slots. Only checked once the rest of the form is valid,
    // to avoid querying on an obviously bad submit.
    if (empty($errors)) {
        $dayRequests = [];
        $dayUnits    = [];
        $dayFlagged  = [];
        foreach ($pOld['dates_needed'] as $row) {
            $d = $row['date'];
            if ($d === '' || isset($dayFlagged[$d])) continue;

            if (!array_key_exists($d, $dayRequests)) {
                $usage = projector_day_usage($conn, $d);
                $dayRequests[$d] = $usage['requests'];
                $dayUnits[$d]    = $usage['units'];
            }

            $freeBefore = max(0, $PROJECTOR_TOTAL_UNITS - $dayUnits[$d]);
            $dayRequests[$d]++;
            $dayUnits[$d] += $pOld['projector_units'];
            $pretty = date('F j, Y', strtotime($d));

            if ($dayRequests[$d] > $DAILY_REQUEST_LIMIT) {
                $dayFlagged[$d] = true;
                $errors[] = "{$pretty} is already fully booked ({$DAILY_REQUEST_LIMIT} requests per day maximum). Please pick another date.";
            } elseif ($dayUnits[$d] > $PROJECTOR_TOTAL_UNITS) {
                $dayFlagged[$d] = true;
                $errors[] = $freeBefore === 0
                    ? "No projectors are left for {$pretty}. Please pick another date."
                    : "Only {$freeBefore} projector(s) are still available on {$pretty}. Please lower the number of units or pick another date.";
            }
        }
    }

    if (empty($errors) && !ensure_projector_event_columns($conn)) {
        $errors[] = 'The request could not be saved because the database is not set up for event types yet. Please let the ITS office know (event_type_migration.sql needs to be run).';
    }

    if (empty($errors)) {
        // First date row is the primary "Date Needed"; the rest are the
        // "additional" dates (kept as their own JSON column, as before).
        $pOld['date_needed'] = $pOld['dates_needed'][0]['date'];
        $pOld['time_from'] = $pOld['dates_needed'][0]['time_from'];
        $pOld['time_to'] = $pOld['dates_needed'][0]['time_to'];
        $pOld['additional_dates'] = array_slice($pOld['dates_needed'], 1);

        $equipmentPayload = [];
        foreach ($pOld['equipment'] as $eqKey) {
            $eqLabel = $EQUIPMENT_OPTIONS[$eqKey] ?? $eqKey;
            if ($eqKey === 'other') {
                $eqLabel = $pOld['equipment_other_text'] !== '' ? $pOld['equipment_other_text'] : 'Other';
            }
            $equipmentPayload[] = $eqLabel;
        }
        $equipmentJson = json_encode($equipmentPayload);
        $additionalDatesJson = json_encode($pOld['additional_dates']);

        $participantsInt = (int) $pOld['participants']; // bind_param needs a variable, not an expression
        $stmt = $conn->prepare("INSERT INTO projector_requests
            (date_requested, name, position, department,
             venue, purpose, event_type, participants, date_needed, time_from, time_to, projector_units, equipment, remarks, additional_dates)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'sssssssisssisss',
            $pOld['date_requested'],
            $pOld['name'],
            $pResolvedPosition,
            $pResolvedDepartment,
            $pOld['venue'],
            $pOld['purpose'],
            $pResolvedEventType,
            $participantsInt,
            $pOld['date_needed'],
            $pOld['time_from'],
            $pOld['time_to'],
            $pOld['projector_units'],
            $equipmentJson,
            $pOld['remarks'],
            $additionalDatesJson
        );
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        $success = true;

        // ---------- Record it in the admin Logs tab ----------
        // Filed under the date(s) it was requested for. The Logs tab's Event
        // column shows the Purpose / Event typed on the form.
        if ($newId > 0) {
            log_new_request($conn, 'projector', [
                'name'            => $pOld['name'],
                'purpose'         => $pOld['purpose'],
                'event_type'      => $pResolvedEventType,
                'venue'           => $pOld['venue'],
                'date_requested'  => $pOld['date_requested'],
                'date_needed'     => $pOld['date_needed'],
                'time_from'       => $pOld['time_from'],
                'time_to'         => $pOld['time_to'],
                'projector_units' => $pOld['projector_units'],
                'additional_dates' => $pOld['additional_dates'],
            ]);
        }

        // ---------- Email the ITS admin inbox about this new request ----------
        // Purpose / Event goes at the very top of the table, in bold.
        $eventEsc = htmlspecialchars($pOld['purpose'], ENT_QUOTES, 'UTF-8');
        $rows = mail_row_raw('Purpose / Event', '<strong>' . $eventEsc . '</strong>')
              . mail_row('Date Requested', $pOld['date_requested'])
              . mail_row('Name', $pOld['name'])
              . mail_row('Position', $pResolvedPosition)
              . mail_row('Department / Office', $pResolvedDepartment)
              . mail_row('Venue / Room', $pOld['venue'])
              . mail_row('Type of Event', $pResolvedEventType)
              . mail_row('Number of Participants', (string) (int) $pOld['participants'])
              . mail_row('Date Needed', $pOld['date_needed'])
              . mail_row('Time', format_clock_time_for_display($pOld['time_from']) . ' to ' . format_clock_time_for_display($pOld['time_to']))
              . mail_row('Number of Units', (string) $pOld['projector_units'])
              . mail_row('Equipment', implode(', ', $equipmentPayload))
              . mail_row('Remarks', $pOld['remarks']);

        // Intro paragraph shown above the main details table. When the
        // requester added extra dates, mention that they're listed in a
        // second table further down the email.
        // The event name is HTML-escaped and wrapped in <strong> so it stands out.
        $introHtml = 'A new LCD projector setup request for <strong>' . $eventEsc . '</strong> has been submitted through the ITS Request Portal. Details are below.';
        if (!empty($pOld['additional_dates'])) {
            $introHtml .= ' This request also includes ' . count($pOld['additional_dates'])
                . ' additional date(s), listed in the table below the main details.';
        }

        $htmlBody = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto 14px;">'
            . '<p style="margin:0;padding:0;font-size:14px;color:#2b2f40;line-height:1.6;">'
            . $introHtml
            . '</p>'
            . '</div>';
        $htmlBody .= mail_wrap_table(
            'New LCD Projector Setup Request',
            $rows
        );

        // ---------- Additional dates get their own table ----------
        // (rows 2+ of "dates_needed"; the first date/time is already shown
        // above as "Date Needed" / "Time"). Previously this was crammed into
        // a single "Additional Dates" row as a <br>-joined text blob; now
        // each extra date gets its own row in a proper table.
        if (!empty($pOld['additional_dates'])) {
            $extraRows = '';
            foreach ($pOld['additional_dates'] as $idx => $row) {
                $timeRange = format_clock_time_for_display($row['time_from']) . ' to ' . format_clock_time_for_display($row['time_to']);
                $extraRows .= mail_row('Date ' . ($idx + 2), $row['date'] . ' — ' . $timeRange);
            }
            $htmlBody .= '<div style="margin-top:16px;">'
                . mail_wrap_table('Additional Dates Requested', $extraRows)
                . '</div>';
        }
        $plainBody = "New LCD Projector Setup Request\n"
            . "Event: {$pOld['purpose']}\n"
            . "Name: {$pOld['name']}\n"
            . "Department: {$pResolvedDepartment}\n"
            . "Venue: {$pOld['venue']}\n"
            . "Type of Event: {$pResolvedEventType} ({$pOld['participants']} participants)\n"
            . "Date Needed: {$pOld['date_needed']} (" . format_clock_time_for_display($pOld['time_from']) . ' to ' . format_clock_time_for_display($pOld['time_to']) . ")\n";
        send_request_notification('New LCD Projector Setup Request', $htmlBody, $plainBody);

        // ---------- Redirect (Post/Redirect/Get) ----------
        // Sends the browser to a plain GET request for the success screen so
        // that refreshing the page afterwards does not re-POST and re-submit
        // (and re-email) the same request.
        header('Location: ' . basename(__FILE__) . '?success=1');
        exit;
    }
}

if (empty($pOld['dates_needed'])) {
    // Fresh (non-POST, or POST with zero rows) load: nothing added yet, the
    // entry fields start empty and the requester adds their first date.
    $pOld['dates_needed'] = [];
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
  --brand: #2b4a9e;          /* ITS logo blue */
  --brand-dark: #213a80;
  --brand-darker: #172a5e;
  --accent: #8fa3e0;         /* light blue highlight (replaces the old gold) */
  --accent-soft: #c9d3f2;
  --tint-50: #f5f7fc;
  --tint-100: #e9edf9;
  --tint-200: #d5ddf3;
  --ribbon-bg: #F1F4FC;
  --ribbon-border: #DDE2EF;
  --ink: #111318;
  --muted: #5B6072;
  --bg: #F1F4FA;
  --white: #ffffff;
  --line: #DDE2EF;
  --ok: #107c10;
  --ok-bg: #dff6dd;
  --err: #a4262c;
  --err-bg: #fde7e9;
  --focus: #2b4a9e;
  --radius: 4px;
  --shadow-1: 0 1.6px 3.6px rgba(20,35,90,0.10), 0 0.3px 0.9px rgba(20,35,90,0.06);
  --shadow-2: 0 18px 40px rgba(15,25,70,0.16), 0 4px 10px rgba(20,35,90,0.08);
  --site-max: 1000px;
}
* { box-sizing: border-box; }
html, body { height: 100%; }
body {
  font-family: "Segoe UI", "Segoe UI Web", Tahoma, Arial, Helvetica, sans-serif;
  background: var(--bg);
  color: var(--ink);
  margin: 0;
  font-size: 14px;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ---------- Header ---------- */
.ribbon {
  background: var(--white);
  border-bottom: 4px solid var(--brand);
  box-shadow: 0 2px 10px rgba(20,35,90,0.08);
}
.ribbon-inner {
  max-width: var(--site-max);
  margin: 0 auto;
  padding: 12px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.brand { display: flex; align-items: center; gap: 16px; }
.brand-badge { flex: none; height: 68px; display: flex; align-items: center; justify-content: center; }
.brand-badge img { height: 100%; width: auto; display: block; }
.brand-text .eyebrow { margin: 0; font-size: 0.72rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--brand); font-weight: 700; }
.brand-text h1 { margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: 0.3px; color: var(--ink); text-transform: uppercase; line-height: 1.15; }
.brand-text .subtitle { margin: 3px 0 0; font-size: 0.85rem; color: var(--brand); font-weight: 600; }

.link-btn {
  display: inline-flex; align-items: center; gap: 7px;
  color: var(--brand); text-decoration: none; font-size: 0.8rem; font-weight: 700;
  border: 1.5px solid var(--brand); background: transparent;
  padding: 8px 16px 8px 12px; border-radius: 24px;
  transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
}
.link-btn svg { width: 15px; height: 15px; transition: transform .18s ease; }
.link-btn:hover { background: var(--brand); color: var(--white); transform: translateX(-2px); }
.link-btn:hover svg { transform: translateX(-2px); }

/* ---------- Hero (home view) ---------- */
.hero { max-width: var(--site-max); margin: 0 auto; padding: 46px 20px 8px; text-align: center; }
.hero .eyebrow { margin: 0 0 8px; font-size: 0.75rem; letter-spacing: 2px; text-transform: uppercase; color: var(--brand); font-weight: 800; }
.hero h2 { margin: 0 0 10px; font-size: 1.6rem; font-weight: 800; color: var(--ink); }
.hero p { margin: 0 auto; max-width: 560px; color: var(--muted); font-size: 0.92rem; line-height: 1.6; }

.option-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.option-card {
  background: var(--white); border: 1px solid var(--line); border-radius: 10px;
  box-shadow: var(--shadow-1); overflow: hidden; text-decoration: none; color: inherit;
  display: flex; flex-direction: column;
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  position: relative;
}
.option-card::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
  background: linear-gradient(180deg, var(--brand) 0%, var(--accent) 100%);
}
.option-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-2); border-color: var(--accent-soft); }
.option-icon {
  width: 62px; height: 62px; border-radius: 12px;
  background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
  color: var(--white); display: flex; align-items: center; justify-content: center;
  margin: 28px 0 0 26px; box-shadow: 0 6px 14px rgba(43,74,158,0.28);
}
.option-icon svg { width: 30px; height: 30px; }
.option-body { padding: 18px 26px 26px; flex: 1; display: flex; flex-direction: column; }
.option-body h3 { margin: 0 0 8px; font-size: 1.12rem; font-weight: 800; color: var(--brand-dark); }
.option-body p { margin: 0 0 18px; font-size: 0.85rem; color: var(--muted); line-height: 1.6; flex: 1; }
.option-cta {
  align-self: flex-start; color: var(--brand-dark); font-size: 0.82rem; font-weight: 700;
  padding: 9px 20px; border: 1.5px solid var(--brand); border-radius: 7px;
  background: transparent; transition: background .18s ease, color .18s ease, box-shadow .18s ease;
}
.option-card:hover .option-cta { background: var(--brand); color: var(--white); box-shadow: 0 6px 14px rgba(43,74,158,0.28); }
@media (max-width: 720px) { .option-grid { grid-template-columns: 1fr; } }

/* ---------- Document canvas (form views) ---------- */
.container { max-width: var(--site-max); margin: 26px auto 60px; padding: 0 20px; }

.card {
  background: var(--white); border: 1px solid var(--line); border-radius: var(--radius);
  box-shadow: var(--shadow-1); padding: 0; margin-bottom: 18px; overflow: hidden;
}
.card-header {
  padding: 13px 22px; border-bottom: 1px solid var(--line); border-left: 4px solid var(--brand);
  background: linear-gradient(180deg, #fafbff 0%, #f1f4fc 100%);
}
.card-header h2 { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--brand-dark); }
.card-header .sub { margin: 0; font-size: 0.75rem; color: var(--muted); }
.card-body { padding: 20px 22px 22px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
.field.span-2 { grid-column: span 2; }
.field label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--brand-dark); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.3px; }
.field .hint { font-weight: 400; color: var(--muted); font-size: 0.72rem; text-transform: none; letter-spacing: 0; }

input[type="text"], input[type="date"], input[type="tel"], input[type="time"], input[type="number"], select, textarea {
  width: 100%; padding: 9px 11px; border: 1px solid #c3c9dc; border-radius: 3px;
  font-size: 0.88rem; font-family: inherit; background: var(--white); color: var(--ink);
  transition: border-color .12s ease, box-shadow .12s ease;
}
textarea { resize: vertical; min-height: 78px; }
select { appearance: none; -webkit-appearance: none;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6'><path d='M0 0l5 6 5-6z' fill='%232b4a9e'/></svg>");
  background-repeat: no-repeat; background-position: right 12px center; padding-right: 30px;
}
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--focus); box-shadow: 0 0 0 2px rgba(43,74,158,0.15); }

/* ---------- Quantity stepper (Number of Projector Units) ---------- */
.qty-stepper {
  display: flex; align-items: center; gap: 16px; width: 100%; max-width: 180px;
}
.qty-stepper input[type="number"] {
  flex: 1 1 auto; width: 100%; min-width: 0; border: none; border-bottom: 1px solid #c3c9dc;
  border-radius: 0; text-align: center; padding: 6px 2px; font-size: 1.05rem; font-weight: 600;
  color: var(--ink); background: transparent; box-shadow: none; -moz-appearance: textfield;
  appearance: textfield;
  transition: border-color .12s ease;
}
.qty-stepper input[type="number"]:focus { border-color: var(--focus); box-shadow: none; }
.qty-stepper input[type="number"]::-webkit-outer-spin-button,
.qty-stepper input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-btn {
  flex: none; width: 28px; height: 28px; border-radius: 50%;
  border: 1px solid var(--brand); background: transparent; color: var(--brand);
  font-size: 1rem; line-height: 1; cursor: pointer; padding: 0;
  display: flex; align-items: center; justify-content: center; user-select: none;
  transition: border-color .12s ease, color .12s ease, background-color .12s ease;
}
.qty-btn:hover:not(:disabled) { background: var(--brand); color: var(--white); }
.qty-btn:active:not(:disabled) { background: var(--brand-dark); border-color: var(--brand-dark); }
.qty-btn:disabled { opacity: 0.3; cursor: not-allowed; border-color: #c3c9dc; color: var(--muted); }

/* ---------- Cascading tree dropdown (Department / Office) ---------- */
.tree-dropdown { position: relative; }
.tree-trigger {
  width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px;
  padding: 9px 11px; font-size: 0.88rem; font-family: inherit; color: var(--ink);
  background: var(--white); border: 1px solid #c3c9dc; border-radius: 3px; cursor: pointer; text-align: left;
}
.tree-trigger .tree-trigger-label { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tree-trigger .tree-trigger-label.placeholder { color: #7a8096; }
.tree-trigger:focus-visible,
.tree-dropdown.open .tree-trigger { outline: none; border-color: var(--focus); box-shadow: 0 0 0 2px rgba(43,74,158,0.15); }
.tree-dropdown.tree-error .tree-trigger { border-color: var(--err); box-shadow: 0 0 0 2px rgba(164,38,44,0.15); }
.tree-chevron { flex: none; transition: transform .15s ease; color: #7a8096; display: flex; }
.tree-dropdown.open .tree-chevron { transform: rotate(180deg); }

.tree-panel {
  position: fixed; max-height: 260px; overflow-y: auto;
  background: var(--white); border: 1px solid var(--line); border-radius: 6px;
  box-shadow: 0 10px 26px rgba(15,25,70,0.16), 0 2px 8px rgba(0,0,0,0.08);
  padding: 4px; z-index: 5000; display: none;
}
.tree-panel.tree-panel-open { display: block; }

.tree-panel ul { list-style: none; margin: 0; padding: 0; }
.tree-node > .tree-row {
  display: flex; align-items: center; gap: 6px; padding: 7px 8px; border-radius: 4px;
  cursor: pointer; font-size: 0.85rem; color: var(--ink);
}
.tree-node > .tree-row:hover { background: var(--tint-50); }
.tree-depth-0 > .tree-row { font-weight: 700; color: var(--brand-dark); }
.tree-depth-1 > .tree-row { padding-left: 24px; font-weight: 600; }
.tree-depth-2 > .tree-row { padding-left: 42px; font-weight: 400; color: #2b2f40; }

.tree-caret {
  flex: none; width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center;
  color: #7a8096; transition: transform .15s ease;
}
.tree-node.expanded > .tree-row .tree-caret { transform: rotate(90deg); }
.tree-leaf .tree-caret { visibility: hidden; }

.tree-children { display: none; }
.tree-node.expanded > .tree-children { display: block; }

.tree-node.selected > .tree-row { color: var(--brand); font-weight: 700; background: var(--tint-100); }

/* Brand-blue-styled selects (Licensed Software / Freeware) to match
   the Department dropdown's look and feel */
select.other-toggle {
  border: 1.5px solid var(--brand);
  background-color: var(--tint-50);
  color: var(--brand-dark);
  font-weight: 600;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6'><path d='M0 0l5 6 5-6z' fill='%232b4a9e'/></svg>");
}
select.other-toggle:hover {
  border-color: var(--brand-dark);
  background-color: var(--tint-100);
}
select.other-toggle:focus {
  border-color: var(--focus);
  background-color: var(--white);
  box-shadow: 0 0 0 2px rgba(43,74,158,0.15);
}

/* repeatable dropdown rows (software form) */
.repeat-list { display: flex; flex-direction: column; gap: 8px; }
.repeat-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.repeat-row span.num {
  flex: none; width: 22px; height: 22px; border-radius: 3px; background: var(--tint-200); color: var(--brand-dark);
  font-size: 0.72rem; font-weight: 700; display: flex; align-items: center; justify-content: center;
}
.repeat-row select { flex: 1 1 140px; min-width: 0; }
.repeat-row .other-input { flex: 1 1 140px; min-width: 0; }
.row-remove-btn {
  flex: none; width: 30px; height: 30px; border-radius: 3px; border: 1px solid var(--line); background: var(--white);
  color: var(--err); font-size: 0.95rem; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.row-remove-btn:hover { background: var(--err-bg); border-color: var(--err); }
.row-remove-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.add-row-btn {
  margin-top: 10px; display: inline-flex; align-items: center; gap: 6px;
  background: var(--brand); color: var(--white); border: none;
  padding: 8px 16px; border-radius: 3px; font-size: 0.8rem; font-weight: 600; cursor: pointer;
}
.add-row-btn:hover { background: var(--brand-dark); }
.add-row-btn .plus { font-size: 0.95rem; font-weight: 700; line-height: 1; }
.add-row-btn:disabled { background: #b9bfd2; color: var(--white); cursor: not-allowed; }
.row-limit-note { margin: 8px 0 0; font-size: 0.75rem; color: var(--err); }

/* ---------- Date(s) & Time(s) Needed: entry row + bubbles/chips ---------- */
.dn-entry-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.dn-entry-row .dn-field { flex: 1 1 150px; min-width: 130px; }
.dn-entry-row .dn-field label {
  display: block; font-size: 0.68rem; font-weight: 600; color: var(--muted);
  margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px;
}
.dn-entry-row .add-row-btn { flex: none; margin-top: 0; align-self: flex-end; height: 38px; }
.dn-entry-row .btn-secondary.dn-cancel-btn { flex: none; align-self: flex-end; height: 38px; padding: 10px 16px; border-radius: 3px; display: none; }
.dn-entry-row .btn-secondary.dn-cancel-btn.show { display: inline-flex; align-items: center; }
.dn-editing-note { margin: 8px 0 0; font-size: 0.78rem; color: var(--brand-dark); font-weight: 600; display: none; }
.dn-editing-note.show { display: block; }
.dn-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.dn-chip {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--tint-100); border: 1px solid var(--brand); color: var(--brand-dark);
  border-radius: 999px; padding: 7px 8px 7px 14px; font-size: 0.8rem; font-weight: 600;
  width: 260px; max-width: 100%; box-sizing: border-box;
  animation: checkPop .25s ease both;
}
.dn-chip.dn-chip-editing { background: var(--white); border-color: var(--brand); box-shadow: 0 0 0 3px rgba(43,74,158,0.25); }
.dn-chip .dn-chip-label {
  flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.dn-chip .dn-chip-tag {
  flex: none; background: var(--brand); color: var(--white); border-radius: 999px;
  font-size: 0.68rem; font-weight: 700; padding: 2px 7px; text-transform: uppercase; letter-spacing: 0.3px;
}
.dn-chip .dn-chip-edit {
  flex: none; width: 20px; height: 20px; border-radius: 50%; border: 1px solid var(--brand); background: var(--white);
  color: var(--brand); font-size: 0.72rem; line-height: 1; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: background .12s ease, color .12s ease;
}
.dn-chip .dn-chip-edit:hover { background: var(--brand); color: var(--white); }
.dn-chip .dn-chip-remove {
  flex: none; width: 20px; height: 20px; border-radius: 50%; border: none; background: var(--brand);
  color: var(--white); font-size: 0.85rem; line-height: 1; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: background .12s ease;
}
.dn-chip .dn-chip-remove:hover { background: var(--brand-dark); }
.dn-error-text { margin: 8px 0 0; font-size: 0.78rem; color: var(--err); display: none; }
.dn-error-text.show { display: block; }

/* "Other" free-text inputs shown under a select (position, etc.) */
.position-other-input { width: 100%; }

/* colour swatch beside "Type of Event" - the same colour the event gets on the admin calendar */
.ev-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-left: 6px; vertical-align: middle; background: transparent; }

.materials-list { margin: 0; padding-left: 20px; color: var(--ink); font-size: 0.85rem; line-height: 1.9; }
.materials-list li { margin-bottom: 2px; }

/* equipment checkbox grid (projector form) */
.equip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.equip-option {
  display: flex; align-items: center; gap: 10px; border: 1px solid var(--line); border-radius: 6px; padding: 11px 13px;
  cursor: pointer; transition: border-color .12s ease, background .12s ease; font-size: 0.85rem; font-weight: 600; color: var(--ink);
}
.equip-option:hover { border-color: var(--accent-soft); background: var(--tint-50); }
.equip-option input { accent-color: var(--brand); width: 16px; height: 16px; flex: none; }
.equip-option.checked { border-color: var(--brand); background: var(--tint-100); color: var(--brand-dark); }
.equip-label-text { flex: 1; }
.equip-other-field { margin-top: 14px; }

.consent-box {
  background: var(--tint-50); border: 1px solid var(--line); border-left: 4px solid var(--brand);
  border-radius: 3px; padding: 16px 18px; font-size: 0.78rem; color: #2a2f45; line-height: 1.55;
}
.consent-box label { display: flex; gap: 9px; margin-top: 12px; font-weight: 600; color: var(--brand-dark); font-size: 0.82rem; }
.consent-box input { accent-color: var(--brand); width: 15px; height: 15px; margin-top: 2px; flex: none; }

.actions-bar { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 6px 0 4px; flex-wrap: wrap; }
.btn {
  display: inline-flex; align-items: center; gap: 8px; justify-content: center;
  background: var(--brand); color: var(--white); border: 1px solid var(--brand-darker);
  padding: 11px 26px; border-radius: 3px; font-size: 0.88rem; font-weight: 600;
  cursor: pointer; text-decoration: none; box-shadow: var(--shadow-1);
}
.btn:hover { background: var(--brand-dark); }
.btn .glyph { font-size: 0.9rem; }

.error-box {
  background: var(--err-bg); border: 1px solid #f1b7bb; border-left: 4px solid var(--err);
  color: var(--err); padding: 12px 16px; border-radius: 3px; margin-bottom: 16px; font-size: 0.84rem;
}
.error-box strong { color: #7a1a1f; }
.error-box ul { margin: 6px 0 0 18px; padding: 0; }

.center { text-align: center; }
.muted { color: var(--muted); }

footer.page-footer {
  text-align: center; color: rgba(255,255,255,0.88); font-size: 0.78rem;
  background: var(--brand);
  padding: 16px 20px; margin-top: auto;
}

/* ---------- Modal popups ---------- */
@keyframes modalPop { from { opacity: 0; transform: translateY(18px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes overlayFade { from { opacity: 0; } to { opacity: 1; } }
@keyframes checkPop { 0% { transform: scale(0); opacity: 0; } 60% { transform: scale(1.15); opacity: 1; } 100% { transform: scale(1); } }

.modal-overlay {
  position: fixed; inset: 0; z-index: 1000; background: rgba(12,20,55, 0.58);
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  align-items: center; justify-content: center; padding: 20px;
  opacity: 0; visibility: hidden; pointer-events: none;
  transition: opacity .2s ease, visibility 0s linear .2s;
}
.modal-overlay.show {
  display: flex; opacity: 1; visibility: visible; pointer-events: auto;
  transition: opacity .2s ease; animation: overlayFade .2s ease;
}

/* ---------- Sending overlay (shown while the request/email is submitted) ---------- */
@keyframes spin { to { transform: rotate(360deg); } }
.sending-overlay {
  position: fixed; inset: 0; z-index: 2000; background: rgba(12,20,55, 0.72);
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center; padding: 20px;
  opacity: 0; animation: overlayFade .2s ease forwards;
}
.sending-overlay.show { display: flex; }
.sending-box {
  background: var(--white); border-radius: 14px; padding: 34px 40px; text-align: center;
  max-width: 320px; box-shadow: 0 24px 60px rgba(15,25,70, 0.35), 0 4px 14px rgba(0,0,0,0.12);
}
.sending-spinner {
  display: inline-block; width: 42px; height: 42px; border-radius: 50%;
  border: 4px solid var(--tint-200); border-top-color: var(--brand);
  animation: spin .8s linear infinite;
}
.sending-text { margin: 16px 0 4px; font-size: 0.95rem; font-weight: 700; color: var(--brand-dark); }
.sending-subtext { margin: 0; font-size: 0.8rem; color: var(--muted); }
.modal-box {
  background: var(--white); border-radius: 14px; width: clamp(300px, 55vw, 720px); max-width: 94vw; max-height: 88vh;
  display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(15,25,70, 0.35), 0 4px 14px rgba(0,0,0,0.12); overflow: hidden;
}
.modal-box.success-modal { width: clamp(260px, 38vw, 480px); max-width: 94vw; }
.modal-overlay.show .modal-box { animation: modalPop .28s cubic-bezier(.2, .9, .3, 1.2); }
.modal-box .modal-header { position: relative; padding: 22px 26px 20px; background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%); }
.modal-box .modal-header::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: linear-gradient(90deg, var(--accent) 0%, var(--accent-soft) 100%); }
.modal-box .modal-header h2 { margin: 0; font-size: 1.08rem; font-weight: 800; color: var(--white); letter-spacing: 0.2px; }
.modal-box .modal-header p { margin: 5px 0 0; font-size: 0.8rem; color: rgba(255,255,255,0.85); }
.modal-close-btn {
  position: absolute; top: 14px; right: 16px; width: 30px; height: 30px; border-radius: 50%; border: none;
  background: rgba(255,255,255,0.14); color: var(--white); font-size: 1rem; line-height: 1; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: background .15s ease, transform .15s ease;
}
.modal-close-btn:hover { background: rgba(255,255,255,0.26); transform: rotate(90deg); }
.modal-box .modal-body { padding: 20px 26px; overflow-y: auto; background: #fafbff; }
.modal-box .modal-body::-webkit-scrollbar { width: 8px; }
.modal-box .modal-body::-webkit-scrollbar-track { background: transparent; }
.modal-box .modal-body::-webkit-scrollbar-thumb { background: #d3d9ea; border-radius: 8px; }
.modal-box .modal-actions { padding: 16px 26px; border-top: 1px solid var(--line); background: var(--white); display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
.btn-secondary {
  background: var(--white); color: var(--brand-dark); border: 1px solid var(--line);
  padding: 10px 20px; border-radius: 24px; font-size: 0.85rem; font-weight: 600; cursor: pointer;
  transition: background .15s ease, border-color .15s ease, transform .1s ease;
}
.btn-secondary:hover { background: #f1f4fc; border-color: #c3c9dc; }
.btn-secondary:active { transform: scale(.97); }
.actions-bar .btn, .modal-actions .btn { border-radius: 24px; transition: transform .1s ease, box-shadow .15s ease, background .15s ease; }
.actions-bar .btn:active, .modal-actions .btn:active { transform: scale(.97); }

.confirm-section { margin-bottom: 16px; background: var(--white); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; }
.confirm-section:last-child { margin-bottom: 0; }
.confirm-section h3 {
  margin: 0 0 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.6px; color: var(--brand); display: flex; align-items: center; gap: 8px;
}
.confirm-section h3::before {
  content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--brand);
  box-shadow: 0 0 0 3px var(--ribbon-bg), 0 0 0 4px var(--brand); flex: none; margin-left: 2px;
}
.confirm-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px dashed var(--line); font-size: 0.85rem; flex-wrap: wrap; }
.confirm-row:last-child { border-bottom: none; }
.confirm-label { color: var(--muted); font-weight: 600; flex: none; width: 44%; }
.confirm-value { color: var(--ink); font-weight: 500; text-align: right; flex: 1; word-break: break-word; }
.confirm-list { margin: 0; padding-left: 20px; font-size: 0.85rem; line-height: 1.7; }
.confirm-empty { margin: 0; font-size: 0.82rem; color: var(--muted); font-style: italic; }

.success-modal .modal-header { background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%); }
.success-modal .modal-header::after { background: linear-gradient(90deg, var(--accent) 0%, var(--accent-soft) 100%); }
.success-modal .modal-body { text-align: center; padding: 30px 26px 26px; }
.success-modal .success-icon {
  width: 66px; height: 66px; border-radius: 50%; background: var(--tint-200); color: var(--brand);
  display: flex; align-items: center; justify-content: center; margin: -14px auto 16px; border: 4px solid var(--white);
  box-shadow: 0 4px 14px rgba(0,0,0,0.15); animation: checkPop .4s ease .1s both;
}
.success-modal .success-icon svg { width: 30px; height: 30px; display: block; }
.success-modal .success-title { margin: 0 0 4px; font-size: 1.02rem; font-weight: 700; color: var(--ink); }
.success-modal .muted { margin: 0; }

/* =====================================================================
   RESPONSIVE BREAKPOINTS
   Mobile-first-ish fluid layout that scales cleanly at every width
   (large desktop -> tablet -> phone -> small phone).
   ===================================================================== */

/* ---- Large desktop: let the shell breathe a bit more ---- */
@media (min-width: 1400px) {
  :root { --site-max: 1120px; }
}

/* ---- Tablets and small laptops ---- */
@media (max-width: 900px) {
  .ribbon-inner { padding: 14px 18px; }
  .container { padding: 0 16px; }
  .card-body { padding: 18px; }
}

/* ---- Tablet portrait / large phone ---- */
@media (max-width: 700px) {
  .form-grid { grid-template-columns: 1fr; }
  .field.span-2 { grid-column: span 1; }
  .brand-badge { height: 56px; }
  .brand-text h1 { font-size: 1.12rem; }
  .brand-text .subtitle { font-size: 0.78rem; }
  .hero { padding: 32px 16px 6px; }
  .hero h2 { font-size: 1.3rem; }
  .modal-box .modal-header { padding: 18px 20px 16px; }
  .modal-box .modal-body { padding: 16px 20px; }
  .modal-box .modal-actions { padding: 14px 20px; }
}

/* ---- Phones ---- */
@media (max-width: 560px) {
  body { font-size: 13.5px; }
  .ribbon-inner { padding: 12px 14px; gap: 10px; }
  .brand { gap: 10px; width: 100%; }
  .brand-badge { height: 44px; }
  .brand-text .eyebrow { font-size: 0.62rem; }
  .brand-text h1 { font-size: 0.98rem; }
  .brand-text .subtitle { font-size: 0.72rem; }
  .link-btn { font-size: 0.75rem; padding: 7px 13px 7px 10px; width: 100%; justify-content: center; }

  .hero { padding: 26px 14px 4px; }
  .hero .eyebrow { font-size: 0.68rem; }
  .hero h2 { font-size: 1.14rem; }
  .hero p { font-size: 0.85rem; }

  .container { padding: 0 12px; margin: 18px auto 40px; }
  .card { border-radius: 6px; }
  .card-header { padding: 11px 14px; }
  .card-header h2 { font-size: 0.88rem; }
  .card-body { padding: 14px; }

  .option-icon { margin: 20px 0 0 18px; width: 52px; height: 52px; }
  .option-body { padding: 14px 18px 20px; }
  .option-body h3 { font-size: 1rem; }

  .equip-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }

  .dn-entry-row { gap: 10px; }
  .dn-entry-row .dn-field { flex-basis: 100%; }
  .dn-entry-row .add-row-btn { flex-basis: 100%; justify-content: center; }

  .actions-bar .btn { width: 100%; }
  .modal-actions { flex-direction: column-reverse; }
  .modal-actions .btn, .modal-actions .btn-secondary { width: 100%; }

  .confirm-label { width: 50%; }

  footer.page-footer { padding: 14px 14px; font-size: 0.72rem; }
}

/* ---- Very small phones ---- */
@media (max-width: 380px) {
  .brand-text h1 { font-size: 0.86rem; }
  .hero h2 { font-size: 1.02rem; }
  .confirm-row { flex-direction: column; gap: 2px; }
  .confirm-label, .confirm-value { width: 100%; text-align: left; }
  .equip-grid { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
  .form-grid { grid-template-columns: 1fr; }
  .field.span-2 { grid-column: span 1; }
}
</style>
</head>
<body>

<div class="sending-overlay" id="sendingOverlay" role="status" aria-live="polite">
  <div class="sending-box">
    <span class="sending-spinner" aria-hidden="true"></span>
    <p class="sending-text">Sending your request&hellip;</p>
    <p class="sending-subtext">Please wait while we email the ITS admin team.</p>
  </div>
</div>

<div class="ribbon">
  <div class="ribbon-inner">
    <div class="brand">
      <div class="brand-badge"><img src="img/logo.png" alt="ITS - Information Technology Services"></div>
      <div class="brand-text">
        <h1>ITS Department</h1>
        <p class="subtitle"><?= h($FORM_TITLE) ?></p>
      </div>
    </div>
      <a class="link-btn" href="index.php">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M19 12H5M5 12l6-6M5 12l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Back to Request Portal
      </a>
  </div>
</div>


<div class="container">

  <?php if ($success): ?>
    <div class="modal-overlay show" id="successModal">
      <div class="modal-box success-modal">
        <div class="modal-header">
          <button type="button" class="modal-close-btn" id="successCloseBtn" aria-label="Close">&times;</button>
          <h2>Request Submitted</h2>
          <p>We've received your LCD projector setup request.</p>
        </div>
        <div class="modal-body">
          <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <p class="success-title">Sent successfully!</p>
        </div>
        <div class="modal-actions" style="justify-content:center;">
          <button type="button" class="btn" id="successOkBtn">Done</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="error-box">
      <strong>Please fix the following:</strong>
      <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>


  <form method="post" action="projector.php" id="requestForm">

    <div class="card">
      <div class="card-header">
        <h2>Requester Details</h2>
        <p class="sub">Basic requester information</p>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field">
            <label>Date Requested</label>
            <input type="date" name="date_requested" value="<?= h($pOld['date_requested']) ?>"
                   max="<?= h($MAX_DATE) ?>" required>
          </div>
          <div class="field">
            <label>Person in Charge</label>
            <input type="text" name="name" placeholder="Full name" value="<?= h($pOld['name']) ?>"
                   pattern="[a-zA-Z\s.'-]+" title="Letters only, no numbers or special characters" required>
          </div>
          <div class="field">
            <label>Department / Office</label>
            <div class="tree-dropdown" id="departmentDropdown" data-input="departmentInput" data-other-input="department_other_input">
              <button type="button" class="tree-trigger" id="departmentTrigger">
                <span class="tree-trigger-label<?= $pOld['department'] === '' ? ' placeholder' : '' ?>" id="departmentTriggerLabel"><?= $pOld['department'] !== '' ? h($pOld['department']) : '-- Select department / office --' ?></span>
                <span class="tree-chevron">
                  <svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
              </button>
              <div class="tree-panel" id="departmentPanel"></div>
            </div>
            <input type="hidden" name="department" id="departmentInput" value="<?= h($pOld['department']) ?>">
            <input type="text" name="department_other" id="department_other_input" class="position-other-input"
                   placeholder="Please specify your department / office" value="<?= h($pOld['department_other']) ?>"
                   style="<?= $pOld['department'] === DEPARTMENT_OTHER_VALUE ? 'margin-top:8px;' : 'display:none;' ?>">
          </div>
          <div class="field">
            <label>Position</label>
            <select name="position" id="position_select" required>
              <option value="">-- Select --</option>
              <?php foreach (['Faculty', 'Staff', 'Student', 'Heads / Dean', 'Other'] as $opt): ?>
                <option value="<?= h($opt) ?>" <?= $pOld['position'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="position_other" id="position_other_input" class="position-other-input"
                   placeholder="Please specify your position" value="<?= h($pOld['position_other']) ?>"
                   style="<?= $pOld['position'] === 'Other' ? 'margin-top:8px;' : 'display:none;' ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Projector Setup Details</h2>
        <p class="sub">Where and when the projector setup is needed</p>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field span-2">
            <label>Purpose / Event <span class="hint">(class, seminar, meeting, etc.)</span></label>
            <input type="text" name="purpose" placeholder="" value="<?= h($pOld['purpose']) ?>" required>
          </div>
          <div class="field">
            <label>Type of Event <i class="ev-dot" id="eventTypeDot"></i></label>
            <select name="event_type" id="event_type_select" required>
              <option value="">-- Select --</option>
              <?php foreach (array_keys(event_type_palette()) as $opt): ?>
                <option value="<?= h($opt) ?>" <?= $pOld['event_type'] === $opt ? 'selected' : '' ?>><?= h($opt === EVENT_TYPE_OTHER_VALUE ? 'Others (please specify)' : $opt) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="event_type_other" id="event_type_other_input" class="position-other-input"
                   maxlength="60" placeholder="Please specify the type of event" value="<?= h($pOld['event_type_other']) ?>"
                   style="<?= $pOld['event_type'] === EVENT_TYPE_OTHER_VALUE ? 'margin-top:8px;' : 'display:none;' ?>">
          </div>
          <div class="field">
            <label>Number of Participants</label>
            <input type="number" name="participants" min="1" max="10000" step="1" inputmode="numeric"
                   placeholder="e.g. 50" value="<?= h($pOld['participants']) ?>" required>
          </div>
          <div class="field">
            <label>Venue / Room</label>
            <input type="text" name="venue" placeholder="" value="<?= h($pOld['venue']) ?>" required>
          </div>
          <div class="field">
            <label>Number of Projector Units</label>
            <div class="qty-stepper" id="projectorUnitsStepper" data-min="0" data-max="<?= (int) $MAX_UNITS_PER_REQUEST ?>">
              <button type="button" class="qty-btn qty-minus" aria-label="Decrease number of projector units">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
              <input type="number" name="projector_units" id="projector_units_input"
                     min="0" max="<?= (int) $MAX_UNITS_PER_REQUEST ?>" step="1" inputmode="numeric"
                     value="<?= h($pOld['projector_units']) ?>" required>
              <button type="button" class="qty-btn qty-plus" aria-label="Increase number of projector units">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 2v8M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
            </div>
            <p class="row-limit-note">Up to <?= (int) $MAX_UNITS_PER_REQUEST ?> unit(s) per request.</p>
          </div>

          <div class="field span-2" id="datesNeededField">
            <label>Date(s) &amp; Time(s) Needed</label>
            <div class="dn-entry-row">
              <div class="dn-field">
                <label for="dn_date_input">Date</label>
                <input type="date" id="dn_date_input"
                       max="<?= h($MAX_DATE) ?>">
              </div>
              <div class="dn-field">
                <label for="dn_time_from_input">Time From</label>
                <input type="time" id="dn_time_from_input"
                       min="<?= TIME_SELECTION_MIN ?>" max="<?= TIME_SELECTION_MAX ?>">
              </div>
              <div class="dn-field">
                <label for="dn_time_to_input">Time To</label>
                <input type="time" id="dn_time_to_input"
                       min="<?= TIME_SELECTION_MIN ?>" max="<?= TIME_SELECTION_MAX ?>">
              </div>
              <button type="button" class="add-row-btn" id="dnAddBtn"><span class="plus">+</span> Add date</button>
              <button type="button" class="btn-secondary dn-cancel-btn" id="dnCancelEditBtn">Cancel</button>
            </div>
            <p class="dn-editing-note" id="dnEditingNote">Editing a date &mdash; update the fields above and click "Save changes", or Cancel.</p>
            <p class="dn-error-text" id="dnErrorText">Please add at least one date.</p>
            <div class="dn-chips" id="dnChips"></div>
            <p class="row-limit-note" id="dnLimitNote" style="display:none;">You can add up to <?= (int) DATES_NEEDED_MAX_ROWS ?> dates.</p>
          </div>

          <div class="field span-2">
            <label>Remarks / Special Instructions <span class="hint">(optional)</span></label>
            <textarea name="remarks" placeholder="Any other details the ITS team should know"><?= h($pOld['remarks']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Additional Equipment Needed</h2>
        <p class="sub">Select all that apply</p>
      </div>
      <div class="card-body">
        <div class="equip-grid">
          <?php foreach ($EQUIPMENT_OPTIONS as $key => $label): if ($key === 'other') continue; ?>
            <?php $isChecked = in_array($key, $pOld['equipment']); ?>
            <label class="equip-option <?= $isChecked ? 'checked' : '' ?>">
              <input type="checkbox" name="equipment[]" value="<?= h($key) ?>" class="equip-checkbox" <?= $isChecked ? 'checked' : '' ?>>
              <span class="equip-label-text"><?= h($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="field equip-other-field">
          <label>Other</label>
          <input type="text" name="equipment_other_text" class="equip-other-text"
                 placeholder="Please specify other equipment needed"
                 value="<?= h($pOld['equipment_other_text']) ?>">
        </div>
      </div>
    </div>

    <div class="consent-box">
      By submitting this LCD projector setup request form, you hereby allow / authorize UPHSD Calamba campus and their
      authorized personnel to gather and process your personal information (department and
      venue details) for documentation and specifically for scheduling and dispatch of ITS equipment and personnel.
      All information gathered from this activity will be kept secure and confidential for a period of five (5) years
      from the collection date and will be destroyed by means of shredding and/or file deletion after the prescribed
      retention period in accordance with Republic Act 10173, the Data Privacy Act of 2012 of the Philippines.
      <label>
        <input type="checkbox" name="consent" value="1" <?= $pOld['consent'] ? 'checked' : '' ?> required> I have read and agree to the above.
      </label>
    </div>

    <div class="actions-bar">
      <button type="submit" class="btn" id="submitRequestBtn"><span class="glyph">&#10003;</span> Submit Request</button>
    </div>

  </form>

  <div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
      <div class="modal-header">
        <button type="button" class="modal-close-btn" id="confirmCloseBtn" aria-label="Close">&times;</button>
        <h2>Confirm Your Request</h2>
        <p>Please review the information below before sending.</p>
      </div>
      <div class="modal-body" id="confirmDetails"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="confirmCancelBtn">Cancel</button>
        <button type="button" class="btn" id="confirmSendBtn"><span class="glyph">&#10003;</span> Confirm</button>
      </div>
    </div>
  </div>

  </div>

</div>

<footer class="page-footer">Information Technology Services Department</footer>

<script>
(function () {
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function selectedText(select) {
    if (!select) return '';
    if (select.selectedIndex < 0) return select.value || '';
    var opt = select.options[select.selectedIndex];
    return opt ? opt.text : select.value;
  }
  function positionValue() {
    var form = document.getElementById('requestForm');
    var select = form.elements['position'];
    var text = selectedText(select);
    if (text === 'Other') {
      var other = document.getElementById('position_other_input');
      return (other && other.value.trim()) ? other.value.trim() : text;
    }
    return text;
  }
  function eventTypeValue() {
    var form = document.getElementById('requestForm');
    var val = form.elements['event_type'] ? form.elements['event_type'].value : '';
    if (val === EVENT_TYPE_OTHER_VALUE) {
      var other = document.getElementById('event_type_other_input');
      return (other && other.value.trim()) ? other.value.trim() : val;
    }
    return val;
  }
  function departmentValue() {
    var form = document.getElementById('requestForm');
    var val = form.elements['department'] ? form.elements['department'].value : '';
    if (val === DEPARTMENT_OTHER_VALUE) {
      var other = document.getElementById('department_other_input');
      return (other && other.value.trim()) ? other.value.trim() : val;
    }
    return val;
  }
  function row(label, value) {
    var v = (value === null || value === undefined || value === '') ? '&mdash;' : escapeHtml(value);
    return '<div class="confirm-row"><span class="confirm-label">' + escapeHtml(label) +
           '</span><span class="confirm-value">' + v + '</span></div>';
  }

  // ---------- Department / Office cascading tree dropdown ----------
  var DEPARTMENT_TREE = <?= json_encode($DEPARTMENT_TREE_DATA, JSON_UNESCAPED_UNICODE) ?>;
  var DEPARTMENT_OTHER_VALUE = <?= json_encode(DEPARTMENT_OTHER_VALUE) ?>;
  var EVENT_TYPE_OTHER_VALUE = <?= json_encode(EVENT_TYPE_OTHER_VALUE) ?>;
  var EVENT_TYPE_COLORS = <?= json_encode(event_type_palette(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  function initTreeDropdown(rootId) {
    var root = document.getElementById(rootId);
    if (!root) return;
    var trigger = root.querySelector('.tree-trigger');
    var triggerLabel = root.querySelector('.tree-trigger-label');
    var panel = root.querySelector('.tree-panel');
    var hiddenInput = document.getElementById(root.getAttribute('data-input'));
    var otherInputId = root.getAttribute('data-other-input');
    var otherInput = otherInputId ? document.getElementById(otherInputId) : null;
    var caretSvg = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none"><path d="M2 1l4 3-4 3" stroke="currentColor" stroke-width="1.5"/></svg>';

    function syncOtherInput(isOther) {
      if (!otherInput) return;
      otherInput.style.display = isOther ? '' : 'none';
      otherInput.style.marginTop = isOther ? '8px' : '';
      otherInput.required = isOther;
      if (!isOther) {
        otherInput.value = '';
      } else {
        otherInput.focus();
      }
    }

    function makeNode(item, depth, pathNames) {
      var isLeaf = !item.children || item.children.length === 0;
      var currentPath = pathNames.concat([item.name]);

      var li = document.createElement('li');
      li.className = 'tree-node tree-depth-' + depth + (isLeaf ? ' tree-leaf' : '');
      li.dataset.path = currentPath.join(' > ');
      li.dataset.leaf = item.name;
      li.dataset.other = item.is_other ? '1' : '';

      var rowEl = document.createElement('div');
      rowEl.className = 'tree-row';

      var caretSpan = document.createElement('span');
      caretSpan.className = 'tree-caret';
      caretSpan.innerHTML = caretSvg;

      var textSpan = document.createElement('span');
      textSpan.textContent = item.name;

      rowEl.appendChild(caretSpan);
      rowEl.appendChild(textSpan);
      li.appendChild(rowEl);

      if (isLeaf) {
        rowEl.addEventListener('click', function (e) {
          e.stopPropagation();
          selectLeaf(li, currentPath);
        });
      } else {
        var childUl = document.createElement('ul');
        childUl.className = 'tree-children';
        item.children.forEach(function (child) {
          childUl.appendChild(makeNode(child, depth + 1, currentPath));
        });
        li.appendChild(childUl);
        rowEl.addEventListener('click', function (e) {
          e.stopPropagation();
          li.classList.toggle('expanded');
        });
      }

      return li;
    }

    function buildTree() {
      panel.innerHTML = '';
      var ul = document.createElement('ul');
      DEPARTMENT_TREE.forEach(function (item) {
        ul.appendChild(makeNode(item, 0, []));
      });
      panel.appendChild(ul);
    }

    function selectLeaf(li, pathArr) {
      panel.querySelectorAll('.tree-node.selected').forEach(function (n) { n.classList.remove('selected'); });
      li.classList.add('selected');
      var full = pathArr.join(' > ');
      var leafName = pathArr[pathArr.length - 1];
      if (triggerLabel) {
        triggerLabel.textContent = leafName;
        triggerLabel.title = full;
        triggerLabel.classList.remove('placeholder');
      }
      if (hiddenInput) hiddenInput.value = leafName;
      syncOtherInput(li.dataset.other === '1');
      root.classList.remove('tree-error');
      closeDropdown();
    }

    // The panel is moved out to <body> so it can float above everything
    // (a card's `overflow: hidden` would otherwise clip/cut it off, and it
    // could get hidden behind later cards) and is positioned to line up
    // with the trigger via getBoundingClientRect().
    document.body.appendChild(panel);

    function positionPanel() {
      var rect = trigger.getBoundingClientRect();
      var vw = document.documentElement.clientWidth;
      var vh = document.documentElement.clientHeight;
      var width = rect.width;
      var left = rect.left;
      // Keep the floating panel fully inside the viewport on narrow screens.
      if (left + width > vw - 8) left = Math.max(8, vw - width - 8);
      panel.style.width = width + 'px';
      panel.style.left = left + 'px';
      var top = rect.bottom + 4;
      var maxHeight = Math.min(260, vh - top - 12);
      if (maxHeight < 140 && rect.top > vh - rect.bottom) {
        // Not enough room below - open upward instead.
        maxHeight = Math.min(260, rect.top - 12);
        panel.style.top = Math.max(8, rect.top - maxHeight - 4) + 'px';
      } else {
        panel.style.top = top + 'px';
      }
      panel.style.maxHeight = Math.max(120, maxHeight) + 'px';
    }

    function openDropdown() {
      positionPanel();
      root.classList.add('open');
      panel.classList.add('tree-panel-open');
    }
    function closeDropdown() {
      root.classList.remove('open');
      panel.classList.remove('tree-panel-open');
    }
    function toggleDropdown() {
      if (panel.classList.contains('tree-panel-open')) {
        closeDropdown();
      } else {
        openDropdown();
      }
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      toggleDropdown();
    });
    document.addEventListener('click', function (e) {
      if (!root.contains(e.target) && !panel.contains(e.target)) closeDropdown();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDropdown();
    });
    // Reposition (or simply close) if the layout shifts under the open panel.
    window.addEventListener('resize', function () {
      if (panel.classList.contains('tree-panel-open')) positionPanel();
    });
    window.addEventListener('scroll', function () {
      if (panel.classList.contains('tree-panel-open')) positionPanel();
    }, true);

    buildTree();

    // Restore a previous selection (e.g. after a validation error re-render)
    if (hiddenInput && hiddenInput.value) {
      var match = null;
      panel.querySelectorAll('.tree-node').forEach(function (n) {
        if (n.dataset.leaf === hiddenInput.value) match = n;
      });
      if (match) {
        var ancestor = match.parentElement;
        while (ancestor && ancestor !== panel) {
          if (ancestor.classList && ancestor.classList.contains('tree-node')) ancestor.classList.add('expanded');
          ancestor = ancestor.parentElement;
        }
        match.classList.add('selected');
        if (otherInput && match.dataset.other === '1') {
          otherInput.required = true;
        }
      }
    }
  }

  initTreeDropdown('departmentDropdown');

  // ---------- Position "Other" reveals a text box ----------
  (function () {
    var select = document.getElementById('position_select');
    var otherInput = document.getElementById('position_other_input');
    if (!select || !otherInput) return;
    var sync = function () {
      var isOther = select.value === 'Other';
      otherInput.style.display = isOther ? '' : 'none';
      otherInput.style.marginTop = isOther ? '8px' : '';
      otherInput.required = isOther;
      if (!isOther) otherInput.value = '';
    };
    select.addEventListener('change', sync);
    sync();
  })();

  // ---------- Type of Event: "Others" reveals a text box; the dot previews the calendar colour ----------
  (function () {
    var select = document.getElementById('event_type_select');
    var otherInput = document.getElementById('event_type_other_input');
    var dot = document.getElementById('eventTypeDot');
    if (!select || !otherInput) return;
    var sync = function () {
      var isOther = select.value === EVENT_TYPE_OTHER_VALUE;
      otherInput.style.display = isOther ? '' : 'none';
      otherInput.style.marginTop = isOther ? '8px' : '';
      otherInput.required = isOther;
      if (!isOther) otherInput.value = '';
      if (dot) dot.style.background = EVENT_TYPE_COLORS[select.value] || 'transparent';
    };
    select.addEventListener('change', sync);
    sync();
  })();

  // ---------- Projector form: equipment checkbox styling ----------
  document.querySelectorAll('.equip-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
      cb.closest('.equip-option').classList.toggle('checked', cb.checked);
    });
  });

  // ---------- Number of Projector Units: tap +/- stepper ----------
  (function () {
    var wrap = document.getElementById('projectorUnitsStepper');
    if (!wrap) return;
    var input = document.getElementById('projector_units_input');
    var minusBtn = wrap.querySelector('.qty-minus');
    var plusBtn = wrap.querySelector('.qty-plus');
    var min = parseInt(wrap.getAttribute('data-min'), 10);
    if (isNaN(min)) min = 0;
    var max = parseInt(wrap.getAttribute('data-max'), 10) || 1;

    function clamp(v) {
      if (isNaN(v)) v = min;
      return Math.min(max, Math.max(min, v));
    }
    function syncButtons() {
      var v = parseInt(input.value, 10);
      if (isNaN(v)) v = min;
      minusBtn.disabled = v <= min;
      plusBtn.disabled = v >= max;
    }
    function setValue(v) {
      input.value = clamp(v);
      syncButtons();
    }
    minusBtn.addEventListener('click', function () {
      setValue((parseInt(input.value, 10) || min) - 1);
    });
    plusBtn.addEventListener('click', function () {
      setValue((parseInt(input.value, 10) || min) + 1);
    });
    input.addEventListener('input', function () {
      input.value = input.value.replace(/[^0-9]/g, '');
      syncButtons();
    });
    input.addEventListener('blur', function () {
      setValue(parseInt(input.value, 10));
    });
    syncButtons();
  })();

  // ---------- Date(s) & Time(s) Needed: shared entry fields + bubbles/chips ----------
  // Mirrors TIME_SELECTION_MIN / TIME_SELECTION_MAX / DATES_NEEDED_MAX_ROWS in PHP.
  var TIME_SELECTION_MIN = <?= json_encode(TIME_SELECTION_MIN) ?>;
  var TIME_SELECTION_MAX = <?= json_encode(TIME_SELECTION_MAX) ?>;
  var TIME_SELECTION_MIN_MINUTES = <?= (int) parse_clock_time_to_minutes(TIME_SELECTION_MIN) ?>;
  var TIME_SELECTION_MAX_MINUTES = <?= (int) parse_clock_time_to_minutes(TIME_SELECTION_MAX) ?>;
  var DATES_NEEDED_MAX_ROWS = <?= (int) DATES_NEEDED_MAX_ROWS ?>;
  // Rows already submitted on a validation-error re-render, so they show up
  // as bubbles again instead of being lost.
  var DN_INITIAL_ROWS = <?= json_encode($pOld['dates_needed']) ?>;

  function formatTimeForDisplay(value) {
    // value is "HH:MM" (24h) from the native time input.
    if (!value) return '';
    var parts = value.split(':');
    var h = parseInt(parts[0], 10);
    var m = parts[1];
    var suffix = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12;
    if (h12 === 0) h12 = 12;
    return h12 + ':' + m + ' ' + suffix;
  }
  function formatDateForDisplay(value) {
    // value is "YYYY-MM-DD" from the native date input.
    if (!value) return '';
    var parts = value.split('-');
    if (parts.length !== 3) return value;
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  }
  // Mirrors parse_clock_time_to_minutes() in PHP: turns either a 24-hour
  // "H:MM"/"HH:MM" value or the picker's 12-hour "h:mm AM/PM" value into
  // minutes-since-midnight so two times can be compared. Returns null for
  // anything else, so the ordering check is skipped when a time isn't in a
  // recognized clock format.
  function parseTimeToMinutes(value) {
    value = (value || '').trim();
    if (!value) return null;
    var m24 = value.match(/^([01]?\d|2[0-3]):([0-5]\d)$/);
    if (m24) {
      return parseInt(m24[1], 10) * 60 + parseInt(m24[2], 10);
    }
    var m12 = value.match(/^(0?[1-9]|1[0-2]):([0-5]\d)\s*([AaPp][Mm])$/);
    if (m12) {
      var h = parseInt(m12[1], 10);
      var mins = parseInt(m12[2], 10);
      var suffix = m12[3].toUpperCase();
      if (suffix === 'AM') { if (h === 12) h = 0; }
      else if (h !== 12) { h += 12; }
      return h * 60 + mins;
    }
    return null;
  }
  // Validates one Time From / Time To pair: each time (if it parses as a
  // recognized clock format) must fall within the 6:00 AM-10:00 PM window,
  // and Time To must be later than Time From. Surfaced via the native
  // validation UI (setCustomValidity + reportValidity).
  function validateTimeFields(fromEl, toEl) {
    if (fromEl) fromEl.setCustomValidity('');
    if (toEl) toEl.setCustomValidity('');

    var fromMin = fromEl ? parseTimeToMinutes(fromEl.value) : null;
    var toMin = toEl ? parseTimeToMinutes(toEl.value) : null;
    var windowMsg = 'Time must be between 6:00 AM and 10:00 PM.';

    var fromOutOfWindow = fromMin !== null && (fromMin < TIME_SELECTION_MIN_MINUTES || fromMin > TIME_SELECTION_MAX_MINUTES);
    var toOutOfWindow = toMin !== null && (toMin < TIME_SELECTION_MIN_MINUTES || toMin > TIME_SELECTION_MAX_MINUTES);
    if (fromEl && fromOutOfWindow) fromEl.setCustomValidity(windowMsg);
    if (toEl && toOutOfWindow) toEl.setCustomValidity(windowMsg);

    if (toEl && !toOutOfWindow && !fromOutOfWindow &&
        fromMin !== null && toMin !== null && toMin <= fromMin) {
      toEl.setCustomValidity('Time To must be later than Time From.');
    }
  }

  var dnManager = (function () {
    var dateInput = document.getElementById('dn_date_input');
    var fromInput = document.getElementById('dn_time_from_input');
    var toInput = document.getElementById('dn_time_to_input');
    var addBtn = document.getElementById('dnAddBtn');
    var cancelBtn = document.getElementById('dnCancelEditBtn');
    var editingNote = document.getElementById('dnEditingNote');
    var chipsWrap = document.getElementById('dnChips');
    var limitNote = document.getElementById('dnLimitNote');
    var errorText = document.getElementById('dnErrorText');
    var rows = []; // committed rows: [{date, time_from, time_to}]
    var editingIndex = null; // index in `rows` currently being edited, or null

    if (!dateInput || !fromInput || !toInput || !addBtn || !chipsWrap) {
      return { getRows: function () { return []; }, commitPending: function () { return true; }, hasAny: function () { return false; } };
    }

    function clearEntryFields() {
      dateInput.value = '';
      fromInput.value = '';
      toInput.value = '';
      dateInput.setCustomValidity('');
      fromInput.setCustomValidity('');
      toInput.setCustomValidity('');
    }

    function setEditingState(isEditing) {
      addBtn.innerHTML = isEditing
        ? '<span class="plus">&#10003;</span> Save changes'
        : '<span class="plus">+</span> Add date';
      if (cancelBtn) cancelBtn.classList.toggle('show', isEditing);
      if (editingNote) editingNote.classList.toggle('show', isEditing);
    }

    function startEdit(idx) {
      editingIndex = idx;
      var r = rows[idx];
      dateInput.disabled = false;
      fromInput.disabled = false;
      toInput.disabled = false;
      dateInput.value = r.date;
      fromInput.value = r.time_from;
      toInput.value = r.time_to;
      dateInput.setCustomValidity('');
      fromInput.setCustomValidity('');
      toInput.setCustomValidity('');
      setEditingState(true);
      render();
      dateInput.focus();
    }

    function cancelEdit() {
      editingIndex = null;
      clearEntryFields();
      setEditingState(false);
      render();
    }

    function render() {
      chipsWrap.innerHTML = '';
      rows.forEach(function (r, idx) {
        var chip = document.createElement('span');
        chip.className = 'dn-chip' + (editingIndex === idx ? ' dn-chip-editing' : '');

        var tag = document.createElement('span');
        tag.className = 'dn-chip-tag';
        tag.textContent = idx === 0 ? 'Date Needed' : ('Date ' + (idx + 1));
        chip.appendChild(tag);

        var labelText = formatDateForDisplay(r.date) + ' \u00B7 ' + formatTimeForDisplay(r.time_from) + ' \u2013 ' + formatTimeForDisplay(r.time_to);
        var label = document.createElement('span');
        label.className = 'dn-chip-label';
        label.textContent = labelText;
        label.title = labelText;
        chip.appendChild(label);

        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'dn-chip-edit';
        editBtn.setAttribute('aria-label', 'Edit this date');
        editBtn.innerHTML = '&#9998;';
        editBtn.addEventListener('click', function () {
          startEdit(idx);
        });
        chip.appendChild(editBtn);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'dn-chip-remove';
        removeBtn.setAttribute('aria-label', 'Remove this date');
        removeBtn.innerHTML = '&times;';
        removeBtn.addEventListener('click', function () {
          rows.splice(idx, 1);
          if (editingIndex === idx) {
            editingIndex = null;
            clearEntryFields();
            setEditingState(false);
          } else if (editingIndex !== null && idx < editingIndex) {
            editingIndex -= 1;
          }
          render();
        });
        chip.appendChild(removeBtn);

        // Hidden inputs so this row is submitted with the form (skipped
        // while the row is being edited, since the live fields cover it).
        if (editingIndex !== idx) {
          ['date', 'time_from', 'time_to'].forEach(function (key) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = key === 'date' ? 'dn_date[]' : (key === 'time_from' ? 'dn_time_from[]' : 'dn_time_to[]');
            hidden.value = r[key];
            chip.appendChild(hidden);
          });
        }

        chipsWrap.appendChild(chip);
      });

      var atMax = editingIndex === null && rows.length >= DATES_NEEDED_MAX_ROWS;
      addBtn.disabled = atMax;
      dateInput.disabled = atMax;
      fromInput.disabled = atMax;
      toInput.disabled = atMax;
      if (limitNote) limitNote.style.display = atMax ? '' : 'none';
      if (rows.length > 0 && errorText) errorText.classList.remove('show');
    }

    // Validates the current entry fields (required + time window/order) and,
    // if valid, either updates the row being edited or pushes a new
    // bubble/chip, then clears the fields so the requester can type the
    // next date. Returns true if a row was captured/updated (or there was
    // nothing pending to capture).
    function commitPending(opts) {
      opts = opts || {};
      var hasAny = dateInput.value.trim() !== '' || fromInput.value.trim() !== '' || toInput.value.trim() !== '';
      if (!hasAny) {
        if (editingIndex !== null) {
          // Nothing typed while "editing" - just cancel back out.
          cancelEdit();
          return true;
        }
        if (opts.requireAtLeastOne && rows.length === 0) {
          if (errorText) errorText.classList.add('show');
          dateInput.focus();
          return false;
        }
        return true;
      }
      if (editingIndex === null && rows.length >= DATES_NEEDED_MAX_ROWS) return true;

      dateInput.required = true;
      fromInput.required = true;
      toInput.required = true;
      validateTimeFields(fromInput, toInput);

      var wrapForm = document.getElementById('requestForm');
      var valid = dateInput.checkValidity() && fromInput.checkValidity() && toInput.checkValidity();
      if (!valid) {
        if (wrapForm) wrapForm.reportValidity();
        return false;
      }

      var newRow = { date: dateInput.value, time_from: fromInput.value, time_to: toInput.value };
      if (editingIndex !== null) {
        rows[editingIndex] = newRow;
        editingIndex = null;
      } else {
        rows.push(newRow);
      }
      clearEntryFields();
      dateInput.required = false;
      fromInput.required = false;
      toInput.required = false;
      setEditingState(false);
      render();
      return true;
    }

    addBtn.addEventListener('click', function () {
      commitPending({ requireAtLeastOne: false });
    });
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        cancelEdit();
      });
    }

    // Seed with any rows carried over from a validation-error re-render.
    rows = (DN_INITIAL_ROWS || []).map(function (r) {
      return { date: r.date || '', time_from: r.time_from || '', time_to: r.time_to || '' };
    });
    render();

    return {
      getRows: function () { return rows.slice(); },
      commitPending: commitPending,
      hasAny: function () { return rows.length > 0; }
    };
  })();

  function buildConfirmationHtml() {
    var form = document.getElementById('requestForm');
    var html = '';
    html += '<div class="confirm-section"><h3>Requester Details</h3>';
    html += row('Date Requested', form.elements['date_requested'].value);
    html += row('Name', form.elements['name'].value);
    html += row('Department / Office', departmentValue());
    html += row('Position', positionValue());
    html += '</div>';

    html += '<div class="confirm-section"><h3>Projector Setup Details</h3>';
    html += row('Purpose / Event', form.elements['purpose'].value);
    html += row('Type of Event', eventTypeValue());
    html += row('Number of Participants', form.elements['participants'].value);
    html += row('Venue / Room', form.elements['venue'].value);
    html += row('Number of Units', form.elements['projector_units'].value);
    var dnRows = dnManager.getRows();
    if (dnRows.length) {
      var dnList = dnRows.map(function (r, idx) {
        var labelPrefix = idx === 0 ? 'Date Needed' : ('Date ' + (idx + 1));
        return '<li>' + escapeHtml(labelPrefix) + ': ' + escapeHtml(formatDateForDisplay(r.date) || '\u2014') +
               ' \u00B7 ' + escapeHtml(formatTimeForDisplay(r.time_from) || '\u2014') +
               ' to ' + escapeHtml(formatTimeForDisplay(r.time_to) || '\u2014') + '</li>';
      }).join('');
      html += '<div class="confirm-row" style="display:block;"><span class="confirm-label" style="display:block;margin-bottom:6px;">Date(s) &amp; Time(s) Needed</span><ul class="confirm-list">' + dnList + '</ul></div>';
    } else {
      html += row('Date(s) & Time(s) Needed', '');
    }
    html += row('Remarks', form.elements['remarks'].value);
    html += '</div>';

    var equipment = [];
    document.querySelectorAll('.equip-checkbox:checked').forEach(function (cb) {
      var wrap = cb.closest('.equip-option');
      var labelEl = wrap.querySelector('.equip-label-text');
      var label = labelEl ? labelEl.textContent.trim() : wrap.textContent.trim();
      equipment.push(label);
    });
    var otherTyped = form.elements['equipment_other_text'] ? form.elements['equipment_other_text'].value.trim() : '';
    if (otherTyped) equipment.push(otherTyped);
    html += '<div class="confirm-section"><h3>Additional Equipment</h3>';
    html += equipment.length
      ? '<ul class="confirm-list">' + equipment.map(function (s) { return '<li>' + escapeHtml(s) + '</li>'; }).join('') + '</ul>'
      : '<p class="confirm-empty">None selected</p>';
    html += '</div>';

    var consentChecked = form.elements['consent'] && form.elements['consent'].checked;
    html += '<div class="confirm-section"><h3>Data Privacy Consent</h3>';
    html += '<p style="margin:0;font-size:0.85rem;">' + (consentChecked ? '<span style="color:var(--brand);font-weight:700;">Agreed &#10003;</span>' : '<span style="color:var(--err);font-weight:700;">Not agreed</span>') + '</p>';
    html += '</div>';
    return html;
  }

  // ---------- Confirmation popup before sending ----------
  var requestForm = document.getElementById('requestForm');
  var submitBtn = document.getElementById('submitRequestBtn');
  var confirmModal = document.getElementById('confirmModal');
  var confirmDetails = document.getElementById('confirmDetails');
  var confirmCancelBtn = document.getElementById('confirmCancelBtn');
  var confirmCloseBtn = document.getElementById('confirmCloseBtn');
  var confirmSendBtn = document.getElementById('confirmSendBtn');

  if (requestForm && submitBtn && confirmModal) {
    submitBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var departmentInputEl = document.getElementById('departmentInput');
      var departmentDropdownEl = document.getElementById('departmentDropdown');
      var departmentOtherEl = document.getElementById('department_other_input');
      var deptOk = !departmentInputEl || departmentInputEl.value.trim() !== '';
      var deptOtherOk = true;
      if (deptOk && departmentInputEl.value === DEPARTMENT_OTHER_VALUE) {
        deptOtherOk = !!(departmentOtherEl && departmentOtherEl.value.trim() !== '');
      }
      if (departmentDropdownEl) departmentDropdownEl.classList.toggle('tree-error', !deptOk);

      // Capture whatever is currently sitting in the Date/Time From/Time To
      // entry fields as one more bubble (so the requester doesn't have to
      // click "Add another date" just for a single date), and make sure at
      // least one date bubble exists.
      var dnOk = dnManager.commitPending({ requireAtLeastOne: true });

      if (!requestForm.checkValidity() || !deptOk || !deptOtherOk || !dnOk) {
        requestForm.reportValidity();
        if (!deptOk && departmentDropdownEl) {
          departmentDropdownEl.querySelector('.tree-trigger').focus();
        } else if (!deptOtherOk && departmentOtherEl) {
          departmentOtherEl.focus();
        }
        return;
      }
      confirmDetails.innerHTML = buildConfirmationHtml();
      confirmModal.classList.add('show');
    });
    function closeConfirmModal() { confirmModal.classList.remove('show'); }
    confirmCancelBtn.addEventListener('click', closeConfirmModal);
    if (confirmCloseBtn) confirmCloseBtn.addEventListener('click', closeConfirmModal);
    confirmSendBtn.addEventListener('click', function () {
      closeConfirmModal();
      var sendingOverlay = document.getElementById('sendingOverlay');
      if (sendingOverlay) sendingOverlay.classList.add('show');
      requestForm.submit();
    });
    confirmModal.addEventListener('click', function (e) {
      if (e.target === confirmModal) closeConfirmModal();
    });
  }

  // ---------- Success popup ----------
  var successModal = document.getElementById('successModal');
  var successOkBtn = document.getElementById('successOkBtn');
  var successCloseBtn = document.getElementById('successCloseBtn');
  if (successModal) {
    function closeSuccessModal() { successModal.classList.remove('show'); }
    if (successOkBtn) successOkBtn.addEventListener('click', closeSuccessModal);
    if (successCloseBtn) successCloseBtn.addEventListener('click', closeSuccessModal);
    successModal.addEventListener('click', function (e) {
      if (e.target === successModal) closeSuccessModal();
    });
    // Strip the "?success=..." query param from the address bar right away
    // (without reloading) so refreshing this page afterwards lands on a
    // plain URL and doesn't pop the success modal up again.
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (confirmModal && confirmModal.classList.contains('show')) confirmModal.classList.remove('show');
    if (successModal && successModal.classList.contains('show')) successModal.classList.remove('show');
  });
})();
</script>

</body>
</html>
<?php $conn->close(); ?>