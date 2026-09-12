<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Start a new game']);
$view->start('content');
$ready = $questionCount >= max(1, $maxLevel) && $maxLevel > 0 && $participants !== [];
?>
<div class="page-head">
  <div><h1>Start a new game</h1><p class="page-head__sub">Pick the participant, then go to the control screen.</p></div>
  <a class="btn btn--ghost" href="<?= e(url('/operator')) ?>">Operator screen →</a>
</div>

<?php if ($activeGame !== null): ?>
  <div class="alert alert--warning">
    <span>!</span>
    <span>
      Game <strong><?= e($activeGame['game_code']) ?></strong> for
      <strong><?= e($activeGame['participant_name'] ?? 'a participant') ?></strong> is still
      <strong><?= e($activeGame['status']) ?></strong>.
      Finish or end it on the <a href="<?= e(url('/operator')) ?>">operator screen</a> before starting another.
    </span>
  </div>
<?php endif; ?>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">1. Choose the participant</h2></div>
    <div class="card__body">
      <?php if ($participants === []): ?>
        <div class="alert alert--warning"><span>!</span><span>No participants are registered yet.</span></div>
        <a class="btn btn--primary" href="<?= e(url('/admin/participants/create')) ?>">Add a participant</a>
      <?php else: ?>
        <div class="field mb-2">
          <label class="label" for="participantSelect">Participant</label>
          <select id="participantSelect">
            <?php foreach ($participants as $participant): ?>
              <option value="<?= (int) $participant['id'] ?>">
                <?= e($participant['name']) ?><?= ($participant['registration_no'] ?? '') !== '' ? ' (' . e($participant['registration_no']) . ')' : '' ?><?= ($participant['city'] ?? '') !== '' ? ' — ' . e($participant['city']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field mb-2">
          <label class="label" for="orderSelect">Question order for this game</label>
          <select id="orderSelect">
            <?php foreach ($orderModes as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $currentOrder === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="btn btn--primary btn--lg btn--block" id="createGameBtn"
                <?= ($ready && $activeGame === null) ? '' : 'disabled' ?>>Create the game</button>
        <div id="setupResult" class="mt-2"></div>
        <p class="field__help mt-1">Creating a game does not start it — you press “Start the game” on the control screen when the cameras are ready.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">2. Pre-show checklist</h2></div>
    <div class="card__body">
      <ul class="check-list">
        <li class="<?= $maxLevel > 0 ? '' : 'is-bad' ?>">
          <span class="check-list__state"><?= $maxLevel > 0 ? '✓' : '✕' ?></span>
          <span class="check-list__label">Prize ladder</span>
          <span class="check-list__value"><?= (int) $maxLevel ?> level(s)</span>
        </li>
        <li class="<?= $questionCount >= max(1, $maxLevel) ? '' : 'is-bad' ?>">
          <span class="check-list__state"><?= $questionCount >= max(1, $maxLevel) ? '✓' : '✕' ?></span>
          <span class="check-list__label">Active questions</span>
          <span class="check-list__value"><?= (int) $questionCount ?> available</span>
        </li>
        <li class="<?= $participants !== [] ? '' : 'is-bad' ?>">
          <span class="check-list__state"><?= $participants !== [] ? '✓' : '✕' ?></span>
          <span class="check-list__label">Participants</span>
          <span class="check-list__value"><?= count($participants) ?> registered</span>
        </li>
        <li>
          <span class="check-list__state">▣</span>
          <span class="check-list__label">Display screen open on monitor 2</span>
          <span class="check-list__value"><a href="<?= e(url('/display')) ?>" target="_blank" rel="noopener">open</a></span>
        </li>
      </ul>

      <h3 class="mt-3">Prize ladder for this show</h3>
      <ul class="ladder-mini">
        <?php foreach (array_reverse($ladder) as $level): ?>
          <li class="<?= (int) $level['is_guaranteed'] === 1 ? 'is-guaranteed' : '' ?>">
            <span>Q<?= (int) $level['level_no'] ?><?= (int) $level['is_guaranteed'] === 1 ? ' · guaranteed' : '' ?></span>
            <span><strong><?= e(money($level['amount'])) ?></strong>
              <?php if (($level['gift_name'] ?? '') !== ''): ?><span class="small muted">+ <?= e($level['gift_name']) ?></span><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php $view->stop(); ?>

<?php $view->start('scripts'); ?>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<script>
(function () {
  var button = document.getElementById('createGameBtn');
  if (!button) return;
  var base = <?= json_encode(rtrim(url('/'), '/')) ?>;
  button.addEventListener('click', function () {
    var participantId = document.getElementById('participantSelect').value;
    var order = document.getElementById('orderSelect').value;
    var box = document.getElementById('setupResult');
    button.disabled = true;
    button.textContent = 'Creating…';

    window.QuizApi.post(base + '/api/game/create', { participant_id: participantId, question_order: order })
      .then(function (response) {
        button.disabled = false;
        button.textContent = 'Create the game';
        if (!response.success) {
          box.innerHTML = '<div class="alert alert--error"><span>✕</span><span>' +
            (response.message || 'The game could not be created.').replace(/[<>&]/g, '') + '</span></div>';
          return;
        }
        box.innerHTML = '<div class="alert alert--success"><span>✓</span><span>Game ' +
          String(response.data.game_code).replace(/[<>&]/g, '') + ' created. Opening the control screen…</span></div>';
        window.setTimeout(function () { window.location.href = base + '/operator'; }, 900);
      });
  });
})();
</script>
<?php $view->stop(); ?>
