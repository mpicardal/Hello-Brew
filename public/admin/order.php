<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('orders');

$id = int_param($_GET, 'id') ?: int_param($_POST, 'id');
$o = q_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $note = mb_substr(post('note'), 0, 255);
    if ($action === 'advance' && can('order_status')) {
        $to = post('to');
        if (!in_array($to, ORDER_FLOW[$o['status']] ?? [], true)) {
            flash('error', 'That status change is not allowed.');
        } elseif ($to === 'out_for_delivery' && !$o['courier_id']) {
            flash('error', 'Assign a courier before sending the order out.');
        } else {
            set_order_status($id, $to, $note);
            flash('success', 'Order moved to ' . status_label($to) . '. The customer was emailed.');
        }
    } elseif ($action === 'assign' && can('assign_courier')) {
        $cid = int_param($_POST, 'courier_id');
        if ($cid === 0 || q_val("SELECT id FROM users WHERE id=? AND role='courier' AND is_active=1", [$cid])) {
            q('UPDATE orders SET courier_id = ? WHERE id = ?', [$cid ?: null, $id]);
            q('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?,?,?,?)',
                [$id, $o['status'], $cid ? 'Courier assigned' : 'Courier unassigned', current_user()['id']]);
            audit('assign_courier', "order #$id courier $cid");
            flash('success', 'Courier updated.');
        }
    } elseif ($action === 'cancel' && can('order_status')) {
        $paid = in_array($o['payment_status'], ['paid', 'partially_refunded'], true);
        if ($paid && !can('refunds')) {
            flash('error', 'This order is paid — an admin must cancel it so the refund can be issued.');
        } elseif (!in_array($o['status'], ['pending', 'confirmed', 'preparing', 'packed'], true)) {
            flash('error', 'This order can no longer be cancelled.');
        } else {
            cancel_order($id, $note ?: 'Cancelled by Hello Brew!');
            flash('success', 'Order cancelled, stock restored' . ($paid ? ' and refund issued.' : '.'));
        }
    }
    redirect('admin/order.php?id=' . $id);
}

$items = q_all('SELECT * FROM order_items WHERE order_id = ?', [$id]);
$history = q_all('SELECT h.*, u.name AS by_name FROM order_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE order_id=? ORDER BY h.created_at, h.id', [$id]);
$payments = q_all('SELECT * FROM payments WHERE order_id=? ORDER BY id', [$id]);
$couriers = q_all("SELECT id, name FROM users WHERE role='courier' AND is_active=1 ORDER BY name");
$requests = q_all('SELECT * FROM return_requests WHERE order_id=? ORDER BY id DESC', [$id]);
$showEmail = can('customer_email');

$pageTitle = 'Order ' . $o['order_no'];
$adminTab = 'orders';
require ROOT . '/includes/layout/admin_header.php';
?>
<a class="link-btn" href="<?= url('admin/orders.php') ?>"><?= icon('arrow-l', 16) ?> Orders</a>
<div class="admin-head">
  <h1>Order <?= e($o['order_no']) ?></h1>
  <div><?= status_badge($o['status']) ?> <?= status_badge($o['payment_status']) ?></div>
</div>

<div class="panel-grid">
  <div class="panel">
    <h3>Items</h3>
    <table class="table compact">
      <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($items as $i): ?>
          <tr><td><?= e($i['product_name']) ?><?= $i['variant_name'] ? ' <span class="muted small">(' . e($i['variant_name']) . ')</span>' : '' ?></td><td><?= (int) $i['qty'] ?></td><td><?= money($i['unit_price']) ?></td><td><?= money($i['line_total']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <dl class="totals">
      <div><dt>Subtotal</dt><dd><?= money($o['subtotal']) ?></dd></div>
      <?php if ((float) $o['discount'] > 0): ?><div><dt>Discount <?= e($o['coupon_code']) ?></dt><dd>−<?= money($o['discount']) ?></dd></div><?php endif; ?>
      <div><dt>Delivery</dt><dd><?= money($o['delivery_fee']) ?></dd></div>
      <div class="total"><dt>Total</dt><dd><?= money($o['total']) ?></dd></div>
    </dl>
  </div>

  <div class="panel">
    <h3>Customer &amp; shipping</h3>
    <p><strong><?= e($o['billing_name']) ?></strong><br>
      <?= e($showEmail ? $o['billing_email'] : mask_email($o['billing_email'])) ?> · <?= e($o['billing_phone']) ?></p>
    <p><strong>Deliver to:</strong> <?= e($o['ship_recipient']) ?> · <?= e($o['ship_phone']) ?><br>
      <?= e($o['ship_line1']) ?><?= $o['ship_line2'] ? ', ' . e($o['ship_line2']) : '' ?><br><?= e($o['ship_city']) ?>, <?= e($o['ship_province']) ?> <?= e($o['ship_postal']) ?></p>
    <?php if ($o['notes']): ?><p class="notice small">Rider note: <?= e($o['notes']) ?></p><?php endif; ?>
    <p><strong>Payment:</strong> <?= e(payment_label($o['payment_method'])) ?><?= $o['card_last4'] ? ' •••• ' . e($o['card_last4']) : '' ?></p>
    <?php if (can('customers')): ?><a class="link-btn" href="<?= url('admin/customers.php?id=' . (int) $o['user_id']) ?>">View customer →</a><?php endif; ?>
  </div>

  <div class="panel">
    <h3>Fulfilment</h3>
    <?php $next = ORDER_FLOW[$o['status']] ?? []; ?>
    <?php if ($next && can('order_status')): ?>
      <form class="stack" method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="advance">
        <label>Note to history (optional)<input name="note" maxlength="255"></label>
        <?php foreach ($next as $to): ?>
          <button class="btn btn-dark" type="submit" name="to" value="<?= e($to) ?>">Mark as <?= e(strtolower(status_label($to))) ?></button>
        <?php endforeach; ?>
      </form>
    <?php else: ?>
      <p class="muted">No further status changes for this order.</p>
    <?php endif; ?>

    <?php if (can('assign_courier') && !in_array($o['status'], ['delivered', 'cancelled', 'returned'], true)): ?>
      <form class="inline-form" method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="assign">
        <label class="grow">Courier
          <select name="courier_id">
            <option value="0">— Unassigned —</option>
            <?php foreach ($couriers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $o['courier_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-tan btn-sm" type="submit">Assign</button>
      </form>
      <?php if (!$couriers): ?><p class="muted small">No courier accounts yet. An admin can add one under Staff &amp; roles.</p><?php endif; ?>
    <?php endif; ?>

    <?php if (in_array($o['status'], ['pending', 'confirmed', 'preparing', 'packed'], true) && can('order_status')): ?>
      <form class="inline-form" method="post" data-confirm="Cancel this order, restore stock<?= $o['payment_status'] === 'paid' ? ' and refund the customer' : '' ?>?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="cancel">
        <label class="grow">Cancellation reason<input name="note" maxlength="255"></label>
        <button class="btn btn-danger btn-sm" type="submit">Cancel order</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>History</h3>
    <ul class="history">
      <?php foreach ($history as $h): ?>
        <li><strong><?= e(status_label($h['status'])) ?></strong> <span class="muted small"><?= fmt_date($h['created_at']) ?><?= $h['by_name'] ? ' · ' . e($h['by_name']) : '' ?></span><?= $h['note'] ? '<br><small>' . e($h['note']) . '</small>' : '' ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (can('refunds') && $payments): ?>
      <h3>Payments</h3>
      <ul class="plain-list">
        <?php foreach ($payments as $p): ?><li><span><?= e(ucfirst($p['kind'])) ?> · <?= e($p['reference']) ?></span><strong><?= $p['kind'] === 'refund' ? '−' : '' ?><?= money($p['amount']) ?></strong></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($requests): ?>
      <h3>Customer requests</h3>
      <?php foreach ($requests as $r): ?><p><?= status_badge($r['status']) ?> <?= e(ucfirst($r['type'])) ?> — <?= e($r['reason']) ?> <?php if (can('returns')): ?><a href="<?= url('admin/returns.php') ?>">Handle →</a><?php endif; ?></p><?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
