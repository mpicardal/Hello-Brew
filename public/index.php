<?php
require __DIR__ . '/../includes/bootstrap.php';
track('page_view', null, 'home');

$groups = ['hot-iced-coffee' => 'coffee', 'tea-non-coffee' => 'noncoffee', 'bakery-snacks' => 'pastries', 'pasta-rice-meals' => 'meals'];

// Best sellers: by delivered/active quantity sold, topped up with featured items.
$best = q_all("SELECT p.*, c.slug AS cat_slug, COALESCE(s.sold,0) AS sold
    FROM products p JOIN categories c ON c.id = p.category_id
    LEFT JOIN (SELECT oi.product_id, SUM(oi.qty) sold FROM order_items oi
               JOIN orders o ON o.id = oi.order_id AND o.status NOT IN ('cancelled','returned')
               GROUP BY oi.product_id) s ON s.product_id = p.id
    WHERE p.is_archived = 0 AND c.is_archived = 0 AND c.type <> 'addon'
    ORDER BY (COALESCE(s.sold,0) > 0) DESC, s.sold DESC, p.is_featured DESC, p.id
    LIMIT 12");

$menuCount  = (int) q_val("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_archived=0 AND c.type<>'addon'");
$cupsServed = (int) q_val("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status='delivered'");
$customers  = (int) q_val("SELECT COUNT(DISTINCT user_id) FROM orders WHERE status='delivered'");
$activeOrders = (int) q_val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','preparing','packed','out_for_delivery')");

$reviews = q_all("SELECT r.rating, r.comment, r.created_at, u.name, p.name AS product
    FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
    WHERE r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 3");

$pageTitle = 'Discover the art of perfect coffee';
$active = 'home';
$theme = 'dark';
require ROOT . '/includes/layout/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-media">
      <span class="hero-ghost" aria-hidden="true">Brew</span>
      <img src="<?= asset('img/hero-cup.png') ?>" alt="Coffee beans splashing into a white cup of coffee" width="625" height="720">
    </div>
    <div class="hero-copy">
      <p class="eyebrow">Specialty coffee · Pastries · Rice meals</p>
      <h1>Discover The Art Of Perfect Coffee</h1>
      <p class="lead">Experience the rich and bold flavors of our coffee blends, crafted to awaken your senses and start your day right.</p>
      <div class="btn-row">
        <a class="btn btn-light" href="<?= url('shop.php') ?>">Order Now <?= icon('arrow-r', 18) ?></a>
        <a class="btn btn-outline-light" href="#about">Explore More</a>
      </div>
      <dl class="hero-stats">
        <div><dt><?= $menuCount ?>+</dt><dd>Items on the menu</dd></div>
        <div><dt><?= $activeOrders > 0 ? $activeOrders : '7' ?></dt><dd><?= $activeOrders > 0 ? 'Orders brewing now' : 'Days a week open' ?></dd></div>
        <div><dt><?= $customers > 0 ? $customers . '+' : '100%' ?></dt><dd><?= $customers > 0 ? 'Happy customers' : 'Arabica beans' ?></dd></div>
      </dl>
    </div>
  </div>
</section>

<section class="story" id="about">
  <div class="container story-grid">
    <div class="story-copy">
      <p class="eyebrow dark">Our story</p>
      <h2>Coffee Heaven</h2>
      <p>Every cup at Hello Brew! starts with freshly roasted beans, pulled by baristas who care about the details — from the crema on a brewed coffee to the micro-foam on a Spanish latte.</p>
      <div class="story-chapter">
        <h3>The Hello Brew! Corner</h3>
        <p>Pair your drink with pan de coco warm from the oven, a slice of chocolate cake, or a hearty tapa rice meal. Order ahead for delivery and we'll have it at your door.</p>
      </div>
      <a class="btn btn-dark" href="<?= url('shop.php') ?>">View All <?= icon('arrow-r', 18) ?></a>
    </div>
    <div class="story-collage">
      <img class="photo photo-a" src="<?= asset('img/latte-art.jpg') ?>" alt="Barista pouring latte art into a cup" width="338" height="410">
      <img class="photo photo-b" src="<?= asset('img/iced-coffee.jpg') ?>" alt="Iced caramel coffee and a hot latte on a café table" width="356" height="424">
      <span class="collage-badge"><img src="<?= asset('img/logo.png') ?>" alt="" width="84" height="84"></span>
    </div>
  </div>
</section>

<section class="bestsellers" aria-labelledby="best-title">
  <div class="container">
    <div class="section-head split">
      <div>
        <h2 id="best-title">Best Selling Items</h2>
        <p>The drinks and bites our regulars order again and again.</p>
      </div>
      <div class="tabs" role="tablist" aria-label="Filter best sellers">
        <?php foreach (['all' => 'All', 'coffee' => 'Coffee', 'noncoffee' => 'Non-Coffee', 'pastries' => 'Pastries', 'meals' => 'Meals'] as $k => $label): ?>
          <button type="button" role="tab" class="tab<?= $k === 'all' ? ' is-active' : '' ?>" data-filter="<?= $k ?>" aria-selected="<?= $k === 'all' ? 'true' : 'false' ?>"><?= $label ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="best-grid">
      <?php foreach ($best as $p): ?>
        <article class="best-card" data-group="<?= e($groups[$p['cat_slug']] ?? 'all') ?>">
          <a class="best-img" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>">
            <img src="<?= e(product_img($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="300" height="300">
          </a>
          <div class="best-body">
            <h3><a href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
            <p class="price"><?= money($p['price']) ?></p>
          </div>
          <form action="<?= url('cart.php') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-tan btn-block" type="submit" <?= (int) $p['stock'] < 1 ? 'disabled' : '' ?>><?= (int) $p['stock'] < 1 ? 'Sold out' : 'Order Now' ?></button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="facilities" id="facilities" aria-labelledby="fac-title">
  <div class="container">
    <h2 id="fac-title" class="center">Facilities</h2>
    <div class="fac-grid">
      <div class="fac-card"><?= icon('wifi', 30) ?><h3>Work-friendly seats</h3><p>Fast Wi-Fi, power outlets and quiet corners for studying or remote work.</p></div>
      <div class="fac-card"><?= icon('truck', 30) ?><h3>Delivery to your door</h3><p>Order online and track every step, from the espresso machine to your doorstep.</p></div>
      <div class="fac-card"><?= icon('bread', 30) ?><h3>Baked fresh daily</h3><p>Breads, cakes and sandwiches are prepared every morning in our kitchen.</p></div>
    </div>
  </div>
</section>

<section class="testimonials" aria-labelledby="rev-title">
  <div class="container">
    <h2 id="rev-title" class="center">What Our Customers Say</h2>
    <?php if ($reviews): ?>
      <div class="rev-grid">
        <?php foreach ($reviews as $r): ?>
          <figure class="rev-card">
            <span class="rev-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
            <blockquote><?= e($r['comment']) ?></blockquote>
            <?= stars((float) $r['rating']) ?>
            <figcaption><strong><?= e(explode(' ', $r['name'])[0]) ?></strong> on <?= e($r['product']) ?> · Verified order</figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="rev-empty">
        <p>Reviews from verified customers will appear here. Ordered from us? Leave a review from your order page after delivery.</p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require ROOT . '/includes/layout/footer.php'; ?>
