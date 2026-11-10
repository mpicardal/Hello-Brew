</main>

<section class="newsletter" aria-labelledby="nl-title">
  <div class="container newsletter-card">
    <div>
      <h2 id="nl-title">Stay up to date on all news and offers.</h2>
      <p>Be the first to know about new drinks, seasonal pastries and what's brewing in our kitchen.</p>
    </div>
    <form class="nl-form" action="<?= url('subscribe.php') ?>" method="post">
      <?= csrf_field() ?>
      <label class="sr-only" for="nl-email">Email address</label>
      <input id="nl-email" type="email" name="email" placeholder="Enter your email address" required maxlength="190">
      <button class="btn btn-dark" type="submit">Subscribe <?= icon('arrow-r', 18) ?></button>
    </form>
  </div>
</section>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?= asset('img/logo.png') ?>" alt="Hello Brew! logo" width="120" height="120">
      <p>Enjoy better and better coffee with Hello Brew! — brewed fresh, baked daily, delivered warm.</p>
    </div>
    <div>
      <h3>Contact Us</h3>
      <ul class="footer-list">
        <li><?= icon('mail', 16) ?> <?= e(CAFE_EMAIL) ?></li>
        <li><?= icon('phone', 16) ?> <?= e(CAFE_PHONE) ?></li>
        <li><?= icon('pin', 16) ?> <?= e(CAFE_ADDRESS) ?></li>
      </ul>
    </div>
    <div>
      <h3>Opening Hours</h3>
      <ul class="footer-list">
        <li><?= icon('clock', 16) ?> Mon – Fri · 7:00 AM – 9:00 PM</li>
        <li><?= icon('clock', 16) ?> Sat – Sun · 8:00 AM – 10:00 PM</li>
        <li><a href="<?= url('shop.php') ?>">Browse the menu →</a></li>
      </ul>
    </div>
    <div>
      <img class="footer-photo" src="<?= asset('img/beans-sack.jpg') ?>" alt="A cup of coffee beside a sack of roasted beans" width="258" height="177">
      <h3>Follow Us</h3>
      <div class="socials">
        <a href="#" aria-label="Facebook"><?= icon('facebook', 18) ?></a>
        <a href="#" aria-label="Instagram"><?= icon('instagram', 18) ?></a>
        <a href="#" aria-label="TikTok"><?= icon('tiktok', 18) ?></a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">© <?= date('Y') ?> Hello Brew! · All rights reserved</div>
</footer>
</body>
</html>
