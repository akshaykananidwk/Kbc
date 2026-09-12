<?php
/** @var App\Core\View $view */
$isEdit = $user !== null;
$view->extend('layouts.admin', ['pageTitle' => $isEdit ? 'Edit user' : 'Add user']);
$view->start('content');
$old = $old ?? []; $errors = $errors ?? [];
$val = static function (string $key, mixed $fallback = '') use ($old, $user) {
    if (array_key_exists($key, $old)) { return (string) $old[$key]; }
    if ($user !== null) { return (string) ($user[$key] ?? $fallback); }
    return (string) $fallback;
};
$action = $isEdit ? url('/admin/users/' . (int) $user['id']) : url('/admin/users');
?>
<div class="page-head">
  <div><h1><?= $isEdit ? 'Edit user' : 'Add user' ?></h1></div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/users')) ?>">← Back</a>
</div>

<form method="post" action="<?= e($action) ?>">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <div class="card">
    <div class="card__body">
      <div class="form-grid">
        <div class="field field--6">
          <label class="label" for="name">Name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required value="<?= e($val('name')) ?>">
        </div>
        <div class="field field--6">
          <label class="label" for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email" required value="<?= e($val('email')) ?>"
                 class="<?= isset($errors['email']) ? 'has-error' : '' ?>">
          <?php if (isset($errors['email'])): ?><div class="field__error"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>
        <div class="field field--4">
          <label class="label" for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= e($val('username')) ?>">
        </div>
        <div class="field field--4">
          <label class="label" for="role_id">Role <span class="required">*</span></label>
          <select id="role_id" name="role_id" required>
            <?php foreach ($roles as $role): ?>
              <option value="<?= (int) $role['id'] ?>" <?= (int) $val('role_id') === (int) $role['id'] ? 'selected' : '' ?>>
                <?= e($role['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--4">
          <label class="label" for="status">Status <span class="required">*</span></label>
          <select id="status" name="status" required>
            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked'] as $key => $label): ?>
              <option value="<?= $key ?>" <?= $val('status', 'active') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--6">
          <label class="label" for="password">Password <?= $isEdit ? '' : '<span class="required">*</span>' ?></label>
          <input type="password" id="password" name="password" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password"
                 class="<?= isset($errors['password']) ? 'has-error' : '' ?>">
          <div class="field__help"><?= $isEdit ? 'Leave blank to keep the current password. ' : '' ?>At least 8 characters with a letter and a number.</div>
          <?php if (isset($errors['password'])): ?><div class="field__error"><?= e($errors['password']) ?></div><?php endif; ?>
        </div>
        <div class="field field--6">
          <label class="label" for="password_confirmation">Confirm password</label>
          <input type="password" id="password_confirmation" name="password_confirmation" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
        </div>
      </div>
    </div>
    <div class="card__foot">
      <button type="submit" class="btn btn--primary btn--lg"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
      <a class="btn btn--ghost" href="<?= e(url('/admin/users')) ?>">Cancel</a>
    </div>
  </div>
</form>
<?php $view->stop(); ?>
