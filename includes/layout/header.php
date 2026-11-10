<?php
/** @var string $pageTitle @var string $active @var string $theme  ('dark' header for the landing hero) */
$me = current_user();
$flashes = take_flashes();
$theme = $theme ?? 'light';
$active = $active ?? '';
$navLinks = [
    'home'   => ['index.php', 'Home'],
    'menu'   => ['shop.php', 'Menu'],
    'about'  => ['index.php#about', 'About Us'],
    'facil'  => ['index.php#facilities', 'Facilities'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Specialty coffee') ?> · Hello Brew!</title>
<meta name="description" content="Hello Brew! — specialty coffee, pastries and rice meals, delivered.">
<link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body class="theme-<?= e($theme) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="announce"><?= icon('truck', 16) ?> Free delivery on orders over <?= money(FREE_DELIVERY_MIN) ?> &nbsp;·&nbsp; New here? Use code <strong>HELLOBREW</strong> for 10% off</div>
<header class="site-header">
  <div class="container">
    <nav class="nav-box" aria-label="Main">
      <a class="brand" href="<?= url('index.php') ?>">
        <img src="<?= asset('img/logo.png') ?>" alt="" width="40" height="40">
        <span>Hello Brew!</span>
      </a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-links"><?= icon('menu', 22) ?><span class="sr-only">Menu</span></button>
      <ul class="nav-links" id="nav-links">
        <?php foreach ($navLinks as $key => [$href, $label]): ?>
          <li><a href="<?= url($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="nav-actions">
        <form class="nav-search" action="<?= url('shop.php') ?>" method="get" role="search">
          <label class="sr-only" for="nav-q">Search the menu</label>
          <input id="nav-q" type="search" name="q" placeholder="Search menu…" value="<?= e(get('q')) ?>" maxlength="60">
          <button type="submit" aria-label="Search"><?= icon('search', 18) ?></button>
        </form>
        <a class="icon-btn" href="<?= url('cart.php') ?>" aria-label="Cart, <?= cart_count() ?> items">
          <?= icon('cart', 22) ?><?php if (cart_count() > 0): ?><span class="count"><?= cart_count() ?></span><?php endif; ?>
        </a>
        <?php if ($me): ?>
          <a class="nav-signin" href="<?= url(home_for_role($me['role'])) ?>"><?= icon('user', 18) ?> <?= e(explode(' ', $me['name'])[0]) ?></a>
        <?php else: ?>
          <a class="nav-signin" href="<?= url('login.php') ?>">Sign In</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<main id="main">
<?php if ($flashes): ?>
  <div class="container flash-wrap">
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
