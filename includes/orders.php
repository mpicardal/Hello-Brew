<?php
declare(strict_types=1);

/** Allowed forward moves for staff. Cancelling is handled separately (restock + refund). */
const ORDER_FLOW = [
    'pending'          => ['confirmed'],
    'confirmed'        => ['preparing'],
    'preparing'        => ['packed'],
    'packed'           => ['out_for_delivery'],
    'out_for_delivery' => ['delivered'],
];

function set_order_status(int $orderId, string $new, string $note = ''): void
{
    $o = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    q('UPDATE orders SET status = ? WHERE id = ?', [$new, $orderId]);
    q('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?,?,?,?)',
        [$orderId, $new, $note !== '' ? mb_substr($note, 0, 255) : null, current_user()['id'] ?? null]);
    // Cash on delivery is collected by the rider on delivery.
    if ($new === 'delivered' && $o['payment_method'] === 'cod' && $o['payment_status'] === 'unpaid') {
        q("UPDATE orders SET payment_status = 'paid' WHERE id = ?", [$orderId]);
        q('INSERT INTO payments (order_id, kind, method, amount, status, reference) VALUES (?,?,?,?,?,?)',
            [$orderId, 'charge', 'cod', $o['total'], 'success', 'COD-' . strtoupper(bin2hex(random_bytes(4)))]);
    }
    $extra = match ($new) {
        'out_for_delivery' => 'Your rider is on the way! Please keep your phone nearby.',
        'delivered'        => 'Enjoy your order! You can leave a review from your order page.',
        default            => '',
    };
    email_order_event($orderId, 'status', $extra);
    audit('order_status', "order #$orderId → $new");
}

function restock_order(int $orderId): void
{
    foreach (q_all('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL', [$orderId]) as $i) {
        q('UPDATE products SET stock = stock + ? WHERE id = ?', [$i['qty'], $i['product_id']]);
    }
}

function refunded_so_far(int $orderId): float
{
    return (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id=? AND kind='refund' AND status='success'", [$orderId]);
}

/** Refund through the mock gateway and update the order's payment status. */
function refund_order(int $orderId, float $amount, string $note = ''): ?string
{
    $o = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    $max = round((float) $o['total'] - refunded_so_far($orderId), 2);
    $amount = round(min($amount, $max), 2);
    if ($amount <= 0 || !in_array($o['payment_status'], ['paid', 'partially_refunded'], true)) {
        return null;
    }
    $ref = MockGateway::refund($orderId, $o['payment_method'], $amount);
    $status = refunded_so_far($orderId) >= (float) $o['total'] - 0.009 ? 'refunded' : 'partially_refunded';
    q('UPDATE orders SET payment_status = ? WHERE id = ?', [$status, $orderId]);
    email_order_event($orderId, 'refund', 'We refunded ' . money($amount) . ' to your original payment method (reference ' . $ref . ").\n"
        . 'It may take 3–7 business days to appear.' . ($note ? "\n\nNote from our team: $note" : ''));
    audit('refund', "order #$orderId " . money($amount) . " $ref");
    return $ref;
}

function cancel_order(int $orderId, string $note): void
{
    $o = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (in_array($o['status'], ['cancelled', 'returned', 'delivered', 'out_for_delivery'], true)) {
        return;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        restock_order($orderId);
        q("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$orderId]);
        q('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?,?,?,?)',
            [$orderId, 'cancelled', mb_substr($note, 0, 255) ?: null, current_user()['id'] ?? null]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    email_order_event($orderId, 'cancelled', $note ? "Reason: $note" : '');
    if (in_array($o['payment_status'], ['paid', 'partially_refunded'], true)) {
        refund_order($orderId, (float) $o['total'] - refunded_so_far($orderId));
    }
    audit('order_cancel', "order #$orderId");
}
