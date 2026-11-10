<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('products');

$id = int_param($_GET, 'id') ?: int_param($_POST, 'id');
$p = $id ? q_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}
$cats = q_all('SELECT id, name FROM categories WHERE is_archived = 0 ORDER BY sort_order, name');
$errors = [];
$form = $p ?? ['name' => '', 'category_id' => $cats[0]['id'] ?? 0, 'description' => '', 'price' => '', 'stock' => 0, 'badge' => '', 'is_featured' => 0, 'image' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'save') {
    $form = array_merge($form, [
        'name' => mb_substr(post('name'), 0, 120),
        'category_id' => int_param($_POST, 'category_id'),
        'description' => mb_substr(post('description'), 0, 2000),
        'price' => post('price'),
        'stock' => post('stock'),
        'badge' => in_array(post('badge'), ['', 'best', 'new', 'sale'], true) ? post('badge') : '',
        'is_featured' => post('is_featured') === '1' ? 1 : 0,
    ]);
    if (mb_strlen($form['name']) < 2) $errors[] = 'Enter a product name.';
    if (!q_val('SELECT id FROM categories WHERE id = ?', [$form['category_id']])) $errors[] = 'Choose a category.';
    if (!is_numeric($form['price']) || (float) $form['price'] < 0 || (float) $form['price'] > 100000) $errors[] = 'Enter a valid price.';
    if (!preg_match('/^\d{1,6}$/', (string) $form['stock'])) $errors[] = 'Stock must be a whole number.';
    $upload = handle_image_upload($_FILES['image'] ?? []);
    if ($upload['error']) $errors[] = $upload['error'];

    if (!$errors) {
        $image = $upload['ok'] ? $upload['path'] : $form['image'];
        if ($p) {
            q('UPDATE products SET name=?, category_id=?, description=?, price=?, stock=?, badge=?, is_featured=?, image=? WHERE id=?',
                [$form['name'], $form['category_id'], $form['description'], (float) $form['price'], (int) $form['stock'], $form['badge'], $form['is_featured'], $image, $id]);
            if ($upload['ok'] && $p['image'] && str_starts_with($p['image'], 'uploads/') && is_file(ROOT . '/public/' . $p['image'])) {
                unlink(ROOT . '/public/' . $p['image']);
            }
            audit('product_update', "product #$id");
        } else {
            $slug = slugify($form['name']);
            while (q_val('SELECT id FROM products WHERE slug = ?', [$slug])) {
                $slug = slugify($form['name']) . '-' . bin2hex(random_bytes(2));
            }
            q('INSERT INTO products (category_id, name, slug, description, price, stock, image, badge, is_featured) VALUES (?,?,?,?,?,?,?,?,?)',
                [$form['category_id'], $form['name'], $slug, $form['description'], (float) $form['price'], (int) $form['stock'], $image, $form['badge'], $form['is_featured']]);
            $id = (int) db()->lastInsertId();
            audit('product_create', "product #$id");
        }
        flash('success', 'Product saved.');
        redirect('admin/product_form.php?id=' . $id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p) {
    $action = post('action');
    if ($action === 'variant_add') {
        $vn = mb_substr(post('vname'), 0, 60);
        $vd = post('vdelta');
        if (mb_strlen($vn) >= 1 && is_numeric($vd) && abs((float) $vd) <= 10000) {
            q('INSERT INTO product_variants (product_id, name, price_delta) VALUES (?,?,?)', [$id, $vn, (float) $vd]);
            flash('success', 'Variant added.');
        } else {
            flash('error', 'Enter a variant name and a price difference.');
        }
    } elseif ($action === 'variant_toggle') {
        q('UPDATE product_variants SET is_archived = 1 - is_archived WHERE id = ? AND product_id = ?', [int_param($_POST, 'vid'), $id]);
        flash('success', 'Variant updated.');
    }
    if ($action !== 'save') {
        redirect('admin/product_form.php?id=' . $id);
    }
}
$variants = $p ? q_all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY is_archived, price_delta, id', [$id]) : [];

$pageTitle = $p ? 'Edit ' . $p['name'] : 'New product';
$adminTab = 'products';
require ROOT . '/includes/layout/admin_header.php';
?>
<a class="link-btn" href="<?= url('admin/products.php') ?>"><?= icon('arrow-l', 16) ?> Products</a>
<div class="admin-head"><h1><?= $p ? 'Edit product' : 'New product' ?></h1><?php if ($p): ?><a class="btn btn-ghost btn-sm" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>">View in store</a><?php endif; ?></div>
<?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="panel-grid">
  <form class="panel stack" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $id ?>">
    <label>Name<input name="name" value="<?= e($form['name']) ?>" required maxlength="120"></label>
    <label>Category
      <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $form['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>Description<textarea name="description" rows="4" maxlength="2000"><?= e($form['description']) ?></textarea></label>
    <div class="grid-2">
      <label>Price (₱)<input type="number" name="price" value="<?= e($form['price']) ?>" min="0" step="0.01" required></label>
      <label>Stock on hand<input type="number" name="stock" value="<?= e($form['stock']) ?>" min="0" step="1" required></label>
      <label>Badge
        <select name="badge"><?php foreach (['' => 'None', 'best' => 'Best Seller', 'new' => 'New', 'sale' => 'Sale'] as $k => $l): ?><option value="<?= $k ?>"<?= $form['badge'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      </label>
      <label class="check"><input type="checkbox" name="is_featured" value="1"<?= $form['is_featured'] ? ' checked' : '' ?>> Feature on the menu page banner</label>
    </div>
    <label>Product image (JPG, PNG or WEBP, max 2 MB)
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="img-preview">
    </label>
    <img id="img-preview" class="preview" src="<?= e(product_img($form['image'])) ?>" alt="Image preview" width="180" height="180">
    <button class="btn btn-dark" type="submit">Save product</button>
  </form>

  <?php if ($p): ?>
  <div class="panel">
    <h3>Variants &amp; options</h3>
    <p class="muted small">E.g. “Iced · Regular” +₱0 or “Large” +₱20. Customers pick one on the product page.</p>
    <table class="table compact">
      <thead><tr><th>Option</th><th>Price change</th><th>Final price</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($variants as $v): ?>
          <tr class="<?= $v['is_archived'] ? 'is-muted' : '' ?>">
            <td><?= e($v['name']) ?></td><td><?= ((float) $v['price_delta'] >= 0 ? '+' : '−') . money(abs((float) $v['price_delta'])) ?></td>
            <td><?= money((float) $p['price'] + (float) $v['price_delta']) ?></td>
            <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="variant_toggle"><input type="hidden" name="vid" value="<?= (int) $v['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $v['is_archived'] ? 'Restore' : 'Archive' ?></button></form></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$variants): ?><tr><td colspan="4" class="muted">No variants — sold as a single item.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <form class="inline-form" method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="variant_add">
      <label class="grow">Option name<input name="vname" maxlength="60" required placeholder="Iced · Large"></label>
      <label>+/− ₱<input class="num" type="number" name="vdelta" value="0" step="0.01" required></label>
      <button class="btn btn-tan btn-sm" type="submit">Add</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php require ROOT . '/includes/layout/admin_footer.php'; ?>
