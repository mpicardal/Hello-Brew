<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_customer();

$lines = cart_lines();
if (!$lines) {
    flash('info', 'Your cart is empty.');
    redirect('shop.php');
}
$addresses = q_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$me['id']]);
$errors = [];
$old = [
    'billing_name' => $me['name'], 'billing_email' => $me['email'], 'billing_phone' => $me['phone'] ?? '',
    'address_id' => (string) ($addresses[0]['id'] ?? 'new'), 'recipient' => $me['name'], 'phone' => $me['phone'] ?? '',
    'line1' => '', 'line2' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'notes' => '', 'payment_method' => 'card',
    'save_address' => '1',
];

function clean_phone(string $p): string
{
    return preg_replace('/[\s\-()]/', '', $p);
}

function validate_address(array $a): array
{
    $err = [];
    if (mb_strlen($a['recipient']) < 2 || mb_strlen($a['recipient']) > 120) $err['recipient'] = 'Enter the recipient’s full name.';
    if (!preg_match('/^(\+639|09)\d{9}$/', clean_phone($a['phone']))) $err['phone'] = 'Enter a PH mobile number, e.g. 09171234567.';
    if (mb_strlen($a['line1']) < 5 || mb_strlen($a['line1']) > 190) $err['line1'] = 'Enter the house number and street.';
    if (mb_strlen($a['line2']) > 190) $err['line2'] = 'Too long.';
    if (mb_strlen($a['city']) < 2 || mb_strlen($a['city']) > 100) $err['city'] = 'Enter the city or municipality.';
    if (mb_strlen($a['province']) < 2 || mb_strlen($a['province']) > 100) $err['province'] = 'Enter the province or region.';
    if (!preg_match('/^\d{4}$/', $a['postal_code'])) $err['postal_code'] = 'PH postal codes have 4 digits.';
    return $err;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = post($k);
    }
    // Billing details
    if (mb_strlen($old['billing_name']) < 2 || mb_strlen($old['billing_name']) > 120) $errors['billing_name'] = 'Enter your full name.';
    if (!filter_var($old['billing_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['billing_email']) > 190) $errors['billing_email'] = 'Enter a valid email address.';
    if (!preg_match('/^(\+639|09)\d{9}$/', clean_phone($old['billing_phone']))) $errors['billing_phone'] = 'Enter a PH mobile number, e.g. 09171234567.';

    // Shipping address: a saved one (must belong to this user) or a new one
    $ship = null;
    if ($old['address_id'] !== 'new') {
        $ship = q_one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [int_param($_POST, 'address_id'), $me['id']]);
        if (!$ship) $errors['address_id'] = 'Choose a delivery address.';
    } else {
        $ship = ['recipient' => $old['recipient'], 'phone' => $old['phone'], 'line1' => $old['line1'], 'line2' => $old['line2'],
                 'city' => $old['city'], 'province' => $old['province'], 'postal_code' => $old['postal_code']];
        $errors += validate_address($ship);
    }
    if (mb_strlen($old['notes']) > 255) $errors['notes'] = 'Keep notes under 255 characters.';
    if (!in_array($old['payment_method'], ['card', 'ewallet', 'cod'], true)) $errors['payment_method'] = 'Choose a payment method.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Lock product rows and re-validate stock and prices inside the transaction.
            $lines = cart_lines();
            $need = [];
            foreach ($lines as $l) {
                $need[$l['product_id']] = ($need[$l['product_id']] ?? 0) + $l['qty'];
            }
            foreach ($need as $pid => $qty) {
                $row = q_one('SELECT name, stock, is_archived FROM products WHERE id = ? FOR UPDATE', [$pid]);
                if (!$row || $row['is_archived'] || (int) $row['stock'] < $qty) {
                    throw new RuntimeException('Sorry, ' . ($row['name'] ?? 'an item') . ' only has ' . (int) ($row['stock'] ?? 0) . ' left. Please update your cart.');
                }
            }
            $t = cart_totals($lines);
            $pay = MockGateway::charge($old['payment_method'], $t['total'], $_POST);
            if (!$pay['ok']) {
                throw new RuntimeException($pay['error']);
            }
            $orderNo = 'HB' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            q('INSERT INTO orders (order_no, user_id, status, subtotal, discount, delivery_fee, total, coupon_code,
                 billing_name, billing_email, billing_phone, ship_recipient, ship_phone, ship_line1, ship_line2,
                 ship_city, ship_province, ship_postal, notes, payment_method, payment_status, card_last4)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $orderNo, $me['id'], 'pending', $t['subtotal'], $t['discount'], $t['delivery'], $t['total'], $t['coupon']['code'] ?? null,
                $old['billing_name'], $old['billing_email'], clean_phone($old['billing_phone']),
                $ship['recipient'], clean_phone($ship['phone']), $ship['line1'], $ship['line2'] ?: null,
                $ship['city'], $ship['province'], $ship['postal_code'], $old['notes'] ?: null,
                $old['payment_method'], $pay['status'], $pay['last4'] ?? null,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            foreach ($lines as $l) {
                q('INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, unit_price, qty, line_total)
                   VALUES (?,?,?,?,?,?,?,?)', [$orderId, $l['product_id'], $l['variant_id'], $l['name'], $l['variant'], $l['unit'], $l['qty'], $l['total']]);
            }
            foreach ($need as $pid => $qty) {
                q('UPDATE products SET stock = stock - ? WHERE id = ?', [$qty, $pid]);
            }
            if ($t['coupon']) {
                q('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$t['coupon']['id']]);
            }
            q('INSERT INTO payments (order_id, kind, method, amount, status, reference) VALUES (?,?,?,?,?,?)',
                [$orderId, 'charge', $old['payment_method'], $t['total'], 'success', $pay['ref']]);
            q('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?,?,?,?)',
                [$orderId, 'pending', 'Order placed · ' . payment_label($old['payment_method']) . ' · ' . $pay['ref'], $me['id']]);
            if ($old['address_id'] === 'new' && $old['save_address'] === '1') {
                q('INSERT INTO addresses (user_id, label, recipient, phone, line1, line2, city, province, postal_code, is_default)
                   VALUES (?,?,?,?,?,?,?,?,?,?)', [$me['id'], 'Home', $ship['recipient'], clean_phone($ship['phone']), $ship['line1'],
                    $ship['line2'] ?: null, $ship['city'], $ship['province'], $ship['postal_code'], $addresses ? 0 : 1]);
            }
            $pdo->commit();
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors['general'] = $ex->getMessage();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('checkout: ' . $ex->getMessage());
            $errors['general'] = 'Something went wrong placing your order. You have not been charged.';
        }
        if (!$errors) {
            email_order_event($orderId, 'confirmation');
            track('purchase', null, $orderNo);
            cart_clear();
            flash('success', 'Thank you! Your order ' . $orderNo . ' has been placed. A confirmation email is on its way.');
            redirect('account/order.php?no=' . urlencode($orderNo));
        }
    }
} else {
    track('begin_checkout');
}

$t = cart_totals($lines);
function fe(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
}
$pageTitle = 'Checkout';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad">
  <h1 class="page-title">Checkout</h1>
  <?php if (!empty($errors['general'])): ?><div class="flash flash-error" role="alert"><?= e($errors['general']) ?></div><?php endif; ?>
  <?php if ($errors && empty($errors['general'])): ?><div class="flash flash-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>
  <form class="checkout" action="<?= url('checkout.php') ?>" method="post" novalidate autocomplete="on">
    <?= csrf_field() ?>
    <div class="checkout-main">
      <fieldset class="card">
        <legend>1 · Billing details</legend>
        <div class="grid-2">
          <label>Full name<input name="billing_name" value="<?= e($old['billing_name']) ?>" required maxlength="120" autocomplete="name"><?= fe($errors, 'billing_name') ?></label>
          <label>Email<input type="email" name="billing_email" value="<?= e($old['billing_email']) ?>" required maxlength="190" autocomplete="email"><?= fe($errors, 'billing_email') ?></label>
          <label>Mobile number<input name="billing_phone" value="<?= e($old['billing_phone']) ?>" required placeholder="09171234567" autocomplete="tel"><?= fe($errors, 'billing_phone') ?></label>
        </div>
      </fieldset>

      <fieldset class="card">
        <legend>2 · Delivery address</legend>
        <?= fe($errors, 'address_id') ?>
        <div class="address-options">
          <?php foreach ($addresses as $a): ?>
            <label class="address-opt">
              <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>"<?= $old['address_id'] === (string) $a['id'] ? ' checked' : '' ?>>
              <span><strong><?= e($a['label']) ?> · <?= e($a['recipient']) ?></strong><br><?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?>, <?= e($a['city']) ?>, <?= e($a['province']) ?> <?= e($a['postal_code']) ?><br><span class="muted small"><?= e($a['phone']) ?></span></span>
            </label>
          <?php endforeach; ?>
          <label class="address-opt">
            <input type="radio" name="address_id" value="new" data-toggle="new-address"<?= $old['address_id'] === 'new' ? ' checked' : '' ?>>
            <span><strong>Deliver to a new address</strong></span>
          </label>
        </div>
        <div class="grid-2 new-address" id="new-address">
          <label>Recipient name<input name="recipient" value="<?= e($old['recipient']) ?>" maxlength="120"><?= fe($errors, 'recipient') ?></label>
          <label>Recipient mobile<input name="phone" value="<?= e($old['phone']) ?>" placeholder="09171234567"><?= fe($errors, 'phone') ?></label>
          <label class="span-2">House no. &amp; street<input name="line1" value="<?= e($old['line1']) ?>" maxlength="190" autocomplete="address-line1"><?= fe($errors, 'line1') ?></label>
          <label class="span-2">Barangay / building (optional)<input name="line2" value="<?= e($old['line2']) ?>" maxlength="190" autocomplete="address-line2"><?= fe($errors, 'line2') ?></label>
          <label>City / municipality<input name="city" value="<?= e($old['city']) ?>" maxlength="100" autocomplete="address-level2"><?= fe($errors, 'city') ?></label>
          <label>Province / region<input name="province" value="<?= e($old['province']) ?>" maxlength="100" autocomplete="address-level1"><?= fe($errors, 'province') ?></label>
          <label>Postal code<input name="postal_code" value="<?= e($old['postal_code']) ?>" inputmode="numeric" maxlength="4" autocomplete="postal-code"><?= fe($errors, 'postal_code') ?></label>
          <label class="check"><input type="checkbox" name="save_address" value="1"<?= $old['save_address'] === '1' ? ' checked' : '' ?>> Save this address to my account</label>
        </div>
        <label>Notes for the rider (optional)<input name="notes" value="<?= e($old['notes']) ?>" maxlength="255" placeholder="Gate code, landmark…"><?= fe($errors, 'notes') ?></label>
      </fieldset>

      <fieldset class="card">
        <legend>3 · Payment</legend>
        <p class="notice small"><?= icon('shield', 16) ?> Demo checkout — no real money is charged. Test card: 4242 4242 4242 4242, any future expiry, any 3-digit CVC. Card numbers are never stored.</p>
        <?= fe($errors, 'payment_method') ?>
        <div class="pay-options">
          <label class="pay-opt"><input type="radio" name="payment_method" value="card"<?= $old['payment_method'] === 'card' ? ' checked' : '' ?>> Credit / debit card</label>
          <label class="pay-opt"><input type="radio" name="payment_method" value="ewallet"<?= $old['payment_method'] === 'ewallet' ? ' checked' : '' ?>> E-wallet</label>
          <label class="pay-opt"><input type="radio" name="payment_method" value="cod"<?= $old['payment_method'] === 'cod' ? ' checked' : '' ?>> Cash on delivery</label>
        </div>
        <div class="pay-panel" data-pay="card">
          <div class="grid-3">
            <label class="span-3">Card number<input name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242" maxlength="23"></label>
            <label>Expiry (MM/YY)<input name="card_exp" autocomplete="cc-exp" placeholder="12/29" maxlength="5"></label>
            <label>CVC<input name="card_cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4"></label>
          </div>
        </div>
        <div class="pay-panel" data-pay="ewallet">
          <label>E-wallet mobile number<input name="wallet_number" inputmode="numeric" placeholder="09171234567" maxlength="13"></label>
        </div>
        <div class="pay-panel" data-pay="cod"><p class="muted small">Pay the rider in cash when your order arrives.</p></div>
      </fieldset>
    </div>

    <aside class="summary">
      <h2>Your order</h2>
      <ul class="mini-lines">
        <?php foreach ($lines as $l): ?>
          <li><span><?= $l['qty'] ?>× <?= e($l['name']) ?><?= $l['variant'] ? ' <small class="muted">(' . e($l['variant']) . ')</small>' : '' ?></span><span><?= money($l['total']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <dl>
        <div><dt>Subtotal</dt><dd><?= money($t['subtotal']) ?></dd></div>
        <?php if ($t['discount'] > 0): ?><div class="discount"><dt>Discount</dt><dd>−<?= money($t['discount']) ?></dd></div><?php endif; ?>
        <div><dt>Delivery fee</dt><dd><?= $t['delivery'] > 0 ? money($t['delivery']) : 'Free' ?></dd></div>
        <div class="total"><dt>Total</dt><dd><?= money($t['total']) ?></dd></div>
      </dl>
      <button class="btn btn-dark btn-block" type="submit">Place order · <?= money($t['total']) ?></button>
      <a class="link-btn center-block" href="<?= url('cart.php') ?>">Edit cart</a>
    </aside>
  </form>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
