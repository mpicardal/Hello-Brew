<?php /** @var string $accTab */ ?>
<aside class="acc-nav">
  <p class="acc-hello">Hi, <strong><?= e(explode(' ', current_user()['name'])[0]) ?></strong></p>
  <nav aria-label="Account">
    <a href="<?= url('account/index.php') ?>"<?= $accTab === 'orders' ? ' aria-current="page"' : '' ?>><?= icon('box', 18) ?> My orders</a>
    <a href="<?= url('account/returns.php') ?>"<?= $accTab === 'returns' ? ' aria-current="page"' : '' ?>><?= icon('return', 18) ?> Returns &amp; cancellations</a>
    <a href="<?= url('account/profile.php') ?>"<?= $accTab === 'profile' ? ' aria-current="page"' : '' ?>><?= icon('user', 18) ?> Profile &amp; addresses</a>
  </nav>
  <form action="<?= url('logout.php') ?>" method="post"><?= csrf_field() ?><button class="link-btn" type="submit"><?= icon('logout', 18) ?> Sign out</button></form>
</aside>
