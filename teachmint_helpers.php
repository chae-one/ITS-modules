<?php
// =====================================================================
// Teachmint helpers - required by teachmint.php.
// Guarded with function_exists() so they can also live in index.php.
// Also creates the teachmint_requests table the first time it's loaded.
// =====================================================================

/** Creates teachmint_requests if it doesn't exist yet (same columns the form inserts + cancel/done columns). */
if (!function_exists('ensure_teachmint_table')) {
    function ensure_teachmint_table($conn) {
        try {
            $created = (bool) $conn->query(
                "CREATE TABLE IF NOT EXISTS `teachmint_requests` (
                    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    date_requested  DATE NOT NULL,
                    name            VARCHAR(150) NOT NULL,
                    position        VARCHAR(100) NOT NULL DEFAULT '',
                    department      VARCHAR(255) NOT NULL DEFAULT '',
                    venue           VARCHAR(255) NOT NULL DEFAULT '',
                    purpose         VARCHAR(255) NOT NULL DEFAULT '',
                    event_type      VARCHAR(100) NOT NULL DEFAULT '',
                    participants    INT UNSIGNED NULL DEFAULT NULL,
                    date_needed     DATE NOT NULL,
                    time_from       VARCHAR(20) NOT NULL DEFAULT '',
                    time_to         VARCHAR(20) NOT NULL DEFAULT '',
                    teachmint_units INT NOT NULL DEFAULT 1,
                    remarks         TEXT NULL,
                    additional_dates TEXT NULL,
                    is_cancelled    TINYINT(1) NOT NULL DEFAULT 0,
                    cancelled_at    DATETIME NULL DEFAULT NULL,
                    cancel_reason   VARCHAR(255) NULL DEFAULT NULL,
                    is_done         TINYINT(1) NOT NULL DEFAULT 0,
                    done_at         DATETIME NULL DEFAULT NULL,
                    done_by         VARCHAR(150) NULL DEFAULT NULL,
                    reminder_1day_sent  TINYINT(1) NOT NULL DEFAULT 0,
                    reminder_1hr_sent   TINYINT(1) NOT NULL DEFAULT 0,
                    reminder_30min_sent TINYINT(1) NOT NULL DEFAULT 0,
                    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY date_needed (date_needed)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            // send_reminders.php reads these three columns. A table created by
            // an older version of this helper won't have them, which makes the
            // reminder query fail silently - so add any that are missing.
            foreach (['reminder_1day_sent', 'reminder_1hr_sent', 'reminder_30min_sent'] as $col) {
                $chk = $conn->query("SHOW COLUMNS FROM `teachmint_requests` LIKE '{$col}'");
                if ($chk && $chk->num_rows === 0) {
                    $conn->query("ALTER TABLE `teachmint_requests` ADD COLUMN `{$col}` TINYINT(1) NOT NULL DEFAULT 0");
                }
            }
            return $created;
        } catch (Throwable $e) {
            error_log('ensure_teachmint_table: ' . $e->getMessage());
            return false;
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    ensure_teachmint_table($conn);
}

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
     * Non-cancelled Teachmint requests and units already booked on $date
     * (primary date + additional_dates entries).
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