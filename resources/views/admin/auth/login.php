<?php /** @var App\Core\View $view */ ?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in &middot; <?= e($siteName) ?></title>
<?php if (($favicon ?? '') !== ''): ?><link rel="icon" href="<?= e(upload_url($favicon)) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<style>:root{--brand-primary:<?= e($theme['primary']) ?>;--brand-secondary:<?= e($theme['secondary']) ?>;--brand-accent:<?= e($theme['accent']) ?>;}</style>
</head>
<body>
<div class="auth-shell">
  <div style="width:100%;max-width:425px">
    <div class="auth-card">
      <div class="auth-card__head">
        <div class="auth-card__mark">
          <?php if (($siteLogo ?? '') !== ''): ?>
            <img src="<?= e(upload_url($siteLogo)) ?>" alt="">
          <?php else: ?>ॐ<?php endif; ?>
        </div>
        <h1 style="font-size:1.2rem;margin-bottom:.15rem"><?= e($siteName) ?></h1>
        <p class="muted small mb-0"><?= e($siteTagline !== '' ? $siteTagline : 'Sign in to manage the show') ?></p>
      </div>
      <div class="auth-card__body">
        <?= $view->include('partials.flash', ['flash' => $flash ?? []]) ?>

        <form method="post" action="<?= e(url('/admin/login')) ?>" autocomplete="on">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

          <div class="field mb-2">
            <label class="label" for="identifier">Email or username</label>
            <input type="text" id="identifier" name="identifier" required autofocus
                   autocomplete="username" value="<?= e(old_value($old ?? [], 'identifier')) ?>">
          </div>

          <div class="field mb-2">
            <label class="label" for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
          </div>

          <button type="submit" class="btn btn--primary btn--block btn--lg" data-busy="Signing in…">Sign in</button>
        </form>
      </div>
    </div>
    <p class="auth-foot">
      <a href="<?= e(url('/display')) ?>">Display screen</a> &middot;
      <a href="<?= e(url('/')) ?>">Home</a>
    </p>
  </div>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
