<?php
/**
 * Audience / TV display screen (monitor 2).
 *
 * SECURITY: this template renders nothing that is not already in the
 * sanitised display payload. The correct answer is not present in
 * $stateJson until the operator reveals the result.
 *
 * @var App\Core\View $view
 */
$config = [
    'endpoint'      => url('/api/display/state'),
    'pollInterval'  => (int) $pollInterval,
    'showLadder'    => (bool) $showLadder,
    'showLifelines' => (bool) $showLifelines,
    'animations'    => (bool) $animations,
];
?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow, noarchive">
<title><?= e($siteName) ?></title>
<?php if (($favicon ?? '') !== ''): ?><link rel="icon" href="<?= e(upload_url($favicon)) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/display.css')) ?>">
<style>
:root {
  --d-primary: <?= e($theme['primary']) ?>;
  --d-secondary: <?= e($theme['secondary']) ?>;
  --d-accent: <?= e($theme['accent']) ?>;
  --d-scale: <?= number_format($fontScale / 100, 2, '.', '') ?>;
}
</style>
</head>
<body>
<div class="d-stage" id="dRoot" data-config='<?= e(json_encode($config, JSON_UNESCAPED_SLASHES)) ?>'>
<script type="application/json" id="dState"><?= $stateJson ?></script>

<div class="d-controls">
  <button type="button" id="dFullscreen">Full screen</button>
  <button type="button" id="dReload">Reload</button>
</div>
<div class="d-offline" id="dOffline" hidden>Connection lost — retrying…</div>
<div class="d-audio-hint" id="dAudioHint">
  <span>🔊</span> Click anywhere once to enable sound
</div>

<div class="d-garland"></div>

<header class="d-head">
  <div class="d-logo">
    <?php if (($siteLogo ?? '') !== ''): ?><img src="<?= e(upload_url($siteLogo)) ?>" alt="">
    <?php elseif ($ganpatiImage !== ''): ?><img src="<?= e($ganpatiImage) ?>" alt="">
    <?php else: ?>ॐ<?php endif; ?>
  </div>
  <div>
    <div class="d-title"><?= e($siteName) ?></div>
    <?php if ($siteTagline !== ''): ?><div class="d-subtitle"><?= e($siteTagline) ?></div><?php endif; ?>
  </div>
  <div class="d-head__right" id="dHeadRight">
    <div class="d-qno" id="dQuestionNo"></div>
    <div class="d-amount" id="dAmount"></div>
    <div class="d-amount__gift" id="dAmountGift"></div>
  </div>
</header>

<!-- Idle / welcome screen -->
<div class="d-welcome" id="dWelcome">
  <div class="d-welcome__mark">
    <?php if ($ganpatiImage !== ''): ?><img src="<?= e($ganpatiImage) ?>" alt="">
    <?php elseif (($siteLogo ?? '') !== ''): ?><img src="<?= e(upload_url($siteLogo)) ?>" alt="">
    <?php else: ?>ॐ<?php endif; ?>
  </div>
  <div class="d-welcome__title"><?= e($welcomeHeading) ?></div>
  <div class="d-welcome__sub"><?= e($welcomeSub !== '' ? $welcomeSub : $siteName) ?></div>

  <!-- Idle panels: hall of fame and sponsors, rotated by the display script -->
  <div class="d-idle" id="dIdlePanels" hidden>
    <section class="d-idle__panel" id="dLeaderboardPanel" hidden>
      <h2 class="d-idle__title">🏆 વિજેતાઓ &middot; Hall of Fame</h2>
      <ol class="d-board" id="dLeaderboard"></ol>
      <div class="d-idle__summary" id="dBoardSummary"></div>
    </section>

    <section class="d-idle__panel" id="dSponsorPanel" hidden>
      <h2 class="d-idle__title">આભાર &middot; Our Sponsors</h2>
      <div class="d-sponsors" id="dSponsors"></div>
    </section>
  </div>
</div>

<!-- Live game -->
<main class="d-main <?= $showLadder ? '' : 'is-noladder' ?>" id="dMain" hidden>
  <div class="d-centre">
    <div class="d-participant" id="dParticipant">
      <div class="d-participant__photo" id="dParticipantPhoto"></div>
      <div>
        <div class="d-participant__name" id="dParticipantName"></div>
        <div class="d-participant__meta" id="dParticipantMeta"></div>
      </div>
    </div>

    <div class="d-question">
      <div class="d-question__media" id="dQuestionMedia" hidden></div>
      <div id="dQuestion"></div>
    </div>

    <div class="d-options" id="dOptions"></div>

    <div class="d-timer">
      <?php if ($timerStyle === 'ring'): ?>
        <div class="d-ring" id="dRing">
          <svg viewBox="0 0 100 100" aria-hidden="true">
            <circle class="d-ring__track" cx="50" cy="50" r="45"></circle>
            <circle class="d-ring__value" id="dRingValue" cx="50" cy="50" r="45"></circle>
          </svg>
          <div class="d-ring__label" id="dTimerLabel">0</div>
        </div>
      <?php elseif ($timerStyle === 'digits'): ?>
        <div class="d-ring" id="dRing" style="width:auto;height:auto">
          <div class="d-ring__label" id="dTimerLabel" style="position:static">0</div>
        </div>
      <?php else: ?>
        <div class="d-timerbar">
          <div class="d-timerbar__track"><div class="d-timerbar__fill" id="dTimerFill"></div></div>
          <div class="d-timerbar__label" id="dTimerBarLabel">0s</div>
        </div>
        <div class="d-ring" id="dRing" hidden><div class="d-ring__label" id="dTimerLabel"></div></div>
      <?php endif; ?>
    </div>

    <?php if ($showLifelines): ?>
      <div class="d-lifelines" id="dLifelines"></div>
    <?php endif; ?>
  </div>

  <?php if ($showLadder): ?>
    <aside class="d-ladder" id="dLadder"></aside>
  <?php endif; ?>
</main>

<footer class="d-foot"><?= e($footerText) ?></footer>

<!-- Result overlay -->
<div class="d-overlay" id="dResultOverlay" hidden>
  <div class="d-overlay__icon" id="dResultIcon"></div>
  <div class="d-overlay__title" id="dResultTitle"></div>
  <div class="d-overlay__sub" id="dResultSub"></div>
  <div class="d-overlay__sub" id="dResultDetail"></div>
  <div class="d-overlay__sub" id="dResultAmountLabel"></div>
  <div class="d-overlay__amount" id="dResultAmount"></div>
  <div class="d-overlay__gift" id="dResultGift"></div>
</div>

<!-- Final overlay -->
<div class="d-overlay" id="dFinalOverlay" hidden>
  <div class="d-overlay__icon" id="dFinalIcon"></div>
  <div class="d-overlay__title" id="dFinalTitle"></div>
  <div class="d-overlay__sub" id="dFinalName"></div>
  <div class="d-overlay__sub">Final winnings</div>
  <div class="d-overlay__amount" id="dFinalAmount"></div>
  <div class="d-overlay__gift" id="dFinalGifts"></div>
</div>

<!-- Big cheque overlay -->
<div class="d-overlay" id="dChequeOverlay" hidden>
  <div class="d-cheque">
    <div class="d-cheque__head">
      <span class="d-cheque__org" id="dChequeOrg"></span>
      <span class="d-cheque__date" id="dChequeDate"></span>
    </div>
    <div class="d-cheque__row">
      <span class="d-cheque__label">Pay to</span>
      <span class="d-cheque__payee" id="dChequePayee"></span>
    </div>
    <div class="d-cheque__row">
      <span class="d-cheque__label">Amount</span>
      <span class="d-cheque__amount" id="dChequeAmount"></span>
    </div>
    <div class="d-cheque__foot">
      <span class="d-cheque__words" id="dChequeWords"></span>
      <span class="d-cheque__sign">ગણપતિ બાપા ક્વિઝ શો</span>
    </div>
  </div>
</div>

<!-- Audience poll overlay -->
<div class="d-overlay" id="dPollOverlay" hidden>
  <div class="d-overlay__title" style="font-size:5vmin">Audience Poll</div>
  <div class="d-poll" id="dPollBars"></div>
</div>

<!-- Expert overlay -->
<div class="d-overlay" id="dExpertOverlay" hidden>
  <div class="d-overlay__title" style="font-size:5vmin">Expert Advice</div>
  <div class="d-expert">
    <div class="d-expert__photo" id="dExpertPhoto">👤</div>
    <div style="text-align:left">
      <div class="d-overlay__sub" id="dExpertName"></div>
      <div class="d-expert__answer" id="dExpertAnswer"></div>
      <div class="d-overlay__sub" id="dExpertConfidence"></div>
      <div class="d-overlay__sub" id="dExpertMessage" style="font-size:2.2vmin"></div>
    </div>
  </div>
</div>

<div class="d-confetti" id="dConfetti"></div>
</div>

<script src="<?= e(asset('assets/js/audio.js')) ?>"></script>
<script src="<?= e(asset('assets/js/display.js')) ?>"></script>
<?php if ($fullscreenAuto): ?>
<script>
// The browser only allows full screen after a user gesture.
document.addEventListener('click', function once() {
  var button = document.getElementById('dFullscreen');
  if (button && !document.fullscreenElement) button.click();
  document.removeEventListener('click', once);
});
</script>
<?php endif; ?>
</body>
</html>
