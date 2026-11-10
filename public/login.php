<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($me = current_user()) {
    redirect(home_for_role($me['role']));
}
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(mb_substr(post('email'), 0, 190));
    $password = (string) ($_POST['password'] ?? '');
    if (login_is_locked($email)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        // Verify against a dummy hash when the user doesn't exist, so timing doesn't reveal accounts.
        $hash = $u['password_hash'] ?? password_hash('timing-equaliser', PASSWORD_DEFAULT);
        if (password_verify($password, $hash) && $u && (int) $u['is_active'] === 1) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            login_clear_failures($email);
            login_user($u);
            if ($u['role'] !== 'customer') {
                audit('login', $u['role']);
            }
            $next = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            if ($u['role'] === 'customer' && is_string($next) && preg_match('#^/[A-Za-z0-9/_\-.?=&%]*$#', $next) && !str_starts_with($next, '//')) {
                $next = APP_BASE !== '' && str_starts_with($next, APP_BASE) ? substr($next, strlen(APP_BASE)) : $next;
                redirect($next);
            }
            redirect(home_for_role($u['role']));
        }
        login_record_failure($email);
        $error = 'The email or password is incorrect.';
    }
}
$pageTitle = 'Sign in';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad">
  <div class="auth-card">
    <img src="<?= asset('img/logo.png') ?>" alt="" width="72" height="72">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to order, track deliveries and manage returns.</p>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form action="<?= url('login.php') ?>" method="post">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email" autofocus></label>
      <label>Password<input type="password" name="password" required maxlength="72" autocomplete="current-password"></label>
      <button class="btn btn-dark btn-block" type="submit">Sign In</button>
    </form>
    <p class="small">New to Hello Brew!? <a href="<?= url('register.php') ?>">Create an account</a></p>
  </div>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
