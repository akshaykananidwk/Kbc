<?php
/** @var App\Core\View $view */
$view->extend('layouts.install');
$view->start('content');
$old = $old ?? [];
$data = $data ?? [];
$value = static fn (string $key, string $fallback = '') => e($old[$key] ?? $data[$key] ?? $fallback);
?>
<div class="card">
  <div class="card__head"><h2 class="card__title">Step 2 &middot; Database connection</h2></div>
  <form method="post" action="<?= e(url('/install/database')) ?>">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="card__body">
      <p class="muted">
        Enter the MySQL / MariaDB details from your hosting control panel.
        Nothing is saved until the connection succeeds.
      </p>

      <div class="form-grid">
        <div class="field field--8">
          <label class="label" for="host">Database host <span class="required">*</span></label>
          <input type="text" id="host" name="host" required value="<?= $value('host', '127.0.0.1') ?>">
          <div class="field__help">Usually <code>localhost</code> or <code>127.0.0.1</code>.</div>
        </div>
        <div class="field field--4">
          <label class="label" for="port">Port <span class="required">*</span></label>
          <input type="number" id="port" name="port" required value="<?= $value('port', '3306') ?>" min="1" max="65535">
        </div>
        <div class="field field--6">
          <label class="label" for="database">Database name <span class="required">*</span></label>
          <input type="text" id="database" name="database" required value="<?= $value('database') ?>">
        </div>
        <div class="field field--6">
          <label class="label" for="username">Database username <span class="required">*</span></label>
          <input type="text" id="username" name="username" required value="<?= $value('username') ?>" autocomplete="off">
        </div>
        <div class="field field--6">
          <label class="label" for="password">Database password</label>
          <input type="password" id="password" name="password" autocomplete="new-password">
          <div class="field__help">Leave blank if the database user has no password.</div>
        </div>
        <div class="field field--6" style="display:flex;align-items:flex-end">
          <label class="check" style="width:100%">
            <input type="checkbox" name="create_database" value="1">
            <span>Create the database if it does not exist
              <small>Only works if the database user has CREATE permission.</small>
            </span>
          </label>
        </div>
      </div>
    </div>
    <div class="card__foot">
      <a class="btn btn--ghost" href="<?= e(url('/install/requirements')) ?>">← Back</a>
      <button type="submit" class="btn btn--primary btn--lg" data-busy="Testing connection…">Test connection &amp; continue →</button>
    </div>
  </form>
</div>
<?php $view->stop(); ?>
