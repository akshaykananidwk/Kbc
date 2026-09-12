<?php
/**
 * Printable winner certificate.
 *
 * Deliberately a standalone page with its own print stylesheet: it must
 * come out of a home printer on A4 landscape with nothing else on it.
 */
$name   = (string) $certificate['participant_name'];
$prize  = (float) $certificate['prize_amount'];
$serial = (string) $certificate['serial_no'];
$issued = (string) $certificate['issued_at'];
?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($serial) ?> — <?= e($name) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/certificate.css')) ?>">
<style>
  :root {
    --c-primary: <?= e($design['primary']) ?>;
    --c-secondary: <?= e($design['secondary']) ?>;
    --c-accent: <?= e($design['accent']) ?>;
  }
</style>
</head>
<body>

<div class="cert-toolbar no-print">
  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/games/' . (int) $game['id'])) ?>">← Back to the game</a>
  <span class="cert-toolbar__meta">
    <?= e($serial) ?> &middot; printed <?= (int) $certificate['print_count'] ?>&times;
  </span>
  <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="cert-page">
  <div class="cert">
    <div class="cert__border">
      <div class="cert__corner cert__corner--tl"></div>
      <div class="cert__corner cert__corner--tr"></div>
      <div class="cert__corner cert__corner--bl"></div>
      <div class="cert__corner cert__corner--br"></div>

      <header class="cert__head">
        <?php if ($design['ganpati'] !== '' || $design['logo'] !== ''): ?>
          <img class="cert__logo" src="<?= e($design['ganpati'] !== '' ? $design['ganpati'] : $design['logo']) ?>" alt="">
        <?php else: ?>
          <div class="cert__logo cert__logo--glyph">ॐ</div>
        <?php endif; ?>

        <?php if ($design['org'] !== ''): ?>
          <div class="cert__org"><?= e($design['org']) ?></div>
        <?php endif; ?>
        <h1 class="cert__title"><?= e($design['title']) ?></h1>
        <div class="cert__rule"><span></span>❧<span></span></div>
      </header>

      <section class="cert__body">
        <p class="cert__lead">આ પ્રમાણપત્ર એનાયત થાય છે</p>
        <p class="cert__name"><?= e($name) ?></p>

        <?php if (($game['participant_city'] ?? '') !== '' || ($game['registration_no'] ?? '') !== ''): ?>
          <p class="cert__sub">
            <?= e(trim(($game['registration_no'] ?? '') . ' ' . (($game['participant_city'] ?? '') !== '' ? '· ' . $game['participant_city'] : ''))) ?>
          </p>
        <?php endif; ?>

        <?php if ($design['message'] !== ''): ?>
          <p class="cert__message"><?= e($design['message']) ?></p>
        <?php endif; ?>

        <div class="cert__prize">
          <span class="cert__prize-label">જીતેલી રકમ</span>
          <span class="cert__prize-amount"><?= e(money($prize)) ?></span>
        </div>

        <div class="cert__facts">
          <div class="cert__fact">
            <span class="cert__fact-label">પ્રશ્નો</span>
            <span class="cert__fact-value"><?= (int) $game['questions_correct'] ?> / <?= (int) $game['questions_attempted'] ?></span>
          </div>
          <?php if ($gifts !== []): ?>
          <div class="cert__fact">
            <span class="cert__fact-label">ભેટ</span>
            <span class="cert__fact-value"><?= e(implode(', ', array_map(static fn ($g) => $g['name'], $gifts))) ?></span>
          </div>
          <?php endif; ?>
          <div class="cert__fact">
            <span class="cert__fact-label">તારીખ</span>
            <span class="cert__fact-value"><?= e(datetime_label($issued, 'd M Y')) ?></span>
          </div>
        </div>
      </section>

      <footer class="cert__foot">
        <div class="cert__sign">
          <?php if ($design['signature'] !== ''): ?>
            <img class="cert__sign-image" src="<?= e($design['signature']) ?>" alt="">
          <?php endif; ?>
          <div class="cert__sign-line"></div>
          <div class="cert__sign-name"><?= e($design['signatory'] !== '' ? $design['signatory'] : '&nbsp;') ?></div>
          <div class="cert__sign-role"><?= e($design['role']) ?></div>
        </div>

        <div class="cert__seal">
          <?php if ($design['seal'] !== ''): ?>
            <img src="<?= e($design['seal']) ?>" alt="">
          <?php else: ?>
            <div class="cert__seal-glyph">
              <span>★</span>
              <small>ગણપતિ બાપા<br>ક્વિઝ શો</small>
            </div>
          <?php endif; ?>
        </div>

        <div class="cert__serial">
          <span class="cert__serial-label">પ્રમાણપત્ર ક્રમાંક</span>
          <span class="cert__serial-value"><?= e($serial) ?></span>
        </div>
      </footer>
    </div>
  </div>
</div>

</body>
</html>
