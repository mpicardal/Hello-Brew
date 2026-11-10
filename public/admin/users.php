<?php
require __DIR__ . '/../../includes/bootstrap.php';
$me = require_perm('users');
$roles = [
    'admin'       => 'Admin — everything, incl. refunds, coupons and staff',
    'staff'       => 'Staff — products, orders, returns, analytics',
    'fulfillment' => 'Fulfillment — order queue and couriers only',
    'courier'     => 'Courier — only their assigned deliveries',
];

function temp_password(): string
{
    $a = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $p = '';
    for ($i = 0; $i < 12; $i++) {
        $p .= $a[random_int(0, strlen($a) - 1)];
    }
    return $p . random_int(10, 99);
}

function active_admins(): int
{
    return (int) q_val("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id = int_param($_POST, 'id');
    $target = $id ? q_one("SELECT * FROM users WHERE id = ? AND role <> 'customer'", [$id]) : null;

    if ($action === 'create') {
        $name = mb_substr(post('name'), 0, 120);
        $email = mb_strtolower(mb_substr(post('email'), 0, 190));
        $role = post('role');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($roles[$role])) {
            flash('error', 'Enter a name, a valid email and a role.');
        } elseif (q_val('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', 'That email is already used by another account.');
        } else {
            $pw = temp_password();
            q('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
            audit('staff_create', "$email as $role");
            $_SESSION['new_password'] = ['email' => $email, 'pw' => $pw];   // shown once, then forgotten
        }
    } elseif ($target && (int) $target['id'] === (int) $me['id']) {
        flash('error', 'You can’t change your own role or status. Ask another admin.');
    } elseif ($target && $action === 'role' && isset($roles[post('role')])) {
        if ($target['role'] === 'admin' && post('role') !== 'admin' && active_admins() <= 1) {
            flash('error', 'There must always be at least one active admin.');
        } else {
            q('UPDATE users SET role = ? WHERE id = ?', [post('role'), $id]);
            audit('staff_role', "user #$id → " . post('role'));
            flash('success', 'Role updated.');
        }
    } elseif ($target && $action === 'toggle') {
        q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
        if ($target['role'] === 'courier' && $target['is_active']) {
            q("UPDATE orders SET courier_id = NULL WHERE courier_id = ? AND status IN ('confirmed','preparing','packed')", [$id]);
        }
        audit('staff_toggle', "user #$id");
        flash('success', $target['is_active'] ? 'Account disabled.' : 'Account enabled.');
    } elseif ($target && $action === 'reset') {
        $pw = temp_password();
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        audit('staff_reset_password', "user #$id");
        $_SESSION['new_password'] = ['email' => $target['email'], 'pw' => $pw];
    }
    redirect('admin/users.php');
}

$newPw = $_SESSION['new_password'] ?? null;
unset($_SESSION['new_password']);
$users = q_all("SELECT id, name, email, role, is_active, last_login_at, created_at FROM users WHERE role <> 'customer' ORDER BY FIELD(role,'admin','staff','fulfillment','courier'), name");

$pageTitle = 'Staff & roles';
$adminTab = 'users';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Staff &amp; roles</h1></div>
<?php if ($newPw): ?>
  <div class="flash flash-success" role="status">Temporary password for <strong><?= e($newPw['email']) ?></strong>: <code><?= e($newPw['pw']) ?></code> — copy it now and share it privately. It won’t be shown again.</div>
<?php endif; ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
      <tr class="<?= $u['is_active'] ? '' : 'is-muted' ?>">
        <td><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="muted small">(you)</span>' : '' ?></td>
        <td><?= e($u['email']) ?></td>
        <td>
          <?php if ($self): ?><?= e(ucfirst($u['role'])) ?>
          <?php else: ?>
            <form class="inline-form tight" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <select name="role" aria-label="Role for <?= e($u['name']) ?>"><?php foreach ($roles as $r => $_): ?><option value="<?= $r ?>"<?= $u['role'] === $r ? ' selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select>
              <button class="btn btn-tan btn-sm" type="submit">Save</button>
            </form>
          <?php endif; ?>
        </td>
        <td><?= $u['last_login_at'] ? fmt_date($u['last_login_at']) : 'Never' ?></td>
        <td><?= $u['is_active'] ? 'Active' : 'Disabled' ?></td>
        <td class="nowrap">
          <?php if (!$self): ?>
            <form class="inline" method="post" data-confirm="Generate a new temporary password for <?= e($u['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Reset password</button></form>
            <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel-grid">
  <form class="panel stack" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h3>Add a team member</h3>
    <label>Full name<input name="name" required maxlength="120"></label>
    <label>Email<input type="email" name="email" required maxlength="190"></label>
    <label>Role<select name="role"><?php foreach ($roles as $r => $l): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-dark" type="submit">Create account</button>
    <p class="muted small">A random temporary password is generated and shown once.</p>
  </form>
  <div class="panel">
    <h3>What each role can do</h3>
    <ul class="plain-list"><?php foreach ($roles as $r => $l): ?><li><span><?= e($l) ?></span></li><?php endforeach; ?></ul>
    <p class="muted small">Customers sign up on the store and can never open the portal. Fulfillment staff see customer emails masked.</p>
  </div>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
