<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Lifelines']);
$view->start('content');
$usageByCode = [];
foreach ($usage as $row) { $usageByCode[(string) $row['code']] = (int) $row['times_used']; }
?>
<div class="page-head">
  <div><h1>Lifelines</h1><p class="page-head__sub">Enable, disable and configure each lifeline. Changes apply to the next question.</p></div>
</div>

<?php foreach ($lifelines as $lifeline): ?>
  <?php $config = is_array($lifeline['config']) ? $lifeline['config'] : []; ?>
  <div class="card">
    <div class="card__head">
      <h2 class="card__title"><?= e($lifeline['name']) ?> <span class="mono small muted"><?= e($lifeline['code']) ?></span></h2>
      <div class="flex">
        <span class="badge badge--<?= (int) $lifeline['is_enabled'] === 1 ? 'ok' : 'muted' ?>">
          <?= (int) $lifeline['is_enabled'] === 1 ? 'enabled' : 'disabled' ?>
        </span>
        <span class="badge">used <?= (int) ($usageByCode[$lifeline['code']] ?? 0) ?>×</span>
      </div>
    </div>
    <form method="post" action="<?= e(url('/admin/lifelines/' . (int) $lifeline['id'])) ?>" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="form-grid">
          <div class="field field--6">
            <label class="label">Display name <span class="required">*</span></label>
            <input type="text" name="name" required value="<?= e($lifeline['name']) ?>">
          </div>
          <div class="field field--3">
            <label class="label">Uses per game <span class="required">*</span></label>
            <input type="number" name="uses_per_game" min="0" max="10" required value="<?= (int) $lifeline['uses_per_game'] ?>">
          </div>
          <div class="field field--3">
            <label class="label">Sort order</label>
            <input type="number" name="sort_order" min="0" value="<?= (int) $lifeline['sort_order'] ?>">
          </div>
          <div class="field">
            <label class="label">Description</label>
            <input type="text" name="description" value="<?= e($lifeline['description'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="check">
              <input type="checkbox" name="is_enabled" value="1" <?= (int) $lifeline['is_enabled'] === 1 ? 'checked' : '' ?>>
              <span>Enabled <small>Disabled lifelines never appear on the operator or display screen.</small></span>
            </label>
          </div>

          <?php if ($lifeline['code'] === 'fifty_fifty'): ?>
            <div class="field field--6">
              <label class="label">Options left on screen</label>
              <select name="keep_options">
                <option value="2" <?= (int) ($config['keep_options'] ?? 2) === 2 ? 'selected' : '' ?>>2 (classic 50:50)</option>
                <option value="3" <?= (int) ($config['keep_options'] ?? 2) === 3 ? 'selected' : '' ?>>3 (easier)</option>
              </select>
              <div class="field__help">The correct answer is always one of the options kept.</div>
            </div>

          <?php elseif ($lifeline['code'] === 'audience_poll'): ?>
            <div class="field field--4">
              <label class="label">Poll mode</label>
              <select name="poll_mode">
                <option value="realistic" <?= ($config['mode'] ?? 'realistic') === 'realistic' ? 'selected' : '' ?>>Realistic (random, leans correct)</option>
                <option value="manual" <?= ($config['mode'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual percentages</option>
              </select>
            </div>
            <div class="field field--4">
              <label class="label">Correct answer share — minimum %</label>
              <input type="number" name="correct_bias_min" min="25" max="95" value="<?= (int) ($config['correct_bias_min'] ?? 45) ?>">
            </div>
            <div class="field field--4">
              <label class="label">Correct answer share — maximum %</label>
              <input type="number" name="correct_bias_max" min="25" max="95" value="<?= (int) ($config['correct_bias_max'] ?? 75) ?>">
            </div>
            <div class="field">
              <label class="label">Manual percentages <span class="label__hint">(used only in manual mode; scaled to total 100%)</span></label>
              <div class="form-grid">
                <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
                  <div class="field field--3">
                    <label class="label" for="manual_<?= $key ?>_<?= (int) $lifeline['id'] ?>">Option <?= $key ?></label>
                    <input type="number" id="manual_<?= $key ?>_<?= (int) $lifeline['id'] ?>" name="manual_<?= $key ?>" min="0" max="100"
                           value="<?= (int) ($config['manual_percentages'][$key] ?? 25) ?>">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

          <?php elseif ($lifeline['code'] === 'expert_advice'): ?>
            <div class="field field--6">
              <label class="label">Expert name</label>
              <input type="text" name="expert_name" value="<?= e($config['expert_name'] ?? 'Quiz Expert') ?>">
            </div>
            <div class="field field--3">
              <label class="label">Answer mode</label>
              <select name="expert_mode">
                <option value="auto" <?= ($config['mode'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automatic</option>
                <option value="manual" <?= ($config['mode'] ?? '') === 'manual' ? 'selected' : '' ?>>Always suggest a fixed option</option>
              </select>
            </div>
            <div class="field field--3">
              <label class="label">Fixed option (manual mode)</label>
              <select name="suggested_option">
                <option value="">—</option>
                <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
                  <option value="<?= $key ?>" <?= ($config['suggested_option'] ?? '') === $key ? 'selected' : '' ?>><?= $key ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field field--4">
              <label class="label">Confidence %</label>
              <input type="number" name="confidence" min="1" max="100" value="<?= (int) ($config['confidence'] ?? 80) ?>">
              <div class="field__help">In automatic mode this is also how often the expert is right.</div>
            </div>
            <div class="field field--8">
              <label class="label">Expert message</label>
              <input type="text" name="expert_message" value="<?= e($config['message'] ?? '') ?>">
            </div>
            <div class="field field--6">
              <label class="label">Expert photo</label>
              <input type="file" name="expert_photo" accept=".jpg,.jpeg,.png,.webp">
              <?php if (($config['expert_photo'] ?? '') !== ''): ?>
                <img class="thumb thumb--lg mt-1" src="<?= e(upload_url($config['expert_photo'])) ?>" alt="">
                <label class="check mt-1"><input type="checkbox" name="remove_expert_photo" value="1"><span>Remove photo</span></label>
              <?php endif; ?>
            </div>

          <?php elseif ($lifeline['code'] === 'skip_question'): ?>
            <div class="field">
              <label class="check">
                <input type="checkbox" name="keep_prize" value="1" <?= ($config['keep_prize'] ?? true) ? 'checked' : '' ?>>
                <span>Keep the same prize level after skipping
                  <small>The participant gets a different question worth the same amount.</small>
                </span>
              </label>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Save <?= e($lifeline['name']) ?></button></div>
    </form>
  </div>
<?php endforeach; ?>
<?php $view->stop(); ?>
