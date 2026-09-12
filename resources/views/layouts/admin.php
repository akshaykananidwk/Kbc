<?php
/**
 * Admin panel shell: sidebar navigation, top bar, flash messages.
 *
 * @var App\Core\View $view
 */
$user = $currentUser ?? null;
$isAdmin = ($user['role_slug'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> &middot; <?= e($siteName) ?></title>
<?php if (($favicon ?? '') !== ''): ?>
<link rel="icon" href="<?= e(upload_url($favicon)) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<style>
:root {
  --brand-primary: <?= e($theme['primary']) ?>;
  --brand-secondary: <?= e($theme['secondary']) ?>;
  --brand-accent: <?= e($theme['accent']) ?>;
}
</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
      <div class="sidebar__mark">
        <?php if (($siteLogo ?? '') !== ''): ?>
          <img src="<?= e(upload_url($siteLogo)) ?>" alt="">
        <?php else: ?>ॐ<?php endif; ?>
      </div>
      <div>
        <div class="sidebar__name"><?= e($siteName) ?></div>
        <div class="sidebar__tag">v<?= e($appVersion) ?></div>
      </div>
    </div>

    <nav class="sidebar__nav">
      <a class="sidebar__link <?= active_when('/admin', true) ?>" href="<?= e(url('/admin')) ?>">
        <span class="sidebar__icon">▦</span> Dashboard
      </a>

      <div class="sidebar__group">Live Show</div>
      <a class="sidebar__link <?= active_when('/operator') ?>" href="<?= e(url('/operator')) ?>">
        <span class="sidebar__icon">▶</span> Operator Screen
      </a>
      <a class="sidebar__link" href="<?= e(url('/display')) ?>" target="_blank" rel="noopener">
        <span class="sidebar__icon">▣</span> Display Screen
      </a>
      <a class="sidebar__link <?= active_when('/admin/games') ?>" href="<?= e(url('/admin/games')) ?>">
        <span class="sidebar__icon">☰</span> Game History
      </a>

      <div class="sidebar__group">Content</div>
      <?php if ($isAdmin): ?>
      <a class="sidebar__link <?= active_when('/admin/questions') ?>" href="<?= e(url('/admin/questions')) ?>">
        <span class="sidebar__icon">?</span> Questions
      </a>
      <a class="sidebar__link <?= active_when('/admin/categories') ?>" href="<?= e(url('/admin/categories')) ?>">
        <span class="sidebar__icon">⊞</span> Categories
      </a>
      <?php endif; ?>
      <a class="sidebar__link <?= active_when('/admin/participants') ?>" href="<?= e(url('/admin/participants')) ?>">
        <span class="sidebar__icon">☻</span> Participants
      </a>

      <?php if ($isAdmin): ?>
      <div class="sidebar__group">Game Setup</div>
      <a class="sidebar__link <?= active_when('/admin/prizes') ?>" href="<?= e(url('/admin/prizes')) ?>">
        <span class="sidebar__icon">₹</span> Prize Ladder
      </a>
      <a class="sidebar__link <?= active_when('/admin/gifts') ?>" href="<?= e(url('/admin/gifts')) ?>">
        <span class="sidebar__icon">✦</span> Gifts
      </a>
      <a class="sidebar__link <?= active_when('/admin/lifelines') ?>" href="<?= e(url('/admin/lifelines')) ?>">
        <span class="sidebar__icon">♥</span> Lifelines
      </a>
      <?php endif; ?>

      <div class="sidebar__group">Insights</div>
      <a class="sidebar__link <?= active_when('/admin/reports') ?>" href="<?= e(url('/admin/reports')) ?>">
        <span class="sidebar__icon">◫</span> Reports
      </a>

      <?php if ($isAdmin): ?>
      <div class="sidebar__group">System</div>
      <a class="sidebar__link <?= active_when('/admin/settings') ?>" href="<?= e(url('/admin/settings')) ?>">
        <span class="sidebar__icon">⚙</span> Settings
      </a>
      <a class="sidebar__link <?= active_when('/admin/users') ?>" href="<?= e(url('/admin/users')) ?>">
        <span class="sidebar__icon">☺</span> Users
      </a>
      <a class="sidebar__link <?= active_when('/admin/backups') ?>" href="<?= e(url('/admin/backups')) ?>">
        <span class="sidebar__icon">⇩</span> Backups
      </a>
      <a class="sidebar__link <?= active_when('/admin/updates') ?>" href="<?= e(url('/admin/updates')) ?>">
        <span class="sidebar__icon">⇧</span> Updates
      </a>
      <a class="sidebar__link <?= active_when('/admin/logs') ?>" href="<?= e(url('/admin/logs/audit')) ?>">
        <span class="sidebar__icon">▤</span> Logs
      </a>
      <?php endif; ?>
    </nav>
  </aside>

  <div class="main">
    <header class="topbar">
      <button type="button" class="topbar__burger" id="navToggle" aria-label="Toggle navigation">☰</button>
      <div class="topbar__title"><?= e($pageTitle ?? 'Dashboard') ?></div>
      <div class="topbar__actions">
        <a class="btn btn--gold btn--sm" href="<?= e(url('/operator')) ?>">Operator</a>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/display')) ?>" target="_blank" rel="noopener">Display</a>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/profile')) ?>"><?= e($user['name'] ?? 'Profile') ?></a>
        <form method="post" action="<?= e(url('/admin/logout')) ?>" style="display:inline">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <button type="submit" class="btn btn--ghost btn--sm">Sign out</button>
        </form>
      </div>
    </header>

    <main class="content">
      <?= $view->include('partials.flash', ['flash' => $flash ?? []]) ?>
      <?= $view->section('content') ?>
    </main>
  </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?= $view->section('scripts') ?>
</body>
</html>
