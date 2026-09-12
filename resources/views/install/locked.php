<?php
/** @var App\Core\View $view */
$view->extend('layouts.install', []);
$view->start('content');
?>
<div class="card">
  <div class="card__head"><h2 class="card__title">Already installed</h2></div>
  <div class="card__body">
    <div class="alert alert--warning">
      <span>!</span>
      <span>This quiz show is already installed, so the installer is locked.</span>
    </div>
    <p>
      Running it again could overwrite your questions, games and settings, so it is blocked.
      If you really do need to reinstall, delete <code>storage/installed.lock</code> on the server first
      &mdash; take a backup before you do.
    </p>
  </div>
  <div class="card__foot">
    <a class="btn btn--primary" href="<?= e(url('/admin/login')) ?>">Sign in</a>
    <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Home</a>
  </div>
</div>
<?php $view->stop(); ?>
