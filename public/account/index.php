<?php
require __DIR__ . '/../../includes/bootstrap.php';
$me = require_customer();

$orders = q_all('SELECT o.id, o.order_no, o.status, o.total, o.payment_status, o.created_at,
                   (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS items
                 FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC', [$me['id']]);
$spent = array_sum(array_map(fn($o) => in_array($o['status'], ['cancelled', 'returned'], true) ? 0 : (float) $o['total'], $orders));
$activeCount = count(array_filter($orders, fn($o) => in_array($o['status'], ['pending', 'confirmed', 'preparing', 'packed', 'out_for_delivery'], true)));

$pageTitle = 'My orders';
$accTab = 'orders';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad acc-layout">
  <?php require ROOT . '/includes/layout/account_nav.php'; ?>
  <section>
    <h1 class="page-title">My orders</h1>
    <div class="stat-row">
      <div class="stat"><span>Orders placed</span><strong><?= count($orders) ?></strong></div>
      <div class="stat"><span>In progress</span><strong><?= $activeCount ?></strong></div>
      <div class="stat"><span>Total spent</span><strong><?= money($spent) ?></strong></div>
    </div>
    <?php if (!$orders): ?>
      <div class="empty"><p>No orders yet.</p><a class="btn btn-dark" href="<?= url('shop.php') ?>">Start your first order</a></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table orders-table">
          <thead><tr><th>Order</th><th>Purchased</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="ot-no"><strong><?= e($o['order_no']) ?></strong></td>
              <td class="ot-date"><?= fmt_date($o['created_at']) ?></td>
              <td class="ot-items"><?= (int) $o['items'] ?></td>
              <td class="ot-total"><?= money($o['total']) ?></td>
              <td class="ot-status"><?= status_badge($o['status']) ?></td>
              <td class="ot-view"><a class="btn btn-tan btn-sm" href="<?= url('account/order.php?no=' . urlencode($o['order_no'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
