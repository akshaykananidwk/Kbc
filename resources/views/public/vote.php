<?php
/**
 * Audience voting page — reached by scanning the QR on the display.
 *
 * SECURITY: shows the question wording and the options only. The correct
 * answer is never fetched or rendered here.
 */
$isOpen = $poll !== null && ($poll['status'] ?? '') === 'open';
?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>મત આપો &middot; <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/public.css')) ?>">
</head>
<body>
<div class="p-shell">
  <header class="p-head">
    <div class="p-head__mark">ॐ</div>
    <h1 class="p-head__title"><?= e($siteName) ?></h1>
    <p class="p-head__sub">પ્રેક્ષક મતદાન &middot; Audience Poll</p>
  </header>

  <?php if ($poll === null): ?>
    <div class="p-card">
      <h2 class="p-card__title">મતદાન મળ્યું નહીં</h2>
      <p class="p-card__sub">આ કોડ ખોટો છે અથવા મતદાન બંધ થઈ ગયું છે. સ્ક્રીન પરનો નવો QR સ્કેન કરો.</p>
    </div>

  <?php elseif (!$isOpen): ?>
    <div class="p-card">
      <h2 class="p-card__title">મતદાન બંધ થઈ ગયું</h2>
      <p class="p-card__sub">આ પ્રશ્ન માટે સમય પૂરો થયો. આભાર!</p>
      <div class="p-status p-status--wait"><?= (int) $poll['total_votes'] ?> મત નોંધાયા</div>
    </div>

  <?php else: ?>
    <div class="p-card" id="voteCard" data-code="<?= e($poll['code']) ?>">
      <h2 class="p-card__title">તમને શું લાગે છે?</h2>
      <p class="p-card__sub">
        <?= $question !== null ? e($question['text']) : 'સ્ક્રીન પરનો પ્રશ્ન જુઓ.' ?>
      </p>

      <div class="p-countdown"><span id="voteSeconds"><?= (int) $poll['closes_in'] ?></span><small>સેકન્ડ બાકી</small></div>
      <div class="p-bar"><div class="p-bar__fill" id="voteBar" style="width:100%"></div></div>

      <div class="p-options" style="margin-top:1.1rem">
        <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
          <?php $text = $question['options'][$key] ?? ''; ?>
          <button type="button" class="p-option" data-option="<?= $key ?>">
            <span class="p-option__key"><?= $key ?></span>
            <span><?= $text !== '' ? e($text) : 'વિકલ્પ ' . $key ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="p-status p-status--wait" id="voteStatus">તમારો જવાબ પસંદ કરો</div>
    </div>
  <?php endif; ?>

  <p class="p-foot">એક ફોનથી એક જ મત &middot; સમય પૂરો થાય ત્યાં સુધી બદલી શકો છો</p>
</div>

<script>
(function () {
  'use strict';
  var card = document.getElementById('voteCard');
  if (!card) return;

  var code = card.getAttribute('data-code');
  var base = <?= json_encode(rtrim(url('/'), '/')) ?>;
  var statusBox = document.getElementById('voteStatus');
  var secondsBox = document.getElementById('voteSeconds');
  var bar = document.getElementById('voteBar');
  var buttons = card.querySelectorAll('.p-option');

  var total = <?= (int) ($poll['closes_in'] ?? 0) ?>;
  var remaining = total;
  var chosen = null;

  /* A random id kept on this device so one phone counts as one voter. */
  function voterToken() {
    var key = 'gq_voter_token';
    var token = '';
    try { token = window.localStorage.getItem(key) || ''; } catch (e) { token = ''; }
    if (!/^[0-9a-f]{32}$/.test(token)) {
      var bytes = new Uint8Array(16);
      (window.crypto || window.msCrypto).getRandomValues(bytes);
      token = Array.prototype.map.call(bytes, function (b) {
        return ('0' + b.toString(16)).slice(-2);
      }).join('');
      try { window.localStorage.setItem(key, token); } catch (e) { /* private mode */ }
    }
    return token;
  }

  function setStatus(message, kind) {
    statusBox.textContent = message;
    statusBox.className = 'p-status p-status--' + kind;
  }

  function lockAll(disabled) {
    buttons.forEach(function (b) { b.disabled = disabled; });
  }

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      var option = button.getAttribute('data-option');
      lockAll(true);
      setStatus('મોકલાઈ રહ્યું છે…', 'wait');

      var body = new FormData();
      body.append('code', code);
      body.append('option', option);
      body.append('voter_token', voterToken());

      fetch(base + '/vote', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body
      }).then(function (r) { return r.json(); }).then(function (result) {
        lockAll(false);
        if (!result.success) { setStatus(result.message || 'મત નોંધાયો નહીં.', 'bad'); return; }

        chosen = option;
        buttons.forEach(function (b) {
          b.classList.toggle('is-chosen', b.getAttribute('data-option') === option);
        });
        setStatus(result.data.changed ? 'તમારો મત બદલાયો ✓' : 'તમારો મત નોંધાયો ✓', 'ok');
      }).catch(function () {
        lockAll(false);
        setStatus('નેટવર્ક મળ્યું નહીં. ફરી પ્રયાસ કરો.', 'bad');
      });
    });
  });

  window.setInterval(function () {
    remaining = Math.max(0, remaining - 1);
    secondsBox.textContent = remaining;
    bar.style.width = total > 0 ? ((remaining / total) * 100) + '%' : '0%';

    if (remaining === 0) {
      lockAll(true);
      setStatus(chosen ? 'મતદાન બંધ. આભાર! ✓' : 'મતદાન બંધ થઈ ગયું.', chosen ? 'ok' : 'wait');
    }
  }, 1000);
})();
</script>
</body>
</html>
