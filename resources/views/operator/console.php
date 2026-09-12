<?php
/** @var App\Core\View $view */
$config = [
    'base'         => url('/'),
    'token'        => $csrfToken,
    'pollInterval' => (int) $pollInterval,
    'allowQuit'    => (bool) $allowQuit,
    'requireLock'  => (bool) $requireLock,
    'audio'        => $audio,
];
?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
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
  <span class="op-bar__title">Operator control</span>
  <span class="op-bar__chip" id="opStatusChip">—</span>
  <span class="op-bar__chip" id="opGameCode">—</span>
  <span class="op-bar__chip" id="opState">—</span>
  <span class="op-bar__spacer"></span>
  <a class="op-link" href="<?= e($displayUrl) ?>" target="_blank" rel="noopener">Open display screen ↗</a>
  <a class="op-link" href="<?= e(url('/operator/setup')) ?>">New game</a>
  <a class="op-link" href="<?= e(url('/admin')) ?>">Admin</a>
</header>

<div class="op-body">
  <div>
    <div class="op-panel">
      <div class="op-panel__head">
        <span>Question</span>
        <button type="button" class="btn btn--ghost btn--sm" id="btnToggleAnswer">Hide answer</button>
      </div>
      <div class="op-panel__body">
        <div class="op-meta">
          <div class="op-meta__item"><div class="op-meta__label">Question</div><div class="op-meta__value" id="opLevel">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label">This prize</div><div class="op-meta__value" id="opPrize">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label">Next prize</div><div class="op-meta__value" id="opNextPrize">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label">Won so far</div><div class="op-meta__value" id="opWon">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label">Guaranteed</div><div class="op-meta__value" id="opGuaranteed">—</div></div>
          <div class="op-meta__item"><div class="op-meta__label">Gift</div><div class="op-meta__value" id="opGift" style="font-size:.88rem">—</div></div>
        </div>

        <div class="op-question" id="opQuestion">Loading…</div>
        <div class="op-options" id="opOptions"></div>

        <div class="op-answer-key" id="opAnswerKey">
          <span class="op-answer-key__label">Correct answer<br>(operator only)</span>
          <span class="op-answer-key__value" id="opAnswerKeyValue">—</span>
          <span class="op-answer-key__text" id="opAnswerKeyText"></span>
        </div>
      </div>
    </div>

    <div class="op-panel mt-2">
      <div class="op-panel__head"><span>Timer</span><span id="opTimerState">—</span></div>
      <div class="op-panel__body">
        <div class="op-timer">
          <div class="op-timer__value" id="opTimerValue">0</div>
          <div class="op-timer__bar"><div class="op-timer__fill" id="opTimerFill"></div></div>
        </div>
        <div class="op-controls">
          <button type="button" class="op-btn op-btn--go" id="btnStartTimer">Start timer<span class="op-btn__hint">space</span></button>
          <button type="button" class="op-btn op-btn--warn" id="btnPauseTimer">Pause<span class="op-btn__hint">P</span></button>
          <button type="button" class="op-btn" id="btnResumeTimer">Resume</button>
          <button type="button" class="op-btn" id="btnResetTimer">Reset timer</button>
        </div>
      </div>
    </div>

    <div class="op-panel mt-2">
      <div class="op-panel__head"><span>Game controls</span></div>
      <div class="op-panel__body">
        <div class="op-controls">
          <button type="button" class="op-btn op-btn--go op-btn--wide" id="btnStartGame">Start the game</button>
          <button type="button" class="op-btn op-btn--blue" id="btnLock">Lock answer<span class="op-btn__hint">L</span></button>
          <button type="button" class="op-btn op-btn--warn" id="btnUnlock">Override lock</button>
          <button type="button" class="op-btn op-btn--gold" id="btnReveal">Reveal result<span class="op-btn__hint">R</span></button>
          <button type="button" class="op-btn op-btn--go" id="btnNext">Next question<span class="op-btn__hint">N</span></button>
          <button type="button" class="op-btn" id="btnPrevious">Previous question</button>
          <button type="button" class="op-btn" id="btnRestart">Restart question</button>
          <button type="button" class="op-btn op-btn--warn" id="btnQuit">Participant quits</button>
          <button type="button" class="op-btn op-btn--danger" id="btnEnd">End game</button>
          <button type="button" class="op-btn op-btn--danger" id="btnReset">Reset game</button>
          <a class="op-btn op-btn--gold op-btn--wide" id="btnSummary"
             href="<?= e(url('/operator/summary/' . (int) ($state['game_id'] ?? 0))) ?>"
             style="display:none;text-decoration:none">View game report →</a>
        </div>
        <div class="op-status mt-2" id="opStatus">Ready.</div>
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
      <div class="op-panel__head"><span>Lifelines</span></div>
      <div class="op-panel__body"><div class="op-lifelines" id="opLifelines"></div></div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head">
        <span>Audience voting</span>
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
          <button type="button" class="op-btn op-btn--blue" id="btnOpenPoll">Open voting</button>
          <button type="button" class="op-btn op-btn--warn" id="btnClosePoll">Close voting</button>
        </div>
        <p class="small" style="color:#a8907f;margin:.6rem 0 0">
          Open voting, let the audience scan the QR on the TV, then use the
          Audience Poll lifeline — it will use the real votes.
        </p>
      </div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head"><span>Prize ladder</span></div>
      <div class="op-panel__body"><div class="op-ladder" id="opLadder"></div></div>
    </div>

    <div class="op-panel">
      <div class="op-panel__head"><span>Keyboard shortcuts</span></div>
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
