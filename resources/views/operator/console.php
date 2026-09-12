<?php
/** @var App\Core\View $view */
$config = [
    'base'         => url('/'),
    'token'        => $csrfToken,
    'pollInterval' => (int) $pollInterval,
    'allowQuit'    => (bool) $allowQuit,
    'requireLock'  => (bool) $requireLock,
    'audio'        => $audio,
    'labels'       => [
        'hideAnswer' => __('Hide answer'),
        'showAnswer' => __('Show answer'),
        'ready'      => __('Ready.'),
    ],
];
?>
<!DOCTYPE html>
<html lang="<?= e(App\Core\Lang::locale()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Operator &middot; <?= e($siteName) ?></title>
<?php if (($favicon ?? '') !== ''): ?><link rel="icon" href="<?= e(upload_url($favicon)) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/operator.css')) ?>">
<style>:root{--brand-primary:<?= e($theme['primary']) ?>;--brand-secondary:<?= e($theme['secondary']) ?>;--brand-accent:<?= e($theme['accent']) ?>;}</style>
</head>
<body class="op-shell">
<div id="opRoot" data-config='<?= e(json_encode($config, JSON_UNESCAPED_SLASHES)) ?>'>
<script type="application/json" id="opState"><?= $stateJson ?></script>

<header class="op-bar">
  <span class="op-bar__title"><?= _e('Operator control') ?></span>
  <span class="op-bar__chip" id="opStatusChip">—</span>
  <span class="op-bar__chip" id="opGameCode">—</span>
  <span class="op-bar__chip" id="opState">—</span>
  <span class="op-bar__chip op-bar__chip--wait" id="opRehearsal" hidden>REHEARSAL</span>
  <span class="op-bar__spacer"></span>
  <a class="op-link" href="<?= e($displayUrl) ?>" target="_blank" rel="noopener">Open display screen ↗</a>
  <a class="op-link" href="<?= e(url('/operator/setup')) ?>"><?= _e('New game') ?></a>
  <a class="op-link" href="<?= e(url('/admin')) ?>"><?= _e('Admin') ?></a>
</header>

<div class="op-body">
  <div>
    <div class="op-panel">
      <div class="op-panel__head">
        <span><?= _e('Question') ?></span>
        <button type="button" class="btn btn--ghost btn--sm" id="btnToggleAnswer"><?= _e('Hide answer') ?></button>
      </div>
      <div class="op-panel__body">
        <div class="op-meta">
          <div class="op-meta__item"><div class="op-meta__label"><?= _e('Question') ?></div><div class="op-meta__value" id="opLevel">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label"><?= _e('This prize') ?></div><div class="op-meta__value" id="opPrize">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label"><?= _e('Next prize') ?></div><div class="op-meta__value" id="opNextPrize">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label"><?= _e('Won so far') ?></div><div class="op-meta__value" id="opWon">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label"><?= _e('Guaranteed') ?></div><div class="op-meta__value" id="opGuaranteed">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label"><?= _e('Gift') ?></div><div class="op-meta__value" id="opGift" style="font-size:.88rem">—</div></div>
        </div>

        <div class="op-question" id="opQuestion">Loading…</div>
        <div class="op-options" id="opOptions"></div>

        <div class="op-answer-key" id="opAnswerKey">
          <span class="op-answer-key__label"><?= _e('Correct answer') ?><br>(operator only)</span>
          <span class="op-answer-key__value" id="opAnswerKeyValue">—</span>
          <span class="op-answer-key__text" id="opAnswerKeyText"></span>
        </div>
      </div>
    </div>

    <div class="op-panel mt-2">
      <div class="op-panel__head"><span><?= _e('Timer') ?></span><span id="opTimerState">—</span></div>
      <div class="op-panel__body">
        <div class="op-timer">
          <div class="op-timer__value" id="opTimerValue">0</div>
          <div class="op-timer__bar"><div class="op-timer__fill" id="opTimerFill"></div></div>
        </div>
        <div class="op-controls">
          <button type="button" class="op-btn op-btn--go" id="btnStartTimer"><?= _e('Start timer') ?><span class="op-btn__hint">space</span></button>
          <button type="button" class="op-btn op-btn--warn" id="btnPauseTimer"><?= _e('Pause') ?><span class="op-btn__hint">P</span></button>
          <button type="button" class="op-btn" id="btnResumeTimer"><?= _e('Resume') ?></button>
          <button type="button" class="op-btn" id="btnResetTimer"><?= _e('Reset timer') ?></button>
        </div>
      </div>
    </div>

    <div class="op-panel mt-2">
      <div class="op-panel__head"><span><?= _e('Game controls') ?></span></div>
      <div class="op-panel__body">
        <div class="op-controls">
          <button type="button" class="op-btn op-btn--go op-btn--wide" id="btnStartGame"><?= _e('Start the game') ?></button>
          <button type="button" class="op-btn op-btn--blue" id="btnLock"><?= _e('Lock answer') ?><span class="op-btn__hint">L</span></button>
          <button type="button" class="op-btn op-btn--warn" id="btnUnlock"><?= _e('Override lock') ?></button>
          <button type="button" class="op-btn op-btn--gold" id="btnReveal"><?= _e('Reveal result') ?><span class="op-btn__hint">R</span></button>
          <button type="button" class="op-btn op-btn--go" id="btnNext"><?= _e('Next question') ?><span class="op-btn__hint">N</span></button>
          <button type="button" class="op-btn" id="btnPrevious"><?= _e('Previous question') ?></button>
          <button type="button" class="op-btn" id="btnRestart"><?= _e('Restart question') ?></button>
          <button type="button" class="op-btn op-btn--warn" id="btnQuit"><?= _e('Participant quits') ?></button>
          <button type="button" class="op-btn op-btn--danger" id="btnEnd"><?= _e('End game') ?></button>
          <button type="button" class="op-btn op-btn--danger" id="btnReset"><?= _e('Reset game') ?></button>
          <a class="op-btn op-btn--gold op-btn--wide" id="btnSummary"
             href="<?= e(url('/operator/summary/' . (int) ($state['game_id'] ?? 0))) ?>"
             style="display:none;text-decoration:none">View game report →</a>
        </div>
        <div class="op-status mt-2" id="opStatus"><?= _e('Ready.') ?></div>
      </div>
    </div>
  </div>

  <div class="op-side">
    <div class="op-panel">
      <div class="op-panel__head"><span>Participant</span></div>
      <div class="op-panel__body">
        <div class="op-participant">
          <div class="op-participant__photo" id="opParticipantPhoto">?</div>
          <div>
            <div class="op-participant__name" id="opParticipantName">—</div>
            <div class="op-participant__meta" id="opParticipantMeta">—</div>
          </div>
        </div>
      </div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head"><span><?= _e('Lifelines') ?></span></div>
      <div class="op-panel__body"><div class="op-lifelines" id="opLifelines"></div></div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head">
        <span><?= _e('Audience voting') ?></span>
        <span id="opPollStatus">closed</span>
      </div>
      <div class="op-panel__body">
        <div class="op-poll" id="opPollBox">
          <div class="op-poll__code" id="opPollCode">—</div>
          <div class="op-poll__meta">
            <span id="opPollVotes">0</span> votes &middot; <span id="opPollSeconds">0</span>s left
          </div>
        </div>
        <div class="op-controls mt-2">
          <button type="button" class="op-btn op-btn--blue" id="btnOpenPoll"><?= _e('Open voting') ?></button>
          <button type="button" class="op-btn op-btn--warn" id="btnClosePoll"><?= _e('Close voting') ?></button>
        </div>
        <p class="small" style="color:#a8907f;margin:.6rem 0 0">
          Open voting, let the audience scan the QR on the TV, then use the
          Audience Poll lifeline — it will use the real votes.
        </p>
      </div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head"><span><?= _e('Prize Ladder') ?></span></div>
      <div class="op-panel__body"><div class="op-ladder" id="opLadder"></div></div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head"><span><?= _e('Keyboard shortcuts') ?></span></div>
      <div class="op-panel__body" style="font-size:.8rem;color:#a8907f;line-height:1.9">
        <strong style="color:#f4ece4">A B C D</strong> — select an option<br>
        <strong style="color:#f4ece4">Space</strong> — start the timer<br>
        <strong style="color:#f4ece4">P</strong> — pause &middot; <strong style="color:#f4ece4">L</strong> — lock<br>
        <strong style="color:#f4ece4">R</strong> — reveal &middot; <strong style="color:#f4ece4">N</strong> — next
      </div>
    </div>
  </div>
</div>
</div>

<script src="<?= e(asset('assets/js/audio.js')) ?>"></script>
<script src="<?= e(asset('assets/js/operator.js')) ?>"></script>
</body>
</html>
