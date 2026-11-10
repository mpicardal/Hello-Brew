<?php
require __DIR__ . '/../../includes/bootstrap.php';
require ROOT . '/includes/charts.php';
require_perm('portal');

$range = in_array(get('range'), ['7', '30', '90'], true) ? (int) get('range') : 30;
$from = date('Y-m-d 00:00:00', strtotime('-' . ($range - 1) . ' days'));

// Order queue (everyone in the portal)
$queue = [];
foreach (q_all("SELECT status, COUNT(*) n FROM orders GROUP BY status") as $r) {
    $queue[$r['status']] = (int) $r['n'];
}

$analytics = can('analytics');
if ($analytics) {
    $valid = "o.status NOT IN ('cancelled') AND o.created_at >= ?";
    $k = q_one("SELECT COUNT(*) orders, COALESCE(SUM(total),0) gross, COALESCE(AVG(total),0) aov,
                       COUNT(DISTINCT user_id) buyers FROM orders o WHERE $valid", [$from]);
    $refunds = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE kind='refund' AND created_at >= ?", [$from]);
    $newCustomers = (int) q_val("SELECT COUNT(*) FROM users WHERE role='customer' AND created_at >= ?", [$from]);

    // Revenue per day
    $byDay = [];
    for ($i = $range - 1; $i >= 0; $i--) {
        $byDay[date('Y-m-d', strtotime("-$i days"))] = 0.0;
    }
    foreach (q_all("SELECT DATE(created_at) d, SUM(total) t FROM orders o WHERE $valid GROUP BY DATE(created_at)", [$from]) as $r) {
        $byDay[$r['d']] = (float) $r['t'];
    }
    $dayLabels = array_map(fn($d) => date('M j', strtotime($d)), array_keys($byDay));

    $top = q_all("SELECT oi.product_name label, SUM(oi.qty) qty, SUM(oi.line_total) rev
                  FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $valid
                  GROUP BY oi.product_name ORDER BY qty DESC LIMIT 6", [$from]);
    $byCat = q_all("SELECT c.name label, SUM(oi.line_total) value FROM order_items oi
                    JOIN orders o ON o.id=oi.order_id JOIN products p ON p.id=oi.product_id JOIN categories c ON c.id=p.category_id
                    WHERE $valid GROUP BY c.name ORDER BY value DESC", [$from]);

    // Funnel: unique sessions reaching each step
    $funnel = [];
    foreach (['page_view' => 'Visited', 'product_view' => 'Viewed a product', 'add_to_cart' => 'Added to cart', 'begin_checkout' => 'Started checkout', 'purchase' => 'Purchased'] as $ev => $label) {
        $funnel[$ev] = ['label' => $label, 'value' => (int) q_val('SELECT COUNT(DISTINCT session_hash) FROM analytics_events WHERE event=? AND created_at >= ?', [$ev, $from])];
    }
    $visits = max(1, $funnel['page_view']['value']);
    foreach ($funnel as &$f) {
        $f['display'] = $f['value'] . ' · ' . round($f['value'] / $visits * 100) . '%';
    }
    unset($f);
    $carts = $funnel['add_to_cart']['value'];
    $conversion = $funnel['page_view']['value'] ? $funnel['purchase']['value'] / $funnel['page_view']['value'] * 100 : 0;
    $abandon = $carts ? (1 - min($carts, $funnel['purchase']['value']) / $carts) * 100 : 0;
    $checkouts = $funnel['begin_checkout']['value'];
    $checkoutAbandon = $checkouts ? (1 - min($checkouts, $funnel['purchase']['value']) / $checkouts) * 100 : 0;

    $searches = q_all("SELECT SUBSTRING(meta,3) term, COUNT(*) n FROM analytics_events WHERE event='search' AND created_at >= ?
                       GROUP BY meta ORDER BY n DESC LIMIT 6", [$from]);
}
$lowStock = can('products') ? q_all('SELECT id, name, stock FROM products WHERE is_archived=0 AND stock <= ? ORDER BY stock, name LIMIT 10', [LOW_STOCK_THRESHOLD]) : [];
$recent = q_all('SELECT id, order_no, billing_name, total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 6');

$pageTitle = 'Dashboard';
$adminTab = 'dashboard';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head">
  <h1>Dashboard</h1>
  <?php if ($analytics): ?>
    <div class="btn-row">
      <nav class="seg" aria-label="Date range">
        <?php foreach ([7, 30, 90] as $r): ?><a href="?range=<?= $r ?>"<?= $range === $r ? ' aria-current="page"' : '' ?>><?= $r ?> days</a><?php endforeach; ?>
      </nav>
      <a class="btn btn-tan btn-sm" href="<?= url('admin/export.php?range=' . $range) ?>">Export CSV</a>
    </div>
  <?php endif; ?>
</div>

<div class="kpis">
  <?php foreach (['pending' => 'New orders', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'packed' => 'Packed', 'out_for_delivery' => 'Out for delivery'] as $s => $l): ?>
    <a class="kpi kpi-link" href="<?= url('admin/orders.php?status=' . $s) ?>"><span><?= $l ?></span><strong><?= $queue[$s] ?? 0 ?></strong></a>
  <?php endforeach; ?>
</div>

<?php if ($analytics): ?>
  <h2 class="admin-sub">Sales · last <?= $range ?> days</h2>
  <div class="kpis">
    <div class="kpi"><span>Gross sales</span><strong><?= money($k['gross']) ?></strong></div>
    <div class="kpi"><span>Refunds</span><strong><?= money($refunds) ?></strong></div>
    <div class="kpi"><span>Net revenue</span><strong><?= money((float) $k['gross'] - $refunds) ?></strong></div>
    <div class="kpi"><span>Orders</span><strong><?= (int) $k['orders'] ?></strong></div>
    <div class="kpi"><span>Avg. order value</span><strong><?= money($k['aov']) ?></strong></div>
    <div class="kpi"><span>New customers</span><strong><?= $newCustomers ?></strong></div>
  </div>

  <div class="panel">
    <h3>Revenue per day</h3>
    <?= column_chart($dayLabels, array_values($byDay), 'Revenue per day', '₱') ?>
  </div>

  <div class="panel-grid">
    <div class="panel">
      <h3>Conversion funnel</h3>
      <?= hbar_chart(array_values($funnel), 'Conversion funnel', '', 'bar bar-alt') ?>
      <div class="mini-kpis">
        <div><span>Conversion rate</span><strong><?= number_format($conversion, 1) ?>%</strong></div>
        <div><span>Cart abandonment</span><strong><?= number_format($abandon, 1) ?>%</strong></div>
        <div><span>Checkout abandonment</span><strong><?= number_format($checkoutAbandon, 1) ?>%</strong></div>
      </div>
    </div>
    <div class="panel">
      <h3>Top products (units sold)</h3>
      <?php if ($top): ?>
        <?= hbar_chart(array_map(fn($t) => ['label' => $t['label'], 'value' => (float) $t['qty'], 'display' => (int) $t['qty'] . ' · ' . money($t['rev'])], $top), 'Top products') ?>
      <?php else: ?><p class="muted">No sales in this period yet.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h3>Sales by category</h3>
      <?php if ($byCat): ?>
        <?= hbar_chart(array_map(fn($c) => ['label' => $c['label'], 'value' => (float) $c['value'], 'display' => money($c['value'])], $byCat), 'Sales by category') ?>
      <?php else: ?><p class="muted">No sales in this period yet.</p><?php endif; ?>
    </div>
    <div class="panel">
      <h3>Top searches</h3>
      <?php if ($searches): ?>
        <ul class="plain-list"><?php foreach ($searches as $s): ?><li><span><?= e($s['term']) ?></span><strong><?= (int) $s['n'] ?></strong></li><?php endforeach; ?></ul>
      <?php else: ?><p class="muted">No searches yet.</p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="panel-grid">
  <?php if (can('products')): ?>
  <div class="panel">
    <h3>Low-stock alerts <span class="muted small">(≤ <?= LOW_STOCK_THRESHOLD ?>)</span></h3>
    <?php if (!$lowStock): ?><p class="muted">All items are well stocked.</p><?php else: ?>
      <ul class="plain-list">
        <?php foreach ($lowStock as $p): ?>
          <li><a href="<?= url('admin/product_form.php?id=' . (int) $p['id']) ?>"><?= e($p['name']) ?></a><span class="badge <?= (int) $p['stock'] === 0 ? 'badge-cancelled' : 'badge-pending' ?>"><?= (int) $p['stock'] ?> left</span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="panel">
    <h3>Latest orders</h3>
    <ul class="plain-list">
      <?php foreach ($recent as $o): ?>
        <li><a href="<?= url('admin/order.php?id=' . (int) $o['id']) ?>"><?= e($o['order_no']) ?></a><span class="muted small"><?= fmt_date($o['created_at'], 'M j, g:i A') ?></span><?= status_badge($o['status']) ?></li>
      <?php endforeach; ?>
      <?php if (!$recent): ?><li class="muted">No orders yet.</li><?php endif; ?>
    </ul>
  </div>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
