<?php
require __DIR__ . '/../../includes/bootstrap.php';
$me = require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'profile') {
        $name = mb_substr(post('name'), 0, 120);
        $phone = preg_replace('/[\s\-()]/', '', post('phone'));
        if (mb_strlen($name) < 2 || ($phone !== '' && !preg_match('/^(\+639|09)\d{9}$/', $phone))) {
            flash('error', 'Enter your name and a valid PH mobile number (or leave the number blank).');
        } else {
            q('UPDATE users SET name=?, phone=? WHERE id=?', [$name, $phone ?: null, $me['id']]);
            flash('success', 'Profile saved.');
        }
    } elseif ($action === 'password') {
        $hash = q_val('SELECT password_hash FROM users WHERE id=?', [$me['id']]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($p = password_problem($new)) {
            flash('error', $p);
        } elseif ($new !== ($_POST['new_password_confirm'] ?? '')) {
            flash('error', 'The new passwords do not match.');
        } else {
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'address_add') {
        $a = ['label' => mb_substr(post('label'), 0, 40) ?: 'Home', 'recipient' => post('recipient'), 'phone' => preg_replace('/[\s\-()]/', '', post('phone')),
              'line1' => post('line1'), 'line2' => post('line2'), 'city' => post('city'), 'province' => post('province'), 'postal_code' => post('postal_code')];
        $ok = mb_strlen($a['recipient']) >= 2 && preg_match('/^(\+639|09)\d{9}$/', $a['phone']) && mb_strlen($a['line1']) >= 5
            && mb_strlen($a['city']) >= 2 && mb_strlen($a['province']) >= 2 && preg_match('/^\d{4}$/', $a['postal_code'])
            && mb_strlen($a['line1']) <= 190 && mb_strlen($a['line2']) <= 190 && mb_strlen($a['recipient']) <= 120;
        if (!$ok) {
            flash('error', 'Please complete every address field (PH mobile number, 4-digit postal code).');
        } else {
            $first = !q_val('SELECT id FROM addresses WHERE user_id=?', [$me['id']]);
            q('INSERT INTO addresses (user_id,label,recipient,phone,line1,line2,city,province,postal_code,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)',
              [$me['id'], $a['label'], $a['recipient'], $a['phone'], $a['line1'], $a['line2'] ?: null, $a['city'], $a['province'], $a['postal_code'], $first ? 1 : 0]);
            flash('success', 'Address added.');
        }
    } elseif ($action === 'address_default') {
        $id = int_param($_POST, 'id');
        if (q_val('SELECT id FROM addresses WHERE id=? AND user_id=?', [$id, $me['id']])) {
            q('UPDATE addresses SET is_default = (id = ?) WHERE user_id = ?', [$id, $me['id']]);
            flash('success', 'Default address updated.');
        }
    } elseif ($action === 'address_delete') {
        q('DELETE FROM addresses WHERE id=? AND user_id=?', [int_param($_POST, 'id'), $me['id']]);
        flash('success', 'Address removed.');
    }
    redirect('account/profile.php');
}

$user = q_one('SELECT name, email, phone, created_at FROM users WHERE id=?', [$me['id']]);
$addresses = q_all('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id', [$me['id']]);
$pageTitle = 'Profile';
$accTab = 'profile';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad acc-layout">
  <?php require ROOT . '/includes/layout/account_nav.php'; ?>
  <section class="stack">
    <h1 class="page-title">Profile &amp; addresses</h1>
    <div class="grid-2 align-start">
      <form class="card" action="<?= url('account/profile.php') ?>" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <h2>Your details</h2>
        <label>Full name<input name="name" value="<?= e($user['name']) ?>" required maxlength="120"></label>
        <label>Email<input value="<?= e($user['email']) ?>" disabled></label>
        <label>Mobile number<input name="phone" value="<?= e($user['phone']) ?>" placeholder="09171234567"></label>
        <p class="muted small">Member since <?= fmt_date($user['created_at'], 'F Y') ?></p>
        <button class="btn btn-dark" type="submit">Save details</button>
      </form>
      <form class="card" action="<?= url('account/profile.php') ?>" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <h2>Change password</h2>
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password" maxlength="72"></label>
        <label>New password<input type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password"></label>
        <label>Confirm new password<input type="password" name="new_password_confirm" required minlength="8" maxlength="72" autocomplete="new-password"></label>
        <button class="btn btn-dark" type="submit">Update password</button>
      </form>
    </div>

    <div class="card">
      <h2>Saved addresses</h2>
      <?php if (!$addresses): ?><p class="muted">No saved addresses yet.</p><?php endif; ?>
      <div class="address-list">
        <?php foreach ($addresses as $a): ?>
          <div class="address-item">
            <p><strong><?= e($a['label']) ?></strong><?= $a['is_default'] ? ' <span class="badge badge-confirmed">Default</span>' : '' ?><br>
              <?= e($a['recipient']) ?> · <?= e($a['phone']) ?><br><?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?>, <?= e($a['city']) ?>, <?= e($a['province']) ?> <?= e($a['postal_code']) ?></p>
            <div class="btn-row">
              <?php if (!$a['is_default']): ?>
                <form action="<?= url('account/profile.php') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address_default"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Make default</button></form>
              <?php endif; ?>
              <form action="<?= url('account/profile.php') ?>" method="post" data-confirm="Remove this address?"><?= csrf_field() ?><input type="hidden" name="action" value="address_delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Remove</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <details class="add-address">
        <summary class="btn btn-tan btn-sm">Add an address</summary>
        <form class="grid-2" action="<?= url('account/profile.php') ?>" method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="address_add">
          <label>Label<input name="label" placeholder="Home, Office…" maxlength="40"></label>
          <label>Recipient<input name="recipient" required maxlength="120"></label>
          <label>Mobile<input name="phone" required placeholder="09171234567"></label>
          <label>Postal code<input name="postal_code" required inputmode="numeric" maxlength="4"></label>
          <label class="span-2">House no. &amp; street<input name="line1" required maxlength="190"></label>
          <label class="span-2">Barangay / building<input name="line2" maxlength="190"></label>
          <label>City<input name="city" required maxlength="100"></label>
          <label>Province<input name="province" required maxlength="100"></label>
          <button class="btn btn-dark" type="submit">Save address</button>
        </form>
      </details>
    </div>
  </section>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
