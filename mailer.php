<?php
// =====================================================================
// EMAIL NOTIFICATION HELPER (PHPMailer over SMTP)
//
// Requires PHPMailer to be installed via Composer:
//   composer require phpmailer/phpmailer
//
// This only needs to be run once inside the project folder - it creates
// a vendor/ folder that autoload.php below depends on.
// =====================================================================

require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/settings.php';

$__PHPMAILER_AUTOLOAD = __DIR__ . '/vendor/autoload.php';
if (file_exists($__PHPMAILER_AUTOLOAD)) {
    require_once $__PHPMAILER_AUTOLOAD;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * The inboxes a notification should go to.
 *
 * Reads the active rows managed from admin.php. If the table is missing,
 * empty, or the database handle is not available (e.g. mailer.php used on
 * its own), it falls back to MAIL_ADMIN_ADDRESS from mail_config.php so
 * mail never silently goes nowhere.
 *
 * @return array[] [['email' => ..., 'name' => ...], ...]
 */
function mail_recipient_list() {
    global $conn;

    $list = [];
    if (isset($conn) && $conn instanceof mysqli && function_exists('get_mail_recipients')) {
        foreach (get_mail_recipients($conn, true) as $row) {
            if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $list[] = ['email' => $row['email'], 'name' => $row['name'] ?? ''];
            }
        }
    }

    if (empty($list)) {
        $list[] = ['email' => MAIL_ADMIN_ADDRESS, 'name' => MAIL_ADMIN_NAME];
    }
    return $list;
}

/**
 * Send the ITS notification inboxes a message about a newly submitted
 * request. Never throws - a mail failure is logged to the PHP error log
 * and quietly ignored so it can never block a request from being saved.
 *
 * @param string $subject   Email subject line.
 * @param string $htmlBody  HTML body (already escaped by the caller).
 * @param string $plainBody Plain-text fallback body.
 * @return bool             true if the mail was sent, false otherwise.
 */
function send_request_notification($subject, $htmlBody, $plainBody) {
    // Every notification and reminder ends with a "View in Calendar" button
    // that opens the Request Calendar section of index.php.
    $calendarUrl = mail_calendar_url();
    if ($calendarUrl !== '') {
        $htmlBody  .= mail_calendar_button($calendarUrl);
        $plainBody .= "\nView in Calendar: " . $calendarUrl . "\n";
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('send_request_notification: PHPMailer is not installed. Run "composer require phpmailer/phpmailer" in the project folder.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

        // One message, everyone on the managed list as a recipient. A bad
        // row is skipped rather than aborting the whole send.
        $added = 0;
        foreach (mail_recipient_list() as $rcpt) {
            try {
                $mail->addAddress($rcpt['email'], $rcpt['name']);
                $added++;
            } catch (Exception $e) {
                error_log('send_request_notification: skipped invalid recipient ' . $rcpt['email']);
            }
        }
        if ($added === 0) {
            error_log('send_request_notification: no valid recipients configured.');
            return false;
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('send_request_notification failed: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send the fixed ITS admin inbox a reminder that a scheduled event/request
 * is starting soon. Uses the same transport and "never throws" guarantee
 * as send_request_notification().
 *
 * @param string $subject   Email subject line.
 * @param string $htmlBody  HTML body (already escaped by the caller).
 * @param string $plainBody Plain-text fallback body.
 * @return bool             true if the mail was sent, false otherwise.
 */
function send_request_reminder($subject, $htmlBody, $plainBody) {
    // Reminders go out through the exact same mailbox/transport as the
    // original "new request" notification, so we simply delegate to it.
    return send_request_notification($subject, $htmlBody, $plainBody);
}

/**
 * Link to the Request Calendar section of index.php, built from
 * MAIL_PORTAL_URL in mail_config.php. Returns '' when it isn't set.
 */
function mail_calendar_url() {
    $base = defined('MAIL_PORTAL_URL') ? trim(MAIL_PORTAL_URL) : '';
    return $base === '' ? '' : $base . '?tab=calendar#manage';
}

/**
 * The "View in Calendar" button, as email-safe HTML (table + inline styles so
 * it renders in Gmail/Outlook), centred under the details table.
 */
function mail_calendar_button($url) {
    $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px 0;text-align:center;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>'
        . '<td align="center" bgcolor="#2b4a9e" style="border-radius:6px;">'
        . '<a href="' . $href . '" target="_blank" style="display:inline-block;padding:12px 26px;font-family:Segoe UI,Arial,sans-serif;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">View in Calendar &rarr;</a>'
        . '</td></tr></table>'
        . '</div>';
}

/**
 * Small helper to build a simple label/value HTML table row. The value is
 * escaped, so this is only for plain text - use mail_row_raw() if the value
 * legitimately contains HTML (e.g. a "<br>"-joined list of lines).
 */
function mail_row($label, $value) {
    $label = htmlspecialchars($label ?? '', ENT_QUOTES, 'UTF-8');
    $value = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    if ($value === '') $value = '&mdash;';
    return mail_row_raw($label, $value, true);
}

/**
 * Same as mail_row(), but the label/value are inserted as-is instead of
 * being escaped. Use this when the value was already safely built out of
 * HTML fragments (e.g. several htmlspecialchars()'d lines joined with
 * "<br>"), since running it through mail_row() again would escape those
 * tags into literal text.
 *
 * @param bool $labelAlreadyEscaped Internal use by mail_row() - pass false
 *                                  (the default) when calling this directly
 *                                  with a plain-text label.
 */
function mail_row_raw($label, $valueHtml, $labelAlreadyEscaped = false) {
    if (!$labelAlreadyEscaped) {
        $label = htmlspecialchars($label ?? '', ENT_QUOTES, 'UTF-8');
    }
    if ($valueHtml === '' || $valueHtml === null) $valueHtml = '&mdash;';
    return '<tr>'
        . '<td style="padding:6px 10px;border-bottom:1px solid #DDE2EF;color:#5B6072;font-weight:600;white-space:nowrap;">' . $label . '</td>'
        . '<td style="padding:6px 10px;border-bottom:1px solid #DDE2EF;color:#111318;">' . $valueHtml . '</td>'
        . '</tr>';
}

/**
 * Wraps a set of mail_row() rows in a simple bordered table, with a small
 * blue heading banner and an optional introduction paragraph (shown above
 * the table, below the banner) explaining what the email is about.
 *
 * @param string $title    Banner heading, e.g. "New Software Request - SIRF-...".
 *                          May contain an em dash character (\u{2014}) or
 *                          other punctuation - it is escaped as a whole, so
 *                          do not pass an HTML entity like "&mdash;" here.
 * @param string $rowsHtml One or more mail_row() strings.
 * @param string $intro    Optional short paragraph shown above the table.
 */
function mail_wrap_table($title, $rowsHtml, $intro = '') {
    $title = htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8');

    $introHtml = '';
    if ($intro !== '') {
        $introHtml = '<p style="margin:0;padding:16px 18px 4px;font-size:13px;color:#2b2f40;line-height:1.6;background:#ffffff;border:1px solid #DDE2EF;border-top:none;border-bottom:none;">'
            . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }

    return '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto;">'
        . '<div style="background:#2b4a9e;color:#ffffff;padding:14px 18px;font-weight:800;border-bottom:3px solid #8fa3e0;border-radius:4px 4px 0 0;">' . $title . '</div>'
        . $introHtml
        . '<table style="width:100%;border-collapse:collapse;background:#ffffff;border:1px solid #DDE2EF;border-top:none;">'
        . $rowsHtml
        . '</table>'
        . '</div>';
}