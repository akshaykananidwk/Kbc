<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Backups']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Backups</h1>
    <p class="page-head__sub">Backups are stored outside the web root and can never be downloaded without signing in.</p>
  </div>
</div>

<?php if (!$writable): ?>
  <div class="alert alert--error"><span>✕</span><span>
    The backup folder is not writable: <code><?= e($directory) ?></code>. Set it to 755 (or 775) on your server.
  </span></div>
<?php endif; ?>

<div class="grid-3">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Database backup</h2></div>
    <div class="card__body"><p class="small muted mb-0">A complete SQL dump of every table, zipped. Safe to run during a show.</p></div>
    <div class="card__foot">
      <form method="post" action="<?= e(url('/admin/backups/database')) ?>" style="width:100%">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="note" value="Manual database backup">
        <button type="submit" class="btn btn--primary btn--block" data-busy="Backing up…">Back up the database</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Files backup</h2></div>
    <div class="card__body"><p class="small muted mb-0">Zips the application code and your uploads. Existing backups are excluded.</p></div>
    <div class="card__foot">
      <form method="post" action="<?= e(url('/admin/backups/files')) ?>" style="width:100%">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="note" value="Manual files backup">
        <button type="submit" class="btn btn--primary btn--block" data-busy="Backing up…">Back up the files</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Storage</h2></div>
    <div class="card__body">
      <ul class="check-list">
        <li><span class="check-list__label">Folder</span><span class="check-list__value"><?= e(basename($directory)) ?></span></li>
        <li><span class="check-list__label">Writable</span><span class="check-list__value"><?= $writable ? 'yes' : 'no' ?></span></li>
        <li><span class="check-list__label">Free space</span><span class="check-list__value"><?= e($freeSpace) ?></span></li>
        <li><span class="check-list__label">Backups kept</span><span class="check-list__value"><?= count($backups) ?></span></li>
      </ul>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">Existing backups</h2></div>
  <div class="card__body card__body--flush">
    <?php if ($backups === []): ?>
      <div class="empty"><div class="empty__icon">⇩</div><p>No backups yet. Take one before your first live show.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>File</th><th>Type</th><th class="num">Size</th><th>Created</th><th>By</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($backups as $backup): ?>
          <tr>
            <td class="mono small"><?= e($backup['filename']) ?>
              <?php if (($backup['note'] ?? '') !== ''): ?><div class="small muted"><?= e($backup['note']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge badge--<?= $backup['type'] === 'database' ? 'info' : 'muted' ?>"><?= e($backup['type']) ?></span></td>
            <td class="num"><?= e($backup['size_human']) ?></td>
            <td class="small nowrap"><?= e(datetime_label((string) $backup['created_at'])) ?></td>
            <td class="small"><?= e($backup['created_by_name'] ?? 'system') ?></td>
            <td>
              <span class="badge badge--<?= e(status_badge((string) $backup['status'])) ?>"><?= e($backup['status']) ?></span>
              <?php if (!$backup['exists']): ?><div><span class="badge badge--bad">file missing</span></div><?php endif; ?>
            </td>
            <td class="actions">
              <?php if ($backup['exists']): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/backups/' . (int) $backup['id'] . '/download')) ?>">Download</a>
                <?php if ($backup['type'] === 'database'): ?>
                <form method="post" action="<?= e(url('/admin/backups/' . (int) $backup['id'] . '/restore')) ?>" style="display:inline"
                      data-confirm="RESTORE this database backup?&#10;&#10;Everything currently in the database will be replaced. A safety backup of the current data is taken first.&#10;&#10;Continue?">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" class="btn btn--gold btn--sm" data-busy="Restoring…">Restore</button>
                </form>
                <?php endif; ?>
              <?php endif; ?>
              <form method="post" action="<?= e(url('/admin/backups/' . (int) $backup['id'] . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this backup file permanently?">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
