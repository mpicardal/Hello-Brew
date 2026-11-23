<?php
require __DIR__ . '/../includes/bootstrap.php';

// Return to the page the visitor subscribed from (same site only), at the newsletter box.
$back = (string) ($_POST['back'] ?? '');
if (!preg_match('#^/[A-Za-z0-9_./?=&%+-]*$#', $back) || str_starts_with($back, '//') || str_contains($back, '..')) {
    $back = '/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim(mb_substr(post('email'), 0, 190)));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $isNew = q('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)', [$email])->rowCount() > 0;
        if ($isNew) {
            send_mail($email, 'Welcome to Hello Brew! news and offers',
                "Hi there,\n\nThanks for subscribing to Hello Brew! You'll be the first to hear about new drinks, seasonal pastries and special offers.\n\n"
                . "To start, use code HELLOBREW for 10% off your next order:\n" . rtrim((string) cfg('APP_URL', 'http://localhost:8000'), '/') . "/shop.php\n\n"
                . "Didn't sign up? Just ignore this email and you won't hear from us again.\n\n— The Hello Brew! team",
                'newsletter');
        }
        // Same message whether or not the email was already subscribed (doesn't reveal who is on the list).
        $_SESSION['nl_msg'] = ['ok', 'Thanks for subscribing! Check your inbox for a welcome email with 10% off.'];
    } else {
        $_SESSION['nl_msg'] = ['err', 'Please enter a valid email address.'];
    }
}
redirect(ltrim($back, '/') . '#newsletter');
