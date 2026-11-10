<?php
require __DIR__ . '/../../includes/bootstrap.php';
$me = require_customer();

// Always scope by user_id so customers can only ever open their own orders.
$o = q_one('SELECT * FROM orders WHERE order_no = ? AND user_id = ?', [get('no') ?: post('no'), $me['id']]);
if (!$o) {
    http_response_code(404);
    flash('error', 'Order not found.');
    redirect('account/index.php');
}
$oid = (int) $o['id'];
$deliveredAt = q_val("SELECT MAX(created_at) FROM order_status_history WHERE order_id=? AND status='delivered'", [$oid]);
$openRequest = q_one("SELECT * FROM return_requests WHERE order_id=? AND status='pending'", [$oid]);
$canCancel = in_array($o['status'], ['pending', 'confirmed'], true) && !$openRequest;
$canReturn = $o['status'] === 'delivered' && !$openRequest && $deliveredAt
    && strtotime($deliveredAt) > strtotime('-' . RETURN_WINDOW_DAYS . ' days')
    && !q_val("SELECT id FROM return_requests WHERE order_id=? AND type='return' AND status IN ('approved','refunded')", [$oid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'request') {
        $type = post('type');
        $reason = mb_substr(post('reason'), 0, 500);
        if (($type === 'cancellation' && $canCancel) || ($type === 'return' && $canReturn)) {
            if (mb_strlen($reason) < 5) {
                flash('error', 'Please tell us briefly why (at least 5 characters).');
            } else {
                q('INSERT INTO return_requests (order_id, user_id, type, reason) VALUES (?,?,?,?)', [$oid, $me['id'], $type, $reason]);
                email_order_event($oid, 'return_update', 'We received your ' . $type . ' request. Our team will review it within 24 hours.');
                flash('success', 'Your ' . $type . ' request was sent. You can follow its status here.');
            }
        } else {
            flash('error', 'This order is not eligible for that request.');
        }
    } elseif ($action === 'review' && $o['status'] === 'delivered') {
        $pid = int_param($_POST, 'product_id');
        $rating = int_param($_POST, 'rating');
        $comment = mb_substr(post('comment'), 0, 600);
        $inOrder = q_val('SELECT id FROM order_items WHERE order_id=? AND product_id=?', [$oid, $pid]);
        if ($inOrder && $rating >= 1 && $rating <= 5 && mb_strlen($comment) >= 5) {
            q('INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?)
               ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), is_approved=0', [$me['id'], $pid, $rating, $comment]);
            flash('success', 'Thanks for your review! It will appear once our team approves it.');
        } else {
            flash('error', 'Pick a rating and write at least a few words.');
        }
    }
    redirect('account/order.php?no=' . urlencode($o['order_no']));
}

$items = q_all('SELECT oi.*, p.slug, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?', [$oid]);
$history = q_all('SELECT status, note, created_at FROM order_status_history WHERE order_id=? ORDER BY created_at, id', [$oid]);
$requests = q_all('SELECT * FROM return_requests WHERE order_id=? ORDER BY created_at DESC', [$oid]);
$reviewed = array_column(q_all('SELECT product_id FROM reviews WHERE user_id=?', [$me['id']]), 'product_id');
$steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'packed' => 'Packed', 'out_for_delivery' => 'On the way', 'delivered' => 'Delivered'];
$stepKeys = array_keys($steps);
$currentIdx = array_search($o['status'], $stepKeys, true);

$pageTitle = 'Order ' . $o['order_no'];
$accTab = 'orders';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad acc-layout">
  <?php require ROOT . '/includes/layout/account_nav.php'; ?>
  <section>
    <a class="link-btn" href="<?= url('account/index.php') ?>"><?= icon('arrow-l', 16) ?> All orders</a>
    <div class="order-head">
      <h1 class="page-title">Order <?= e($o['order_no']) ?></h1>
      <?= status_badge($o['status']) ?>
    </div>
    <p class="muted">Purchased <?= fmt_date($o['created_at']) ?></p>

    <?php if ($currentIdx !== false): ?>
      <ol class="tracker" aria-label="Order progress">
        <?php foreach ($steps as $k => $label): $idx = array_search($k, $stepKeys, true); ?>
          <li class="<?= $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'current' : '') ?>"><span></span><?= e($label) ?></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <div class="order-grid">
      <div class="card">
        <h2>Items</h2>
        <ul class="order-items">
          <?php foreach ($items as $i): ?>
            <li>
              <img src="<?= e(product_img($i['image'])) ?>" alt="" width="56" height="56">
              <span><strong><?= e($i['product_name']) ?></strong><?= $i['variant_name'] ? '<br><small class="muted">' . e($i['variant_name']) . '</small>' : '' ?><br><small><?= (int) $i['qty'] ?> × <?= money($i['unit_price']) ?></small></span>
              <strong><?= money($i['line_total']) ?></strong>
            </li>
            <?php if ($o['status'] === 'delivered' && $i['product_id'] && !in_array($i['product_id'], $reviewed)): ?>
              <li class="review-form">
                <form action="<?= url('account/order.php') ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="no" value="<?= e($o['order_no']) ?>">
                  <input type="hidden" name="action" value="review">
                  <input type="hidden" name="product_id" value="<?= (int) $i['product_id'] ?>">
                  <label>Rate <?= e($i['product_name']) ?>
                    <select name="rating" required><option value="">Stars…</option><?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"><?= str_repeat('★', $s) ?></option><?php endfor; ?></select>
                  </label>
                  <label class="grow">Comment<input name="comment" maxlength="600" required placeholder="How was it?"></label>
                  <button class="btn btn-tan btn-sm" type="submit">Post review</button>
                </form>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
        <dl class="totals">
          <div><dt>Subtotal</dt><dd><?= money($o['subtotal']) ?></dd></div>
          <?php if ((float) $o['discount'] > 0): ?><div><dt>Discount <?= e($o['coupon_code']) ?></dt><dd>−<?= money($o['discount']) ?></dd></div><?php endif; ?>
          <div><dt>Delivery</dt><dd><?= money($o['delivery_fee']) ?></dd></div>
          <div class="total"><dt>Total</dt><dd><?= money($o['total']) ?></dd></div>
        </dl>
      </div>
      <div class="stack">
        <div class="card">
          <h2>Delivery</h2>
          <p><strong><?= e($o['ship_recipient']) ?></strong> · <?= e($o['ship_phone']) ?><br><?= e($o['ship_line1']) ?><?= $o['ship_line2'] ? ', ' . e($o['ship_line2']) : '' ?><br><?= e($o['ship_city']) ?>, <?= e($o['ship_province']) ?> <?= e($o['ship_postal']) ?></p>
          <?php if ($o['notes']): ?><p class="muted small">Note: <?= e($o['notes']) ?></p><?php endif; ?>
        </div>
        <div class="card">
          <h2>Payment</h2>
          <p><?= e(payment_label($o['payment_method'])) ?><?= $o['card_last4'] ? ' ending ' . e($o['card_last4']) : '' ?><br><?= status_badge($o['payment_status']) ?></p>
        </div>
        <div class="card">
          <h2>History</h2>
          <ul class="history">
            <?php foreach ($history as $h): ?>
              <li><strong><?= e(status_label($h['status'])) ?></strong> <span class="muted small"><?= fmt_date($h['created_at']) ?></span><?= $h['note'] ? '<br><small>' . e($h['note']) . '</small>' : '' ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <?php if ($requests): ?>
      <div class="card">
        <h2>Your requests</h2>
        <?php foreach ($requests as $r): ?>
          <p><?= status_badge($r['status']) ?> <strong><?= e(ucfirst($r['type'])) ?></strong> · <?= fmt_date($r['created_at']) ?><br>
            <span class="muted small">Reason: <?= e($r['reason']) ?></span>
            <?= $r['admin_note'] ? '<br><small>Reply: ' . e($r['admin_note']) . '</small>' : '' ?>
            <?= $r['refund_amount'] !== null ? '<br><small>Refunded: ' . money($r['refund_amount']) . '</small>' : '' ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($canCancel || $canReturn): ?>
      <div class="card">
        <h2><?= $canCancel ? 'Need to cancel?' : 'Something wrong with your order?' ?></h2>
        <p class="muted small"><?= $canCancel ? 'You can request a cancellation until we start preparing your order.' : 'You can request a return within ' . RETURN_WINDOW_DAYS . ' days of delivery.' ?></p>
        <form class="inline-form" action="<?= url('account/order.php') ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="no" value="<?= e($o['order_no']) ?>">
          <input type="hidden" name="action" value="request">
          <input type="hidden" name="type" value="<?= $canCancel ? 'cancellation' : 'return' ?>">
          <label class="grow">Reason<input name="reason" maxlength="500" required placeholder="Tell us what happened"></label>
          <button class="btn btn-dark btn-sm" type="submit">Request <?= $canCancel ? 'cancellation' : 'return' ?></button>
        </form>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
