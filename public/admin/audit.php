<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('audit');

$rows = q_all('SELECT a.*, u.name, u.role FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 300');
$pageTitle = 'Audit log';
$adminTab = 'audit';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Audit log</h1><span class="muted small">Last 300 staff actions</span></div>
<div class="table-wrap">
  <table class="table compact">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap"><?= fmt_date($r['created_at']) ?></td>
          <td><?= e($r['name'] ?? 'System') ?><?= $r['role'] ? ' <span class="muted small">' . e($r['role']) . '</span>' : '' ?></td>
          <td><span class="badge<?= $r['action'] === 'access_denied' ? ' badge-cancelled' : '' ?>"><?= e($r['action']) ?></span></td>
          <td class="small"><?= e($r['details']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nothing logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
