<?php
/** @var App\Core\View $view */
$view->extend('layouts.install');
$view->start('content');
$old = $old ?? [];
$data = $data ?? [];
$value = static fn (string $key, string $fallback = '') => e($old[$key] ?? $data[$key] ?? $fallback);
?>
<div class="card">
  <div class="card__head"><h2 class="card__title">Step 3 &middot; Administrator account</h2></div>
  <form method="post" action="<?= e(url('/install/admin')) ?>">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="card__body">
      <p class="muted">This is the account you will use to sign in and run the show. Keep the password safe.</p>
      <div class="form-grid">
        <div class="field field--6">
          <label class="label" for="name">Your name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required value="<?= $value('name') ?>">
        </div>
        <div class="field field--6">
          <label class="label" for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= $value('username', 'admin') ?>">
          <div class="field__help">You can sign in with either the username or the email.</div>
        </div>
        <div class="field field--12">
          <label class="label" for="email">Email address <span class="required">*</span></label>
          <input type="email" id="email" name="email" required value="<?= $value('email') ?>">
        </div>
        <div class="field field--6">
          <label class="label" for="password">Password <span class="required">*</span></label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
          <div class="field__help">At least 8 characters, with one letter and one number.</div>
        </div>
        <div class="field field--6">
          <label class="label" for="password_confirmation">Confirm password <span class="required">*</span></label>
          <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        </div>
      </div>
    </div>
    <div class="card__foot">
      <a class="btn btn--ghost" href="<?= e(url('/install/database')) ?>">← Back</a>
      <button type="submit" class="btn btn--primary btn--lg">Continue →</button>
    </div>
  </form>
</div>
<?php $view->stop(); ?>
