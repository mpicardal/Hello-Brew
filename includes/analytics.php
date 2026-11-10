<?php
declare(strict_types=1);

/*
 * First-party, privacy-friendly analytics: no third-party scripts, no IPs,
 * no cookies beyond the session. The session id is stored only as a keyed hash.
 * Staff/admin/courier activity is not tracked.
 */
function track(string $event, ?int $productId = null, ?string $meta = null): void
{
    $u = current_user();
    if ($u && $u['role'] !== 'customer') {
        return;
    }
    if (preg_match('/bot|crawl|spider|slurp/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        return;
    }
    if (empty($_SESSION['visitor'])) {
        $_SESSION['visitor'] = bin2hex(random_bytes(16));   // stable across id rotation
    }
    $hash = hash_hmac('sha256', $_SESSION['visitor'], APP_SECRET);
    try {
        q('INSERT INTO analytics_events (session_hash, user_id, event, product_id, meta) VALUES (?,?,?,?,?)',
            [$hash, $u['id'] ?? null, $event, $productId, $meta !== null ? mb_substr($meta, 0, 120) : null]);
    } catch (Throwable $e) {
        error_log('analytics: ' . $e->getMessage());   // analytics must never break a page
    }
}
