<?php
require_once 'db_config.php';
require_once 'settings.php';
require_once 'mailer.php';

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

function resolve_position_value($position, $positionOther) {
    if ($position === OTHER_OPTION_VALUE) {
        return trim($positionOther);
    }
    return $position;
}

$SITE_NAME = 'ITS Department';
$FORM_TITLE = 'IT Incident Report Form';

const OTHER_OPTION_VALUE = 'Other';
const DEPARTMENT_OTHER_VALUE = 'Others';

$DEPARTMENT_TREE_DATA = load_department_tree($conn);
$DEPARTMENT_TREE_DATA[] = ['name' => DEPARTMENT_OTHER_VALUE, 'is_other' => true];

$INCIDENT_CATEGORY_OPTIONS = [
    'Hardware Failure',
    'Software / System Error',
    'Network / Internet Issue',
    'Security Incident (unauthorized access, malware, phishing)',
    'Data Loss / Corruption',
    'Account / Access Issue',
    'Policy Violation',
    'Other',
];

$AFFECTED_SYSTEM_OPTIONS = [
    'workstation' => 'Workstation / PC',
    'server'      => 'Server',
    'network'     => 'Network / Wi-Fi',
    'printer'     => 'Printer',
    'software'    => 'Software / Application',
    'email'       => 'Email / Account',
    'website'     => 'Website / Portal',
    'other'       => 'Other',
];

$STATUS_OPTIONS = ['Ongoing', 'Resolved', 'Under Investigation'];

$errors = [];
$success = null;
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = true;
}

$MIN_DATE = date('Y-m-d');
$TODAY = date('Y-m-d');

// =====================================================================
// IT INCIDENT REPORT - state + submit handling
// =====================================================================
$old = [
    'date_reported' => date('Y-m-d'),
    'name' => '',
    'position' => '',
    'position_other' => '',
    'department' => '',
    'department_other' => '',
    'incident_date' => '',
    'incident_time' => '',
    'location' => '',
    'category' => '',
    'affected_systems' => [],
    'affected_systems_other_text' => '',
    'description' => '',
    'action_taken' => '',
    'status' => '',
    'remarks' => '',
    'consent' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['date_reported'] = trim($_POST['date_reported'] ?? '');
    $old['name'] = trim($_POST['name'] ?? '');
    $old['position'] = trim($_POST['position'] ?? '');
    $old['position_other'] = trim($_POST['position_other'] ?? '');
    $old['department'] = trim($_POST['department'] ?? '');
    $old['department_other'] = trim($_POST['department_other'] ?? '');
    $old['incident_date'] = trim($_POST['incident_date'] ?? '');
    $old['incident_time'] = trim($_POST['incident_time'] ?? '');
    $old['location'] = trim($_POST['location'] ?? '');
    $old['category'] = trim($_POST['category'] ?? '');
    $old['affected_systems'] = array_intersect($_POST['affected_systems'] ?? [], array_keys($AFFECTED_SYSTEM_OPTIONS));
    $old['affected_systems_other_text'] = trim($_POST['affected_systems_other_text'] ?? '');
    if ($old['affected_systems_other_text'] !== '') {
        $old['affected_systems'][] = 'other';
    }
    $old['description'] = trim($_POST['description'] ?? '');
    $old['action_taken'] = trim($_POST['action_taken'] ?? '');
    $old['status'] = trim($_POST['status'] ?? '');
    $old['remarks'] = trim($_POST['remarks'] ?? '');
    $old['consent'] = isset($_POST['consent']) ? 1 : 0;

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
    if ($old['department'] === '') $errors[] = 'Department is required.';
    if ($old['department'] === DEPARTMENT_OTHER_VALUE && $old['department_other'] === '') {
        $errors[] = 'Please specify your department / office.';
    }
    $resolvedDepartment = ($old['department'] === DEPARTMENT_OTHER_VALUE)
        ? $old['department_other']
        : $old['department'];

    if ($old['incident_date'] === '') $errors[] = 'Date of Incident is required.';
    if ($old['incident_date'] !== '' && $old['incident_date'] > $TODAY) {
        $errors[] = 'Date of Incident cannot be in the future.';
    }
    if ($old['location'] === '') $errors[] = 'Location / Venue is required.';
    if ($old['category'] === '') $errors[] = 'Incident Category is required.';
    if ($old['description'] === '') $errors[] = 'Description of Incident is required.';
    if ($old['status'] === '' || !in_array($old['status'], $STATUS_OPTIONS, true)) {
        $errors[] = 'Current Status is required.';
    }
    if (!$old['consent']) $errors[] = 'You must agree to the data privacy consent before submitting.';

    if (empty($errors)) {
        $systemsPayload = [];
        foreach ($old['affected_systems'] as $sysKey) {
            $sysLabel = $AFFECTED_SYSTEM_OPTIONS[$sysKey] ?? $sysKey;
            if ($sysKey === 'other') {
                $sysLabel = $old['affected_systems_other_text'] !== '' ? $old['affected_systems_other_text'] : 'Other';
            }
            $systemsPayload[] = $sysLabel;
        }
        $systemsJson = json_encode($systemsPayload);

        $stmt = $conn->prepare("INSERT INTO incident_reports
            (date_reported, name, position, department,
             incident_date, incident_time, location, category,
             affected_systems, description, action_taken, status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'sssssssssssss',
            $old['date_reported'],
            $old['name'],
            $resolvedPosition,
            $resolvedDepartment,
            $old['incident_date'],
            $old['incident_time'],
            $old['location'],
            $old['category'],
            $systemsJson,
            $old['description'],
            $old['action_taken'],
            $old['status'],
            $old['remarks']
        );
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        $success = true;

        // ---------- Record it in the admin Logs tab ----------
        if ($newId > 0) {
            try {
                log_new_request($conn, 'incident', [
                    'name'          => $old['name'],
                    'category'      => $old['category'],
                    'location'      => $old['location'],
                    'date_reported' => $old['date_reported'],
                    'incident_date' => $old['incident_date'],
                    'incident_time' => $old['incident_time'],
                    'status'        => $old['status'],
                ]);
            } catch (Throwable $t) {
                error_log('incident: could not write activity log - ' . $t->getMessage());
            }
        }

        // ---------- Email the ITS admin inbox about this new incident ----------
        $categoryEsc = htmlspecialchars($old['category'], ENT_QUOTES, 'UTF-8');
        $rows = mail_row_raw('Incident Category', '<strong>' . $categoryEsc . '</strong>')
              . mail_row('Date Reported', $old['date_reported'])
              . mail_row('Reported By', $old['name'])
              . mail_row('Position', $resolvedPosition)
              . mail_row('Department / Office', $resolvedDepartment)
              . mail_row('Date & Time of Incident', $old['incident_date'] . ' ' . $old['incident_time'])
              . mail_row('Location / Venue', $old['location'])
              . mail_row('Systems / Equipment Affected', implode(', ', $systemsPayload))
              . mail_row('Description of Incident', $old['description'])
              . mail_row('Immediate Action Taken', $old['action_taken'])
              . mail_row('Current Status', $old['status'])
              . mail_row('Remarks', $old['remarks']);

        $introHtml = 'A new <strong>' . $categoryEsc . '</strong> incident '
            . 'has been reported through the ITS Request Portal. Details are below.';

        $htmlBody = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto 14px;">'
            . '<p style="margin:0;padding:0;font-size:14px;color:#2b2f40;line-height:1.6;">'
            . $introHtml
            . '</p>'
            . '</div>';
        $htmlBody .= mail_wrap_table('New IT Incident Report', $rows);

        $plainBody = "New IT Incident Report\n"
            . "Category: {$old['category']}\n"
            . "Reported By: {$old['name']}\n"
            . "Department: {$resolvedDepartment}\n"
            . "Date & Time of Incident: {$old['incident_date']} {$old['incident_time']}\n"
            . "Location: {$old['location']}\n"
            . "Status: {$old['status']}\n";
        try {
            send_request_notification('New IT Incident Report - ' . $old['category'], $htmlBody, $plainBody);
        } catch (Throwable $t) {
            error_log('incident: notification mail failed for report #' . $newId . ' - ' . $t->getMessage());
        }

        // ---------- Redirect (Post/Redirect/Get) ----------
        header('Location: ' . basename(__FILE__) . '?success=1');
        exit;
    }
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
  --brand-darker: #172a5e;
  --accent: #8fa3e0;
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
  --sev-critical: #a4262c;
  --sev-critical-bg: #fde7e9;
  --sev-high: #b3541e;
  --sev-high-bg: #fbe7d8;
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
  background: var(--bg); color: var(--ink); margin: 0; font-size: 14px;
  min-height: 100vh; display: flex; flex-direction: column;
}

/* ---------- Header ---------- */
.ribbon { background: var(--white); border-bottom: 4px solid var(--brand); box-shadow: 0 2px 10px rgba(20,35,90,0.08); }
.ribbon-inner { max-width: var(--site-max); margin: 0 auto; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.brand { display: flex; align-items: center; gap: 16px; }
.brand-badge { flex: none; height: 68px; display: flex; align-items: center; justify-content: center; }
.brand-badge img { height: 100%; width: auto; display: block; }
.brand-text .eyebrow { margin: 0; font-size: 0.72rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--brand); font-weight: 700; }
.brand-text h1 { margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: 0.3px; color: var(--ink); text-transform: uppercase; line-height: 1.15; }
.brand-text .subtitle { margin: 3px 0 0; font-size: 0.85rem; color: var(--brand); font-weight: 600; }
.link-btn {
  display: inline-flex; align-items: center; gap: 7px; color: var(--brand); text-decoration: none;
  font-size: 0.8rem; font-weight: 700; border: 1.5px solid var(--brand); background: transparent;
  padding: 8px 16px 8px 12px; border-radius: 24px;
  transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
}
.link-btn svg { width: 15px; height: 15px; transition: transform .18s ease; }
.link-btn:hover { background: var(--brand); color: var(--white); transform: translateX(-2px); }
.link-btn:hover svg { transform: translateX(-2px); }

/* ---------- Document canvas ---------- */
.container { max-width: var(--site-max); margin: 26px auto 60px; padding: 0 20px; }
.card { background: var(--white); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow-1); padding: 0; margin-bottom: 18px; overflow: hidden; }
.card-header { padding: 13px 22px; border-bottom: 1px solid var(--line); border-left: 4px solid var(--brand); background: linear-gradient(180deg, #fafbff 0%, #f1f4fc 100%); }
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
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--focus); box-shadow: 0 0 0 3px rgba(43,74,158,0.15); }
input:required:invalid, select:required:invalid { box-shadow: none; }

/* ---------- Department tree dropdown ---------- */
.tree-dropdown { position: relative; }
.tree-dropdown.tree-error .tree-trigger { border-color: var(--err); }
.tree-trigger {
  width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 9px 11px; border: 1px solid #c3c9dc; border-radius: 3px; background: var(--white);
  font-size: 0.88rem; font-family: inherit; color: var(--ink); cursor: pointer; text-align: left;
}
.tree-dropdown.open .tree-trigger { border-color: var(--focus); box-shadow: 0 0 0 3px rgba(43,74,158,0.15); }
.tree-trigger-label.placeholder { color: #8891a8; }
.tree-chevron { flex: none; color: var(--brand); transition: transform .15s ease; }
.tree-dropdown.open .tree-chevron { transform: rotate(180deg); }
.tree-panel {
  display: none; position: fixed; z-index: 1200; background: var(--white); border: 1px solid var(--line);
  border-radius: 6px; box-shadow: var(--shadow-2); overflow-y: auto; padding: 6px;
}
.tree-panel.tree-panel-open { display: block; }
.tree-panel ul { list-style: none; margin: 0; padding: 0; }
.tree-node > .tree-row { display: flex; align-items: center; gap: 6px; padding: 7px 8px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: var(--ink); }
.tree-node > .tree-row:hover { background: var(--tint-50); }
.tree-depth-0 > .tree-row { font-weight: 700; color: var(--brand-dark); }
.tree-depth-1 > .tree-row { padding-left: 24px; font-weight: 600; }
.tree-depth-2 > .tree-row { padding-left: 42px; font-weight: 400; color: #2b2f40; }
.tree-caret { flex: none; width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; color: #7a8096; transition: transform .15s ease; }
.tree-node.expanded > .tree-row .tree-caret { transform: rotate(90deg); }
.tree-leaf .tree-caret { visibility: hidden; }
.tree-children { display: none; }
.tree-node.expanded > .tree-children { display: block; }
.tree-node.selected > .tree-row { color: var(--brand); font-weight: 700; background: var(--tint-100); }

.position-other-input { width: 100%; }

/* checkbox grid (affected systems) */
.equip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.equip-option { display: flex; align-items: center; gap: 10px; border: 1px solid var(--line); border-radius: 6px; padding: 11px 13px; cursor: pointer; transition: border-color .12s ease, background .12s ease; font-size: 0.85rem; font-weight: 600; color: var(--ink); }
.equip-option:hover { border-color: var(--accent-soft); background: var(--tint-50); }
.equip-option input { accent-color: var(--brand); width: 16px; height: 16px; flex: none; }
.equip-option.checked { border-color: var(--brand); background: var(--tint-100); color: var(--brand-dark); }
.equip-label-text { flex: 1; }
.equip-other-field { margin-top: 14px; }

.consent-box { background: var(--tint-50); border: 1px solid var(--line); border-left: 4px solid var(--brand); border-radius: 3px; padding: 16px 18px; font-size: 0.78rem; color: #2a2f45; line-height: 1.55; }
.consent-box label { display: flex; gap: 9px; margin-top: 12px; font-weight: 600; color: var(--brand-dark); font-size: 0.82rem; }
.consent-box input { accent-color: var(--brand); width: 15px; height: 15px; margin-top: 2px; flex: none; }

.actions-bar { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 6px 0 4px; flex-wrap: wrap; }
.btn { display: inline-flex; align-items: center; gap: 8px; justify-content: center; background: var(--brand); color: var(--white); border: 1px solid var(--brand-darker); padding: 11px 26px; border-radius: 3px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; box-shadow: var(--shadow-1); }
.btn:hover { background: var(--brand-dark); }
.btn .glyph { font-size: 0.9rem; }

.error-box { background: var(--err-bg); border: 1px solid #f1b7bb; border-left: 4px solid var(--err); color: var(--err); padding: 12px 16px; border-radius: 3px; margin-bottom: 16px; font-size: 0.84rem; }
.error-box strong { color: #7a1a1f; }
.error-box ul { margin: 6px 0 0 18px; padding: 0; }

.center { text-align: center; }
.muted { color: var(--muted); }

footer.page-footer { text-align: center; color: rgba(255,255,255,0.88); font-size: 0.78rem; background: var(--brand); padding: 16px 20px; margin-top: auto; }

/* ---------- Modal popups ---------- */
@keyframes modalPop { from { opacity: 0; transform: translateY(18px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes overlayFade { from { opacity: 0; } to { opacity: 1; } }
@keyframes checkPop { 0% { transform: scale(0); opacity: 0; } 60% { transform: scale(1.15); opacity: 1; } 100% { transform: scale(1); } }
.modal-overlay { position: fixed; inset: 0; z-index: 1000; background: rgba(12,20,55, 0.58); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .2s ease, visibility 0s linear .2s; }
.modal-overlay.show { display: flex; opacity: 1; visibility: visible; pointer-events: auto; transition: opacity .2s ease; animation: overlayFade .2s ease; }

@keyframes spin { to { transform: rotate(360deg); } }
.sending-overlay { position: fixed; inset: 0; z-index: 2000; background: rgba(12,20,55, 0.72); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; padding: 20px; opacity: 0; animation: overlayFade .2s ease forwards; }
.sending-overlay.show { display: flex; }
.sending-box { background: var(--white); border-radius: 14px; padding: 34px 40px; text-align: center; max-width: 320px; box-shadow: 0 24px 60px rgba(15,25,70, 0.35), 0 4px 14px rgba(0,0,0,0.12); }
.sending-spinner { display: inline-block; width: 42px; height: 42px; border-radius: 50%; border: 4px solid var(--tint-200); border-top-color: var(--brand); animation: spin .8s linear infinite; }
.sending-text { margin: 16px 0 4px; font-size: 0.95rem; font-weight: 700; color: var(--brand-dark); }
.sending-subtext { margin: 0; font-size: 0.8rem; color: var(--muted); }

.modal-box { background: var(--white); border-radius: 14px; width: clamp(300px, 55vw, 720px); max-width: 94vw; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(15,25,70, 0.35), 0 4px 14px rgba(0,0,0,0.12); overflow: hidden; }
.modal-box.success-modal { width: clamp(260px, 38vw, 480px); max-width: 94vw; }
.modal-overlay.show .modal-box { animation: modalPop .28s cubic-bezier(.2, .9, .3, 1.2); }
.modal-box .modal-header { position: relative; padding: 22px 26px 20px; background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%); }
.modal-box .modal-header::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: linear-gradient(90deg, var(--accent) 0%, var(--accent-soft) 100%); }
.modal-box .modal-header h2 { margin: 0; font-size: 1.08rem; font-weight: 800; color: var(--white); letter-spacing: 0.2px; }
.modal-box .modal-header p { margin: 5px 0 0; font-size: 0.8rem; color: rgba(255,255,255,0.85); }
.modal-close-btn { position: absolute; top: 14px; right: 16px; width: 30px; height: 30px; border-radius: 50%; border: none; background: rgba(255,255,255,0.14); color: var(--white); font-size: 1rem; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s ease, transform .15s ease; }
.modal-close-btn:hover { background: rgba(255,255,255,0.26); transform: rotate(90deg); }
.modal-box .modal-body { padding: 20px 26px; overflow-y: auto; background: #fafbff; }
.modal-box .modal-body::-webkit-scrollbar { width: 8px; }
.modal-box .modal-body::-webkit-scrollbar-track { background: transparent; }
.modal-box .modal-body::-webkit-scrollbar-thumb { background: #d3d9ea; border-radius: 8px; }
.modal-box .modal-actions { padding: 16px 26px; border-top: 1px solid var(--line); background: var(--white); display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
.btn-secondary { background: var(--white); color: var(--brand-dark); border: 1px solid var(--line); padding: 10px 20px; border-radius: 24px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background .15s ease, border-color .15s ease, transform .1s ease; }
.btn-secondary:hover { background: #f1f4fc; border-color: #c3c9dc; }
.btn-secondary:active { transform: scale(.97); }
.actions-bar .btn, .modal-actions .btn { border-radius: 24px; transition: transform .1s ease, box-shadow .15s ease, background .15s ease; }
.actions-bar .btn:active, .modal-actions .btn:active { transform: scale(.97); }

.confirm-section { margin-bottom: 16px; background: var(--white); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; }
.confirm-section:last-child { margin-bottom: 0; }
.confirm-section h3 { margin: 0 0 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; color: var(--brand); display: flex; align-items: center; gap: 8px; }
.confirm-section h3::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--brand); box-shadow: 0 0 0 3px var(--ribbon-bg), 0 0 0 4px var(--brand); flex: none; margin-left: 2px; }
.confirm-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px dashed var(--line); font-size: 0.85rem; flex-wrap: wrap; }
.confirm-row:last-child { border-bottom: none; }
.confirm-label { color: var(--muted); font-weight: 600; flex: none; width: 44%; }
.confirm-value { color: var(--ink); font-weight: 500; text-align: right; flex: 1; word-break: break-word; }
.confirm-list { margin: 0; padding-left: 20px; font-size: 0.85rem; line-height: 1.7; }
.confirm-empty { margin: 0; font-size: 0.82rem; color: var(--muted); font-style: italic; }

.success-modal .modal-header { background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%); }
.success-modal .modal-header::after { background: linear-gradient(90deg, var(--accent) 0%, var(--accent-soft) 100%); }
.success-modal .modal-body { text-align: center; padding: 30px 26px 26px; }
.success-modal .success-icon { width: 66px; height: 66px; border-radius: 50%; background: var(--tint-200); color: var(--brand); display: flex; align-items: center; justify-content: center; margin: -14px auto 16px; border: 4px solid var(--white); box-shadow: 0 4px 14px rgba(0,0,0,0.15); animation: checkPop .4s ease .1s both; }
.success-modal .success-icon svg { width: 30px; height: 30px; display: block; }
.success-modal .success-title { margin: 0 0 4px; font-size: 1.02rem; font-weight: 700; color: var(--ink); }
.success-modal .muted { margin: 0; }

@media (min-width: 1400px) { :root { --site-max: 1120px; } }
@media (max-width: 900px) { .ribbon-inner { padding: 14px 18px; } .container { padding: 0 16px; } .card-body { padding: 18px; } }
@media (max-width: 700px) {
  .form-grid { grid-template-columns: 1fr; }
  .field.span-2 { grid-column: span 1; }
  .brand-badge { height: 56px; }
  .brand-text h1 { font-size: 1.12rem; }
  .brand-text .subtitle { font-size: 0.78rem; }
  .modal-box .modal-header { padding: 18px 20px 16px; }
  .modal-box .modal-body { padding: 16px 20px; }
  .modal-box .modal-actions { padding: 14px 20px; }
}
@media (max-width: 560px) {
  body { font-size: 13.5px; }
  .ribbon-inner { padding: 12px 14px; gap: 10px; }
  .brand { gap: 10px; width: 100%; }
  .brand-badge { height: 44px; }
  .brand-text .eyebrow { font-size: 0.62rem; }
  .brand-text h1 { font-size: 0.98rem; }
  .brand-text .subtitle { font-size: 0.72rem; }
  .link-btn { font-size: 0.75rem; padding: 7px 13px 7px 10px; width: 100%; justify-content: center; }
  .container { padding: 0 12px; margin: 18px auto 40px; }
  .card { border-radius: 6px; }
  .card-header { padding: 11px 14px; }
  .card-header h2 { font-size: 0.88rem; }
  .card-body { padding: 14px; }
  .equip-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
  .actions-bar .btn { width: 100%; }
  .modal-actions { flex-direction: column-reverse; }
  .modal-actions .btn, .modal-actions .btn-secondary { width: 100%; }
  .confirm-label { width: 50%; }
  footer.page-footer { padding: 14px 14px; font-size: 0.72rem; }
}
@media (max-width: 380px) {
  .brand-text h1 { font-size: 0.86rem; }
  .confirm-row { flex-direction: column; gap: 2px; }
  .confirm-label, .confirm-value { width: 100%; text-align: left; }
  .equip-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="sending-overlay" id="sendingOverlay" role="status" aria-live="polite">
  <div class="sending-box">
    <span class="sending-spinner" aria-hidden="true"></span>
    <p class="sending-text">Sending your report&hellip;</p>
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
          <h2>Report Submitted</h2>
          <p>We've received your IT incident report.</p>
        </div>
        <div class="modal-body">
          <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <p class="success-title">Sent successfully!</p>
          <p class="muted">The ITS team has been notified and will follow up as needed.</p>
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

  <form method="post" action="incident_report.php" id="requestForm">

    <div class="card">
      <div class="card-header">
        <h2>Reporter Details</h2>
        <p class="sub">Who is filing this incident report</p>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field">
            <label>Date Reported</label>
            <input type="date" name="date_reported" value="<?= h($old['date_reported']) ?>" max="<?= h($TODAY) ?>" required>
          </div>
          <div class="field">
            <label>Name</label>
            <input type="text" name="name" placeholder="Full name" value="<?= h($old['name']) ?>"
                   pattern="[a-zA-Z\s.'-]+" title="Letters only, no numbers or special characters" required>
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
            <label>Position</label>
            <select name="position" id="position_select" required>
              <option value="">-- Select --</option>
              <?php foreach (['Faculty', 'Staff', 'Student', 'Heads / Dean', 'Other'] as $opt): ?>
                <option value="<?= h($opt) ?>" <?= $old['position'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="position_other" id="position_other_input" class="position-other-input"
                   placeholder="Please specify your position" value="<?= h($old['position_other']) ?>"
                   style="<?= $old['position'] === 'Other' ? 'margin-top:8px;' : 'display:none;' ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Incident Details</h2>
        <p class="sub">What happened, where, and when</p>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field">
            <label>Date of Incident</label>
            <input type="date" name="incident_date" value="<?= h($old['incident_date']) ?>" max="<?= h($TODAY) ?>" required>
          </div>
          <div class="field">
            <label>Time of Incident <span class="hint">(optional)</span></label>
            <input type="time" name="incident_time" value="<?= h($old['incident_time']) ?>">
          </div>
          <div class="field span-2">
            <label>Location / Venue <span class="hint">(room, office, building)</span></label>
            <input type="text" name="location" placeholder="" value="<?= h($old['location']) ?>" required>
          </div>
          <div class="field">
            <label>Incident Category</label>
            <select name="category" id="category_select" required>
              <option value="">-- Select --</option>
              <?php foreach ($INCIDENT_CATEGORY_OPTIONS as $opt): ?>
                <option value="<?= h($opt) ?>" <?= $old['category'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field span-2">
            <label>Description of Incident</label>
            <textarea name="description" placeholder="Describe what happened, what you observed, and any error messages" required><?= h($old['description']) ?></textarea>
          </div>
          <div class="field span-2">
            <label>Immediate Action Taken <span class="hint">(optional)</span></label>
            <textarea name="action_taken" placeholder="Any steps already taken before this report was filed"><?= h($old['action_taken']) ?></textarea>
          </div>
          <div class="field">
            <label>Current Status</label>
            <select name="status" id="status_select" required>
              <option value="">-- Select --</option>
              <?php foreach ($STATUS_OPTIONS as $opt): ?>
                <option value="<?= h($opt) ?>" <?= $old['status'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Systems / Equipment Affected</h2>
        <p class="sub">Select all that apply</p>
      </div>
      <div class="card-body">
        <div class="equip-grid">
          <?php foreach ($AFFECTED_SYSTEM_OPTIONS as $key => $label): if ($key === 'other') continue; ?>
            <?php $isChecked = in_array($key, $old['affected_systems']); ?>
            <label class="equip-option <?= $isChecked ? 'checked' : '' ?>">
              <input type="checkbox" name="affected_systems[]" value="<?= h($key) ?>" class="equip-checkbox" <?= $isChecked ? 'checked' : '' ?>>
              <span class="equip-label-text"><?= h($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="field equip-other-field">
          <label>Other</label>
          <input type="text" name="affected_systems_other_text" class="equip-other-text"
                 placeholder="Please specify other system / equipment affected"
                 value="<?= h($old['affected_systems_other_text']) ?>">
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Additional Information</h2>
        <p class="sub">Anything else the ITS team should know</p>
      </div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field span-2">
            <label>Remarks <span class="hint">(optional)</span></label>
            <textarea name="remarks" placeholder="Any other details relevant to this incident"><?= h($old['remarks']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="consent-box">
      By submitting this IT Incident Report form, you hereby allow / authorize UPHSD Calamba campus and their
      authorized personnel to gather and process your personal information (department and location details)
      for documentation and specifically for the investigation, resolution, and monitoring of ITS-related incidents.
      All information gathered from this activity will be kept secure and confidential for a period of five (5) years
      from the collection date and will be destroyed by means of shredding and/or file deletion after the prescribed
      retention period in accordance with Republic Act 10173, the Data Privacy Act of 2012 of the Philippines.
      <label>
        <input type="checkbox" name="consent" value="1" <?= $old['consent'] ? 'checked' : '' ?> required> I have read and agree to the above.
      </label>
    </div>

    <div class="actions-bar">
      <button type="submit" class="btn" id="submitRequestBtn"><span class="glyph">&#10003;</span> Submit Report</button>
    </div>
  </form>

  <div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
      <div class="modal-header">
        <button type="button" class="modal-close-btn" id="confirmCloseBtn" aria-label="Close">&times;</button>
        <h2>Confirm Your Report</h2>
        <p>Please review the details before sending.</p>
      </div>
      <div class="modal-body" id="confirmDetails"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="confirmCancelBtn">Go Back</button>
        <button type="button" class="btn" id="confirmSendBtn"><span class="glyph">&#10003;</span> Confirm &amp; Send</button>
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

    document.body.appendChild(panel);

    function positionPanel() {
      var rect = trigger.getBoundingClientRect();
      var vw = document.documentElement.clientWidth;
      var vh = document.documentElement.clientHeight;
      var width = rect.width;
      var left = rect.left;
      if (left + width > vw - 8) left = Math.max(8, vw - width - 8);
      panel.style.width = width + 'px';
      panel.style.left = left + 'px';
      var top = rect.bottom + 4;
      var maxHeight = Math.min(260, vh - top - 12);
      if (maxHeight < 140 && rect.top > vh - rect.bottom) {
        maxHeight = Math.min(260, rect.top - 12);
        panel.style.top = Math.max(8, rect.top - maxHeight - 4) + 'px';
      } else {
        panel.style.top = top + 'px';
      }
      panel.style.maxHeight = Math.max(120, maxHeight) + 'px';
    }

    function openDropdown() { positionPanel(); root.classList.add('open'); panel.classList.add('tree-panel-open'); }
    function closeDropdown() { root.classList.remove('open'); panel.classList.remove('tree-panel-open'); }
    function toggleDropdown() {
      if (panel.classList.contains('tree-panel-open')) closeDropdown(); else openDropdown();
    }

    trigger.addEventListener('click', function (e) { e.stopPropagation(); toggleDropdown(); });
    document.addEventListener('click', function (e) {
      if (!root.contains(e.target) && !panel.contains(e.target)) closeDropdown();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDropdown(); });
    window.addEventListener('resize', function () { if (panel.classList.contains('tree-panel-open')) positionPanel(); });
    window.addEventListener('scroll', function () { if (panel.classList.contains('tree-panel-open')) positionPanel(); }, true);

    buildTree();

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

  // ---------- Affected-systems checkboxes toggle their "checked" styling ----------
  document.querySelectorAll('.equip-checkbox').forEach(function (cb) {
    var sync = function () { cb.closest('.equip-option').classList.toggle('checked', cb.checked); };
    cb.addEventListener('change', sync);
    sync();
  });

  function buildConfirmationHtml() {
    var form = document.getElementById('requestForm');
    var html = '';
    html += '<div class="confirm-section"><h3>Reporter Details</h3>';
    html += row('Date Reported', form.elements['date_reported'].value);
    html += row('Name', form.elements['name'].value);
    html += row('Department / Office', departmentValue());
    html += row('Position', positionValue());
    html += '</div>';

    html += '<div class="confirm-section"><h3>Incident Details</h3>';
    html += row('Date of Incident', form.elements['incident_date'].value);
    html += row('Time of Incident', form.elements['incident_time'].value);
    html += row('Location / Venue', form.elements['location'].value);
    html += row('Incident Category', selectedText(form.elements['category']));
    html += row('Current Status', selectedText(form.elements['status']));
    html += row('Description of Incident', form.elements['description'].value);
    html += row('Immediate Action Taken', form.elements['action_taken'].value);
    html += '</div>';

    var systems = [];
    document.querySelectorAll('.equip-checkbox:checked').forEach(function (cb) {
      var wrap = cb.closest('.equip-option');
      var labelEl = wrap.querySelector('.equip-label-text');
      var label = labelEl ? labelEl.textContent.trim() : wrap.textContent.trim();
      systems.push(label);
    });
    var otherTyped = form.elements['affected_systems_other_text'] ? form.elements['affected_systems_other_text'].value.trim() : '';
    if (otherTyped) systems.push(otherTyped);
    html += '<div class="confirm-section"><h3>Systems / Equipment Affected</h3>';
    html += systems.length
      ? '<ul class="confirm-list">' + systems.map(function (s) { return '<li>' + escapeHtml(s) + '</li>'; }).join('') + '</ul>'
      : '<p class="confirm-empty">None selected</p>';
    html += '</div>';

    html += '<div class="confirm-section"><h3>Additional Information</h3>';
    html += row('Remarks', form.elements['remarks'].value);
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