<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/config/config.php';
require ROOT . '/includes/db.php';
require ROOT . '/includes/helpers.php';
require ROOT . '/includes/icons.php';
require ROOT . '/includes/security.php';
require ROOT . '/includes/auth.php';
require ROOT . '/includes/cart.php';
require ROOT . '/includes/mailer.php';
require ROOT . '/includes/payment.php';
require ROOT . '/includes/analytics.php';
require ROOT . '/includes/orders.php';

send_security_headers();
start_secure_session();

// Every POST request on the site must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}
