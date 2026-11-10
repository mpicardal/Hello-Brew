<?php
declare(strict_types=1);

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
        . "script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('HBSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => APP_BASE === '' ? '/' : APP_BASE . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    // Idle timeout for signed-in users.
    if (isset($_SESSION['user_id'], $_SESSION['last_seen']) && $now - $_SESSION['last_seen'] > SESSION_IDLE_SECONDS) {
        $cart = $_SESSION['cart'] ?? [];
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['cart'] = $cart;
        $_SESSION['flash'][] = ['type' => 'info', 'msg' => 'You were signed out after 30 minutes of inactivity.'];
    }
    $_SESSION['last_seen'] = $now;
    // Rotate the session id periodically to limit fixation/hijack windows.
    if (!isset($_SESSION['rotated_at']) || $now - $_SESSION['rotated_at'] > 900) {
        session_regenerate_id(true);
        $_SESSION['rotated_at'] = $now;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Your session expired or the form was tampered with. Go back, refresh the page and try again.');
    }
}

/* ---------- Login throttling: 5 failures per 15 minutes per email+IP ---------- */

function throttle_key(string $email): string
{
    return hash_hmac('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''), APP_SECRET);
}

function login_is_locked(string $email): bool
{
    $n = (int) q_val(
        'SELECT COUNT(*) FROM login_attempts WHERE key_hash = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)',
        [throttle_key($email)]
    );
    return $n >= 5;
}

function login_record_failure(string $email): void
{
    q('INSERT INTO login_attempts (key_hash) VALUES (?)', [throttle_key($email)]);
}

function login_clear_failures(string $email): void
{
    q('DELETE FROM login_attempts WHERE key_hash = ?', [throttle_key($email)]);
}

function password_problem(string $pw): ?string
{
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password must contain letters and numbers.';
    }
    if (strlen($pw) > 72) {
        return 'Password must be 72 characters or fewer.';
    }
    return null;
}

/* ---------- Safe image upload: type-checked, size-limited, re-encoded ---------- */

function handle_image_upload(array $file, string $subdir = 'products'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'Image must be 2 MB or smaller.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'Only JPG, PNG or WEBP images are allowed.'];
    }
    // Re-encode through GD: strips metadata and anything hidden inside the file.
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
    };
    if (!$src) {
        return ['ok' => false, 'error' => 'That image could not be read.'];
    }
    $w = imagesx($src); $h = imagesy($src);
    $max = 1000;
    if ($w > $max || $h > $max) {
        $ratio = min($max / $w, $max / $h);
        $dst = imagecreatetruecolor((int) ($w * $ratio), (int) ($h * $ratio));
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        imagedestroy($src);
        $src = $dst;
    }
    $dir = ROOT . '/public/uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(16)) . ($mime === 'image/png' ? '.png' : '.jpg');
    $ok = $mime === 'image/png' ? imagepng($src, "$dir/$name", 6) : imagejpeg($src, "$dir/$name", 85);
    imagedestroy($src);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not save the image.'];
    }
    return ['ok' => true, 'path' => "uploads/$subdir/$name"];
}
