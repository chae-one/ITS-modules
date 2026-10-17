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

// ---- "Receipt" / do-not-reply behaviour (override any of these in mail_config.php) ----
// Replies are pointed at an address that cannot receive mail (.invalid is a
// reserved TLD), so a reply bounces instead of landing in the sending inbox.
if (!defined('MAIL_NOREPLY_ADDRESS')) define('MAIL_NOREPLY_ADDRESS', 'no-reply@portal.invalid');
if (!defined('MAIL_NOREPLY_NAME'))    define('MAIL_NOREPLY_NAME', 'Do Not Reply');
if (!defined('MAIL_RECEIPT_NOTE'))    define('MAIL_RECEIPT_NOTE', 'This is an automated receipt from the ITS Request Portal. This mailbox is not monitored - please do not reply to this email.');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * The inboxes a message should go to.
 *
 * Reads the rows managed from admin.php:
 *   'request'  - NEW-REQUEST emails: active inboxes that are also opted in
 *                to new-request emails (notify_new_requests = 1).
 *   'reminder' - event reminders: EVERY active inbox, regardless of the
 *                new-request opt-in.
 *
 * If there are no active rows at all (table missing/empty, or the database
 * handle is not available, e.g. mailer.php used on its own) it falls back to
 * MAIL_ADMIN_ADDRESS so mail never silently goes nowhere. If there ARE active
 * inboxes but all of them opted out of new-request emails, nobody is mailed -
 * that is what opting out means.
 *
 * @param string $audience 'request' | 'reminder'
 * @return array[] [['email' => ..., 'name' => ...], ...]
 */
function mail_recipient_list($audience = 'request') {
    global $conn;

    $list = [];
    $haveActive = false;
    if (isset($conn) && $conn instanceof mysqli && function_exists('get_mail_recipients')) {
        foreach (get_mail_recipients($conn, true) as $row) {
            $haveActive = true;
            if ($audience === 'request' && isset($row['notify_new_requests']) && (int) $row['notify_new_requests'] !== 1) {
                continue; // opted out of new-request emails
            }
            if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $list[] = ['email' => $row['email'], 'name' => $row['name'] ?? ''];
            }
        }
    }

    if (empty($list) && !$haveActive) {
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
    return mail_send_to_audience('request', $subject, $htmlBody, $plainBody);
}

/**
 * Shared sender behind send_request_notification() (new requests) and
 * send_request_reminder() (event reminders). Same "never throws" guarantee.
 *
 * @param string $audience 'request' | 'reminder' - see mail_recipient_list().
 */
function mail_send_to_audience($audience, $subject, $htmlBody, $plainBody) {
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('mail_send_to_audience: PHPMailer is not installed. Run "composer require phpmailer/phpmailer" in the project folder.');
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

        // Receipt-style, no-reply message: replies go to an address that
        // cannot receive mail, and auto-responders / out-of-office bots are
        // told to leave it alone.
        $mail->addReplyTo(MAIL_NOREPLY_ADDRESS, MAIL_NOREPLY_NAME);
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');

        // One message, everyone on the managed list as a recipient. A bad
        // row is skipped rather than aborting the whole send.
        $added = 0;
        foreach (mail_recipient_list($audience) as $rcpt) {
            try {
                $mail->addAddress($rcpt['email'], $rcpt['name']);
                $added++;
            } catch (Exception $e) {
                error_log('send_request_notification: skipped invalid recipient ' . $rcpt['email']);
            }
        }
        if ($added === 0) {
            // Nothing to send to: either no valid addresses, or (for new
            // requests) every active inbox has opted out. Not an error.
            if ($audience === 'request') {
                error_log('send_request_notification: no recipients opted in to new-request emails - nothing sent.');
            } else {
                error_log('send_request_reminder: no valid active recipients configured.');
            }
            return false;
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = rtrim($plainBody) . "\n\n-- \n" . MAIL_RECEIPT_NOTE . "\n";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('send_request_notification failed: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send every ACTIVE ITS inbox a reminder that a scheduled event/request
 * is starting soon. Uses the same transport and "never throws" guarantee
 * as send_request_notification().
 *
 * @param string $subject   Email subject line.
 * @param string $htmlBody  HTML body (already escaped by the caller).
 * @param string $plainBody Plain-text fallback body.
 * @return bool             true if the mail was sent, false otherwise.
 */
function send_request_reminder($subject, $htmlBody, $plainBody) {
    // Same mailbox/transport as the "new request" notification, but sent to
    // EVERY active inbox - the new-request opt-in toggle does not apply here.
    return mail_send_to_audience('reminder', $subject, $htmlBody, $plainBody);
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
        . '<p style="margin:0;padding:12px 18px;font-size:12px;color:#5B6072;line-height:1.5;background:#F4F6FB;border:1px solid #DDE2EF;border-top:none;border-radius:0 0 4px 4px;">'
        . htmlspecialchars(MAIL_RECEIPT_NOTE, ENT_QUOTES, 'UTF-8')
        . '</p>'
        . '</div>';
}