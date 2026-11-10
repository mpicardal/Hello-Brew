<?php
require __DIR__ . '/../includes/bootstrap.php';

$p = q_one('SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p JOIN categories c ON c.id=p.category_id
            WHERE p.slug = ? AND p.is_archived = 0', [get('slug')]);
if (!$p) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require ROOT . '/includes/layout/header.php';
    echo '<div class="container page-pad"><div class="empty"><h1>We couldn’t find that item</h1><a class="btn btn-dark" href="' . url('shop.php') . '">Back to the menu</a></div></div>';
    require ROOT . '/includes/layout/footer.php';
    exit;
}
track('product_view', (int) $p['id']);

$variants = q_all('SELECT * FROM product_variants WHERE product_id = ? AND is_archived = 0 ORDER BY price_delta, id', [$p['id']]);
$reviews = q_all('SELECT r.*, u.name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.is_approved=1 ORDER BY r.created_at DESC LIMIT 10', [$p['id']]);
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$related = q_all('SELECT * FROM products WHERE category_id=? AND id<>? AND is_archived=0 ORDER BY RAND() LIMIT 4', [$p['category_id'], $p['id']]);
$addons = q_all("SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE c.type='addon' AND p.is_archived=0 AND c.is_archived=0");
$out = (int) $p['stock'] < 1;

$pageTitle = $p['name'];
$active = 'menu';
require ROOT . '/includes/layout/header.php';
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= url('shop.php') ?>">Menu</a> / <a href="<?= url('shop.php?cat=' . urlencode($p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a> / <span><?= e($p['name']) ?></span></nav>
  <div class="pdp">
    <div class="pdp-media"><img src="<?= e(product_img($p['image'])) ?>" alt="<?= e($p['name']) ?>" width="520" height="520"></div>
    <div class="pdp-info">
      <p class="kicker"><?= e($p['cat_name']) ?></p>
      <h1><?= e($p['name']) ?></h1>
      <?php if ($reviews): ?><p class="rating"><?= stars($avg) ?> <span class="muted small"><?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?></span></p><?php endif; ?>
      <p class="pdp-price" data-base="<?= e($p['price']) ?>"><?= money($p['price']) ?></p>
      <p><?= nl2br(e($p['description'])) ?></p>
      <p class="stock <?= $out ? 'stock-out' : ((int) $p['stock'] <= LOW_STOCK_THRESHOLD ? 'stock-low' : '') ?>">
        <?= $out ? 'Sold out for today' : ((int) $p['stock'] <= LOW_STOCK_THRESHOLD ? 'Only ' . (int) $p['stock'] . ' left today' : 'Available today') ?>
      </p>
      <form class="pdp-form" action="<?= url('cart.php') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <?php if ($variants): ?>
          <fieldset class="variants">
            <legend>Choose an option</legend>
            <?php foreach ($variants as $i => $v): ?>
              <label class="variant">
                <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" data-delta="<?= e($v['price_delta']) ?>"<?= $i === 0 ? ' checked' : '' ?>>
                <span><?= e($v['name']) ?><?php if ((float) $v['price_delta'] != 0): ?> <small>+<?= money($v['price_delta']) ?></small><?php endif; ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
        <?php endif; ?>
        <div class="qty-row">
          <div class="qty" data-qty>
            <button type="button" data-step="-1" aria-label="Decrease quantity"><?= icon('minus', 16) ?></button>
            <input type="number" name="qty" value="1" min="1" max="50" aria-label="Quantity">
            <button type="button" data-step="1" aria-label="Increase quantity"><?= icon('plus', 16) ?></button>
          </div>
          <button class="btn btn-dark" type="submit" <?= $out ? 'disabled' : '' ?>><?= icon('cart', 18) ?> Add to cart</button>
        </div>
      </form>
      <?php if ($addons && $p['cat_slug'] === 'hot-iced-coffee'): ?>
        <div class="addons">
          <p class="small"><strong>Make it yours:</strong></p>
          <?php foreach ($addons as $a): ?>
            <form action="<?= url('cart.php') ?>" method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= (int) $a['id'] ?>">
              <button class="chip-btn" type="submit"><?= icon('plus', 14) ?> <?= e($a['name']) ?> <?= money($a['price']) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <section class="pdp-reviews" aria-labelledby="pr-title">
    <h2 id="pr-title">Customer reviews</h2>
    <?php if (!$reviews): ?>
      <p class="muted">No reviews yet. Customers can review this item after their order is delivered.</p>
    <?php endif; ?>
    <?php foreach ($reviews as $r): ?>
      <div class="review-row">
        <?= stars((float) $r['rating']) ?> <strong><?= e(explode(' ', $r['name'])[0]) ?></strong> <span class="muted small"><?= fmt_date($r['created_at'], 'M j, Y') ?> · Verified order</span>
        <p><?= e($r['comment']) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($related): ?>
    <section aria-labelledby="rel-title">
      <h2 id="rel-title">You may also like</h2>
      <div class="product-grid">
        <?php foreach ($related as $r): ?>
          <article class="product-card">
            <a class="product-img" href="<?= url('product.php?slug=' . urlencode($r['slug'])) ?>"><img src="<?= e(product_img($r['image'])) ?>" alt="<?= e($r['name']) ?>" loading="lazy" width="260" height="260"></a>
            <div class="product-info"><h3><a href="<?= url('product.php?slug=' . urlencode($r['slug'])) ?>"><?= e($r['name']) ?></a></h3><span class="price"><?= money($r['price']) ?></span></div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
