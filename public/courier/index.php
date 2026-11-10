<?php
require __DIR__ . '/../../includes/bootstrap.php';
$me = require_perm('courier');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Couriers can only move orders assigned to them, one step at a time.
    $o = q_one('SELECT id, status FROM orders WHERE id = ? AND courier_id = ?', [int_param($_POST, 'id'), $me['id']]);
    $to = post('to');
    $allowed = ['packed' => 'out_for_delivery', 'out_for_delivery' => 'delivered'];
    if (!$o || ($allowed[$o['status']] ?? null) !== $to) {
        flash('error', 'That update isn’t allowed for this order.');
    } else {
        set_order_status((int) $o['id'], $to, mb_substr(post('note'), 0, 255) ?: ($to === 'delivered' ? 'Delivered by rider' : 'Picked up by rider'));
        flash('success', $to === 'delivered' ? 'Marked as delivered. Nice work!' : 'Marked as out for delivery. The customer was notified.');
    }
    redirect('courier/index.php');
}

// Only what a rider needs: recipient, phone, address, notes and cash to collect. No emails.
$active = q_all("SELECT id, order_no, status, total, payment_method, payment_status, ship_recipient, ship_phone, ship_line1, ship_line2,
                        ship_city, ship_province, ship_postal, notes, created_at,
                        (SELECT SUM(qty) FROM order_items WHERE order_id = orders.id) items
                 FROM orders WHERE courier_id = ? AND status IN ('confirmed','preparing','packed','out_for_delivery')
                 ORDER BY FIELD(status,'out_for_delivery','packed','preparing','confirmed'), created_at", [$me['id']]);
$done = q_all("SELECT o.order_no, o.ship_city, o.total, h.created_at FROM orders o
               JOIN order_status_history h ON h.order_id = o.id AND h.status = 'delivered'
               WHERE o.courier_id = ? AND o.status = 'delivered' ORDER BY h.created_at DESC LIMIT 15", [$me['id']]);
$todayCount = (int) q_val("SELECT COUNT(*) FROM orders o JOIN order_status_history h ON h.order_id=o.id AND h.status='delivered'
                          WHERE o.courier_id = ? AND DATE(h.created_at) = CURDATE()", [$me['id']]);
$cashToCollect = array_sum(array_map(fn($o) => $o['payment_method'] === 'cod' && $o['payment_status'] === 'unpaid' ? (float) $o['total'] : 0, $active));

$pageTitle = 'My deliveries';
$adminTab = 'deliveries';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>My deliveries</h1></div>
<div class="kpis">
  <div class="kpi"><span>Assigned to you</span><strong><?= count($active) ?></strong></div>
  <div class="kpi"><span>Delivered today</span><strong><?= $todayCount ?></strong></div>
  <div class="kpi"><span>Cash to collect (COD)</span><strong><?= money($cashToCollect) ?></strong></div>
</div>

<?php if (!$active): ?><div class="panel"><p class="muted">No deliveries assigned to you right now.</p></div><?php endif; ?>
<div class="panel-grid">
  <?php foreach ($active as $o): ?>
    <div class="panel courier-card">
      <div class="admin-head"><h3><?= e($o['order_no']) ?></h3><?= status_badge($o['status']) ?></div>
      <p><strong><?= e($o['ship_recipient']) ?></strong> · <a href="tel:<?= e($o['ship_phone']) ?>"><?= e($o['ship_phone']) ?></a><br>
        <?= e($o['ship_line1']) ?><?= $o['ship_line2'] ? ', ' . e($o['ship_line2']) : '' ?><br><?= e($o['ship_city']) ?>, <?= e($o['ship_province']) ?> <?= e($o['ship_postal']) ?></p>
      <?php if ($o['notes']): ?><p class="notice small"><?= icon('pin', 16) ?> <?= e($o['notes']) ?></p><?php endif; ?>
      <p class="small"><?= (int) $o['items'] ?> item(s) ·
        <?= $o['payment_method'] === 'cod' && $o['payment_status'] === 'unpaid' ? '<strong>Collect ' . money($o['total']) . ' cash</strong>' : 'Already paid' ?></p>
      <?php if ($o['status'] === 'packed' || $o['status'] === 'out_for_delivery'): $to = $o['status'] === 'packed' ? 'out_for_delivery' : 'delivered'; ?>
        <form class="inline-form" method="post"<?= $to === 'delivered' ? ' data-confirm="Confirm the order was handed to the customer?"' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="to" value="<?= $to ?>">
          <label class="grow">Note (optional)<input name="note" maxlength="255" placeholder="<?= $to === 'delivered' ? 'Left with guard…' : 'ETA 20 mins' ?>"></label>
          <button class="btn btn-dark btn-sm" type="submit"><?= $to === 'delivered' ? 'Confirm delivery' : 'Picked up — start delivery' ?></button>
        </form>
      <?php else: ?>
        <p class="muted small">Still being prepared at the café. You can pick it up once it’s packed.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h3>Recently delivered</h3>
  <ul class="plain-list">
    <?php foreach ($done as $d): ?><li><span><?= e($d['order_no']) ?> · <?= e($d['ship_city']) ?></span><span class="muted small"><?= fmt_date($d['created_at']) ?></span></li><?php endforeach; ?>
    <?php if (!$done): ?><li class="muted">Nothing yet.</li><?php endif; ?>
  </ul>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
