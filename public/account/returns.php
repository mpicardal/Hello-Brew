<?php
require __DIR__ . '/../../includes/bootstrap.php';
$me = require_customer();

$rows = q_all('SELECT r.*, o.order_no FROM return_requests r JOIN orders o ON o.id=r.order_id
               WHERE r.user_id = ? ORDER BY r.created_at DESC', [$me['id']]);
$pageTitle = 'Returns & cancellations';
$accTab = 'returns';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad acc-layout">
  <?php require ROOT . '/includes/layout/account_nav.php'; ?>
  <section>
    <h1 class="page-title">Returns &amp; cancellations</h1>
    <p class="muted">To start a request, open the order from <a href="<?= url('account/index.php') ?>">My orders</a>.</p>
    <?php if (!$rows): ?>
      <div class="empty"><p>You haven't made any requests.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Order</th><th>Type</th><th>Requested</th><th>Status</th><th>Refund</th><th>Reply</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><a href="<?= url('account/order.php?no=' . urlencode($r['order_no'])) ?>"><?= e($r['order_no']) ?></a></td>
              <td><?= e(ucfirst($r['type'])) ?></td>
              <td><?= fmt_date($r['created_at']) ?></td>
              <td><?= status_badge($r['status']) ?></td>
              <td><?= $r['refund_amount'] !== null ? money($r['refund_amount']) : '—' ?></td>
              <td class="small"><?= e($r['admin_note'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
