<?php
/** @var App\Core\View $view */
$isEdit = $participant !== null;
$view->extend('layouts.admin', ['pageTitle' => $isEdit ? 'Edit participant' : 'Add participant']);
$view->start('content');
$old = $old ?? []; $errors = $errors ?? [];
$val = static function (string $key, mixed $fallback = '') use ($old, $participant) {
    if (array_key_exists($key, $old)) { return (string) $old[$key]; }
    if ($participant !== null) { return (string) ($participant[$key] ?? $fallback); }
    return (string) $fallback;
};
$action = $isEdit ? url('/admin/participants/' . (int) $participant['id']) : url('/admin/participants');
?>
<div class="page-head">
  <div><h1><?= $isEdit ? 'Edit participant' : 'Add participant' ?></h1></div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/participants')) ?>">← Back</a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <div class="card">
    <div class="card__body">
      <div class="form-grid">
        <div class="field field--8">
          <label class="label" for="name">Full name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required value="<?= e($val('name')) ?>"
                 class="<?= isset($errors['name']) ? 'has-error' : '' ?>">
          <?php if (isset($errors['name'])): ?><div class="field__error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="field field--4">
          <label class="label" for="registration_no">Registration number</label>
          <input type="text" id="registration_no" name="registration_no" value="<?= e($val('registration_no', $nextRegistration)) ?>"
                 class="<?= isset($errors['registration_no']) ? 'has-error' : '' ?>">
          <?php if (isset($errors['registration_no'])): ?><div class="field__error"><?= e($errors['registration_no']) ?></div><?php endif; ?>
        </div>
        <div class="field field--4">
          <label class="label" for="mobile">Mobile</label>
          <input type="tel" id="mobile" name="mobile" value="<?= e($val('mobile')) ?>">
        </div>
        <div class="field field--4">
          <label class="label" for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($val('email')) ?>">
        </div>
        <div class="field field--4">
          <label class="label" for="city">City</label>
          <input type="text" id="city" name="city" value="<?= e($val('city')) ?>">
        </div>
        <div class="field field--3">
          <label class="label" for="age">Age</label>
          <input type="number" id="age" name="age" min="1" max="120" value="<?= e($val('age')) ?>">
        </div>
        <div class="field field--3">
          <label class="label" for="gender">Gender</label>
          <select id="gender" name="gender">
            <?php foreach (['unspecified' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $key => $label): ?>
              <option value="<?= $key ?>" <?= $val('gender', 'unspecified') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--3">
          <label class="label" for="status">Status</label>
          <select id="status" name="status" required>
            <?php foreach (['active' => 'Active', 'played' => 'Already played', 'inactive' => 'Inactive'] as $key => $label): ?>
              <option value="<?= $key ?>" <?= $val('status', 'active') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--3">
          <label class="label" for="photo">Photo</label>
          <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp" data-preview="#photoPreview">
          <img id="photoPreview" class="thumb thumb--lg mt-1"
               src="<?= e($isEdit && ($participant['photo_path'] ?? '') !== '' ? upload_url($participant['photo_path']) : '') ?>"
               style="<?= $isEdit && ($participant['photo_path'] ?? '') !== '' ? '' : 'display:none' ?>" alt="">
          <?php if ($isEdit && ($participant['photo_path'] ?? '') !== ''): ?>
            <label class="check mt-1"><input type="checkbox" name="remove_photo" value="1"><span>Remove photo</span></label>
          <?php endif; ?>
        </div>
        <div class="field">
          <label class="label" for="notes">Notes <span class="label__hint">(only visible to the operator)</span></label>
          <textarea id="notes" name="notes" rows="2"><?= e($val('notes')) ?></textarea>
        </div>
      </div>
    </div>
    <div class="card__foot">
      <button type="submit" class="btn btn--primary btn--lg"><?= $isEdit ? 'Save changes' : 'Add participant' ?></button>
      <a class="btn btn--ghost" href="<?= e(url('/admin/participants')) ?>">Cancel</a>
    </div>
  </div>
</form>
<?php $view->stop(); ?>
