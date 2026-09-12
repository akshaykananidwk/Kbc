<?php
/** @var App\Core\View $view */
$view->extend('layouts.install');
$view->start('content');
$render = static function (array $rows): void {
    foreach ($rows as $row) {
        $class = $row['ok'] ? '' : ($row['required'] ? 'is-bad' : 'is-warn');
        ?>
        <li class="<?= $class ?>">
          <span class="check-list__state"><?= $row['ok'] ? '✓' : ($row['required'] ? '✕' : '!') ?></span>
          <span class="check-list__label">
            <?= e($row['label']) ?>
            <?php if (!$row['required']): ?><span class="badge badge--muted">optional</span><?php endif; ?>
          </span>
          <span class="check-list__value"><?= e($row['current']) ?></span>
        </li>
        <?php
    }
};
?>
<div class="card">
  <div class="card__head">
    <h2 class="card__title">Step 1 &middot; System check</h2>
    <?php if ($check['can_continue']): ?>
      <span class="badge badge--ok">Ready to install</span>
    <?php else: ?>
      <span class="badge badge--bad"><?= count($check['failed_required']) ?> problem(s) to fix</span>
    <?php endif; ?>
  </div>
  <div class="card__body">
    <p class="muted">
      These checks confirm your hosting can run the quiz show. Anything marked
      <strong>optional</strong> will not stop the installation.
    </p>

    <h3 class="mt-2">PHP</h3>
    <ul class="check-list"><?php $render($check['php']); ?></ul>

    <h3 class="mt-3">PHP extensions</h3>
    <ul class="check-list"><?php $render($check['extensions']); ?></ul>

    <h3 class="mt-3">Folder permissions</h3>
    <ul class="check-list"><?php $render($check['writables']); ?></ul>

    <h3 class="mt-3">Server</h3>
    <ul class="check-list"><?php $render($check['server']); ?></ul>

    <?php if (!$check['can_continue']): ?>
      <div class="alert alert--error mt-3">
        <span>✕</span>
        <span>
          Please fix the items marked in red, then reload this page.
          Most hosts let you change the PHP version and folder permissions from the control panel.
        </span>
      </div>
    <?php endif; ?>
  </div>
  <div class="card__foot">
    <?php if ($check['can_continue']): ?>
      <a class="btn btn--primary btn--lg" href="<?= e(url('/install/database')) ?>">Continue to database setup →</a>
    <?php else: ?>
      <a class="btn btn--ghost btn--lg" href="<?= e(url('/install/requirements')) ?>">Re-run the checks</a>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
