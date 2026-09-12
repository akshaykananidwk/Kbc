<?php
/** @var App\Core\View $view */
$view->extend('layouts.install');
$view->start('content');
?>
<div class="card">
  <div class="card__head"><h2 class="card__title">Step 5 &middot; Review and install</h2></div>
  <div class="card__body">
    <p class="muted">Check the details below, then start the installation.</p>

    <div class="grid-2">
      <div>
        <h3>Database</h3>
        <ul class="check-list">
          <li><span class="check-list__label">Host</span><span class="check-list__value"><?= e($database['host']) ?>:<?= e($database['port']) ?></span></li>
          <li><span class="check-list__label">Database</span><span class="check-list__value"><?= e($database['database']) ?></span></li>
          <li><span class="check-list__label">Username</span><span class="check-list__value"><?= e($database['username']) ?></span></li>
        </ul>
      </div>
      <div>
        <h3>Administrator</h3>
        <ul class="check-list">
          <li><span class="check-list__label">Name</span><span class="check-list__value"><?= e($admin['name']) ?></span></li>
          <li><span class="check-list__label">Email</span><span class="check-list__value"><?= e($admin['email']) ?></span></li>
          <li><span class="check-list__label">Username</span><span class="check-list__value"><?= e($admin['username']) ?></span></li>
        </ul>
      </div>
      <div>
        <h3>Website</h3>
        <ul class="check-list">
          <li><span class="check-list__label">Name</span><span class="check-list__value"><?= e($website['site_name']) ?></span></li>
          <li><span class="check-list__label">Timezone</span><span class="check-list__value"><?= e($website['timezone']) ?></span></li>
          <li><span class="check-list__label">Language</span><span class="check-list__value"><?= e($website['language']) ?></span></li>
          <li><span class="check-list__label">Demo data</span><span class="check-list__value"><?= $website['demo_data'] === '1' ? 'yes' : 'no' ?></span></li>
        </ul>
      </div>
      <div>
        <h3>What happens next</h3>
        <ul class="check-list">
          <li><span class="check-list__state">1</span><span class="check-list__label">Write the .env configuration file</span></li>
          <li><span class="check-list__state">2</span><span class="check-list__label">Create all database tables</span></li>
          <li><span class="check-list__state">3</span><span class="check-list__label">Insert settings, prize ladder and lifelines</span></li>
          <li><span class="check-list__state">4</span><span class="check-list__label">Create your administrator account</span></li>
          <li><span class="check-list__state">5</span><span class="check-list__label">Lock the installer</span></li>
        </ul>
      </div>
    </div>

    <?php if (!empty($_SESSION['install.partial_steps'])): ?>
      <div class="alert alert--warning mt-2">
        <span>!</span>
        <span>The previous attempt stopped after: <?= e(implode(', ', (array) $_SESSION['install.partial_steps'])) ?>. Fix the error above and try again — the installer is safe to re-run.</span>
      </div>
    <?php endif; ?>
  </div>
  <div class="card__foot">
    <a class="btn btn--ghost" href="<?= e(url('/install/website')) ?>">← Back</a>
    <form method="post" action="<?= e(url('/install/run')) ?>" style="flex:1">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <button type="submit" class="btn btn--primary btn--lg btn--block" data-busy="Installing, please wait…">
        Install now
      </button>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
