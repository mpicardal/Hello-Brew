<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('emails');

$type = get('type');
$types = ['confirmation', 'status', 'cancelled', 'refund', 'return_update'];
$rows = in_array($type, $types, true)
    ? q_all('SELECT * FROM email_log WHERE type = ? ORDER BY id DESC LIMIT 100', [$type])
    : q_all('SELECT * FROM email_log ORDER BY id DESC LIMIT 100');

$pageTitle = 'Email log';
$adminTab = 'emails';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head">
  <h1>Email log</h1>
  <nav class="seg"><a href="?"<?= $type === '' ? ' aria-current="page"' : '' ?>>All</a><?php foreach ($types as $t): ?><a href="?type=<?= $t ?>"<?= $type === $t ? ' aria-current="page"' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $t))) ?></a><?php endforeach; ?></nav>
</div>
<p class="muted small">Every customer email (order confirmation, shipping updates, cancellations, refunds) is recorded here. Transport: <strong><?= e(cfg('MAIL_TRANSPORT', 'log')) ?></strong>.</p>
<div class="stack">
  <?php foreach ($rows as $m): ?>
    <details class="panel">
      <summary><strong><?= e($m['subject']) ?></strong> <span class="muted small">→ <?= e($m['to_email']) ?> · <?= fmt_date($m['created_at']) ?></span> <span class="badge badge-<?= $m['status'] === 'failed' ? 'failed' : 'confirmed' ?>"><?= e($m['status']) ?></span></summary>
      <pre class="mail"><?= e($m['body']) ?></pre>
    </details>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel"><p class="muted">No emails yet.</p></div><?php endif; ?>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
