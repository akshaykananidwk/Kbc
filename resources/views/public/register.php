<?php /** Public self-registration, reached by scanning a QR at the venue. */ ?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>નોંધણી &middot; <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/public.css')) ?>">
</head>
<body>
<div class="p-shell">
  <header class="p-head">
    <div class="p-head__mark">
      <?php if ($ganpati !== ''): ?><img src="<?= e($ganpati) ?>" alt=""><?php else: ?>ॐ<?php endif; ?>
    </div>
    <h1 class="p-head__title"><?= e($siteName) ?></h1>
    <p class="p-head__sub"><?= e($tagline !== '' ? $tagline : 'ભાગ લેવા માટે નોંધણી કરો') ?></p>
  </header>

  <div class="p-card" id="regCard">
    <h2 class="p-card__title">ક્વિઝમાં ભાગ લો</h2>
    <p class="p-card__sub">નામ અને મોબાઇલ નંબર ભરો. તમને એક નોંધણી ક્રમાંક મળશે.</p>

    <form id="regForm" novalidate>
      <div class="p-field">
        <label class="p-label" for="name">પૂરું નામ *</label>
        <input class="p-input" type="text" id="name" name="name" required autocomplete="name">
        <div class="p-error" id="err_name" hidden></div>
      </div>

      <div class="p-field">
        <label class="p-label" for="mobile">મોબાઇલ નંબર *</label>
        <input class="p-input" type="tel" id="mobile" name="mobile" required inputmode="numeric" autocomplete="tel">
        <div class="p-error" id="err_mobile" hidden></div>
      </div>

      <div class="p-field">
        <label class="p-label" for="city">શહેર / ગામ</label>
        <input class="p-input" type="text" id="city" name="city" autocomplete="address-level2">
      </div>

      <div class="p-field">
        <label class="p-label" for="age">ઉંમર</label>
        <input class="p-input" type="number" id="age" name="age" min="5" max="120" inputmode="numeric" required>
        <small class="p-help">શો બે ભાગમાં રમાય છે — ૧૦ થી ૨૦ અને ૨૧ થી ૫૦. ઉંમર પરથી તમારો ભાગ નક્કી થશે.</small>
      </div>

      <!-- Honeypot: hidden from people, tempting to bots -->
      <div class="p-hp" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <button type="submit" class="p-btn" id="regSubmit">નોંધણી કરો</button>
      <div class="p-status p-status--bad" id="regError" hidden></div>
    </form>
  </div>

  <div class="p-card" id="regDone" hidden>
    <div class="p-done">
      <div class="p-done__icon">🎉</div>
      <h2 class="p-card__title" id="doneTitle">નોંધણી થઈ ગઈ!</h2>
      <p class="p-card__sub" id="doneName"></p>
      <p class="p-card__sub">તમારો નોંધણી ક્રમાંક</p>
      <div class="p-done__reg" id="doneReg"></div>
      <p class="p-card__sub" style="margin-top:1rem">
        આ ક્રમાંક યાદ રાખો. તમારો વારો આવે ત્યારે સંચાલક તમને બોલાવશે.
      </p>
    </div>
  </div>

  <p class="p-foot">ગણપતિ બાપા મોરિયા 🙏</p>
</div>

<script>
(function () {
  'use strict';
  var form = document.getElementById('regForm');
  var base = <?= json_encode(rtrim(url('/'), '/')) ?>;
  var submit = document.getElementById('regSubmit');
  var errorBox = document.getElementById('regError');

  function showFieldError(field, message) {
    var box = document.getElementById('err_' + field);
    var input = document.getElementById(field);
    if (box) { box.textContent = message; box.hidden = !message; }
    if (input) { input.classList.toggle('has-error', !!message); }
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    ['name', 'mobile'].forEach(function (f) { showFieldError(f, ''); });
    errorBox.hidden = true;

    var name = document.getElementById('name').value.trim();
    var mobile = document.getElementById('mobile').value.trim();
    var ok = true;

    if (name.length < 2) { showFieldError('name', 'કૃપા કરીને પૂરું નામ લખો.'); ok = false; }
    if (mobile.replace(/\D/g, '').length < 6) { showFieldError('mobile', 'સાચો મોબાઇલ નંબર લખો.'); ok = false; }
    if (!ok) return;

    submit.disabled = true;
    submit.textContent = 'મોકલાઈ રહ્યું છે…';

    fetch(base + '/register', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    }).then(function (r) { return r.json(); }).then(function (result) {
      submit.disabled = false;
      submit.textContent = 'નોંધણી કરો';

      if (!result.success) {
        errorBox.textContent = result.message || 'નોંધણી થઈ શકી નહીં.';
        errorBox.hidden = false;
        if (result.errors) {
          Object.keys(result.errors).forEach(function (f) { showFieldError(f, result.errors[f]); });
        }
        return;
      }

      document.getElementById('regCard').hidden = true;
      var done = document.getElementById('regDone');
      document.getElementById('doneTitle').textContent = result.data.duplicate ? 'તમે પહેલેથી નોંધાયેલા છો' : 'નોંધણી થઈ ગઈ!';
      document.getElementById('doneName').textContent = result.data.name || '';
      document.getElementById('doneReg').textContent = result.data.registration_no || '';
      done.hidden = false;
      window.scrollTo(0, 0);
    }).catch(function () {
      submit.disabled = false;
      submit.textContent = 'નોંધણી કરો';
      errorBox.textContent = 'નેટવર્ક મળ્યું નહીં. ફરી પ્રયાસ કરો.';
      errorBox.hidden = false;
    });
  });
})();
</script>
</body>
</html>
