<?php

// ---- SMTP account used to SEND the mail ----
define('SMTP_HOST', 'smtp.gmail.com');       // e.g. smtp.gmail.com, smtp.office365.com
define('SMTP_PORT', 587);                    // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', 'tls');                // 'tls' for 587, 'ssl' for 465
define('SMTP_USERNAME', 'jonasagojo09@gmail.com');
define('SMTP_PASSWORD', 'bjpf pxux tpcz wduy'); // Gmail/Office365: use an "app password", not your normal login password

// ---- How the mail should appear to the recipient ----
define('MAIL_FROM_ADDRESS', 'your-account@gmail.com'); // usually must match SMTP_USERNAME
define('MAIL_FROM_NAME', 'ITS Request Portal');

// ---- Fixed inbox that should receive every new request ----
define('MAIL_ADMIN_ADDRESS', 'kakarot8823@gmail.com');
define('MAIL_ADMIN_NAME', 'ITS Office');
