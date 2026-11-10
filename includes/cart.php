<?php
declare(strict_types=1);

/*
 * The cart lives in the server-side session, so it survives page navigation
 * and can't be edited by the browser. Prices are always re-read from the DB.
 * Shape: $_SESSION['cart'] = ['<productId>:<variantId>' => qty]
 */

function cart_raw(): array
{
    return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
}

function cart_add(int $productId, int $variantId, int $qty): bool
{
    $qty = max(1, min(50, $qty));
    $p = q_one('SELECT id, stock FROM products WHERE id = ? AND is_archived = 0', [$productId]);
    if (!$p) {
        return false;
    }
    if ($variantId > 0 && !q_val('SELECT id FROM product_variants WHERE id = ? AND product_id = ? AND is_archived = 0', [$variantId, $productId])) {
        return false;
    }
    $key = $productId . ':' . $variantId;
    $cart = cart_raw();
    $cart[$key] = min(50, ($cart[$key] ?? 0) + $qty);
    $_SESSION['cart'] = $cart;
    return true;
}

function cart_set(string $key, int $qty): void
{
    if (!preg_match('/^\d+:\d+$/', $key)) {
        return;
    }
    $cart = cart_raw();
    if ($qty <= 0) {
        unset($cart[$key]);
    } elseif (isset($cart[$key])) {
        $cart[$key] = min(50, $qty);
    }
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    unset($_SESSION['cart'], $_SESSION['coupon']);
}

function cart_count(): int
{
    return array_sum(cart_raw());
}

/** Detailed lines with live prices and stock. */
function cart_lines(): array
{
    $lines = [];
    foreach (cart_raw() as $key => $qty) {
        [$pid, $vid] = array_map('intval', explode(':', (string) $key));
        $p = q_one('SELECT p.id, p.name, p.slug, p.price, p.stock, p.image, c.name AS category
                    FROM products p JOIN categories c ON c.id = p.category_id
                    WHERE p.id = ? AND p.is_archived = 0', [$pid]);
        if (!$p) {
            continue;
        }
        $v = $vid ? q_one('SELECT id, name, price_delta FROM product_variants WHERE id = ? AND product_id = ? AND is_archived = 0', [$vid, $pid]) : null;
        if ($vid && !$v) {
            continue;
        }
        $unit = (float) $p['price'] + (float) ($v['price_delta'] ?? 0);
        $lines[] = [
            'key' => $key, 'product_id' => $pid, 'variant_id' => $v ? (int) $v['id'] : null,
            'name' => $p['name'], 'slug' => $p['slug'], 'variant' => $v['name'] ?? null,
            'image' => $p['image'], 'category' => $p['category'], 'stock' => (int) $p['stock'],
            'unit' => $unit, 'qty' => (int) $qty, 'total' => round($unit * $qty, 2),
        ];
    }
    return $lines;
}

/** Validate a coupon code against the subtotal. Returns [coupon|null, error|null]. */
function coupon_check(string $code, float $subtotal): array
{
    $c = q_one('SELECT * FROM coupons WHERE code = ? AND is_active = 1', [strtoupper($code)]);
    if (!$c) {
        return [null, 'That coupon code is not valid.'];
    }
    if ($c['expires_at'] && strtotime($c['expires_at']) < time()) {
        return [null, 'That coupon has expired.'];
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return [null, 'That coupon has reached its usage limit.'];
    }
    if ($subtotal < (float) $c['min_subtotal']) {
        return [null, 'This coupon needs a subtotal of at least ' . money($c['min_subtotal']) . '.'];
    }
    return [$c, null];
}

function cart_totals(array $lines): array
{
    $subtotal = round(array_sum(array_column($lines, 'total')), 2);
    $discount = 0.0;
    $coupon = null;
    if (!empty($_SESSION['coupon'])) {
        [$coupon, $err] = coupon_check($_SESSION['coupon'], $subtotal);
        if ($coupon) {
            $discount = $coupon['type'] === 'percent'
                ? round($subtotal * (float) $coupon['value'] / 100, 2)
                : min($subtotal, (float) $coupon['value']);
        } else {
            unset($_SESSION['coupon']);
        }
    }
    $delivery = ($subtotal === 0.0 || $subtotal - $discount >= FREE_DELIVERY_MIN) ? 0.0 : DELIVERY_FEE;
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'delivery' => $delivery,
        'total'    => round($subtotal - $discount + $delivery, 2),
        'coupon'   => $coupon,
    ];
}
