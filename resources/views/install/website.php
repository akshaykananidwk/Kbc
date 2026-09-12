<?php
/** @var App\Core\View $view */
$view->extend('layouts.install');
$view->start('content');
$old = $old ?? [];
$data = $data ?? [];
$value = static fn (string $key, string $fallback = '') => (string) ($old[$key] ?? $data[$key] ?? $fallback);
?>
<div class="card">
  <div class="card__head"><h2 class="card__title">Step 4 &middot; Website settings</h2></div>
  <form method="post" action="<?= e(url('/install/website')) ?>">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="card__body">
      <p class="muted">Every one of these can be changed later from Settings.</p>
      <div class="form-grid">
        <div class="field field--12">
          <label class="label" for="site_name">Website name <span class="required">*</span></label>
          <input type="text" id="site_name" name="site_name" required value="<?= e($value('site_name', 'ગણપતિ બાપા ક્વિઝ શો')) ?>">
        </div>
        <div class="field field--4">
          <label class="label" for="timezone">Timezone <span class="required">*</span></label>
          <select id="timezone" name="timezone" required>
            <?php foreach ($timezones as $zone): ?>
              <option value="<?= e($zone) ?>" <?= $value('timezone', 'Asia/Kolkata') === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--4">
          <label class="label" for="language">Primary language <span class="required">*</span></label>
          <select id="language" name="language" required>
            <?php foreach ($languages as $code => $label): ?>
              <option value="<?= e($code) ?>" <?= $value('language', 'gu') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--4">
          <label class="label" for="currency">Currency symbol <span class="required">*</span></label>
          <input type="text" id="currency" name="currency" required maxlength="8" value="<?= e($value('currency', '₹')) ?>">
        </div>
        <div class="field field--12">
          <label class="check">
            <input type="checkbox" name="demo_data" value="1" <?= $value('demo_data', '1') === '1' ? 'checked' : '' ?>>
            <span>Install demo content
              <small>14 sample questions (Gujarati, Hindi and English), 5 gifts, 3 participants and a 10-step prize ladder. You can remove it later in one click.</small>
            </span>
          </label>
        </div>
      </div>
    </div>
    <div class="card__foot">
      <a class="btn btn--ghost" href="<?= e(url('/install/admin')) ?>">← Back</a>
      <button type="submit" class="btn btn--primary btn--lg">Continue →</button>
    </div>
  </form>
</div>
<?php $view->stop(); ?>
