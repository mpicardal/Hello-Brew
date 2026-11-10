<?php
require __DIR__ . '/../includes/bootstrap.php';

// Logout is POST-only (CSRF-protected) so a link on another site can't sign users out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
logout_user();
start_secure_session();
flash('success', 'You have been signed out.');
redirect('index.php');
