<?php /** @var App\Core\View $view */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install &middot; Ganpati Bapa Quiz Show</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="install-shell">
<div class="brand-band">
  <div class="install-head">
    <h1>ગણપતિ બાપા ક્વિઝ શો</h1>
    <p>Ganpati Bapa Quiz Show &middot; Installation wizard &middot; v<?= e($appVersion ?? '1.0.0') ?></p>
  </div>
</div>

<div class="install-wrap install-body">
  <?php if (!empty($steps)): ?>
  <div class="steps">
    <?php foreach ($steps as $number => $step): ?>
      <?php
      $class = 'steps__item';
      if ($number === ($currentStep ?? 1)) { $class .= ' is-current'; }
      elseif ($number < ($currentStep ?? 1)) { $class .= ' is-done'; }
      ?>
      <div class="<?= $class ?>">
        <span class="steps__no"><?= $number < ($currentStep ?? 1) ? '✓' : $number ?></span>
        <?= e($step['label']) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?= $view->include('partials.flash', ['flash' => $flash ?? []]) ?>
  <?= $view->section('content') ?>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
