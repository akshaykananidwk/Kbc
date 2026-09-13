<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Settings']);
$view->start('content');

$booleanKeys = [];
foreach ($groups as $rows) {
    foreach ($rows as $row) {
        if ($row['type'] === 'boolean') { $booleanKeys[] = (string) $row['key_name']; }
    }
}
$languageNames = ['gu' => 'ગુજરાતી (Gujarati)', 'hi' => 'हिन्दी (Hindi)', 'en' => 'English'];
$interfaceOptions = [];
foreach (App\Core\Lang::available() as $available) {
    $coverage = App\Core\Lang::coverage($available);
    $label = $languageNames[$available] ?? strtoupper($available);
    if ($available !== 'en' && $coverage['total'] > 0 && $coverage['translated'] < $coverage['total']) {
        $label .= ' — ' . round($coverage['translated'] / $coverage['total'] * 100) . '% translated';
    }
    $interfaceOptions[$available] = $label;
}

$selectOptions = [
    'question_order'     => ['fixed' => 'Fixed order', 'random' => 'Random', 'category' => 'By level category', 'difficulty' => 'By level difficulty'],
    'timer_style'        => ['ring' => 'Ring', 'bar' => 'Bar', 'digits' => 'Digits only'],
    'language'           => $languageNames,
    'interface_language' => $interfaceOptions,
];
?>
<div class="page-head">
  <div><h1>Settings</h1><p class="page-head__sub">Everything the show uses is configurable here — no code changes needed.</p></div>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <input type="hidden" name="settings_boolean_keys" value="<?= e(implode(',', $booleanKeys)) ?>">

  <?php foreach ($groups as $groupKey => $rows): ?>
    <div class="card">
      <div class="card__head">
        <h2 class="card__title"><?= e($groupNames[$groupKey] ?? ucfirst($groupKey)) ?></h2>
        <span class="badge"><?= count($rows) ?> setting(s)</span>
      </div>
      <div class="card__body">
        <?php if (in_array($groupKey, ['sound', 'music'], true)): ?>
          <div class="hint mb-1">
            <strong>Uploads on this server are limited to <?= e($serverUpload['limit_human']) ?>.</strong>
            Every sound also works with no file at all — the app generates its own tones.

            <?php if ($serverUpload['is_low']): ?>
              <p class="mb-1 mt-1">
                A full song is usually 4–8 MB, so music cannot be uploaded until
                <code>upload_max_filesize</code> and <code>post_max_size</code> are raised.
              </p>

              <?php if ($serverUpload['can_write']): ?>
                <button type="button" class="btn btn--gold btn--sm" id="phpLimitsBtn">
                  <?= $serverUpload['file_exists'] ? 'Rewrite .user.ini' : 'Create .user.ini for me' ?>
                </button>
                <span id="phpLimitsResult" class="small"></span>
              <?php else: ?>
                <p class="mb-1"><strong>This server will not let PHP create the file itself.</strong></p>
              <?php endif; ?>

              <details class="mt-1">
                <summary>Do it by hand (works on every host)</summary>
                <p class="mb-1 mt-1">
                  Create a file called <code>.user.ini</code> in
                  <code><?= e($serverUpload['path']) ?></code> containing:
                </p>
                <pre class="code-block"><?= e($userIniText) ?></pre>
                <p class="mb-0">
                  PHP caches this file for up to five minutes. If the limit still does not change,
                  your host enforces its own — raise it in the control panel
                  (cPanel → <em>MultiPHP INI Editor</em>, or <em>Select PHP Version → Options</em>).
                </p>
              </details>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="form-grid">
          <?php foreach ($rows as $row): ?>
            <?php
            $key = (string) $row['key_name'];
            $type = (string) $row['type'];
            $label = (string) ($row['label'] ?: ucfirst(str_replace('_', ' ', $key)));
            $id = 'set_' . $key;
            $isUpload = isset($uploadFields[$key]);
            $width = ($isUpload || in_array($type, ['text', 'boolean'], true)) ? 'field--6' : 'field--6';
            if ($type === 'text' || $type === 'boolean') { $width = ''; }
            ?>
            <div class="field <?= $width ?>">
              <?php if ($isUpload): ?>
                <?= $view->include('partials.media-field', [
                    'key'       => $key,
                    'label'     => $label,
                    'value'     => (string) $row['display_value'],
                    'spec'      => $uploadFields[$key],
                    'csrfToken' => $csrfToken,
                ]) ?>

              <?php elseif ($type === 'boolean'): ?>
                <label class="check">
                  <input type="checkbox" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" value="1"
                         <?= (string) $row['display_value'] === '1' ? 'checked' : '' ?>>
                  <span><?= e($label) ?>
                    <?php if (($row['description'] ?? '') !== ''): ?><small><?= e($row['description']) ?></small><?php endif; ?>
                  </span>
                </label>

              <?php elseif (isset($selectOptions[$key])): ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
                <select id="<?= e($id) ?>" name="settings[<?= e($key) ?>]">
                  <?php foreach ($selectOptions[$key] as $optionValue => $optionLabel): ?>
                    <option value="<?= e($optionValue) ?>" <?= (string) $row['display_value'] === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                  <?php endforeach; ?>
                </select>

              <?php elseif ($key === 'timezone'): ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
                <select id="<?= e($id) ?>" name="settings[<?= e($key) ?>]">
                  <?php foreach ($timezones as $zone): ?>
                    <option value="<?= e($zone) ?>" <?= (string) $row['display_value'] === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                  <?php endforeach; ?>
                </select>

              <?php elseif (str_ends_with($key, '_color')): ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
                <input type="color" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" value="<?= e($row['display_value'] ?: '#b3141a') ?>">

              <?php elseif ($type === 'integer'): ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
                <input type="number" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" value="<?= e($row['display_value']) ?>">

              <?php elseif ($type === 'text'): ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
                <textarea id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" rows="2"><?= e($row['display_value']) ?></textarea>

              <?php elseif ((bool) $row['is_secret']): ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?>
                  <span class="label__hint"><?= $row['is_configured'] ? '(configured — leave masked to keep it)' : '(not set)' ?></span></label>
                <input type="password" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]"
                       value="<?= e($row['display_value']) ?>" autocomplete="off">

              <?php else: ?>
                <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
                <input type="text" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" value="<?= e($row['display_value']) ?>">
              <?php endif; ?>

            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <div class="card__foot">
      <button type="submit" class="btn btn--primary btn--lg">Save all settings</button>
      <span class="muted small">Secrets left as <code>****</code> keep their current value.</span>
    </div>
  </div>
</form>

<div class="card">
  <div class="card__head"><h2 class="card__title">Demo data</h2></div>
  <div class="card__body">
    <p class="small muted mb-0">
      Removes the sample questions, gifts and participants that were installed with the demo option.
      Anything already used in a real game is kept so your history stays intact.
    </p>
  </div>
  <div class="card__foot">
    <form method="post" action="<?= e(url('/admin/settings/demo/remove')) ?>"
          data-confirm="Remove all unused demo data? This cannot be undone.">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <button type="submit" class="btn btn--danger">Remove demo data</button>
    </form>
  </div>
</div>
<?php $view->stop(); ?>

<?php $view->start('scripts'); ?>
<script src="<?= e(asset('assets/js/audio.js')) ?>"></script>
<script>
(function () {
  var button = document.getElementById('phpLimitsBtn');
  if (!button) return;
  var out = document.getElementById('phpLimitsResult');
  var token = <?= json_encode($csrfToken) ?>;
  var url = <?= json_encode(url('/admin/settings/php-limits')) ?>;

  button.addEventListener('click', function () {
    button.disabled = true;
    out.textContent = ' Writing…';

    var body = new FormData();
    body.append('_token', token);

    fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (r) { return r.json(); })
      .then(function (result) {
        button.disabled = false;
        out.textContent = ' ' + (result.message || '');
        out.className = 'small ' + (result.success ? 'text-good' : 'text-bad');
      })
      .catch(function () {
        button.disabled = false;
        out.textContent = ' Could not reach the server.';
        out.className = 'small text-bad';
      });
  });
})();
</script>
<?php $view->stop(); ?>
