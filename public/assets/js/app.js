/* Hello Brew! — small progressive enhancements. Every page still works without JS. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // Mobile nav
  const toggle = $('.nav-toggle');
  if (toggle) {
    toggle.addEventListener('click', () => {
      const links = $('#nav-links');
      const open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  // Best-seller tabs (landing page)
  $$('.tabs [data-filter]').forEach(tab => {
    tab.addEventListener('click', () => {
      const f = tab.dataset.filter;
      $$('.tabs [data-filter]').forEach(t => {
        const on = t === tab;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', String(on));
      });
      $$('.best-card').forEach(c => { c.hidden = f !== 'all' && c.dataset.group !== f; });
    });
  });

  // Featured carousel (menu page)
  const car = $('[data-carousel]');
  if (car) {
    const slides = $$('[data-slide]', car);
    const dots = $$('[data-dot]', car);
    let i = 0, timer;
    const show = n => {
      i = (n + slides.length) % slides.length;
      slides.forEach((s, k) => s.classList.toggle('is-active', k === i));
      dots.forEach((d, k) => d.classList.toggle('is-active', k === i));
    };
    const start = () => { if (slides.length > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => show(i + 1), 6000); };
    dots.forEach(d => d.addEventListener('click', () => { clearInterval(timer); show(+d.dataset.dot); start(); }));
    car.addEventListener('mouseenter', () => clearInterval(timer));
    car.addEventListener('mouseleave', start);
    start();
  }

  // Category scroller arrows
  const scroller = $('[data-scroller]');
  $$('[data-scroll]').forEach(b => b.addEventListener('click', () => {
    if (scroller) scroller.scrollBy({ left: +b.dataset.scroll * scroller.clientWidth * 0.8, behavior: 'smooth' });
  }));

  // Offer countdown
  const cd = $('[data-deadline]');
  if (cd) {
    const end = new Date(cd.dataset.deadline).getTime();
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      let s = Math.max(0, Math.floor((end - Date.now()) / 1000));
      const d = Math.floor(s / 86400); s -= d * 86400;
      const h = Math.floor(s / 3600); s -= h * 3600;
      const m = Math.floor(s / 60); s -= m * 60;
      $('[data-cd=d]', cd).textContent = pad(d);
      $('[data-cd=h]', cd).textContent = pad(h);
      $('[data-cd=m]', cd).textContent = pad(m);
      $('[data-cd=s]', cd).textContent = pad(s);
    };
    tick();
    setInterval(tick, 1000);
  }

  // Quantity steppers
  $$('[data-qty]').forEach(box => {
    const input = $('input', box);
    $$('[data-step]', box).forEach(b => b.addEventListener('click', () => {
      const min = +input.min || 0, max = +input.max || 50;
      input.value = Math.min(max, Math.max(min, (+input.value || 0) + +b.dataset.step));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
  });

  // Product page: price follows the chosen variant
  const priceEl = $('.pdp-price[data-base]');
  if (priceEl) {
    const update = () => {
      const v = $('input[name=variant_id]:checked');
      priceEl.textContent = peso(+priceEl.dataset.base + (v ? +v.dataset.delta : 0));
    };
    $$('input[name=variant_id]').forEach(r => r.addEventListener('change', update));
    update();
  }

  // Checkout: show the new-address form and the matching payment panel
  const newAddr = $('#new-address');
  if (newAddr) {
    const sync = () => {
      const sel = $('input[name=address_id]:checked');
      newAddr.hidden = !(sel && sel.value === 'new');
    };
    $$('input[name=address_id]').forEach(r => r.addEventListener('change', sync));
    sync();
  }
  const panels = $$('[data-pay]');
  if (panels.length) {
    const sync = () => {
      const m = ($('input[name=payment_method]:checked') || {}).value;
      panels.forEach(p => { p.hidden = p.dataset.pay !== m; });
    };
    $$('input[name=payment_method]').forEach(r => r.addEventListener('change', sync));
    sync();
  }

  // Confirm destructive actions
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!window.confirm(f.dataset.confirm)) e.preventDefault();
  }));

  // Image preview on the product form (data: URL, allowed by the CSP)
  $$('input[type=file][data-preview]').forEach(inp => inp.addEventListener('change', () => {
    const img = document.getElementById(inp.dataset.preview);
    const file = inp.files && inp.files[0];
    if (!img || !file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
    const reader = new FileReader();
    reader.onload = () => { img.src = reader.result; };
    reader.readAsDataURL(file);
  }));
})();
