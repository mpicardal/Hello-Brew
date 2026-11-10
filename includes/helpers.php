<?php
declare(strict_types=1);

/** Escape for HTML output. Use on every value that reaches a page. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function product_img(?string $image): string
{
    if (!$image) {
        return asset('img/products/placeholder.svg');
    }
    return url($image);
}

function money($amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#', $path) ? url('') : url($path);
    header('Location: ' . $target);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function int_param(array $src, string $key, int $default = 0): int
{
    $v = $src[$key] ?? null;
    return (is_string($v) && preg_match('/^-?\d{1,9}$/', $v)) ? (int) $v : $default;
}

function slugify(string $text): string
{
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $text !== '' ? $text : bin2hex(random_bytes(4));
}

function status_label(string $s): string
{
    return [
        'pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing',
        'packed' => 'Packed', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered',
        'cancelled' => 'Cancelled', 'returned' => 'Returned', 'approved' => 'Approved',
        'rejected' => 'Rejected', 'refunded' => 'Refunded', 'unpaid' => 'Unpaid', 'paid' => 'Paid',
        'partially_refunded' => 'Partly refunded', 'failed' => 'Failed',
    ][$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function status_badge(string $s): string
{
    return '<span class="badge badge-' . e($s) . '">' . e(status_label($s)) . '</span>';
}

function payment_label(string $m): string
{
    return ['card' => 'Card (mock gateway)', 'ewallet' => 'E-wallet (mock)', 'cod' => 'Cash on delivery'][$m] ?? $m;
}

function audit(string $action, string $details = ''): void
{
    $u = current_user();
    q('INSERT INTO audit_log (user_id, action, details) VALUES (?,?,?)', [$u['id'] ?? null, $action, mb_substr($details, 0, 500)]);
}

/** Mask an email for roles that only need to recognise, not contact, the customer. */
function mask_email(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($name, 0, 2) . str_repeat('•', max(1, mb_strlen($name) - 2)) . '@' . $domain;
}

function fmt_date(?string $dt, string $format = 'M j, Y g:i A'): string
{
    return $dt ? date($format, strtotime($dt)) : '—';
}

function stars(float $rating): string
{
    $full = (int) round($rating);
    return '<span class="stars" aria-label="' . e(number_format($rating, 1)) . ' out of 5">'
        . str_repeat('★', $full) . '<span class="stars-off">' . str_repeat('★', 5 - $full) . '</span></span>';
}
