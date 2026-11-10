<?php
require __DIR__ . '/../includes/bootstrap.php';

/** Same-site "go back" target; anything else falls back to the cart. */
function back_target(): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $parts = parse_url($ref);
    if (!$parts || ($parts['host'] ?? '') !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        return 'cart.php';
    }
    $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    if (APP_BASE !== '' && str_starts_with($path, APP_BASE)) {
        $path = substr($path, strlen(APP_BASE));
    }
    return ltrim($path, '/') ?: 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'add') {
        $pid = int_param($_POST, 'product_id');
        $vid = int_param($_POST, 'variant_id');
        if ($vid === 0) {   // default to the first option when adding from a listing
            $vid = (int) (q_val('SELECT id FROM product_variants WHERE product_id=? AND is_archived=0 ORDER BY price_delta, id LIMIT 1', [$pid]) ?? 0);
        }
        $qty = int_param($_POST, 'qty', 1);
        if (cart_add($pid, $vid, $qty)) {
            track('add_to_cart', $pid);
            flash('success', q_val('SELECT name FROM products WHERE id=?', [$pid]) . ' was added to your cart.');
        } else {
            flash('error', 'That item is not available right now.');
        }
        redirect(back_target());
    }
    if ($action === 'update') {
        foreach ((array) ($_POST['qty'] ?? []) as $key => $qty) {
            cart_set((string) $key, (int) $qty);
        }
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        cart_set(post('key'), 0);
        flash('success', 'Item removed.');
    } elseif ($action === 'coupon') {
        $code = strtoupper(mb_substr(post('code'), 0, 30));
        $totals = cart_totals(cart_lines());
        [$c, $err] = coupon_check($code, $totals['subtotal']);
        if ($c) {
            $_SESSION['coupon'] = $c['code'];
            flash('success', 'Coupon ' . $c['code'] . ' applied.');
        } else {
            flash('error', $err);
        }
    } elseif ($action === 'remove_coupon') {
        unset($_SESSION['coupon']);
    }
    redirect('cart.php');
}

$lines = cart_lines();
$t = cart_totals($lines);
$pageTitle = 'Your cart';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad">
  <h1 class="page-title">Your Cart</h1>
  <?php if (!$lines): ?>
    <div class="empty">
      <p>Your cart is empty. Let's fix that.</p>
      <a class="btn btn-dark" href="<?= url('shop.php') ?>">Browse the menu <?= icon('arrow-r', 18) ?></a>
    </div>
  <?php else: ?>
  <div class="cart-layout">
    <div>
      <form id="cart-form" action="<?= url('cart.php') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <ul class="cart-list">
          <?php foreach ($lines as $l): ?>
            <li class="cart-line">
              <img src="<?= e(product_img($l['image'])) ?>" alt="" width="84" height="84">
              <div class="cart-line-info">
                <a href="<?= url('product.php?slug=' . urlencode($l['slug'])) ?>"><strong><?= e($l['name']) ?></strong></a>
                <span class="muted small"><?= e($l['variant'] ?? $l['category']) ?> · <?= money($l['unit']) ?> each</span>
                <?php if ($l['qty'] > $l['stock']): ?><span class="stock-low small">Only <?= $l['stock'] ?> available — please lower the quantity.</span><?php endif; ?>
              </div>
              <div class="qty" data-qty>
                <button type="button" data-step="-1" aria-label="Decrease"><?= icon('minus', 16) ?></button>
                <input type="number" name="qty[<?= e($l['key']) ?>]" value="<?= $l['qty'] ?>" min="0" max="50" aria-label="Quantity of <?= e($l['name']) ?>">
                <button type="button" data-step="1" aria-label="Increase"><?= icon('plus', 16) ?></button>
              </div>
              <strong class="line-total"><?= money($l['total']) ?></strong>
              <button class="icon-btn danger" type="submit" form="rm-<?= e(str_replace(':', '-', $l['key'])) ?>" aria-label="Remove <?= e($l['name']) ?>"><?= icon('trash', 18) ?></button>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="btn-row">
          <button class="btn btn-tan" type="submit">Update cart</button>
          <a class="btn btn-ghost" href="<?= url('shop.php') ?>">Continue shopping</a>
        </div>
      </form>
      <?php foreach ($lines as $l): ?>
        <form id="rm-<?= e(str_replace(':', '-', $l['key'])) ?>" action="<?= url('cart.php') ?>" method="post" hidden>
          <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="key" value="<?= e($l['key']) ?>">
        </form>
      <?php endforeach; ?>
    </div>
    <aside class="summary">
      <h2>Order summary</h2>
      <dl>
        <div><dt>Subtotal</dt><dd><?= money($t['subtotal']) ?></dd></div>
        <?php if ($t['discount'] > 0): ?><div class="discount"><dt>Discount (<?= e($t['coupon']['code']) ?>)</dt><dd>−<?= money($t['discount']) ?></dd></div><?php endif; ?>
        <div><dt>Delivery fee</dt><dd><?= $t['delivery'] > 0 ? money($t['delivery']) : 'Free' ?></dd></div>
        <div class="total"><dt>Total</dt><dd><?= money($t['total']) ?></dd></div>
      </dl>
      <?php if ($t['delivery'] > 0): ?><p class="muted small">Add <?= money(FREE_DELIVERY_MIN - ($t['subtotal'] - $t['discount'])) ?> more for free delivery.</p><?php endif; ?>
      <?php if ($t['coupon']): ?>
        <form action="<?= url('cart.php') ?>" method="post" class="coupon-applied">
          <?= csrf_field() ?><input type="hidden" name="action" value="remove_coupon">
          <span class="chip"><?= icon('tag', 14) ?> <?= e($t['coupon']['code']) ?></span>
          <button class="link-btn" type="submit">Remove</button>
        </form>
      <?php else: ?>
        <form action="<?= url('cart.php') ?>" method="post" class="coupon-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="coupon">
          <label class="sr-only" for="coupon">Coupon code</label>
          <input id="coupon" name="code" placeholder="Coupon code" maxlength="30" required>
          <button class="btn btn-tan btn-sm" type="submit">Apply</button>
        </form>
      <?php endif; ?>
      <a class="btn btn-dark btn-block" href="<?= url('checkout.php') ?>">Proceed to checkout <?= icon('arrow-r', 18) ?></a>
    </aside>
  </div>
  <?php endif; ?>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
