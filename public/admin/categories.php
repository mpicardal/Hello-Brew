<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('categories');
$icons = ['cup', 'iced', 'leaf', 'bread', 'bowl', 'plus', 'star'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id = int_param($_POST, 'id');
    $name = mb_substr(post('name'), 0, 80);
    $type = in_array(post('type'), ['drink', 'food', 'addon'], true) ? post('type') : 'drink';
    $icon = in_array(post('icon'), $icons, true) ? post('icon') : 'cup';
    $sort = int_param($_POST, 'sort_order');
    if (in_array($action, ['create', 'update'], true) && mb_strlen($name) < 2) {
        flash('error', 'Enter a category name.');
    } elseif ($action === 'create') {
        $slug = slugify($name);
        if (q_val('SELECT id FROM categories WHERE slug=?', [$slug])) {
            flash('error', 'A category with that name already exists.');
        } else {
            q('INSERT INTO categories (name, slug, type, icon, sort_order) VALUES (?,?,?,?,?)', [$name, $slug, $type, $icon, $sort]);
            audit('category_create', $name);
            flash('success', 'Category added.');
        }
    } elseif ($action === 'update') {
        q('UPDATE categories SET name=?, type=?, icon=?, sort_order=? WHERE id=?', [$name, $type, $icon, $sort, $id]);
        audit('category_update', "#$id");
        flash('success', 'Category saved.');
    } elseif ($action === 'toggle') {
        q('UPDATE categories SET is_archived = 1 - is_archived WHERE id=?', [$id]);
        audit('category_toggle', "#$id");
        flash('success', 'Category visibility changed.');
    }
    redirect('admin/categories.php');
}
$cats = q_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) n FROM categories c ORDER BY sort_order, name');

$pageTitle = 'Categories';
$adminTab = 'categories';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head"><h1>Categories</h1></div>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Name</th><th>Type</th><th>Icon</th><th>Order</th><th>Products</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr class="<?= $c['is_archived'] ? 'is-muted' : '' ?>">
        <td colspan="4">
          <form class="inline-form tight" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input name="name" value="<?= e($c['name']) ?>" maxlength="80" aria-label="Name">
            <select name="type" aria-label="Type"><?php foreach (['drink', 'food', 'addon'] as $t): ?><option<?= $c['type'] === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
            <select name="icon" aria-label="Icon"><?php foreach ($icons as $i): ?><option<?= $c['icon'] === $i ? ' selected' : '' ?>><?= $i ?></option><?php endforeach; ?></select>
            <input class="num" type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>" aria-label="Sort order">
            <button class="btn btn-tan btn-sm" type="submit">Save</button>
          </form>
        </td>
        <td><?= (int) $c['n'] ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $c['is_archived'] ? 'Restore' : 'Archive' ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<form class="panel inline-form" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label class="grow">New category<input name="name" maxlength="80" required></label>
  <label>Type<select name="type"><option>drink</option><option>food</option><option>addon</option></select></label>
  <label>Icon<select name="icon"><?php foreach ($icons as $i): ?><option><?= $i ?></option><?php endforeach; ?></select></label>
  <label>Order<input class="num" type="number" name="sort_order" value="10"></label>
  <button class="btn btn-dark btn-sm" type="submit">Add category</button>
</form>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
