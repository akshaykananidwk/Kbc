<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'System log']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>System error log</h1><p class="page-head__sub">Errors are logged here and never shown to visitors.</p></div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/logs/audit')) ?>">Audit log</a>
</div>

<div class="card">
  <div class="card__head">
    <form method="get" action="<?= e(url('/admin/logs')) ?>" class="flex">
      <label class="label mb-0" for="file">Log file</label>
      <select id="file" name="file" data-auto-submit style="width:auto;min-width:220px">
        <?php foreach ($files as $file): ?>
          <option value="<?= e($file) ?>" <?= $selected === $file ? 'selected' : '' ?>><?= e($file) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="btn btn--ghost btn--sm">Show</button></noscript>
    </form>
    <span class="badge"><?= count($files) ?> file(s)</span>
  </div>
  <div class="card__body">
    <?php if ($files === []): ?>
      <div class="empty"><div class="empty__icon">✓</div><p>No errors have been logged. That is good news.</p></div>
    <?php else: ?>
      <div class="log-view"><?= e($content !== '' ? $content : 'This log file is empty.') ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
