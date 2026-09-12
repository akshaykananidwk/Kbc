<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Updates']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>System updates</h1>
    <p class="page-head__sub">Check GitHub for a newer version and install it with one click — no ZIP uploads.</p>
  </div>
  <div class="btn-row">
    <button type="button" class="btn btn--primary" id="checkUpdateBtn">Check for update</button>
    <button type="button" class="btn btn--ghost" id="clearCacheBtn">Clear cache</button>
  </div>
</div>

<meta name="csrf-token" content="<?= e($csrfToken) ?>">

<?php if (!$curlAvailable || !$zipAvailable): ?>
  <div class="alert alert--error"><span>✕</span><span>
    <?= !$curlAvailable ? 'The PHP cURL extension is missing, so GitHub cannot be reached. ' : '' ?>
    <?= !$zipAvailable ? 'The PHP zip extension is missing, so packages cannot be extracted. ' : '' ?>
    Ask your host to enable them.
  </span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat"><div class="stat__label">Current version</div><div class="stat__value">v<?= e($currentVersion) ?></div>
    <div class="stat__meta"><?= $currentCommit !== '' ? 'commit ' . e(substr($currentCommit, 0, 7)) : 'no commit recorded' ?></div></div>
  <div class="stat"><div class="stat__label">Repository</div>
    <div class="stat__value" style="font-size:1rem"><?= $config['configured'] ? e($config['owner'] . '/' . $config['repo']) : 'not configured' ?></div>
    <div class="stat__meta">branch: <?= e($config['branch']) ?></div></div>
  <div class="stat"><div class="stat__label">Token</div>
    <div class="stat__value" style="font-size:1rem"><?= $config['has_token'] ? e($config['token_mask']) : 'not set' ?></div>
    <div class="stat__meta"><?= $config['has_token'] ? 'stored encrypted' : 'public repositories only' ?></div></div>
  <div class="stat"><div class="stat__label">Last checked</div>
    <div class="stat__value" style="font-size:1rem"><?= e($lastChecked !== '' ? datetime_label($lastChecked) : 'never') ?></div></div>
</div>

<div class="card" id="updateResultCard" hidden>
  <div class="card__head"><h2 class="card__title">Update check result</h2></div>
  <div class="card__body" id="updateResult"></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">GitHub configuration</h2></div>
    <form method="post" action="<?= e(url('/admin/updates/settings')) ?>">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="form-grid">
          <div class="field field--6">
            <label class="label" for="github_owner">Repository owner <span class="required">*</span></label>
            <input type="text" id="github_owner" name="github_owner" value="<?= e($config['owner']) ?>" placeholder="your-github-username">
          </div>
          <div class="field field--6">
            <label class="label" for="github_repo">Repository name <span class="required">*</span></label>
            <input type="text" id="github_repo" name="github_repo" value="<?= e($config['repo']) ?>" placeholder="ganpati-quiz-show">
          </div>
          <div class="field field--6">
            <label class="label" for="github_branch">Branch</label>
            <input type="text" id="github_branch" name="github_branch" value="<?= e($config['branch']) ?>" placeholder="main">
          </div>
          <div class="field field--6">
            <label class="label" for="github_token">GitHub token
              <span class="label__hint"><?= $config['has_token'] ? '(saved — leave blank to keep)' : '(needed for private repos)' ?></span></label>
            <input type="password" id="github_token" name="github_token" autocomplete="off"
                   placeholder="<?= $config['has_token'] ? e($config['token_mask']) : 'ghp_…' ?>">
            <div class="field__help">
              Use a fine-grained token with <strong>Contents: read-only</strong> on this repository only.
              It is encrypted before it is stored and never appears in any API response or log.
            </div>
          </div>
          <?php if ($config['has_token']): ?>
          <div class="field">
            <label class="check"><input type="checkbox" name="remove_token" value="1">
              <span>Remove the stored token</span></label>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Save configuration</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">What an update does</h2></div>
    <div class="card__body">
      <ol class="small">
        <li>Verifies the repository, branch and token.</li>
        <li>Backs up the database <em>and</em> snapshots your application files.</li>
        <li>Downloads the package from GitHub and validates it.</li>
        <li>Replaces application files, leaving protected paths untouched.</li>
        <li>Runs any new database migrations.</li>
        <li>Clears the application cache.</li>
        <li>If anything fails, everything is rolled back automatically.</li>
      </ol>

      <h3 class="mt-2">Never overwritten</h3>
      <ul class="small mono muted">
        <?php foreach ($protectedPaths as $path): ?><li><?= e($path) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title">Update history</h2>
    <span class="badge"><?= count($history) ?> run(s)</span>
  </div>
  <div class="card__body card__body--flush">
    <?php if ($history === []): ?>
      <div class="empty"><div class="empty__icon">⇧</div><p>No updates have been run yet.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>From → To</th><th>Commit</th><th>Status</th><th>Backup</th><th>Migration</th><th>Rollback</th><th>When</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= (int) $row['id'] ?></td>
            <td class="small">v<?= e($row['old_version'] ?? '?') ?> → <strong>v<?= e($row['new_version'] ?? '?') ?></strong></td>
            <td class="small mono"><?= e(substr((string) ($row['commit_sha'] ?? ''), 0, 7)) ?>
              <div class="small muted"><?= e(mb_strimwidth((string) ($row['commit_message'] ?? ''), 0, 44, '…')) ?></div></td>
            <td><span class="badge badge--<?= e(status_badge((string) $row['status'])) ?>"><?= e(str_replace('_', ' ', (string) $row['status'])) ?></span></td>
            <td class="small"><?= e($row['backup_status']) ?></td>
            <td class="small"><?= e($row['migration_status']) ?></td>
            <td class="small"><?= e(str_replace('_', ' ', (string) $row['rollback_status'])) ?></td>
            <td class="small nowrap"><?= e(datetime_label((string) $row['created_at'])) ?></td>
            <td class="actions"><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/updates/' . (int) $row['id'])) ?>">Details</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>

<?php $view->start('scripts'); ?>
<script>
(function () {
  'use strict';
  var checkBtn = document.getElementById('checkUpdateBtn');
  var cacheBtn = document.getElementById('clearCacheBtn');
  var card = document.getElementById('updateResultCard');
  var box = document.getElementById('updateResult');
  var base = <?= json_encode(url('/')) ?>;
  var esc = function (value) {
    var div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
  };

  function show(html) {
    box.innerHTML = html;
    card.removeAttribute('hidden');
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function runUpdate() {
    var confirmed = window.confirm(
      'Back up and update now?\n\n' +
      'A database backup and a file snapshot are taken first.\n' +
      'Your .env, uploads and storage folders are never overwritten.\n' +
      'If anything fails the update is rolled back automatically.\n\n' +
      'Do not close this page while the update runs.'
    );
    if (!confirmed) return;

    show('<div class="alert alert--info"><span>⏳</span><span>Updating. This can take a minute — please keep this page open.</span></div>');

    window.QuizApi.post(base.replace(/\/$/, '') + '/api/updates/run', { confirm: true }).then(function (response) {
      if (!response.success) {
        show('<div class="alert alert--error"><span>✕</span><span>' + esc(response.message) + '</span></div>' +
             '<p class="small muted">The update history below shows exactly which step failed and whether the rollback succeeded.</p>');
        return;
      }
      var update = response.data.update || {};
      show('<div class="alert alert--success"><span>✓</span><span>' + esc(response.message) + '</span></div>' +
           '<p>Now running <strong>v' + esc(update.new_version) + '</strong>' +
           ' (commit ' + esc(String(update.commit_sha || '').substring(0, 7)) + ').</p>' +
           '<pre class="log-view">' + esc(update.log || '') + '</pre>' +
           '<button type="button" class="btn btn--primary" onclick="window.location.reload()">Reload the page</button>');
    });
  }

  if (checkBtn) {
    checkBtn.addEventListener('click', function () {
      checkBtn.disabled = true;
      checkBtn.textContent = 'Checking…';
      show('<p class="muted">Contacting GitHub…</p>');

      window.QuizApi.get(base.replace(/\/$/, '') + '/api/updates/check').then(function (response) {
        checkBtn.disabled = false;
        checkBtn.textContent = 'Check for update';

        if (!response.success) {
          show('<div class="alert alert--error"><span>✕</span><span>' + esc(response.message) + '</span></div>');
          return;
        }

        var d = response.data;
        if (!d.configured) {
          show('<div class="alert alert--warning"><span>!</span><span>' + esc(d.message) + '</span></div>');
          return;
        }

        var html = '<div class="grid-2"><div><ul class="check-list">' +
          '<li><span class="check-list__label">Current version</span><span class="check-list__value">v' + esc(d.current_version) + '</span></li>' +
          '<li><span class="check-list__label">Latest version</span><span class="check-list__value">v' + esc(d.latest_version) + '</span></li>' +
          '<li><span class="check-list__label">Latest commit</span><span class="check-list__value">' + esc(String(d.latest_commit).substring(0, 7)) + '</span></li>' +
          '<li><span class="check-list__label">Commit message</span><span class="check-list__value">' + esc(d.commit_message) + '</span></li>' +
          '<li><span class="check-list__label">Author</span><span class="check-list__value">' + esc(d.commit_author) + '</span></li>' +
          '<li><span class="check-list__label">Date</span><span class="check-list__value">' + esc(d.commit_date) + '</span></li>' +
          '</ul></div><div>';

        if (d.changed_files && d.changed_files.length) {
          html += '<h3>Changed files (' + d.changed_files.length + ')</h3><ul class="small mono muted" style="max-height:220px;overflow:auto">';
          d.changed_files.forEach(function (file) {
            html += '<li>' + esc(file.status) + ': ' + esc(file.filename) + '</li>';
          });
          html += '</ul>';
        }
        html += '</div></div>';

        if (d.update_available) {
          html = '<div class="alert alert--warning"><span>⇧</span><span><strong>An update is available.</strong> ' +
                 'Review the details, then choose Backup &amp; update.</span></div>' + html +
                 '<div class="btn-row mt-2"><button type="button" class="btn btn--gold btn--lg" id="doUpdateBtn">Backup &amp; update now</button></div>';
        } else {
          html = '<div class="alert alert--success"><span>✓</span><span>You are running the latest version.</span></div>' + html;
        }

        show(html);

        var doBtn = document.getElementById('doUpdateBtn');
        if (doBtn) doBtn.addEventListener('click', runUpdate);
      });
    });
  }

  if (cacheBtn) {
    cacheBtn.addEventListener('click', function () {
      cacheBtn.disabled = true;
      window.QuizApi.post(base.replace(/\/$/, '') + '/api/updates/cache/clear', {}).then(function (response) {
        cacheBtn.disabled = false;
        show('<div class="alert alert--' + (response.success ? 'success' : 'error') + '"><span>' +
             (response.success ? '✓' : '✕') + '</span><span>' + esc(response.message) + '</span></div>');
      });
    });
  }
})();
</script>
<?php $view->stop(); ?>
