<?php

// ---- SMTP account used to SEND the mail ----
define('SMTP_HOST', 'smtp.gmail.com');       // e.g. smtp.gmail.com, smtp.office365.com
define('SMTP_PORT', 587);                    // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', 'tls');                // 'tls' for 587, 'ssl' for 465
define('SMTP_USERNAME', 'calamba.its@gmail.com');
define('SMTP_PASSWORD', 'zfhb ybft laek ijbv'); // Gmail/Office365: use an "app password", not your normal login password

// ---- How the mail should appear to the recipient ----
define('MAIL_FROM_ADDRESS', SMTP_USERNAME); // Gmail rewrites any other From address, so keep it equal to SMTP_USERNAME
define('MAIL_FROM_NAME', 'ITS Request Portal');

// ---- Fixed inbox that should receive every new request ----
define('MAIL_ADMIN_NAME', 'ITS Office');
define('MAIL_ADMIN_ADDRESS', SMTP_USERNAME); // fallback inbox if the recipient list in admin.php is empty (mailer.php needs this)