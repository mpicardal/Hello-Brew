<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('customers');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'toggle' && can('users')) {
    $id = int_param($_POST, 'id');
    q("UPDATE users SET is_active = 1 - is_active WHERE id = ? AND role = 'customer'", [$id]);
    audit('customer_toggle', "user #$id");
    flash('success', 'Customer account updated.');
    redirect('admin/customers.php?id=' . $id);
}

$showEmail = can('customer_email');
$id = int_param($_GET, 'id');

if ($id) {
    $c = q_one("SELECT id, name, email, phone, is_active, created_at, last_login_at FROM users WHERE id = ? AND role = 'customer'", [$id]);
    if (!$c) {
        flash('error', 'Customer not found.');
        redirect('admin/customers.php');
    }
    audit('view_customer', "user #$id");   // record who looked at personal data
    $orders = q_all('SELECT id, order_no, status, total, payment_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC', [$id]);
    $addresses = q_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC', [$id]);
    $requests = q_all('SELECT r.*, o.order_no FROM return_requests r JOIN orders o ON o.id = r.order_id WHERE r.user_id = ? ORDER BY r.created_at DESC', [$id]);
    $spent = array_sum(array_map(fn($o) => in_array($o['status'], ['cancelled', 'returned'], true) ? 0 : (float) $o['total'], $orders));

    $pageTitle = $c['name'];
    $adminTab = 'customers';
    require ROOT . '/includes/layout/admin_header.php';
    ?>
    <a class="link-btn" href="<?= url('admin/customers.php') ?>"><?= icon('arrow-l', 16) ?> Customers</a>
    <div class="admin-head">
      <h1><?= e($c['name']) ?></h1>
      <?= $c['is_active'] ? status_badge('confirmed') : '<span class="badge badge-cancelled">Disabled</span>' ?>
    </div>
    <div class="kpis">
      <div class="kpi"><span>Orders</span><strong><?= count($orders) ?></strong></div>
      <div class="kpi"><span>Lifetime spend</span><strong><?= money($spent) ?></strong></div>
      <div class="kpi"><span>Member since</span><strong><?= fmt_date($c['created_at'], 'M Y') ?></strong></div>
      <div class="kpi"><span>Last sign-in</span><strong><?= $c['last_login_at'] ? fmt_date($c['last_login_at'], 'M j') : '—' ?></strong></div>
    </div>
    <div class="panel-grid">
      <div class="panel">
        <h3>Contact</h3>
        <p><?= e($showEmail ? $c['email'] : mask_email($c['email'])) ?><br><?= e($c['phone'] ?? 'No phone') ?></p>
        <h3>Addresses</h3>
        <?php foreach ($addresses as $a): ?>
          <p class="small"><strong><?= e($a['label']) ?></strong><?= $a['is_default'] ? ' (default)' : '' ?><br><?= e($a['line1']) ?>, <?= e($a['city']) ?>, <?= e($a['province']) ?> <?= e($a['postal_code']) ?></p>
        <?php endforeach; ?>
        <?php if (!$addresses): ?><p class="muted small">No saved addresses.</p><?php endif; ?>
        <?php if (can('users')): ?>
          <form method="post" data-confirm="<?= $c['is_active'] ? 'Disable this account? They will be signed out and unable to log in.' : 'Re-enable this account?' ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn <?= $c['is_active'] ? 'btn-danger' : 'btn-dark' ?> btn-sm" type="submit"><?= $c['is_active'] ? 'Disable account' : 'Enable account' ?></button>
          </form>
        <?php endif; ?>
      </div>
      <div class="panel">
        <h3>Support history</h3>
        <?php if (!$requests): ?><p class="muted">No cancellation or return requests.</p><?php endif; ?>
        <ul class="plain-list">
          <?php foreach ($requests as $r): ?>
            <li><span><?= e(ucfirst($r['type'])) ?> · <?= e($r['order_no']) ?><br><small class="muted"><?= e(mb_strimwidth($r['reason'], 0, 70, '…')) ?></small></span><?= status_badge($r['status']) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="panel">
      <h3>Order history</h3>
      <table class="table compact">
        <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr><td><a href="<?= url('admin/order.php?id=' . (int) $o['id']) ?>"><?= e($o['order_no']) ?></a></td><td><?= fmt_date($o['created_at']) ?></td><td><?= money($o['total']) ?></td><td><?= status_badge($o['payment_status']) ?></td><td><?= status_badge($o['status']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$orders): ?><tr><td colspan="5" class="muted">No orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    require ROOT . '/includes/layout/admin_footer.php';
    exit;
}

$search = mb_substr(get('q'), 0, 60);
$where = "u.role = 'customer'";
$params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params = [$like, $like];
}
$page = max(1, int_param($_GET, 'page', 1));
$per = 30;
$total = (int) q_val("SELECT COUNT(*) FROM users u WHERE $where", $params);
$rows = q_all("SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                 (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) orders,
                 (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status NOT IN ('cancelled','returned')) spent,
                 (SELECT MAX(created_at) FROM orders o WHERE o.user_id = u.id) last_order
               FROM users u WHERE $where ORDER BY spent DESC, u.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
$pages = max(1, (int) ceil($total / $per));

$pageTitle = 'Customers';
$adminTab = 'customers';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Customers</h1><span class="muted"><?= $total ?> accounts</span></div>
<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name or email" maxlength="60">
  <button class="btn btn-dark btn-sm" type="submit">Search</button>
</form>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Customer</th><th>Email</th><th>Joined</th><th>Orders</th><th>Spent</th><th>Last order</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= url('admin/customers.php?id=' . (int) $r['id']) ?>"><strong><?= e($r['name']) ?></strong></a></td>
        <td><?= e($showEmail ? $r['email'] : mask_email($r['email'])) ?></td>
        <td><?= fmt_date($r['created_at'], 'M j, Y') ?></td>
        <td><?= (int) $r['orders'] ?></td>
        <td><?= money($r['spent']) ?></td>
        <td><?= $r['last_order'] ? fmt_date($r['last_order'], 'M j, Y') : '—' ?></td>
        <td><?= $r['is_active'] ? 'Active' : '<span class="badge badge-cancelled">Disabled</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted">No customers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="?<?= e(http_build_query(['q' => $search, 'page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
