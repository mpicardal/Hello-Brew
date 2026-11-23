<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('customers');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'remove') {
    $id = int_param($_POST, 'id');
    q('DELETE FROM newsletter_subscribers WHERE id = ?', [$id]);
    audit('subscriber_remove', "subscriber #$id");
    flash('success', 'Subscriber removed from the list.');
    redirect('admin/subscribers.php');
}

$showEmail = can('customer_email');
$rows = q_all('SELECT s.id, s.email, s.created_at, u.name
               FROM newsletter_subscribers s LEFT JOIN users u ON u.email = s.email AND u.role = \'customer\'
               ORDER BY s.created_at DESC LIMIT 500');
$thisMonth = count(array_filter($rows, fn($r) => substr($r['created_at'], 0, 7) === date('Y-m')));

$pageTitle = 'Subscribers';
$adminTab = 'subscribers';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Newsletter subscribers</h1></div>
<div class="kpis">
  <div class="kpi"><span>Total subscribers</span><strong><?= count($rows) ?></strong></div>
  <div class="kpi"><span>New this month</span><strong><?= $thisMonth ?></strong></div>
  <div class="kpi"><span>Also have an account</span><strong><?= count(array_filter($rows, fn($r) => $r['name'])) ?></strong></div>
</div>
<p class="muted small">People who signed up with the “Stay up to date” box at the bottom of every store page. Each one gets a welcome email with the HELLOBREW code. Remove someone if they ask to unsubscribe.</p>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Email</th><th>Customer account</th><th>Subscribed</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($showEmail ? $r['email'] : mask_email($r['email'])) ?></td>
          <td><?= $r['name'] ? e($r['name']) : '<span class="muted">—</span>' ?></td>
          <td><?= fmt_date($r['created_at']) ?></td>
          <td class="nowrap">
            <form class="inline" method="post" data-confirm="Remove this email from the newsletter list?">
              <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="muted">No subscribers yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
