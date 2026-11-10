<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(mb_substr(post('email'), 0, 190));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        q('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)', [$email]);
        // Same message whether or not the email already existed (no account enumeration).
        flash('success', 'Thanks! You’re on the list for news and offers.');
    } else {
        flash('error', 'Please enter a valid email address.');
    }
}
redirect('index.php');
