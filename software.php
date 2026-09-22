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

// ---------- The Software Request form uses a simplified Department/Office list ----------
// Basic Education Department and Senior High School are picked directly (no
// sub-menu), College / Academic Clusters only offers the Technology Cluster,
// and "Others" reveals a free-text box so the requester can type one in.
function build_software_department_tree($conn) {
    $fullTree = load_department_tree($conn);

    $techCluster = null;
    foreach ($fullTree as $node) {
        if ($node['name'] === 'COLLEGE / ACADEMIC CLUSTERS') {
            foreach ($node['children'] ?? [] as $child) {
                if ($child['name'] === 'TECHNOLOGY CLUSTER') {
                    $techCluster = $child;
                    break;
                }
            }
            break;
        }
    }

    $collegeNode = ['name' => 'COLLEGE / ACADEMIC CLUSTERS'];
    if ($techCluster !== null) {
        $collegeNode['children'] = [$techCluster];
    }

    return [
        ['name' => 'BASIC EDUCATION DEPARTMENT'],
        ['name' => 'SENIOR HIGH SCHOOL'],
        $collegeNode,
        ['name' => DEPARTMENT_OTHER_VALUE, 'is_other' => true],
    ];
}

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$SITE_NAME = 'ITS Department';

$FORM_TITLE = 'Software Installation Request Form';

const OTHER_OPTION_VALUE = 'Other';
const DEPARTMENT_OTHER_VALUE = 'Others';

// ---------- Shared dropdown lists (loaded from the software_options table) ----------
$LICENSED_SOFTWARE_OPTIONS = load_software_options($conn, 'licensed');
$FREEWARE_OPTIONS = load_software_options($conn, 'freeware');
$DEPARTMENT_TREE_DATA = build_software_department_tree($conn);

// A row can pick any named option once, plus "Other (please specify)" - so the
// row limit is simply the option count + 1. Keeps the cap in sync with the DB.
$LICENSED_SOFTWARE_MAX_ROWS = count($LICENSED_SOFTWARE_OPTIONS) + 1; // currently 3
$FREEWARE_MAX_ROWS = count($FREEWARE_OPTIONS) + 1; // currently 14

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
// SOFTWARE INSTALLATION REQUEST - state + submit handling
// =====================================================================
$old = [
    'date_requested' => date('Y-m-d'),
    'name' => '',
    'position' => '',
    'position_other' => '',
    'computer_laboratory' => '',
    'department' => '',
    'department_other' => '',
    'licensed_software' => [''],
    'licensed_software_other' => [''],
    'freeware' => [''],
    'freeware_other' => [''],
    'supervisor_name' => '',
    'consent' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['date_requested'] = trim($_POST['date_requested'] ?? '');
    $old['name'] = trim($_POST['name'] ?? '');
    $old['position'] = trim($_POST['position'] ?? '');
    $old['position_other'] = trim($_POST['position_other'] ?? '');
    $old['computer_laboratory'] = trim($_POST['computer_laboratory'] ?? '');
    $old['department'] = trim($_POST['department'] ?? '');
    $old['department_other'] = trim($_POST['department_other'] ?? '');
    $old['licensed_software'] = array_map('trim', $_POST['licensed_software'] ?? ['']);
    $old['licensed_software_other'] = array_map('trim', $_POST['licensed_software_other'] ?? ['']);
    $old['freeware'] = array_map('trim', $_POST['freeware'] ?? ['']);
    $old['freeware_other'] = array_map('trim', $_POST['freeware_other'] ?? ['']);
    $old['supervisor_name'] = trim($_POST['supervisor_name'] ?? '');
    $old['consent'] = isset($_POST['consent']) ? 1 : 0;

    if (empty($old['licensed_software'])) $old['licensed_software'] = [''];
    if (empty($old['freeware'])) $old['freeware'] = [''];

    // Enforce the row caps server-side too, in case JS was bypassed.
    if (count($old['licensed_software']) > $LICENSED_SOFTWARE_MAX_ROWS) {
        $old['licensed_software'] = array_slice($old['licensed_software'], 0, $LICENSED_SOFTWARE_MAX_ROWS);
        $old['licensed_software_other'] = array_slice($old['licensed_software_other'], 0, $LICENSED_SOFTWARE_MAX_ROWS);
        $errors[] = 'Licensed Software can have at most ' . $LICENSED_SOFTWARE_MAX_ROWS . ' entries.';
    }
    if (count($old['freeware']) > $FREEWARE_MAX_ROWS) {
        $old['freeware'] = array_slice($old['freeware'], 0, $FREEWARE_MAX_ROWS);
        $old['freeware_other'] = array_slice($old['freeware_other'], 0, $FREEWARE_MAX_ROWS);
        $errors[] = 'Freeware can have at most ' . $FREEWARE_MAX_ROWS . ' entries.';
    }

    $resolvedPosition = resolve_position_value($old['position'], $old['position_other']);

    if ($old['name'] === '') {
        $errors[] = 'Name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s.\'-]+$/', $old['name'])) {
        $errors[] = 'Name must contain letters only (no numbers or special characters).';
    }
    if ($old['position'] === '') $errors[] = 'Position is required.';
    if ($old['position'] === OTHER_OPTION_VALUE && $old['position_other'] === '') {
        $errors[] = 'Please specify your position.';
    }
    if ($old['computer_laboratory'] === '') $errors[] = 'Computer Laboratory is required.';
    if ($old['department'] === '') $errors[] = 'Department is required.';
    if ($old['department'] === DEPARTMENT_OTHER_VALUE && $old['department_other'] === '') {
        $errors[] = 'Please specify your department / office.';
    }

    $resolvedDepartment = ($old['department'] === DEPARTMENT_OTHER_VALUE)
        ? $old['department_other']
        : $old['department'];

    if ($old['date_requested'] === '') {
        $errors[] = 'Date Requested is required.';
    } elseif ($old['date_requested'] < $MIN_DATE) {
        $errors[] = 'Date Requested cannot be a past date.';
    } elseif ($old['date_requested'] > $MAX_DATE) {
        $errors[] = 'Date Requested cannot be more than 6 months from today.';
    }

    $resolvedLicensed = resolve_other_values($old['licensed_software'], $old['licensed_software_other']);
    $resolvedFreeware = resolve_other_values($old['freeware'], $old['freeware_other']);

    foreach ($old['licensed_software'] as $i => $v) {
        if ($v === OTHER_OPTION_VALUE && trim($old['licensed_software_other'][$i] ?? '') === '') {
            $errors[] = 'Please type the name of the licensed software for row ' . ($i + 1) . ', or choose a different option.';
        }
    }
    foreach ($old['freeware'] as $i => $v) {
        if ($v === OTHER_OPTION_VALUE && trim($old['freeware_other'][$i] ?? '') === '') {
            $errors[] = 'Please type the name of the freeware for row ' . ($i + 1) . ', or choose a different option.';
        }
    }

    $pickedLicensed = array_filter($old['licensed_software'], fn($v) => $v !== '' && $v !== OTHER_OPTION_VALUE);
    if (count($pickedLicensed) !== count(array_unique($pickedLicensed))) {
        $errors[] = 'Each licensed software can only be selected once - please remove the duplicate entry.';
    }
    $pickedFreeware = array_filter($old['freeware'], fn($v) => $v !== '' && $v !== OTHER_OPTION_VALUE);
    if (count($pickedFreeware) !== count(array_unique($pickedFreeware))) {
        $errors[] = 'Each freeware can only be selected once - please remove the duplicate entry.';
    }

    $hasLicensed = count(array_filter($resolvedLicensed, fn($v) => $v !== '')) > 0;
    $hasFreeware = count(array_filter($resolvedFreeware, fn($v) => $v !== '')) > 0;
    if (!$hasLicensed && !$hasFreeware) {
        $errors[] = 'Please list at least one software package (licensed or freeware).';
    }

    if (!$old['consent']) {
        $errors[] = 'You must agree to the data privacy notice before submitting.';
    }

    if ($old['supervisor_name'] !== '' && !preg_match('/^[a-zA-Z\s.\'-]+$/', $old['supervisor_name'])) {
        $errors[] = 'Supervisor / Dept. Head / Dean name must contain letters only (no numbers or special characters).';
    }

    if (empty($errors)) {
        $licensedJson = json_encode(array_values(array_filter($resolvedLicensed, fn($v) => $v !== '')));
        $freewareJson = json_encode(array_values(array_filter($resolvedFreeware, fn($v) => $v !== '')));

        $stmt = $conn->prepare("INSERT INTO software_request
            (date_requested, name, position, computer_laboratory, department,
             licensed_software, freeware, supervisor_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'ssssssss',
            $old['date_requested'],
            $old['name'],
            $resolvedPosition,
            $old['computer_laboratory'],
            $resolvedDepartment,
            $licensedJson,
            $freewareJson,
            $old['supervisor_name']
        );
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        $success = true;

        // ---------- Record it in the admin Logs tab (under its Date Requested) ----------
        if ($id > 0) {
            log_new_request($conn, 'software', [
                'name'           => $old['name'],
                'date_requested' => $old['date_requested'],
            ]);
        }

        // ---------- Email the ITS admin inbox about this new request ----------
        $licensedList = implode(', ', json_decode($licensedJson, true) ?: []);
        $freewareList = implode(', ', json_decode($freewareJson, true) ?: []);
        $rows = mail_row('Date Requested', $old['date_requested'])
              . mail_row('Name', $old['name'])
              . mail_row('Position', $resolvedPosition)
              . mail_row('Computer Laboratory', $old['computer_laboratory'])
              . mail_row('Department / Office', $resolvedDepartment)
              . mail_row('Licensed Software', $licensedList)
              . mail_row('Freeware', $freewareList)
              . mail_row('Supervisor / Dept. Head / Dean', $old['supervisor_name']);

        // Intro sentence sits OUTSIDE the table, above the title banner.
        $introText = 'A new software installation request has been submitted through the ITS Request Portal. Details are below.';
        $htmlBody = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto 14px;">'
            . '<p style="margin:0;padding:0;font-size:14px;color:#2b2f40;line-height:1.6;">'
            . htmlspecialchars($introText, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '</div>';
        $htmlBody .= mail_wrap_table(
            'New Software Installation Request',
            $rows
        );
        $plainBody = "New Software Installation Request\n"
            . "Name: {$old['name']}\n"
            . "Department: {$resolvedDepartment}\n"
            . "Computer Laboratory: {$old['computer_laboratory']}\n"
            . "Licensed Software: {$licensedList}\n"
            . "Freeware: {$freewareList}\n";
        send_request_notification('New Software Installation Request', $htmlBody, $plainBody);

        // ---------- Redirect (Post/Redirect/Get) ----------
        // Sends the browser to a plain GET request for the success screen so
        // that refreshing the page afterwards does not re-POST and re-submit
        // (and re-email) the same request.
        header('Location: ' . basename(__FILE__) . '?success=1');
        exit;
    }
}

// =====================================================================
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

/* ---------- Cascading tree dropdown (Department / Office) ---------- */
.tree-dropdown { position: relative; width: 100%; min-width: 0; }
.tree-trigger {
  width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  padding: 9px 11px; font-size: 0.88rem; font-family: inherit; color: var(--ink);
  background: var(--white); border: 1px solid #c3c9dc; border-radius: 3px; cursor: pointer; text-align: left;
}
.tree-trigger .tree-trigger-label { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1 1 auto; min-width: 0; }
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

/* "Other" free-text inputs shown under a select (position, etc.) */
.position-other-input { width: 100%; }

/* Time From/To: free-typed field with a picker button that fills it in */
.time-input-wrap { position: relative; }
.time-input-wrap .time-manual-input { padding-right: 38px; }
.time-picker-btn {
  position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
  width: 28px; height: 28px; border: none; border-radius: 3px; background: transparent;
  color: var(--brand); cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.time-picker-btn:hover { background: var(--tint-200); }
.time-picker-hidden {
  position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
  width: 28px; height: 28px; opacity: 0; padding: 0; border: none; pointer-events: none;
}

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

  .repeat-row { gap: 8px; justify-content: space-between; }
  .repeat-row span.num { order: 1; }
  .row-remove-btn { order: 2; margin-left: 0; }
  .repeat-row select { order: 3; flex-basis: 100%; }
  .repeat-row .other-input { order: 4; flex-basis: 100%; }

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
          <p>We've received your software installation request.</p>
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


  <form method="post" action="software.php" id="requestForm">

    <div class="card">
      <div class="card-header">
        <h2>Request Details</h2>
        <p class="sub">Basic requester and location information</p>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field">
            <label>Date Requested</label>
            <input type="date" name="date_requested" value="<?= h($old['date_requested']) ?>"
                   min="<?= h($MIN_DATE) ?>" max="<?= h($MAX_DATE) ?>" required>
          </div>
          <div class="field">
            <label>Department / Office</label>
            <div class="tree-dropdown" id="departmentDropdown" data-input="departmentInput" data-other-input="department_other_input">
              <button type="button" class="tree-trigger" id="departmentTrigger">
                <span class="tree-trigger-label<?= $old['department'] === '' ? ' placeholder' : '' ?>" id="departmentTriggerLabel"><?= $old['department'] !== '' ? h($old['department']) : '-- Select department / office --' ?></span>
                <span class="tree-chevron">
                  <svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
              </button>
              <div class="tree-panel" id="departmentPanel"></div>
            </div>
            <input type="hidden" name="department" id="departmentInput" value="<?= h($old['department']) ?>">
            <input type="text" name="department_other" id="department_other_input" class="position-other-input"
                   placeholder="Please specify your department / office" value="<?= h($old['department_other']) ?>"
                   style="<?= $old['department'] === DEPARTMENT_OTHER_VALUE ? 'margin-top:8px;' : 'display:none;' ?>">
          </div>
          <div class="field">
            <label>Name</label>
            <input type="text" name="name" placeholder="Full name" value="<?= h($old['name']) ?>"
                   pattern="[a-zA-Z\s.'-]+" title="Letters only, no numbers or special characters" required>
          </div>
          <div class="field">
            <label>Position</label>
            <select name="position" id="position_select" required>
              <option value="">-- Select --</option>
              <?php foreach (['Faculty', 'Custodian', 'Other'] as $opt): ?>
                <option value="<?= h($opt) ?>" <?= $old['position'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="position_other" id="position_other_input" class="position-other-input"
                   placeholder="Please specify your position" value="<?= h($old['position_other']) ?>"
                   style="<?= $old['position'] === 'Other' ? 'margin-top:8px;' : 'display:none;' ?>">
          </div>
          <div class="field">
            <label>Computer Laboratory</label>
            <input type="text" name="computer_laboratory" placeholder="e.g. 317" value="<?= h($old['computer_laboratory']) ?>" required>
          </div>
          <div class="field">
            <label>Supervisor / Dept. Head / Dean <span class="hint">(name)</span></label>
            <input type="text" name="supervisor_name" placeholder="Printed name" value="<?= h($old['supervisor_name']) ?>"
                   pattern="[a-zA-Z\s.'-]*" title="Letters only, no numbers or special characters">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Name of Licensed Software Package</h2>
        <p class="sub">Add one entry for each package. Click &ldquo;Add software&rdquo; to add more (up to <?= (int)$LICENSED_SOFTWARE_MAX_ROWS ?>).</p>
      </div>
      <div class="card-body">
        <div class="repeat-list" id="licensed-container">
          <?php foreach ($old['licensed_software'] as $i => $val): ?>
            <div class="repeat-row">
              <span class="num"><?= $i + 1 ?></span>
              <select name="licensed_software[]" class="other-toggle">
                <?= render_options($LICENSED_SOFTWARE_OPTIONS, $val) ?>
              </select>
              <input type="text" name="licensed_software_other[]" class="other-input"
                     placeholder="Type software name"
                     value="<?= h($old['licensed_software_other'][$i] ?? '') ?>"
                     style="<?= $val === 'Other' ? '' : 'display:none;' ?>">
              <button type="button" class="row-remove-btn" title="Remove">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="add-row-btn" data-target="licensed-container" data-max="<?= (int)$LICENSED_SOFTWARE_MAX_ROWS ?>">
          <span class="plus">+</span> Add software
        </button>
        <p class="row-limit-note" id="licensed-container-limit-note" style="display:none;">Maximum of <?= (int)$LICENSED_SOFTWARE_MAX_ROWS ?> entries reached.</p>
        <template id="licensed-template">
          <select name="licensed_software[]" class="other-toggle"><?= render_options($LICENSED_SOFTWARE_OPTIONS, '') ?></select>
          <input type="text" name="licensed_software_other[]" class="other-input" placeholder="Type software name" style="display:none;">
        </template>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Name of Freeware</h2>
        <p class="sub">Add one entry for each package. Click &ldquo;Add software&rdquo; to add more (up to <?= (int)$FREEWARE_MAX_ROWS ?>).</p>
      </div>
      <div class="card-body">
        <div class="repeat-list" id="freeware-container">
          <?php foreach ($old['freeware'] as $i => $val): ?>
            <div class="repeat-row">
              <span class="num"><?= $i + 1 ?></span>
              <select name="freeware[]" class="other-toggle">
                <?= render_options($FREEWARE_OPTIONS, $val) ?>
              </select>
              <input type="text" name="freeware_other[]" class="other-input"
                     placeholder="Type software name"
                     value="<?= h($old['freeware_other'][$i] ?? '') ?>"
                     style="<?= $val === 'Other' ? '' : 'display:none;' ?>">
              <button type="button" class="row-remove-btn" title="Remove">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="add-row-btn" data-target="freeware-container" data-max="<?= (int)$FREEWARE_MAX_ROWS ?>">
          <span class="plus">+</span> Add software
        </button>
        <p class="row-limit-note" id="freeware-container-limit-note" style="display:none;">Maximum of <?= (int)$FREEWARE_MAX_ROWS ?> entries reached.</p>
        <template id="freeware-template">
          <select name="freeware[]" class="other-toggle"><?= render_options($FREEWARE_OPTIONS, '') ?></select>
          <input type="text" name="freeware_other[]" class="other-input" placeholder="Type software name" style="display:none;">
        </template>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Required Materials for Licensed Software</h2>
      </div>
      <div class="card-body">
        <ul class="materials-list">
          <li>Copy of the software media (CD, disk, etc.)</li>
          <li>Manual(s) / Software License Agreement</li>
          <li>Any userids and passwords needed to access or use software / verification of licenses</li>
        </ul>
      </div>
    </div>

    <div class="consent-box">
      By submitting this software installation request form, you hereby allow / authorize UPHD Calamba campus and their
      authorized personnel to gather and process your personal information (name, address, contact number, email
      address, and picture, if applicable) for documentation and specifically for use by the end-user to request for
      software. End-users do not have the authority to install software or applications on their own. All information
      gathered from this activity will be kept secure and confidential for a period of five (5) years from the
      collection date and will be destroyed by means of shredding and/or file deletion after the prescribed
      retention period in accordance with Republic Act 10173, the Data Privacy Act of 2012 of the Philippines.
      <label>
        <input type="checkbox" name="consent" value="1" <?= $old['consent'] ? 'checked' : '' ?> required> I have read and agree to the above.
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

  // ---------- Software form: repeatable rows ----------
  // Prevent picking the same software twice across rows in the same
  // container (Licensed Software / Freeware) - "Other" stays selectable
  // multiple times since each row's typed text can differ.
  function syncDropdownAvailability(container) {
    var selects = Array.prototype.slice.call(container.querySelectorAll('.other-toggle'));
    var selectedElsewhere = selects.map(function (s) { return s.value; })
      .filter(function (v) { return v && v !== 'Other'; });
    selects.forEach(function (select) {
      Array.prototype.forEach.call(select.options, function (opt) {
        if (opt.value === '' || opt.value === 'Other') { opt.disabled = false; return; }
        opt.disabled = selectedElsewhere.indexOf(opt.value) !== -1 && select.value !== opt.value;
      });
    });
  }
  function renumber(container) {
    var rows = container.querySelectorAll('.repeat-row');
    rows.forEach(function (row, idx) {
      var num = row.querySelector('.num');
      if (num) num.textContent = idx + 1;
      var removeBtn = row.querySelector('.row-remove-btn');
      if (removeBtn) removeBtn.disabled = rows.length <= 1;
    });
    var addBtn = document.querySelector('.add-row-btn[data-target="' + container.id + '"]');
    var note = document.getElementById(container.id + '-limit-note');
    if (addBtn) {
      var max = parseInt(addBtn.getAttribute('data-max'), 10) || Infinity;
      var atLimit = rows.length >= max;
      addBtn.disabled = atLimit;
      if (note) note.style.display = atLimit ? '' : 'none';
    }
  }
  function wireRemove(row, container) {
    var btn = row.querySelector('.row-remove-btn');
    btn.addEventListener('click', function () {
      if (container.querySelectorAll('.repeat-row').length <= 1) return;
      row.remove();
      renumber(container);
      syncDropdownAvailability(container);
    });
  }
  function wireOtherToggle(row) {
    var select = row.querySelector('.other-toggle');
    var otherInput = row.querySelector('.other-input');
    if (!select || !otherInput) return;
    var container = row.closest('.repeat-list');
    var sync = function () {
      var isOther = select.value === 'Other';
      otherInput.style.display = isOther ? '' : 'none';
      otherInput.required = isOther;
      if (!isOther) otherInput.value = '';
      if (container) syncDropdownAvailability(container);
    };
    select.addEventListener('change', sync);
    sync();
  }
  document.querySelectorAll('.repeat-list').forEach(function (container) {
    container.querySelectorAll('.repeat-row').forEach(function (row) {
      wireRemove(row, container);
      wireOtherToggle(row);
    });
    renumber(container);
    syncDropdownAvailability(container);
  });
  document.querySelectorAll('.add-row-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-target');
      var container = document.getElementById(targetId);
      var template = document.getElementById(targetId.replace('-container', '-template'));
      if (!container || !template) return;
      var max = parseInt(btn.getAttribute('data-max'), 10) || Infinity;
      if (container.querySelectorAll('.repeat-row').length >= max) return;
      var row = document.createElement('div');
      row.className = 'repeat-row';
      var num = document.createElement('span');
      num.className = 'num';
      row.appendChild(num);
      var fragment = template.content.cloneNode(true);
      row.appendChild(fragment);
      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'row-remove-btn';
      removeBtn.title = 'Remove';
      removeBtn.innerHTML = '&times;';
      row.appendChild(removeBtn);
      container.appendChild(row);
      wireRemove(row, container);
      wireOtherToggle(row);
      renumber(container);
      syncDropdownAvailability(container);
    });
  });

  function collectSoftwareRows(containerId) {
    var container = document.getElementById(containerId);
    if (!container) return [];
    var items = [];
    container.querySelectorAll('.repeat-row').forEach(function (rowEl) {
      var select = rowEl.querySelector('.other-toggle');
      var otherInput = rowEl.querySelector('.other-input');
      if (!select) return;
      var val = select.value;
      if (!val) return;
      if (val === 'Other') {
        var typed = otherInput ? otherInput.value.trim() : '';
        if (typed) items.push(typed);
      } else {
        items.push(val);
      }
    });
    return items;
  }

  function buildConfirmationHtml() {
    var form = document.getElementById('requestForm');
    var html = '';
    html += '<div class="confirm-section"><h3>Request Details</h3>';
    html += row('Date Requested', form.elements['date_requested'].value);
    html += row('Department / Office', departmentValue());
    html += row('Name', form.elements['name'].value);
    html += row('Position', positionValue());
    html += row('Computer Laboratory', form.elements['computer_laboratory'].value);
    html += row('Supervisor / Dept. Head / Dean', form.elements['supervisor_name'].value);
    html += '</div>';

    var licensed = collectSoftwareRows('licensed-container');
    html += '<div class="confirm-section"><h3>Licensed Software</h3>';
    html += licensed.length
      ? '<ul class="confirm-list">' + licensed.map(function (s) { return '<li>' + escapeHtml(s) + '</li>'; }).join('') + '</ul>'
      : '<p class="confirm-empty">None specified</p>';
    html += '</div>';

    var freeware = collectSoftwareRows('freeware-container');
    html += '<div class="confirm-section"><h3>Freeware</h3>';
    html += freeware.length
      ? '<ul class="confirm-list">' + freeware.map(function (s) { return '<li>' + escapeHtml(s) + '</li>'; }).join('') + '</ul>'
      : '<p class="confirm-empty">None specified</p>';
    html += '</div>';

    var consentChecked = form.elements['consent'] && form.elements['consent'].checked;
    html += '<div class="confirm-section"><h3>Data Privacy Consent</h3>';
    html += '<p style="margin:0;font-size:0.85rem;">' + (consentChecked ? '<span style="color:var(--brand);font-weight:700;">Agreed &#10003;</span>' : '<span style="color:var(--err);font-weight:700;">Not agreed</span>') + '</p>';
    html += '</div>';
    return html;
  }
  // ---------- Restrict Name to letters only ----------
  var nameInput = document.querySelector('input[name="name"]');
  if (nameInput) {
    nameInput.addEventListener('input', function () {
      nameInput.value = nameInput.value.replace(/[^a-zA-Z\s.'-]/g, '');
    });
  }

  // ---------- Restrict Supervisor / Dept. Head / Dean name to letters only ----------
  var supervisorInput = document.querySelector('input[name="supervisor_name"]');
  if (supervisorInput) {
    supervisorInput.addEventListener('input', function () {
      supervisorInput.value = supervisorInput.value.replace(/[^a-zA-Z\s.'-]/g, '');
    });
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

      if (!requestForm.checkValidity() || !deptOk || !deptOtherOk) {
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