<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('returns');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = q_one('SELECT r.*, o.status AS order_status, o.payment_status, o.total, o.id AS oid
                FROM return_requests r JOIN orders o ON o.id=r.order_id WHERE r.id = ?', [int_param($_POST, 'id')]);
    $action = post('action');
    $note = mb_substr(post('admin_note'), 0, 500);
    if (!$r) {
        flash('error', 'Request not found.');
    } elseif ($action === 'reject' && $r['status'] === 'pending') {
        q("UPDATE return_requests SET status='rejected', admin_note=? WHERE id=?", [$note ?: 'Request declined.', $r['id']]);
        email_order_event((int) $r['oid'], 'return_update', 'Your ' . $r['type'] . " request was declined.\n" . ($note ? "Note: $note" : ''));
        audit('return_reject', "request #{$r['id']}");
        flash('success', 'Request rejected and the customer was emailed.');
    } elseif ($action === 'approve' && $r['status'] === 'pending') {
        if ($r['type'] === 'cancellation') {
            $paid = in_array($r['payment_status'], ['paid', 'partially_refunded'], true);
            if ($paid && !can('refunds')) {
                flash('error', 'This order is paid — an admin must approve it so the refund can be issued.');
            } elseif (!in_array($r['order_status'], ['pending', 'confirmed', 'preparing', 'packed'], true)) {
                flash('error', 'The order has already left the café and can’t be cancelled. Reject and suggest a return instead.');
            } else {
                cancel_order((int) $r['oid'], 'Customer cancellation approved');
                q("UPDATE return_requests SET status=?, admin_note=?, refund_amount=?, restocked=1 WHERE id=?",
                    [$paid ? 'refunded' : 'approved', $note ?: null, $paid ? $r['total'] : null, $r['id']]);
                flash('success', 'Cancellation approved. Stock restored' . ($paid ? ' and refund issued.' : '.'));
            }
        } else {
            q("UPDATE return_requests SET status='approved', admin_note=? WHERE id=?", [$note ?: null, $r['id']]);
            email_order_event((int) $r['oid'], 'return_update', "Your return request was approved. We'll process your refund shortly." . ($note ? "\nNote: $note" : ''));
            audit('return_approve', "request #{$r['id']}");
            flash('success', 'Return approved. Process the refund when the items are back.');
        }
    } elseif ($action === 'refund' && $r['status'] === 'approved' && $r['type'] === 'return') {
        if (!can('refunds')) {
            http_response_code(403);
            exit('403 — Only admins can issue refunds.');
        }
        $amount = round((float) post('amount'), 2);
        $max = round((float) $r['total'] - refunded_so_far((int) $r['oid']), 2);
        if (!in_array($r['payment_status'], ['paid', 'partially_refunded'], true)) {
            flash('error', 'This order has no captured payment to refund.');
        } elseif ($amount <= 0 || $amount > $max) {
            flash('error', 'Refund must be between ₱0.01 and ' . money($max) . '.');
        } else {
            $restock = post('restock') === '1';
            if ($restock) {
                restock_order((int) $r['oid']);
            }
            refund_order((int) $r['oid'], $amount, $note);
            q("UPDATE return_requests SET status='refunded', refund_amount=?, restocked=?, admin_note=COALESCE(?, admin_note) WHERE id=?",
                [$amount, $restock ? 1 : 0, $note ?: null, $r['id']]);
            if ($amount >= $max - 0.009) {
                set_order_status((int) $r['oid'], 'returned', 'Return refunded');
            }
            flash('success', 'Refund of ' . money($amount) . ' issued' . ($restock ? ' and items restocked.' : '.'));
        }
    }
    redirect('admin/returns.php?show=' . urlencode(get('show') ?: post('show')));
}

$show = get('show') === 'all' ? 'all' : 'open';
$rows = q_all('SELECT r.*, o.order_no, o.total, o.payment_status, o.status AS order_status, u.name
               FROM return_requests r JOIN orders o ON o.id=r.order_id JOIN users u ON u.id=r.user_id '
    . ($show === 'open' ? "WHERE r.status IN ('pending','approved') " : '') . 'ORDER BY r.created_at DESC LIMIT 200');

$pageTitle = 'Returns & refunds';
$adminTab = 'returns';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head">
  <h1>Returns &amp; refunds</h1>
  <nav class="seg"><a href="?show=open"<?= $show === 'open' ? ' aria-current="page"' : '' ?>>Open</a><a href="?show=all"<?= $show === 'all' ? ' aria-current="page"' : '' ?>>All</a></nav>
</div>
<?php if (!$rows): ?><div class="panel"><p class="muted">No <?= $show === 'open' ? 'open ' : '' ?>requests.</p></div><?php endif; ?>
<div class="stack">
<?php foreach ($rows as $r): ?>
  <div class="panel">
    <div class="admin-head">
      <h3><?= e(ucfirst($r['type'])) ?> · <a href="<?= url('admin/order.php?id=' . (int) $r['order_id']) ?>"><?= e($r['order_no']) ?></a></h3>
      <div><?= status_badge($r['status']) ?> <span class="muted small">Order: <?= e(status_label($r['order_status'])) ?> · <?= e(status_label($r['payment_status'])) ?> · <?= money($r['total']) ?></span></div>
    </div>
    <p><strong><?= e($r['name']) ?></strong> <span class="muted small"><?= fmt_date($r['created_at']) ?></span><br>“<?= e($r['reason']) ?>”</p>
    <?php if ($r['admin_note']): ?><p class="small">Note: <?= e($r['admin_note']) ?></p><?php endif; ?>
    <?php if ($r['refund_amount'] !== null): ?><p class="small">Refunded <?= money($r['refund_amount']) ?><?= $r['restocked'] ? ' · restocked' : '' ?></p><?php endif; ?>

    <?php if ($r['status'] === 'pending'): ?>
      <form class="inline-form" method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="show" value="<?= e($show) ?>">
        <label class="grow">Note to customer<input name="admin_note" maxlength="500"></label>
        <button class="btn btn-dark btn-sm" type="submit" name="action" value="approve">Approve</button>
        <button class="btn btn-danger btn-sm" type="submit" name="action" value="reject">Reject</button>
      </form>
    <?php elseif ($r['status'] === 'approved' && $r['type'] === 'return'): ?>
      <?php if (can('refunds')): ?>
        <form class="inline-form" method="post" data-confirm="Issue this refund?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="refund"><input type="hidden" name="show" value="<?= e($show) ?>">
          <label>Refund ₱<input class="num" type="number" name="amount" step="0.01" min="0.01" value="<?= e(number_format((float) $r['total'] - refunded_so_far((int) $r['order_id']), 2, '.', '')) ?>"></label>
          <label class="check"><input type="checkbox" name="restock" value="1" checked> Return items to stock</label>
          <label class="grow">Note<input name="admin_note" maxlength="500"></label>
          <button class="btn btn-dark btn-sm" type="submit">Process refund</button>
        </form>
      <?php else: ?><p class="muted small">Waiting for an admin to issue the refund.</p><?php endif; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
