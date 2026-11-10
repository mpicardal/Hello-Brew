<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('reviews');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = int_param($_POST, 'id');
    $action = post('action');
    if ($action === 'approve' || $action === 'hide') {
        q('UPDATE reviews SET is_approved = ? WHERE id = ?', [$action === 'approve' ? 1 : 0, $id]);
        flash('success', $action === 'approve' ? 'Review published.' : 'Review hidden from the store.');
    } elseif ($action === 'delete') {
        q('DELETE FROM reviews WHERE id = ?', [$id]);
        flash('success', 'Review deleted.');
    }
    audit('review_' . $action, "review #$id");
    redirect('admin/reviews.php?show=' . (post('show') === 'all' ? 'all' : 'pending'));
}

$show = get('show') === 'all' ? 'all' : 'pending';
$rows = q_all('SELECT r.*, u.name, p.name AS product FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id '
    . ($show === 'pending' ? 'WHERE r.is_approved = 0 ' : '') . 'ORDER BY r.created_at DESC LIMIT 200');

$pageTitle = 'Reviews';
$adminTab = 'reviews';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head">
  <h1>Reviews</h1>
  <nav class="seg"><a href="?show=pending"<?= $show === 'pending' ? ' aria-current="page"' : '' ?>>Waiting for approval</a><a href="?show=all"<?= $show === 'all' ? ' aria-current="page"' : '' ?>>All</a></nav>
</div>
<p class="muted small">Only customers with a delivered order can review an item. Reviews appear in the store after approval.</p>
<div class="stack">
  <?php foreach ($rows as $r): ?>
    <div class="panel">
      <div class="admin-head">
        <h3><?= e($r['product']) ?> <?= stars((float) $r['rating']) ?></h3>
        <?= $r['is_approved'] ? status_badge('approved') : status_badge('pending') ?>
      </div>
      <p>“<?= e($r['comment']) ?>”</p>
      <p class="muted small"><?= e($r['name']) ?> · <?= fmt_date($r['created_at']) ?></p>
      <div class="btn-row">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="show" value="<?= $show ?>">
          <button class="btn btn-dark btn-sm" type="submit" name="action" value="<?= $r['is_approved'] ? 'hide' : 'approve' ?>"><?= $r['is_approved'] ? 'Hide' : 'Approve' ?></button></form>
        <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="show" value="<?= $show ?>">
          <button class="btn btn-ghost btn-sm" type="submit" name="action" value="delete">Delete</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="panel"><p class="muted">No reviews <?= $show === 'pending' ? 'waiting for approval' : 'yet' ?>.</p></div><?php endif; ?>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
