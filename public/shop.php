<?php
require __DIR__ . '/../includes/bootstrap.php';

$qText = mb_substr(get('q'), 0, 60);
$catSlug = get('cat');
$sort = get('sort', 'popular');
$min = is_numeric(get('min')) ? max(0, (float) get('min')) : null;
$max = is_numeric(get('max')) ? max(0, (float) get('max')) : null;
$page = max(1, int_param($_GET, 'page', 1));
$perPage = 12;
$filtering = $qText !== '' || $catSlug !== '' || $min !== null || $max !== null;

track($qText !== '' ? 'search' : 'page_view', null, $qText !== '' ? 'q:' . $qText : 'shop');

$categories = q_all("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.is_archived=0) AS items
                     FROM categories c WHERE c.is_archived = 0 ORDER BY c.sort_order, c.name");

$where = ['p.is_archived = 0', 'c.is_archived = 0'];
$params = [];
if ($qText !== '') {
    $like = '%' . addcslashes($qText, '%_\\') . '%';
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($catSlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $catSlug;
}
if ($min !== null) { $where[] = 'p.price >= ?'; $params[] = $min; }
if ($max !== null) { $where[] = 'p.price <= ?'; $params[] = $max; }
$orderBy = [
    'popular'    => 'sold DESC, p.is_featured DESC, p.id',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'newest'     => 'p.created_at DESC, p.id DESC',
    'name'       => 'p.name ASC',
][$sort] ?? 'sold DESC, p.id';

$whereSql = implode(' AND ', $where);
$total = (int) q_val("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE $whereSql", $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$products = q_all("SELECT p.*, c.name AS cat_name,
        COALESCE((SELECT SUM(oi.qty) FROM order_items oi JOIN orders o ON o.id=oi.order_id
                  WHERE oi.product_id=p.id AND o.status NOT IN ('cancelled','returned')),0) AS sold,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.id AND r.is_approved=1) AS rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.product_id=p.id AND r.is_approved=1) AS rating_count
    FROM products p JOIN categories c ON c.id = p.category_id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);

$featured = $filtering ? [] : q_all("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id
                                     WHERE p.is_featured=1 AND p.is_archived=0 ORDER BY p.id LIMIT 3");
$deal = q_one("SELECT * FROM coupons WHERE is_active=1 AND expires_at > NOW() AND (usage_limit IS NULL OR used_count < usage_limit)
               ORDER BY expires_at LIMIT 1");
$happy = (int) q_val("SELECT COUNT(DISTINCT user_id) FROM orders WHERE status NOT IN ('cancelled')");

function shop_link(array $over): string
{
    $params = array_filter(array_merge([
        'q' => get('q'), 'cat' => get('cat'), 'sort' => get('sort'), 'min' => get('min'), 'max' => get('max'),
    ], $over), fn($v) => $v !== '' && $v !== null);
    return url('shop.php' . ($params ? '?' . http_build_query($params) : ''));
}
$badgeText = ['best' => 'Best Seller', 'new' => 'New', 'sale' => 'Sale'];

$pageTitle = 'Menu';
$active = 'menu';
require ROOT . '/includes/layout/header.php';
?>
<div class="shop">
<?php if ($featured): ?>
  <section class="container shop-hero" data-carousel>
    <?php foreach ($featured as $i => $f): ?>
      <div class="slide<?= $i === 0 ? ' is-active' : '' ?>" data-slide>
        <div class="slide-copy">
          <span class="chip"><?= icon('star', 14) ?> Today's pick · <?= e($f['cat_name']) ?></span>
          <h1><?= e($f['name']) ?></h1>
          <p><?= e($f['description']) ?></p>
          <div class="btn-row">
            <a class="btn btn-dark" href="<?= url('product.php?slug=' . urlencode($f['slug'])) ?>">Shop Now <?= icon('arrow-r', 18) ?></a>
            <?php if ($happy > 0): ?><span class="happy"><strong><?= $happy ?>+</strong> happy customers</span><?php endif; ?>
          </div>
        </div>
        <div class="slide-media">
          <img src="<?= e(product_img($f['image'])) ?>" alt="<?= e($f['name']) ?>" width="420" height="420">
          <span class="price-bubble">Only<strong><?= money($f['price']) ?></strong></span>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="dots" role="tablist" aria-label="Featured items">
      <?php foreach ($featured as $i => $f): ?>
        <button type="button" class="dot<?= $i === 0 ? ' is-active' : '' ?>" data-dot="<?= $i ?>" aria-label="Show <?= e($f['name']) ?>"></button>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

  <section class="container shop-cats" aria-labelledby="cat-title">
    <div class="section-head split">
      <div>
        <p class="kicker"><?= icon('shop', 14) ?> Shop by category</p>
        <h2 id="cat-title">Browse by Category</h2>
      </div>
      <div class="arrows">
        <button type="button" class="round-btn" data-scroll="-1" aria-label="Scroll categories left"><?= icon('arrow-l', 18) ?></button>
        <button type="button" class="round-btn" data-scroll="1" aria-label="Scroll categories right"><?= icon('arrow-r', 18) ?></button>
      </div>
    </div>
    <div class="cat-row" data-scroller>
      <a class="cat-card<?= $catSlug === '' ? ' is-active' : '' ?>" href="<?= shop_link(['cat' => '', 'page' => '']) ?>">
        <span class="cat-icon"><?= icon('list', 30) ?></span><strong>Everything</strong><small><?= array_sum(array_column($categories, 'items')) ?> items</small>
      </a>
      <?php foreach ($categories as $c): ?>
        <a class="cat-card<?= $catSlug === $c['slug'] ? ' is-active' : '' ?>" href="<?= shop_link(['cat' => $c['slug'], 'page' => '']) ?>">
          <span class="cat-icon"><?= icon($c['icon'], 30) ?></span><strong><?= e($c['name']) ?></strong><small><?= (int) $c['items'] ?> items</small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

<?php if ($deal && !$filtering): ?>
  <section class="container">
    <div class="offer">
      <div class="offer-copy">
        <span class="chip chip-light"><?= icon('tag', 14) ?> Special offer</span>
        <h2>Enhance Your Coffee Break</h2>
        <p><?= e($deal['description']) ?> — use code <strong><?= e($deal['code']) ?></strong> at checkout.</p>
        <div class="countdown" data-deadline="<?= e(date('c', strtotime($deal['expires_at']))) ?>">
          <div><strong data-cd="d">--</strong><span>Days</span></div>
          <div><strong data-cd="h">--</strong><span>Hours</span></div>
          <div><strong data-cd="m">--</strong><span>Mins</span></div>
          <div><strong data-cd="s">--</strong><span>Secs</span></div>
        </div>
        <a class="btn btn-light" href="<?= shop_link(['cat' => 'hot-iced-coffee']) ?>">Check it Out <?= icon('arrow-r', 18) ?></a>
      </div>
      <img class="offer-img" src="<?= asset('img/latte-art.jpg') ?>" alt="Latte art being poured" width="338" height="410">
    </div>
  </section>
<?php endif; ?>

  <section class="container shop-products" id="products" aria-labelledby="prod-title">
    <div class="section-head split">
      <div>
        <p class="kicker"><?= icon('cup', 14) ?> Our collection</p>
        <h2 id="prod-title"><?= $qText !== '' ? 'Results for “' . e($qText) . '”' : 'Explore Our Best Sellers' ?></h2>
      </div>
      <form class="filters" method="get" action="<?= url('shop.php') ?>#products">
        <?php if ($qText !== ''): ?><input type="hidden" name="q" value="<?= e($qText) ?>"><?php endif; ?>
        <label><span class="sr-only">Category</span>
          <select name="cat">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= e($c['slug']) ?>"<?= $catSlug === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><span class="sr-only">Minimum price</span><input type="number" name="min" min="0" step="1" placeholder="Min ₱" value="<?= e(get('min')) ?>"></label>
        <label><span class="sr-only">Maximum price</span><input type="number" name="max" min="0" step="1" placeholder="Max ₱" value="<?= e(get('max')) ?>"></label>
        <label><span class="sr-only">Sort by</span>
          <select name="sort">
            <?php foreach (['popular' => 'Most popular', 'newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'name' => 'Name A–Z'] as $k => $l): ?>
              <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-dark btn-sm" type="submit">Apply</button>
        <?php if ($filtering): ?><a class="btn btn-ghost btn-sm" href="<?= url('shop.php') ?>">Clear</a><?php endif; ?>
      </form>
    </div>

    <?php if (!$products): ?>
      <div class="empty">
        <p>No items match your search. Try another word or clear the filters.</p>
        <a class="btn btn-dark" href="<?= url('shop.php') ?>">See the full menu</a>
      </div>
    <?php else: ?>
      <p class="muted small"><?= $total ?> item<?= $total === 1 ? '' : 's' ?></p>
      <div class="product-grid">
        <?php foreach ($products as $p): $out = (int) $p['stock'] < 1; ?>
          <article class="product-card">
            <?php if ($out): ?><span class="pbadge pbadge-out">Sold out</span>
            <?php elseif ($p['badge'] !== ''): ?><span class="pbadge pbadge-<?= e($p['badge']) ?>"><?= e($badgeText[$p['badge']]) ?></span><?php endif; ?>
            <a class="product-img" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>">
              <img src="<?= e(product_img($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="260" height="260">
            </a>
            <div class="product-info">
              <h3><a href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
              <p class="muted small"><?= e($p['cat_name']) ?></p>
              <?php if ((int) $p['rating_count'] > 0): ?>
                <p class="rating"><?= stars((float) $p['rating']) ?> <span class="muted small">(<?= (int) $p['rating_count'] ?>)</span></p>
              <?php endif; ?>
              <div class="product-foot">
                <span class="price"><?= money($p['price']) ?></span>
                <form action="<?= url('cart.php') ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                  <button class="cart-btn" type="submit" aria-label="Add <?= e($p['name']) ?> to cart" <?= $out ? 'disabled' : '' ?>><?= icon('cart', 18) ?></button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="<?= shop_link(['page' => $i]) ?>#products"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="container">
    <div class="perks">
      <div><?= icon('truck', 26) ?><p><strong>Free Delivery</strong><span>On orders over <?= money(FREE_DELIVERY_MIN) ?></span></p></div>
      <div><?= icon('return', 26) ?><p><strong><?= RETURN_WINDOW_DAYS ?>-Day Returns</strong><span>Request it from your account</span></p></div>
      <div><?= icon('shield', 26) ?><p><strong>Secure Payment</strong><span>Protected checkout</span></p></div>
      <div><?= icon('chat', 26) ?><p><strong>Friendly Support</strong><span><?= e(CAFE_EMAIL) ?></span></p></div>
    </div>
  </section>
</div>
<?php require ROOT . '/includes/layout/footer.php'; ?>
