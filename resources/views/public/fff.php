<?php
/**
 * Fastest Finger First — the contender's own phone.
 *
 * SECURITY: renders the question and the four items only. The correct
 * order is never sent here; the server times and judges the answer.
 */
$isRunning = $round !== null && ($round['status'] ?? '') === 'running';
$isPending = $round !== null && ($round['status'] ?? '') === 'pending';
$isClosed  = $round !== null && ($round['status'] ?? '') === 'closed';

// The question text carries the four items on their own lines.
$lines = $round === null ? [] : preg_split('/\R/', (string) $round['question']);
$prompt = $lines[0] ?? '';
$items = [];
foreach (array_slice($lines, 1) as $line) {
    if (preg_match('/^\s*([ABCD])[\.\)]\s*(.+)$/u', trim($line), $m)) {
        $items[strtoupper($m[1])] = trim($m[2]);
    }
}
if ($items === []) {
    $items = ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'];
}
?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>ફાસ્ટેસ્ટ ફિંગર &middot; <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/public.css')) ?>">
</head>
<body>
<div class="p-shell">
  <header class="p-head">
    <div class="p-head__mark">⚡</div>
    <h1 class="p-head__title">ફાસ્ટેસ્ટ ફિંગર ફર્સ્ટ</h1>
    <p class="p-head__sub"><?= e($siteName) ?></p>
  </header>

  <?php if ($round === null): ?>
    <div class="p-card">
      <h2 class="p-card__title">રાઉન્ડ મળ્યો નહીં</h2>
      <p class="p-card__sub">સંચાલક પાસેથી સાચી લિંક લો.</p>
    </div>

  <?php elseif ($isClosed): ?>
    <div class="p-card">
      <h2 class="p-card__title">રાઉન્ડ પૂરો થયો</h2>
      <p class="p-card__sub">
        <?= $round['winner'] !== null
            ? 'વિજેતા: ' . e($round['winner']['name']) . ' — ' . e($round['winner']['time_label'])
            : 'કોઈએ સાચો ક્રમ આપ્યો નહીં.' ?>
      </p>
    </div>

  <?php else: ?>
    <!-- Step 1: identify yourself with the code the operator gave you -->
    <div class="p-card" id="codeCard">
      <h2 class="p-card__title">તમારો કોડ નાખો</h2>
      <p class="p-card__sub">સંચાલકે તમને આપેલો ૪ અક્ષરનો કોડ.</p>
      <div class="p-field">
        <input class="p-input" type="text" id="accessCode" maxlength="4" autocapitalize="characters"
               autocomplete="off" style="text-align:center;font-size:2rem;letter-spacing:.4em;font-weight:900">
        <div class="p-error" id="codeError" hidden></div>
      </div>
      <button type="button" class="p-btn" id="codeSubmit">આગળ વધો</button>
    </div>

    <!-- Step 2: put the four items in order -->
    <div class="p-card" id="orderCard" hidden data-round="<?= (int) $roundId ?>">
      <h2 class="p-card__title" id="greeting"></h2>
      <p class="p-card__sub"><?= e($prompt) ?></p>

      <?php if ($isPending): ?>
        <div class="p-status p-status--wait" id="waitBox">સંચાલક શરૂ કરે તેની રાહ જુઓ…</div>
      <?php endif; ?>

      <p class="p-card__sub" style="margin-top:.8rem">સાચા ક્રમમાં ટૅપ કરો:</p>
      <div class="p-options" id="fffItems">
        <?php foreach ($items as $key => $text): ?>
          <button type="button" class="p-option" data-key="<?= e($key) ?>">
            <span class="p-option__key"><?= e($key) ?></span>
            <span><?= e($text) ?></span>
            <span class="p-option__pos" data-pos></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="p-status p-status--wait" id="orderStatus">કોઈ પસંદગી નથી</div>

      <button type="button" class="p-btn p-btn--gold" id="fffSubmit" disabled style="margin-top:.8rem">
        જવાબ મોકલો
      </button>
      <button type="button" class="p-btn" id="fffReset" style="margin-top:.5rem;background:#f4ece3;color:#1c1512">
        ફરી ગોઠવો
      </button>
    </div>
  <?php endif; ?>

  <p class="p-foot">સૌથી ઝડપી સાચો જવાબ જીતે છે</p>
</div>

<script>
(function () {
  'use strict';
  var orderCard = document.getElementById('orderCard');
  if (!orderCard) return;

  var base = <?= json_encode(rtrim(url('/'), '/')) ?>;
  var roundId = parseInt(orderCard.getAttribute('data-round'), 10);
  var code = '';
  var picked = [];
  var sent = false;

  var codeInput = document.getElementById('accessCode');
  var codeError = document.getElementById('codeError');
  var statusBox = document.getElementById('orderStatus');
  var submitBtn = document.getElementById('fffSubmit');

  function setStatus(message, kind) {
    statusBox.textContent = message;
    statusBox.className = 'p-status p-status--' + kind;
  }

  document.getElementById('codeSubmit').addEventListener('click', function () {
    var value = (codeInput.value || '').trim().toUpperCase();
    codeError.hidden = true;

    if (value.length !== 4) {
      codeError.textContent = 'કોડ ૪ અક્ષરનો હોય છે.';
      codeError.hidden = false;
      return;
    }

    var body = new FormData();
    body.append('round_id', roundId);
    body.append('access_code', value);

    fetch(base + '/fff/check', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (r) { return r.json(); }).then(function (result) {
      if (!result.success) {
        codeError.textContent = result.message || 'કોડ ખોટો છે.';
        codeError.hidden = false;
        return;
      }
      code = value;
      document.getElementById('codeCard').hidden = true;
      orderCard.hidden = false;
      document.getElementById('greeting').textContent = 'નમસ્તે, ' + result.data.name;
      if (result.data.answered) {
        setStatus('તમે જવાબ આપી દીધો છે ✓', 'ok');
        submitBtn.disabled = true;
        document.querySelectorAll('#fffItems .p-option').forEach(function (b) { b.disabled = true; });
      }
    }).catch(function () {
      codeError.textContent = 'નેટવર્ક મળ્યું નહીં.';
      codeError.hidden = false;
    });
  });

  function repaint() {
    document.querySelectorAll('#fffItems .p-option').forEach(function (button) {
      var key = button.getAttribute('data-key');
      var index = picked.indexOf(key);
      var badge = button.querySelector('[data-pos]');
      button.classList.toggle('is-chosen', index !== -1);
      if (badge) { badge.textContent = index === -1 ? '' : String(index + 1); }
    });

    submitBtn.disabled = picked.length !== 4 || sent;
    if (picked.length === 0) { setStatus('કોઈ પસંદગી નથી', 'wait'); }
    else if (picked.length < 4) { setStatus(picked.join(' → ') + '  (' + picked.length + '/4)', 'wait'); }
    else { setStatus('ક્રમ: ' + picked.join(' → ') + ' — મોકલવા તૈયાર', 'ok'); }
  }

  document.querySelectorAll('#fffItems .p-option').forEach(function (button) {
    button.addEventListener('click', function () {
      if (sent) return;
      var key = button.getAttribute('data-key');
      var index = picked.indexOf(key);
      if (index === -1) { if (picked.length < 4) picked.push(key); }
      else { picked.splice(index, 1); }
      repaint();
    });
  });

  document.getElementById('fffReset').addEventListener('click', function () {
    if (sent) return;
    picked = [];
    repaint();
  });

  submitBtn.addEventListener('click', function () {
    if (picked.length !== 4 || sent) return;
    submitBtn.disabled = true;
    setStatus('મોકલાઈ રહ્યું છે…', 'wait');

    var body = new FormData();
    body.append('round_id', roundId);
    body.append('access_code', code);
    body.append('order_string', picked.join(''));

    fetch(base + '/fff/submit', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (r) { return r.json(); }).then(function (result) {
      if (!result.success) {
        submitBtn.disabled = false;
        setStatus(result.message || 'જવાબ ગયો નહીં.', 'bad');
        return;
      }
      sent = true;
      setStatus('જવાબ નોંધાયો ✓  સ્ક્રીન જુઓ!', 'ok');
      document.querySelectorAll('#fffItems .p-option').forEach(function (b) { b.disabled = true; });
    }).catch(function () {
      submitBtn.disabled = false;
      setStatus('નેટવર્ક મળ્યું નહીં.', 'bad');
    });
  });

  repaint();
})();
</script>
</body>
</html>
