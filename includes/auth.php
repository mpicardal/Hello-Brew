<?php
declare(strict_types=1);

/**
 * Role-based access control. Each permission lists the roles allowed to use it.
 * Customers never reach /admin or /courier; couriers only see orders assigned to them.
 */
const PERMISSIONS = [
    'portal'         => ['admin', 'staff', 'fulfillment'],
    'analytics'      => ['admin', 'staff'],
    'products'       => ['admin', 'staff'],
    'categories'     => ['admin', 'staff'],
    'coupons'        => ['admin'],
    'orders'         => ['admin', 'staff', 'fulfillment'],
    'order_status'   => ['admin', 'staff', 'fulfillment'],
    'assign_courier' => ['admin', 'fulfillment'],
    'returns'        => ['admin', 'staff'],
    'refunds'        => ['admin'],
    'customers'      => ['admin', 'staff'],
    'customer_email' => ['admin', 'staff'],      // fulfillment sees masked emails
    'users'          => ['admin'],
    'reviews'        => ['admin', 'staff'],
    'emails'         => ['admin'],
    'audit'          => ['admin'],
    'courier'        => ['courier'],
];

function current_user(): ?array
{
    static $loadedFor = -1, $cache = null;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($loadedFor === $id) {
        return $cache;
    }
    $loadedFor = $id;
    $cache = null;
    if ($id > 0) {
        $u = q_one('SELECT id, name, email, phone, role, is_active FROM users WHERE id = ?', [$_SESSION['user_id']]);
        if ($u && (int) $u['is_active'] === 1) {
            $cache = $u;
        } else {
            unset($_SESSION['user_id'], $_SESSION['role']);
        }
    }
    return $cache;
}

function can(string $permission): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], PERMISSIONS[$permission] ?? [], true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $u;
}

function require_customer(): array
{
    $u = require_login();
    if ($u['role'] !== 'customer') {
        flash('info', 'Staff accounts cannot shop. Use a customer account.');
        redirect(home_for_role($u['role']));
    }
    return $u;
}

function require_perm(string $permission): array
{
    $u = require_login();
    if (!can($permission)) {
        http_response_code(403);
        audit('access_denied', $permission . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));
        exit('403 — You do not have permission to view this page.');
    }
    return $u;
}

function home_for_role(string $role): string
{
    return match ($role) {
        'admin', 'staff', 'fulfillment' => 'admin/index.php',
        'courier' => 'courier/index.php',
        default => 'account/index.php',
    };
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $_SESSION['rotated_at'] = time();
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
