<?php
/**
 * Inline media control for a setting that holds a file.
 *
 * Shows the current image or audio player, an upload button that works
 * without leaving the page, and a remove button. No path typing anywhere.
 *
 * @var string $key
 * @var string $label
 * @var string $value      stored relative path, e.g. uploads/branding/x.mp3
 * @var array  $spec       from SettingsController::uploadFields()
 * @var string $csrfToken
 */
$kind = $spec['kind'] ?? 'image';
$accept = $kind === 'audio' ? '.mp3,.wav,.ogg,.m4a' : '.jpg,.jpeg,.png,.gif,.webp,.svg,.ico';
$maxLabel = \App\Support\Str::humanBytes((int) ($spec['max'] ?? 4194304));
$hasFile = trim($value) !== '';
$fileUrl = $hasFile ? upload_url($value) : '';
?>
<div class="media-field" data-media-field data-key="<?= e($key) ?>" data-kind="<?= e($kind) ?>">
  <label class="label" for="media_<?= e($key) ?>">
    <?= e($label) ?>
    <span class="label__hint"><?= $kind === 'audio' ? 'MP3, WAV, OGG or M4A' : 'JPG, PNG, WEBP or SVG' ?> &middot; max <?= e($maxLabel) ?></span>
  </label>

  <div class="media-field__body">
    <div class="media-field__preview" data-media-preview>
      <?php if ($hasFile && $kind === 'audio'): ?>
        <audio controls preload="none" src="<?= e($fileUrl) ?>"></audio>
      <?php elseif ($hasFile): ?>
        <img src="<?= e($fileUrl) ?>" alt="">
      <?php else: ?>
        <span class="media-field__empty"><?= $kind === 'audio' ? '♪ No file yet' : 'No image yet' ?></span>
      <?php endif; ?>
    </div>

    <div class="media-field__actions">
      <label class="btn btn--gold btn--sm media-field__pick">
        <?= $hasFile ? 'Replace' : ($kind === 'audio' ? 'Upload music' : 'Upload image') ?>
        <input type="file" accept="<?= e($accept) ?>" data-media-input hidden>
      </label>
      <?php if ($kind === 'audio'): ?>
        <button type="button" class="btn btn--ghost btn--sm" data-media-test
                data-cue="<?= e(str_starts_with($key, 'sound_') ? substr($key, 6) : '') ?>">&#9654; Test sound</button>
      <?php endif; ?>
      <button type="button" class="btn btn--ghost btn--sm" data-media-remove <?= $hasFile ? '' : 'hidden' ?>>Remove</button>
    </div>
  </div>

  <div class="media-field__status" data-media-status><?= $hasFile ? e(basename($value)) : '' ?></div>
  <input type="hidden" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>" data-media-value>
</div>
