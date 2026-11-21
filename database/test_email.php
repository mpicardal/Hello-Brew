<?php
/** Send one test email to check the Gmail settings in .env:  php database/test_email.php you@gmail.com */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/includes/db.php';
require ROOT . '/includes/helpers.php';
require ROOT . '/includes/mailer.php';

$to = trim($argv[1] ?? '');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit("Usage: php database/test_email.php you@gmail.com\n");
}
if (cfg('MAIL_TRANSPORT', 'log') !== 'smtp') {
    exit("✘ MAIL_TRANSPORT is \"" . cfg('MAIL_TRANSPORT', 'log') . "\" in .env. Change it to smtp first.\n");
}
if (!extension_loaded('openssl')) {
    exit("✘ The PHP openssl extension is off. Enable extension=openssl in php.ini.\n");
}
echo "Sending a test email to $to through " . cfg('SMTP_HOST', 'smtp.gmail.com') . " as " . cfg('SMTP_USER', '(no SMTP_USER)') . " …\n";
$error = smtp_send($to, 'Hello Brew! test email', "It works! Order confirmations and updates will now arrive in customers' inboxes.\n\n— Hello Brew!");
if ($error === '') {
    echo "✔ Sent. Check the inbox (and the Spam folder) of $to.\n";
} else {
    echo "✘ Not sent: $error\n";
    if (stripos($error, 'authenticate') !== false || stripos($error, 'Username and Password') !== false) {
        echo "  → Check SMTP_USER and SMTP_PASS. SMTP_PASS must be a Gmail App Password, not your normal password.\n";
    } elseif (stripos($error, 'connect') !== false) {
        echo "  → Can't reach Gmail. Check your internet connection, or try SMTP_PORT=465.\n";
    }
    exit(1);
}
