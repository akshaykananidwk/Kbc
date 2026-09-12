<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Update #' . (int) $update['id']]);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Update #<?= (int) $update['id'] ?></h1>
    <p class="page-head__sub">v<?= e($update['old_version'] ?? '?') ?> → v<?= e($update['new_version'] ?? '?') ?></p>
  </div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/updates')) ?>">← Back</a>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title">Summary</h2>
    <span class="badge badge--<?= e(status_badge((string) $update['status'])) ?>"><?= e(str_replace('_', ' ', (string) $update['status'])) ?></span>
  </div>
  <div class="card__body">
    <ul class="check-list">
      <li><span class="check-list__label">Commit SHA</span><span class="check-list__value mono"><?= e($update['commit_sha'] ?? '—') ?></span></li>
      <li><span class="check-list__label">Commit message</span><span class="check-list__value"><?= e($update['commit_message'] ?? '—') ?></span></li>
      <li><span class="check-list__label">Author</span><span class="check-list__value"><?= e($update['commit_author'] ?? '—') ?></span></li>
      <li><span class="check-list__label">Commit date</span><span class="check-list__value"><?= e(datetime_label($update['commit_date'])) ?></span></li>
      <li><span class="check-list__label">Started by</span><span class="check-list__value"><?= e($update['initiated_by_name'] ?? 'system') ?></span></li>
      <li><span class="check-list__label">Started</span><span class="check-list__value"><?= e(datetime_label($update['started_at'])) ?></span></li>
      <li><span class="check-list__label">Finished</span><span class="check-list__value"><?= e(datetime_label($update['finished_at'])) ?></span></li>
      <li><span class="check-list__label">Backup status</span><span class="check-list__value"><?= e($update['backup_status']) ?></span></li>
      <li><span class="check-list__label">Files changed</span><span class="check-list__value"><?= (int) $update['files_changed'] ?></span></li>
      <li><span class="check-list__label">Migration status</span><span class="check-list__value"><?= e($update['migration_status']) ?></span></li>
      <li><span class="check-list__label">Migrations run</span><span class="check-list__value"><?= e($update['migrations_run'] ?? 'none') ?></span></li>
      <li><span class="check-list__label">Rollback status</span><span class="check-list__value"><?= e(str_replace('_', ' ', (string) $update['rollback_status'])) ?></span></li>
    </ul>

    <?php if (($update['error_message'] ?? '') !== ''): ?>
      <div class="alert alert--error mt-2"><span>✕</span><span><?= e($update['error_message']) ?></span></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">Full log</h2></div>
  <div class="card__body">
    <div class="log-view"><?= e($update['log'] ?? 'No log was recorded.') ?></div>
  </div>
</div>
<?php $view->stop(); ?>
