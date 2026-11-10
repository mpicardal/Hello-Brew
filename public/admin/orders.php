<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('orders');

$status = get('status');
$search = mb_substr(get('q'), 0, 60);
$where = ['1=1']; $params = [];
if (array_key_exists($status, ORDER_FLOW) || in_array($status, ['delivered', 'cancelled', 'returned'], true)) {
    $where[] = 'o.status = ?'; $params[] = $status;
}
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(o.order_no LIKE ? OR o.billing_name LIKE ? OR o.ship_city LIKE ?)';
    array_push($params, $like, $like, $like);
}
$page = max(1, int_param($_GET, 'page', 1));
$per = 25;
$total = (int) q_val('SELECT COUNT(*) FROM orders o WHERE ' . implode(' AND ', $where), $params);
$orders = q_all('SELECT o.*, c.name AS courier, (SELECT SUM(qty) FROM order_items WHERE order_id=o.id) items
                 FROM orders o LEFT JOIN users c ON c.id=o.courier_id
                 WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $params);
$pages = max(1, (int) ceil($total / $per));

$pageTitle = 'Orders';
$adminTab = 'orders';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Orders</h1></div>
<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Order no., customer or city" maxlength="60">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach (['pending', 'confirmed', 'preparing', 'packed', 'out_for_delivery', 'delivered', 'cancelled', 'returned'] as $s): ?>
      <option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-dark btn-sm" type="submit">Filter</button>
</form>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Order</th><th>Placed</th><th>Customer</th><th>City</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Courier</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><a href="<?= url('admin/order.php?id=' . (int) $o['id']) ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
          <td><?= fmt_date($o['created_at'], 'M j, g:i A') ?></td>
          <td><?= e($o['billing_name']) ?></td>
          <td><?= e($o['ship_city']) ?></td>
          <td><?= (int) $o['items'] ?></td>
          <td><?= money($o['total']) ?></td>
          <td><?= status_badge($o['payment_status']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td><?= e($o['courier'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="9" class="muted">No orders found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="?<?= e(http_build_query(['status' => $status, 'q' => $search, 'page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
