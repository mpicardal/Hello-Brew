<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($me = current_user()) {
    redirect(home_for_role($me['role']));
}
$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = ['name' => mb_substr(post('name'), 0, 120), 'email' => mb_strtolower(mb_substr(post('email'), 0, 190)), 'phone' => post('phone')];
    $pw = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password_confirm'] ?? '');
    if (mb_strlen($old['name']) < 2) $errors[] = 'Enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    $phone = preg_replace('/[\s\-()]/', '', $old['phone']);
    if ($phone !== '' && !preg_match('/^(\+639|09)\d{9}$/', $phone)) $errors[] = 'Enter a PH mobile number like 09171234567, or leave it blank.';
    if ($p = password_problem($pw)) $errors[] = $p;
    if ($pw !== $pw2) $errors[] = 'The passwords do not match.';
    if (empty($_POST['terms'])) $errors[] = 'Please agree to the privacy notice.';
    if (!$errors && q_val('SELECT id FROM users WHERE email = ?', [$old['email']])) {
        $errors[] = 'An account with that email already exists. Try signing in.';
    }
    if (!$errors) {
        // Public sign-up can only ever create customer accounts.
        q('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)',
            [$old['name'], $old['email'], $phone ?: null, password_hash($pw, PASSWORD_DEFAULT), 'customer']);
        $u = q_one('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()]);
        login_user($u);
        flash('success', 'Welcome to Hello Brew!, ' . explode(' ', $u['name'])[0] . '!');
        redirect(cart_count() > 0 ? 'checkout.php' : 'shop.php');
    }
}
$pageTitle = 'Create account';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad">
  <div class="auth-card">
    <img src="<?= asset('img/logo.png') ?>" alt="" width="72" height="72">
    <h1>Create your account</h1>
    <p class="muted">Order ahead, track deliveries and see your order history.</p>
    <?php if ($errors): ?><div class="flash flash-error" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form action="<?= url('register.php') ?>" method="post">
      <?= csrf_field() ?>
      <label>Full name<input name="name" value="<?= e($old['name']) ?>" required maxlength="120" autocomplete="name"></label>
      <label>Email<input type="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190" autocomplete="email"></label>
      <label>Mobile number (optional)<input name="phone" value="<?= e($old['phone']) ?>" placeholder="09171234567" autocomplete="tel"></label>
      <label>Password<input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password"><span class="hint">At least 8 characters with letters and numbers.</span></label>
      <label>Confirm password<input type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password"></label>
      <label class="check"><input type="checkbox" name="terms" value="1" required> I agree that Hello Brew! stores my details to process orders. Passwords are stored as one-way hashes.</label>
      <button class="btn btn-dark btn-block" type="submit">Create account</button>
    </form>
    <p class="small">Already have an account? <a href="<?= url('login.php') ?>">Sign in</a></p>
  </div>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
