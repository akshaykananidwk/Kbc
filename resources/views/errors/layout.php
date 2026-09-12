<?php /** @var App\Core\View $view */ ?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= (int) ($status ?? 500) ?> &middot; <?= e($siteName ?? 'Ganpati Bapa Quiz Show') ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card" style="max-width:520px">
    <div class="auth-card__head">
      <div class="auth-card__mark" style="font-size:1.5rem"><?= e($glyph ?? '!') ?></div>
      <h1 style="font-size:2.6rem;margin:0;line-height:1"><?= (int) ($status ?? 500) ?></h1>
      <p class="muted mb-0" style="font-weight:700"><?= e($heading ?? 'Something went wrong') ?></p>
    </div>
    <div class="auth-card__body text-center">
      <p><?= e($message ?? '') ?></p>
      <?php if (!empty($exception) && $exception instanceof Throwable): ?>
        <div class="log-view text-left" style="text-align:left;max-height:260px"><?= e($exception->getMessage()) ?>

<?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?>
        </div>
      <?php endif; ?>
      <div class="btn-row mt-2" style="justify-content:center">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Go to the home page</a>
        <a class="btn btn--ghost" href="<?= e(url('/admin')) ?>">Admin panel</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
