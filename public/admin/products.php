<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('products');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = int_param($_POST, 'id');
    $action = post('action');
    if ($action === 'stock') {
        $delta = int_param($_POST, 'delta');
        if ($delta !== 0 && abs($delta) <= 10000) {
            q('UPDATE products SET stock = GREATEST(0, stock + ?) WHERE id = ?', [$delta, $id]);
            audit('stock_adjust', "product #$id " . ($delta > 0 ? '+' : '') . $delta);
            flash('success', 'Stock updated.');
        }
    } elseif ($action === 'archive' || $action === 'restore') {
        q('UPDATE products SET is_archived = ? WHERE id = ?', [$action === 'archive' ? 1 : 0, $id]);
        audit('product_' . $action, "product #$id");
        flash('success', $action === 'archive' ? 'Product archived — hidden from the store.' : 'Product restored to the store.');
    } elseif ($action === 'delete') {
        // Products that were ever ordered are archived instead, to keep order history intact.
        if (q_val('SELECT id FROM order_items WHERE product_id = ? LIMIT 1', [$id])) {
            q('UPDATE products SET is_archived = 1 WHERE id = ?', [$id]);
            flash('info', 'This product has past orders, so it was archived instead of deleted.');
        } else {
            $img = q_val('SELECT image FROM products WHERE id = ?', [$id]);
            q('DELETE FROM products WHERE id = ?', [$id]);
            if ($img && str_starts_with($img, 'uploads/') && is_file(ROOT . '/public/' . $img)) {
                unlink(ROOT . '/public/' . $img);
            }
            flash('success', 'Product deleted.');
        }
        audit('product_delete', "product #$id");
    }
    redirect('admin/products.php?' . http_build_query(['q' => post('q'), 'cat' => post('cat'), 'archived' => post('archived')]));
}

$search = mb_substr(get('q'), 0, 60);
$cat = int_param($_GET, 'cat');
$archived = get('archived') === '1';
$where = ['p.is_archived = ?']; $params = [$archived ? 1 : 0];
if ($search !== '') { $where[] = 'p.name LIKE ?'; $params[] = '%' . addcslashes($search, '%_\\') . '%'; }
if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
$products = q_all('SELECT p.*, c.name cat, (SELECT COUNT(*) FROM product_variants v WHERE v.product_id=p.id AND v.is_archived=0) variants
                   FROM products p JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort_order, p.name', $params);
$cats = q_all('SELECT id, name FROM categories ORDER BY sort_order, name');

$pageTitle = 'Products';
$adminTab = 'products';
require ROOT . '/includes/layout/admin_header.php';
?>
<div class="admin-head">
  <h1>Products &amp; stock</h1>
  <a class="btn btn-dark" href="<?= url('admin/product_form.php') ?>"><?= icon('plus', 18) ?> New product</a>
</div>
<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products" maxlength="60">
  <select name="cat"><option value="0">All categories</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="archived"><option value="0">Active</option><option value="1"<?= $archived ? ' selected' : '' ?>>Archived</option></select>
  <button class="btn btn-dark btn-sm" type="submit">Filter</button>
</form>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Variants</th><th>Stock</th><th>Adjust stock</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><img class="thumb" src="<?= e(product_img($p['image'])) ?>" alt="" width="44" height="44"></td>
        <td><a href="<?= url('admin/product_form.php?id=' . (int) $p['id']) ?>"><strong><?= e($p['name']) ?></strong></a><?= $p['is_featured'] ? ' <span class="badge badge-confirmed">Featured</span>' : '' ?></td>
        <td><?= e($p['cat']) ?></td>
        <td><?= money($p['price']) ?></td>
        <td><?= (int) $p['variants'] ?></td>
        <td><span class="badge <?= (int) $p['stock'] === 0 ? 'badge-cancelled' : ((int) $p['stock'] <= LOW_STOCK_THRESHOLD ? 'badge-pending' : 'badge-delivered') ?>"><?= (int) $p['stock'] ?></span></td>
        <td>
          <form class="inline-form tight" method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="stock">
            <input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="cat" value="<?= $cat ?>"><input type="hidden" name="archived" value="<?= $archived ? 1 : 0 ?>">
            <input class="num" type="number" name="delta" value="10" min="-10000" max="10000" aria-label="Stock change for <?= e($p['name']) ?>">
            <button class="btn btn-tan btn-sm" type="submit">Apply</button>
          </form>
        </td>
        <td class="nowrap">
          <a class="btn btn-ghost btn-sm" href="<?= url('admin/product_form.php?id=' . (int) $p['id']) ?>">Edit</a>
          <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="<?= $archived ? 'restore' : 'archive' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button></form>
          <?php if (current_user()['role'] === 'admin'): ?>
            <form class="inline" method="post" data-confirm="Delete this product permanently?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="8" class="muted">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
