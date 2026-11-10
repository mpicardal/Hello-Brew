<?php
/** Reset an account's password from the command line:  php database/reset_password.php someone@example.com */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/includes/db.php';

$email = strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Usage: php database/reset_password.php email@example.com\n");
}
$id = q_val('SELECT id FROM users WHERE email = ?', [$email]);
if (!$id) {
    exit("No account with that email.\n");
}
$alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
$pw = '';
for ($i = 0; $i < 12; $i++) {
    $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}
$pw .= random_int(10, 99);
q('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
q('DELETE FROM login_attempts');
echo "New password for $email: $pw\n";
