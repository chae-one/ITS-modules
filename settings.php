<?php
// =====================================================================
// SHARED SETTINGS / AVAILABILITY HELPERS
//
// Central place for the values that admin.php lets the ITS office edit,
// plus the projector-availability maths used by both the request form
// and the admin calendar.
//
// Requires the tables created by admin_setup.sql. If those tables do not
// exist yet, every getter quietly falls back to the defaults below, so
// the site keeps working until the SQL is run.
// =====================================================================

/**
 * Built-in fallbacks. Anything missing from `app_settings` uses these.
 */
function app_settings_defaults() {
    return [
        'projector_total_units' => 10, // projectors available to lend on any one day
        'max_units_per_request' => 2,  // most units a single request may ask for
        'daily_request_limit'   => 5,  // most projector requests accepted per day
    ];
}

/**
 * Load every setting once per request, merged over the defaults. The
 * cache lives in $GLOBALS so set_setting() can invalidate it.
 */
function get_settings($conn) {
    if (isset($GLOBALS['__app_settings_cache'])) {
        return $GLOBALS['__app_settings_cache'];
    }

    $values = app_settings_defaults();
    $res = @$conn->query("SELECT setting_key, setting_value FROM app_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $values)) {
                $values[$row['setting_key']] = (int) $row['setting_value'];
            }
        }
        $res->free();
    }

    $GLOBALS['__app_settings_cache'] = $values;
    return $values;
}

/**
 * One setting as an integer.
 */
function get_setting_int($conn, $key) {
    $all = get_settings($conn);
    $defaults = app_settings_defaults();
    return (int) ($all[$key] ?? $defaults[$key] ?? 0);
}

/**
 * Write a setting. Only keys listed in app_settings_defaults() are
 * accepted, so a tampered form field cannot create arbitrary rows.
 *
 * @return bool true when the row was written.
 */
function set_setting($conn, $key, $value) {
    $defaults = app_settings_defaults();
    if (!array_key_exists($key, $defaults)) return false;

    $stmt = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    if (!$stmt) {
        error_log('set_setting: prepare failed - ' . $conn->error);
        return false;
    }
    $val = (string) $value;
    $stmt->bind_param('ss', $key, $val);
    $ok = $stmt->execute();
    $stmt->close();

    // Drop the cache so anything further down this request re-reads.
    unset($GLOBALS['__app_settings_cache']);
    return $ok;
}

/**
 * Every date a single projector request occupies, as 'YYYY-MM-DD' strings.
 * A request holds its primary `date_needed` plus each row inside the
 * `additional_dates` JSON column.
 *
 * @param array $row A projector_requests row (needs date_needed + additional_dates).
 * @return string[] Dates, duplicates preserved (asking twice uses two slots).
 */
function projector_request_dates($row) {
    $dates = [];
    if (!empty($row['date_needed'])) {
        $dates[] = $row['date_needed'];
    }
    if (!empty($row['additional_dates'])) {
        $extra = json_decode($row['additional_dates'], true);
        if (is_array($extra)) {
            foreach ($extra as $e) {
                if (is_array($e) && !empty($e['date'])) {
                    $dates[] = $e['date'];
                }
            }
        }
    }
    return $dates;
}

/**
 * True when projector_requests has the is_cancelled column added by
 * cancel_migration.sql. Looked up once per request and cached, so the
 * booking form and reminder job keep working even before that SQL is run.
 */
function projector_has_cancel_column($conn) {
    static $has = null;
    if ($has !== null) return $has;

    $has = false;
    try {
        $res = $conn->query("SHOW COLUMNS FROM projector_requests LIKE 'is_cancelled'");
        if ($res) {
            $has = $res->num_rows > 0;
            $res->free();
        }
    } catch (Throwable $e) {
        error_log('projector_has_cancel_column: ' . $e->getMessage());
    }
    return $has;
}

/**
 * SQL fragment that leaves cancelled requests out of a projector_requests
 * query: " AND is_cancelled = 0" once the column exists, otherwise "".
 * Append it to a WHERE clause (wrap any OR conditions in parentheses first).
 */
function projector_active_filter($conn) {
    return projector_has_cancel_column($conn) ? ' AND is_cancelled = 0' : '';
}

// ---------------------------------------------------------------------
// EVENT TYPES
//
// The "Type of Event" choices on the projector form, and the colour each
// one gets on the admin calendar (index.php). Anything a requester types
// under "Others" is stored as typed and drawn with the "Others" colour.
// All colours are dark enough for white chip text.
// ---------------------------------------------------------------------
const EVENT_TYPE_OTHER_VALUE = 'Others';

/** label => hex colour, in the order shown in the dropdown and the legend. */
function event_type_palette() {
    return [
        'Meeting'              => '#c2410c', // orange
        'Seminar'              => '#86198f', // plum (kept clear of the brand blue)
        'Orientation'          => '#4d7c0f', // olive green
        'Event'                => '#be185d', // pink
        'MANCOM Meeting'       => '#1e293b', // dark slate
        'Training'             => '#a16207', // dark gold
        EVENT_TYPE_OTHER_VALUE => '#6b7280', // grey
    ];
}

/** Colour for a stored event type. Blank (requests filed before event types existed) uses the ITS brand blue. */
function event_type_color($type) {
    $type = trim((string) $type);
    if ($type === '') return '#2b4a9e';
    foreach (event_type_palette() as $label => $color) {
        if (strcasecmp($label, $type) === 0) return $color;
    }
    return event_type_palette()[EVENT_TYPE_OTHER_VALUE];
}

/** The text to store: the picked type, or what was typed when "Others" is picked. */
function resolve_event_type($type, $typeOther) {
    return $type === EVENT_TYPE_OTHER_VALUE ? trim((string) $typeOther) : $type;
}

/**
 * Adds projector_requests.event_type and .participants if they are missing
 * (same idea as ensure_cancel_columns() in index.php), so the form works
 * without a manual SQL step. event_type_migration.sql does the same thing
 * by hand for setups where the DB user cannot ALTER.
 *
 * @return bool true when both columns exist afterwards.
 */
function ensure_projector_event_columns($conn) {
    static $ok = null;
    if ($ok !== null) return $ok;

    $wanted = [
        'event_type'   => "VARCHAR(100) NOT NULL DEFAULT ''",
        'participants' => 'INT UNSIGNED NULL DEFAULT NULL',
    ];
    try {
        foreach ($wanted as $col => $definition) {
            $res = $conn->query("SHOW COLUMNS FROM projector_requests LIKE '$col'");
            $exists = $res && $res->num_rows > 0;
            if ($res instanceof mysqli_result) $res->free();
            if (!$exists && !$conn->query("ALTER TABLE projector_requests ADD COLUMN `$col` $definition")) {
                return $ok = false;
            }
        }
    } catch (Throwable $e) {
        error_log('ensure_projector_event_columns: ' . $e->getMessage());
        return $ok = false; // e.g. the DB user has no ALTER privilege
    }
    return $ok = true;
}

/**
 * How much of a given day is already taken.
 *
 * @return array ['requests' => int, 'units' => int]
 */
function projector_day_usage($conn, $date) {
    // Cancelled requests are kept on record but no longer hold any projectors
    // or count toward the daily request limit.
    $stmt = $conn->prepare(
        "SELECT projector_units, date_needed, additional_dates
           FROM projector_requests
          WHERE (date_needed = ? OR additional_dates LIKE ?)" . projector_active_filter($conn)
    );
    if (!$stmt) {
        error_log('projector_day_usage: prepare failed - ' . $conn->error);
        return ['requests' => 0, 'units' => 0];
    }
    $like = '%"date":"' . $date . '"%';
    $stmt->bind_param('ss', $date, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $requests = 0;
    $units    = 0;
    while ($res && ($row = $res->fetch_assoc())) {
        // A request that lists the same day twice occupies it twice.
        $hits = 0;
        foreach (projector_request_dates($row) as $d) {
            if ($d === $date) $hits++;
        }
        if ($hits === 0) continue; // LIKE matched some other field/date
        $requests += $hits;
        $units    += $hits * (int) $row['projector_units'];
    }
    $stmt->close();

    return ['requests' => $requests, 'units' => $units];
}

/**
 * Projectors still free on a date, never below zero.
 */
function projector_units_available($conn, $date) {
    $total = get_setting_int($conn, 'projector_total_units');
    $used  = projector_day_usage($conn, $date)['units'];
    return max(0, $total - $used);
}

// ---------------------------------------------------------------------
// ACTIVITY / CHANGE LOG
//
// A timestamped audit trail: every NEW request as it is filed (LCD projector,
// software, service call), plus every change made from admin.php - settings
// edits, mail recipient changes, request cancellations, mark-as-done /
// reopen, and schedule/detail edits made through the report editor. Shown
// on its own "Logs" tab next to Settings.
//
// A new request is logged under the date it is requested for: an LCD
// projector request under its Date Needed (plus any additional dates), a
// software / service request under its Date Requested - the same dates the
// admin calendar files them under.
//
// Each entry carries, in its Event column, the LCD projector request's
// Purpose / Event name when the action is about a projector request, and the
// requester's own name (its `name` column, stored as its own field so it
// survives even if the request is later edited or removed) for every
// action tied to a request. Actions that aren't tied to a request
// (settings edits, recipient changes) leave both blank.
//
// The table is created automatically the first time something is logged
// (same pattern as ensure_projector_event_columns() above); run
// activity_log_migration.sql instead if the DB user has no CREATE
// privilege. Logging never blocks or fails the action that triggered it -
// if the table can't be created or the insert fails, the action still
// completes and only the log entry is lost (noted in the PHP error log).
// ---------------------------------------------------------------------

/** Name of the activity log table (kept in one place, matches the .sql file). */
function activity_log_table() {
    return 'activity_logs';
}

/** Creates the activity_logs table if it doesn't exist yet, and brings an older table up to date. Cached per request. */
function ensure_activity_log_table($conn) {
    static $ok = null;
    if ($ok !== null) return $ok;

    $table = activity_log_table();
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
                    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    created_at   DATETIME NOT NULL,
                    action       VARCHAR(40) NOT NULL,
                    event_type   VARCHAR(100) NOT NULL DEFAULT '',
                    requested_by VARCHAR(150) NOT NULL DEFAULT '',
                    summary      VARCHAR(500) NOT NULL,
                    details      TEXT NULL,
                    PRIMARY KEY (id),
                    KEY created_at (created_at),
                    KEY event_type (event_type),
                    KEY requested_by (requested_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $ok = (bool) $conn->query($sql);
        if ($ok) $ok = activity_log_migrate_columns($conn, $table);
    } catch (Throwable $e) {
        error_log('ensure_activity_log_table: ' . $e->getMessage());
        $ok = false; // e.g. the DB user has no CREATE privilege
    }
    return $ok;
}

/**
 * Brings a table created by an older version of this file up to date:
 * adds event_type / requested_by if they're missing, and drops the retired
 * entity_type/entity_id/actor columns if they're still there. Safe to
 * call every request - each check is a no-op once it's done. A failure
 * here doesn't stop logging; the table just keeps its old shape.
 */
function activity_log_migrate_columns($conn, $table) {
    $hasColumn = function ($col) use ($conn, $table) {
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        $exists = $res && $res->num_rows > 0;
        if ($res instanceof mysqli_result) $res->free();
        return $exists;
    };

    if (!$hasColumn('event_type')) {
        if (!$conn->query("ALTER TABLE `$table` ADD COLUMN event_type VARCHAR(100) NOT NULL DEFAULT '' AFTER action")) {
            return false;
        }
    }
    if (!$hasColumn('requested_by')) {
        if (!$conn->query("ALTER TABLE `$table` ADD COLUMN requested_by VARCHAR(150) NOT NULL DEFAULT '' AFTER event_type")) {
            return false;
        }
    }
    foreach (['entity_type', 'entity_id', 'actor'] as $col) {
        if ($hasColumn($col)) {
            $conn->query("ALTER TABLE `$table` DROP COLUMN `$col`");
        }
    }
    return true;
}

/**
 * Records one change to the audit trail.
 *
 * @param string $action      Short machine tag, e.g. 'request_cancelled'. See activity_log_action_label().
 * @param string $eventType   The projector request's Type of Event (Meeting, Seminar, ...) when this
 *                             action is about a projector request; '' when it isn't (settings, recipients,
 *                             or a software/service request, which has no Type of Event).
 * @param string $requestedBy Who filed the request this action is about (its own `name` column), stored
 *                             as its own field so the name survives even if the request is later edited or
 *                             removed; '' when the action isn't tied to a request (settings, recipients).
 * @param string $summary     One-line, human-readable description shown in the log list.
 * @param array  $details     Optional extra context (old/new values, a cancel reason, ...), stored as JSON.
 * @return bool true when the entry was written.
 */
function log_activity($conn, $action, $eventType, $requestedBy, $summary, array $details = []) {
    if (!ensure_activity_log_table($conn)) return false;

    $table = activity_log_table();
    $stmt  = $conn->prepare(
        "INSERT INTO `$table` (created_at, action, event_type, requested_by, summary, details)
         VALUES (NOW(), ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        error_log('log_activity: prepare failed - ' . $conn->error);
        return false;
    }
    $eventType   = (string) $eventType;
    $requestedBy = (string) $requestedBy;
    $json        = $details ? json_encode($details) : null;
    $stmt->bind_param('sssss', $action, $eventType, $requestedBy, $summary, $json);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * The Type of Event to log for a request action - the projector request's
 * own event_type column, or '' for software/service requests (they have
 * no Type of Event) and for anything not tied to a request row.
 */
function request_event_type($conn, $type, $table, $id) {
    if ($type !== 'projector') return '';
    $res = @$conn->query("SELECT event_type FROM `$table` WHERE id = " . (int) $id);
    $val = '';
    if ($res) {
        if ($row = $res->fetch_assoc()) $val = trim((string) ($row['event_type'] ?? ''));
        $res->free();
    }
    return $val;
}

/**
 * Who filed a request - its own `name` column, looked up when the caller
 * doesn't already have the row. Every request table (projector, software,
 * service) carries a `name` column, so this works for all three types.
 */
function request_requester_name($conn, $table, $id) {
    $name = '';
    $res = @$conn->query("SELECT name FROM `$table` WHERE id = " . (int) $id);
    if ($res) {
        if ($row = $res->fetch_assoc()) $name = trim((string) ($row['name'] ?? ''));
        $res->free();
    }
    return $name;
}

/**
 * Human-readable stand-in for a request's database id in Logs tab
 * summaries - looked up when the caller doesn't already have the row.
 * A projector request is identified by its Type of Event ("Projector
 * request (Meeting)"); a software or service request is identified by
 * who asked for it ("Software request for Jane Dela Cruz"). Falls back
 * to the bare type label when that information isn't set.
 */
function request_log_label($conn, $type, $table, $id) {
    if ($type === 'projector') {
        $eventType = request_event_type($conn, $type, $table, $id);
        return $eventType !== '' ? "Projector request ($eventType)" : 'Projector request';
    }
    $name = request_requester_name($conn, $table, $id);
    $label = ucfirst($type) . ' request';
    return $name !== '' ? "$label for $name" : $label;
}

/**
 * Same as request_log_label(), but built from a row already in hand (no
 * extra query) - used where the caller has just SELECTed or is about to
 * UPDATE the row anyway.
 */
function request_log_label_from_row($type, array $row) {
    if ($type === 'projector') {
        $eventType = trim((string) ($row['event_type'] ?? ''));
        return $eventType !== '' ? "Projector request ($eventType)" : 'Projector request';
    }
    $name = trim((string) ($row['name'] ?? ''));
    $label = ucfirst($type) . ' request';
    return $name !== '' ? "$label for $name" : $label;
}

/** Trims text to $max characters (multibyte-safe) so it fits the activity_logs VARCHAR columns. */
function activity_log_clip($text, $max) {
    $text = trim((string) $text);
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max - 1) . "\xE2\x80\xA6"; // "\xE2\x80\xA6" = ...
}

/**
 * What the Logs tab's Event column shows for a request. For an LCD projector
 * request that is its Purpose / Event (what the requester typed as the event
 * name), so each entry can be told apart at a glance. Software and service
 * requests have no such field, so theirs stays blank.
 */
function request_log_event($type, array $row) {
    if ($type !== 'projector') return '';
    return activity_log_clip($row['purpose'] ?? '', 100);
}

/**
 * The projector request's Type of Event (Meeting, Seminar, ...), stored in
 * the log entry's details so the Event chip can keep the same colour the
 * request has on the calendar even though it now shows the Purpose text.
 * Merge it into a log entry's $details; empty for software/service requests.
 */
function request_log_type_detail($type, array $row) {
    if ($type !== 'projector') return [];
    return ['type_of_event' => trim((string) ($row['event_type'] ?? ''))];
}

/**
 * Records a newly submitted request in the activity log, so it shows up in
 * the Logs tab as soon as it is filed. Never throws - a logging problem
 * must not make the requester's submission fail.
 *
 * The entry is filed under the date the request is for: an LCD projector
 * request under its Date Needed (any additional dates are noted too), a
 * software / service request under its Date Requested.
 *
 * @param string $type 'projector' | 'software' | 'service'
 * @param array  $row  The submitted values. Always: name, date_requested.
 *                     Projector also: purpose, event_type, venue, date_needed,
 *                     time_from, time_to, projector_units, and additional_dates
 *                     (list of ['date' => .., 'time_from' => .., 'time_to' => ..]).
 * @return bool true when the entry was written.
 */
function log_new_request($conn, $type, array $row) {
    try {
        $label     = request_log_label_from_row($type, $row);
        $requester = activity_log_clip($row['name'] ?? '', 150);
        $dateReq   = trim((string) ($row['date_requested'] ?? ''));
        $details   = ['date_requested' => $dateReq];

        if ($type === 'projector') {
            $dateNeeded = trim((string) ($row['date_needed'] ?? ''));
            $more = [];
            foreach ((array) ($row['additional_dates'] ?? []) as $extra) {
                if (is_array($extra) && !empty($extra['date'])) $more[] = (string) $extra['date'];
            }
            $summary = "$label submitted for $dateNeeded";
            if ($more) $summary .= ' (+' . count($more) . ' more date' . (count($more) === 1 ? '' : 's') . ')';

            $details += [
                'date_needed' => $dateNeeded,
                'time_from'   => (string) ($row['time_from'] ?? ''),
                'time_to'     => (string) ($row['time_to'] ?? ''),
                'more_dates'  => $more,
                'venue'       => trim((string) ($row['venue'] ?? '')),
                'units'       => (int) ($row['projector_units'] ?? 0),
            ];
            $details += request_log_type_detail($type, $row);
        } else {
            $summary = "$label submitted" . ($dateReq !== '' ? " (dated $dateReq)" : '');
        }

        return log_activity(
            $conn,
            'request_submitted',
            request_log_event($type, $row),
            $requester,
            activity_log_clip($summary, 500),
            $details
        );
    } catch (Throwable $e) {
        error_log('log_new_request: ' . $e->getMessage());
        return false;
    }
}

/**
 * Recent activity log entries, newest first, ready for display.
 * @return array Rows with created_at/action/event_type/requested_by/summary,
 *               plus 'details' already decoded to an array (empty when none was stored).
 */
function get_activity_logs($conn, $limit = 200) {
    if (!ensure_activity_log_table($conn)) return [];
    $table = activity_log_table();
    $limit = max(1, min(1000, (int) $limit));
    $res = @$conn->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT $limit");
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['details'] = $row['details'] ? (json_decode($row['details'], true) ?: []) : [];
            $out[] = $row;
        }
        $res->free();
    }
    return $out;
}

/** Human-readable label for a log action tag. */
function activity_log_action_label($action) {
    $labels = [
        'request_submitted' => 'New request',
        'settings_updated'  => 'Settings updated',
        'recipient_added'   => 'Recipient added',
        'recipient_updated' => 'Recipients updated',
        'recipient_deleted' => 'Recipient removed',
        'request_cancelled' => 'Request cancelled',
        'request_done'      => 'Marked as done',
        'request_reopened'  => 'Report reopened',
        'request_updated'   => 'Report updated',
    ];
    return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
}

/** label => hex colour for the Logs tab's Action chip, one per action tag. */
function activity_log_action_palette() {
    return [
        'request_submitted' => '#0369a1', // sky blue
        'settings_updated'  => '#2b4a9e', // brand blue
        'recipient_added'   => '#15803d', // green
        'recipient_updated' => '#a16207', // dark gold
        'recipient_deleted' => '#b91c1c', // red
        'request_cancelled' => '#9f1239', // rose
        'request_done'      => '#0f766e', // teal
        'request_reopened'  => '#6d28d9', // purple
        'request_updated'   => '#c2410c', // orange
    ];
}

/** Colour for a log action tag. Anything not in the palette falls back to grey. */
function activity_log_action_color($action) {
    $palette = activity_log_action_palette();
    return $palette[$action] ?? '#6b7280';
}

/**
 * Active mail recipients as [['email'=>..,'name'=>..], ...].
 * Falls back to the address in mail_config.php when the table is empty
 * or missing, so notifications never silently stop going anywhere.
 */
function get_mail_recipients($conn, $activeOnly = true) {
    $list = [];
    $sql  = "SELECT id, email, name, is_active FROM mail_recipients";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY id ASC";

    $res = @$conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) $list[] = $row;
        $res->free();
    }
    return $list;
}