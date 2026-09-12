<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'My profile']);
$view->start('content');
?>
<div class="page-head"><div><h1>My profile</h1></div></div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Details</h2></div>
    <form method="post" action="<?= e(url('/admin/profile')) ?>">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="field mb-2">
          <label class="label" for="name">Name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required value="<?= e($user['name'] ?? '') ?>">
        </div>
        <div class="field mb-2">
          <label class="label" for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email" required value="<?= e($user['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="label">Role</label>
          <p class="mb-0"><span class="badge badge--gold"><?= e($user['role_name'] ?? '') ?></span></p>
        </div>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Save details</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Change password</h2></div>
    <form method="post" action="<?= e(url('/admin/profile/password')) ?>">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="field mb-2">
          <label class="label" for="current_password">Current password <span class="required">*</span></label>
          <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field mb-2">
          <label class="label" for="new_password">New password <span class="required">*</span></label>
          <input type="password" id="new_password" name="password" required autocomplete="new-password">
          <div class="field__help">At least 8 characters with a letter and a number.</div>
        </div>
        <div class="field">
          <label class="label" for="password_confirmation">Confirm new password <span class="required">*</span></label>
          <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        </div>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Change password</button></div>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
