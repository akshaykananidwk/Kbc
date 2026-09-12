<?php
/** @var App\Core\View $view */
$isEdit = $question !== null;
$view->extend('layouts.admin', ['pageTitle' => $isEdit ? 'Edit question' : 'Add question']);
$view->start('content');
$old = $old ?? [];
$errors = $errors ?? [];
$val = static function (string $key, mixed $fallback = '') use ($old, $question) {
    if (array_key_exists($key, $old)) { return (string) $old[$key]; }
    if ($question !== null && array_key_exists($key, $question)) { return (string) ($question[$key] ?? ''); }
    return (string) $fallback;
};
$optionVal = static function (string $key) use ($old, $options) {
    $formKey = 'option_' . strtolower($key);
    return array_key_exists($formKey, $old) ? (string) $old[$formKey] : (string) ($options[$key] ?? '');
};
$action = $isEdit ? url('/admin/questions/' . (int) $question['id']) : url('/admin/questions');
?>
<div class="page-head">
  <div>
    <h1><?= $isEdit ? 'Edit question' : 'Add question' ?></h1>
    <p class="page-head__sub">Gujarati, Hindi and English text are all supported.</p>
  </div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/questions')) ?>">← Back to list</a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

  <div class="card">
    <div class="card__head"><h2 class="card__title">The question</h2></div>
    <div class="card__body">
      <div class="form-grid">
        <div class="field">
          <label class="label" for="question_text">Question text <span class="required">*</span></label>
          <textarea id="question_text" name="question_text" required rows="3"
            class="<?= isset($errors['question_text']) ? 'has-error' : '' ?>"><?= e($val('question_text')) ?></textarea>
          <?php if (isset($errors['question_text'])): ?><div class="field__error"><?= e($errors['question_text']) ?></div><?php endif; ?>
        </div>

        <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
        <div class="field field--6">
          <label class="label" for="option_<?= strtolower($key) ?>">Option <?= $key ?> <span class="required">*</span></label>
          <input type="text" id="option_<?= strtolower($key) ?>" name="option_<?= strtolower($key) ?>" required
            class="<?= isset($errors['option_' . $key]) ? 'has-error' : '' ?>"
            value="<?= e($optionVal($key)) ?>">
          <?php if (isset($errors['option_' . $key])): ?><div class="field__error"><?= e($errors['option_' . $key]) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="field field--4">
          <label class="label" for="correct_option">Correct answer <span class="required">*</span></label>
          <select id="correct_option" name="correct_option" required>
            <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
              <option value="<?= $key ?>" <?= $val('correct_option', 'A') === $key ? 'selected' : '' ?>>Option <?= $key ?></option>
            <?php endforeach; ?>
          </select>
          <div class="field__help">Never sent to the display screen before the reveal.</div>
        </div>

        <div class="field field--8">
          <label class="label" for="explanation">Explanation <span class="label__hint">(shown after the answer is revealed)</span></label>
          <textarea id="explanation" name="explanation" rows="2"><?= e($val('explanation')) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Game settings</h2></div>
    <div class="card__body">
      <div class="form-grid">
        <div class="field field--4">
          <label class="label" for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">Uncategorised</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id'] ?>" <?= (int) $val('category_id') === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--4">
          <label class="label" for="difficulty">Difficulty</label>
          <select id="difficulty" name="difficulty" required>
            <?php foreach (['easy', 'medium', 'hard', 'expert'] as $level): ?>
              <option value="<?= $level ?>" <?= $val('difficulty', 'medium') === $level ? 'selected' : '' ?>><?= ucfirst($level) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--4">
          <label class="label" for="time_limit">Time limit (seconds) <span class="required">*</span></label>
          <input type="number" id="time_limit" name="time_limit" min="5" max="600" required value="<?= e($val('time_limit', (string) $defaultTime)) ?>">
        </div>
        <div class="field field--4">
          <label class="label" for="prize_level">Pin to prize level</label>
          <select id="prize_level" name="prize_level">
            <option value="">Not pinned — use anywhere</option>
            <?php for ($i = 1; $i <= max(1, (int) $maxLevel); $i++): ?>
              <option value="<?= $i ?>" <?= (int) $val('prize_level') === $i ? 'selected' : '' ?>>Question <?= $i ?></option>
            <?php endfor; ?>
          </select>
          <div class="field__help">Pinned questions always appear at that level.</div>
        </div>
        <div class="field field--4">
          <label class="label" for="sort_order">Sort order</label>
          <input type="number" id="sort_order" name="sort_order" min="0" value="<?= e($val('sort_order', '0')) ?>">
        </div>
        <div class="field field--4">
          <label class="label">Options</label>
          <label class="check mb-1">
            <input type="checkbox" name="status" value="1" <?= ($isEdit ? $val('status', 'active') === 'active' : true) ? 'checked' : '' ?>>
            <span>Active <small>Only active questions are used in games.</small></span>
          </label>
          <label class="check">
            <input type="checkbox" name="lifelines_allowed" value="1" <?= ($isEdit ? (string) $val('lifelines_allowed', '1') === '1' : true) ? 'checked' : '' ?>>
            <span>Allow lifelines <small>Uncheck to block lifelines on this question.</small></span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Media <span class="label__hint">(optional)</span></h2></div>
    <div class="card__body">
      <div class="form-grid">
        <div class="field field--4">
          <label class="label" for="image">Image</label>
          <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" data-preview="#imagePreview">
          <img id="imagePreview" class="thumb thumb--lg mt-1"
               src="<?= e($isEdit && ($question['image_path'] ?? '') !== '' ? upload_url($question['image_path']) : '') ?>"
               style="<?= $isEdit && ($question['image_path'] ?? '') !== '' ? '' : 'display:none' ?>" alt="">
          <?php if ($isEdit && ($question['image_path'] ?? '') !== ''): ?>
            <label class="check mt-1"><input type="checkbox" name="remove_image" value="1"><span>Remove image</span></label>
          <?php endif; ?>
        </div>
        <div class="field field--4">
          <label class="label" for="audio">Audio</label>
          <input type="file" id="audio" name="audio" accept=".mp3,.wav,.ogg,.m4a">
          <?php if ($isEdit && ($question['audio_path'] ?? '') !== ''): ?>
            <audio class="mt-1" controls src="<?= e(upload_url($question['audio_path'])) ?>" style="width:100%"></audio>
            <label class="check mt-1"><input type="checkbox" name="remove_audio" value="1"><span>Remove audio</span></label>
          <?php endif; ?>
        </div>
        <div class="field field--4">
          <label class="label" for="video">Video</label>
          <input type="file" id="video" name="video" accept=".mp4,.webm">
          <?php if ($isEdit && ($question['video_path'] ?? '') !== ''): ?>
            <label class="check mt-1"><input type="checkbox" name="remove_video" value="1"><span>Remove video</span></label>
          <?php endif; ?>
        </div>
      </div>
      <p class="field__help mt-1">
        Only image, audio and video files are accepted. Files are renamed, checked for real content
        and stored where they can never be executed.
      </p>
    </div>
    <div class="card__foot">
      <button type="submit" class="btn btn--primary btn--lg"><?= $isEdit ? 'Save changes' : 'Add question' ?></button>
      <a class="btn btn--ghost" href="<?= e(url('/admin/questions')) ?>">Cancel</a>
    </div>
  </div>
</form>
<?php $view->stop(); ?>
