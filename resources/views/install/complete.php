<?php
/** @var App\Core\View $view */
$view->extend('layouts.install');
$view->start('content');
?>
<div class="card">
  <div class="card__head">
    <h2 class="card__title">Step 6 &middot; Installation complete</h2>
    <span class="badge badge--ok">Success</span>
  </div>
  <div class="card__body">
    <div class="alert alert--success">
      <span>✓</span>
      <span><strong><?= e($result['site_name']) ?></strong> is installed and ready to use.</span>
    </div>

    <h3>What was done</h3>
    <ul class="check-list">
      <?php foreach ($result['steps'] as $step): ?>
        <li><span class="check-list__state">✓</span><span class="check-list__label"><?= e($step) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <h3 class="mt-3">Next steps</h3>
    <ol>
      <li>Sign in with <strong><?= e($result['admin_email']) ?></strong> and the password you chose.</li>
      <li>Review the <strong>prize ladder</strong> and <strong>gifts</strong> in the admin panel.</li>
      <li>Add your questions, or edit the demo ones.</li>
      <li>Open the <strong>display screen</strong> on your second monitor and press Full Screen.</li>
      <li>Run the show from the <strong>operator screen</strong> on your first monitor.</li>
    </ol>

    <div class="alert alert--info mt-2">
      <span>i</span>
      <span>
        The installer is now locked. For extra safety you can delete the
        <code>install</code> route from your server, but it will refuse to run again either way.
      </span>
    </div>
  </div>
  <div class="card__foot">
    <a class="btn btn--primary btn--lg" href="<?= e(url('/admin/login')) ?>">Sign in to the admin panel →</a>
    <a class="btn btn--ghost" href="<?= e(url('/display')) ?>" target="_blank" rel="noopener">Open display screen</a>
  </div>
</div>
<?php $view->stop(); ?>
