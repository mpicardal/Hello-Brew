<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('coupons');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'create') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('code')));
        $type = post('type') === 'fixed' ? 'fixed' : 'percent';
        $value = (float) post('value');
        $min = max(0, (float) post('min_subtotal'));
        $limit = post('usage_limit') !== '' ? max(1, (int) post('usage_limit')) : null;
        $exp = post('expires_at') !== '' && strtotime(post('expires_at')) ? date('Y-m-d H:i:s', strtotime(post('expires_at'))) : null;
        if (strlen($code) < 3 || strlen($code) > 30 || $value <= 0 || ($type === 'percent' && $value > 100)) {
            flash('error', 'Enter a 3–30 character code and a valid value (percent ≤ 100).');
        } elseif (q_val('SELECT id FROM coupons WHERE code=?', [$code])) {
            flash('error', 'That code already exists.');
        } else {
            q('INSERT INTO coupons (code, description, type, value, min_subtotal, usage_limit, expires_at) VALUES (?,?,?,?,?,?,?)',
                [$code, mb_substr(post('description'), 0, 160), $type, $value, $min, $limit, $exp]);
            audit('coupon_create', $code);
            flash('success', 'Coupon created.');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE coupons SET is_active = 1 - is_active WHERE id=?', [int_param($_POST, 'id')]);
        flash('success', 'Coupon updated.');
    }
    redirect('admin/coupons.php');
}
$coupons = q_all('SELECT * FROM coupons ORDER BY is_active DESC, id DESC');
$pageTitle = 'Coupons';
$adminTab = 'coupons';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Coupons</h1></div>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Code</th><th>Description</th><th>Discount</th><th>Min. subtotal</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($coupons as $c): ?>
      <tr>
        <td><strong><?= e($c['code']) ?></strong></td><td><?= e($c['description']) ?></td>
        <td><?= $c['type'] === 'percent' ? (float) $c['value'] . '%' : money($c['value']) ?></td>
        <td><?= money($c['min_subtotal']) ?></td>
        <td><?= (int) $c['used_count'] ?><?= $c['usage_limit'] ? ' / ' . (int) $c['usage_limit'] : '' ?></td>
        <td><?= $c['expires_at'] ? fmt_date($c['expires_at']) : 'Never' ?></td>
        <td><?= $c['is_active'] ? status_badge('confirmed') : status_badge('cancelled') ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $c['is_active'] ? 'Disable' : 'Enable' ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<form class="panel grid-3" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label>Code<input name="code" maxlength="30" required placeholder="SUMMER15"></label>
  <label>Type<select name="type"><option value="percent">Percent off</option><option value="fixed">Fixed ₱ off</option></select></label>
  <label>Value<input type="number" name="value" step="0.01" min="0.01" required></label>
  <label>Min. subtotal (₱)<input type="number" name="min_subtotal" step="0.01" min="0" value="0"></label>
  <label>Usage limit<input type="number" name="usage_limit" min="1" placeholder="Unlimited"></label>
  <label>Expires<input type="datetime-local" name="expires_at"></label>
  <label class="span-3">Description<input name="description" maxlength="160"></label>
  <button class="btn btn-dark" type="submit">Create coupon</button>
</form>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
