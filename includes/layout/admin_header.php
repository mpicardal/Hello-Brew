<?php
/** @var string $pageTitle @var string $adminTab */
$me = current_user();
$flashes = take_flashes();
$pendingReturns = can('returns') ? (int) q_val("SELECT COUNT(*) FROM return_requests WHERE status='pending'") : 0;
$newOrders = can('orders') ? (int) q_val("SELECT COUNT(*) FROM orders WHERE status='pending'") : 0;
$menu = [
    ['admin/index.php', 'dashboard', 'Dashboard', 'chart', 'portal'],
    ['admin/orders.php', 'orders', 'Orders', 'box', 'orders'],
    ['admin/returns.php', 'returns', 'Returns & refunds', 'return', 'returns'],
    ['admin/products.php', 'products', 'Products & stock', 'cup', 'products'],
    ['admin/categories.php', 'categories', 'Categories', 'list', 'categories'],
    ['admin/coupons.php', 'coupons', 'Coupons', 'tag', 'coupons'],
    ['admin/customers.php', 'customers', 'Customers', 'users', 'customers'],
    ['admin/reviews.php', 'reviews', 'Reviews', 'star', 'reviews'],
    ['admin/users.php', 'users', 'Staff & roles', 'shield', 'users'],
    ['admin/emails.php', 'emails', 'Email log', 'mail', 'emails'],
    ['admin/audit.php', 'audit', 'Audit log', 'eye', 'audit'],
    ['courier/index.php', 'deliveries', 'My deliveries', 'truck', 'courier'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> · Hello Brew! Admin</title>
<link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Abril+Fatface&family=Lora:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<script src="<?= asset('js/app.js') ?>" defer></script>
</head>
<body class="admin-body">
<div class="admin">
  <aside class="admin-side">
    <a class="brand" href="<?= url(home_for_role($me['role'])) ?>"><img src="<?= asset('img/logo.png') ?>" alt="" width="40" height="40"><span>Hello Brew!</span></a>
    <p class="role-pill"><?= e(ucfirst($me['role'])) ?> portal</p>
    <p class="side-note">Signed in as <?= e($me['name']) ?></p>
    <nav aria-label="Admin">
      <?php foreach ($menu as [$href, $key, $label, $ic, $perm]): if (!can($perm)) continue; ?>
        <a href="<?= url($href) ?>"<?= ($adminTab ?? '') === $key ? ' aria-current="page"' : '' ?>><?= icon($ic, 18) ?> <?= e($label) ?>
          <?php if ($key === 'orders' && $newOrders): ?><span class="count"><?= $newOrders ?></span><?php endif; ?>
          <?php if ($key === 'returns' && $pendingReturns): ?><span class="count"><?= $pendingReturns ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-side-foot">
      <a href="<?= url('index.php') ?>"><?= icon('shop', 18) ?> View store</a>
      <form action="<?= url('logout.php') ?>" method="post"><?= csrf_field() ?><button class="link-btn" type="submit"><?= icon('logout', 18) ?> Sign out <?= e(explode(' ', $me['name'])[0]) ?></button></form>
    </div>
  </aside>
  <main class="admin-main" id="main">
    <?php foreach ($flashes as $f): ?><div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div><?php endforeach; ?>
