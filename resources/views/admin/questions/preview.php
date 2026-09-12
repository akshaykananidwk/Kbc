<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Question preview']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Question preview</h1>
    <p class="page-head__sub">This is what the operator sees. The display screen never receives the correct answer.</p>
  </div>
  <div class="btn-row">
    <a class="btn btn--primary" href="<?= e(url('/admin/questions/' . (int) $question['id'] . '/edit')) ?>">Edit</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/questions')) ?>">← Back</a>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title"><?= e($question['category_name'] ?? 'Uncategorised') ?></h2>
    <div class="flex">
      <span class="badge"><?= e($question['difficulty']) ?></span>
      <span class="badge badge--info"><?= (int) $question['time_limit'] ?>s</span>
      <?php if ($question['prize_level'] !== null): ?><span class="badge badge--gold">Question <?= (int) $question['prize_level'] ?></span><?php endif; ?>
      <span class="badge badge--<?= e(status_badge((string) $question['status'])) ?>"><?= e($question['status']) ?></span>
    </div>
  </div>
  <div class="card__body">
    <?php if (($question['image_path'] ?? '') !== ''): ?>
      <img src="<?= e(upload_url($question['image_path'])) ?>" alt="" style="max-width:360px;border-radius:12px;margin-bottom:1rem">
    <?php endif; ?>
    <?php if (($question['audio_path'] ?? '') !== ''): ?>
      <audio controls src="<?= e(upload_url($question['audio_path'])) ?>" style="width:100%;margin-bottom:1rem"></audio>
    <?php endif; ?>

    <h2 style="font-size:1.3rem;margin-bottom:1rem"><?= e($question['question_text']) ?></h2>

    <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
      <div class="option-line <?= $key === $question['correct_option'] ? 'is-correct' : '' ?>">
        <span class="option-key"><?= $key ?></span>
        <span><?= e($question['options'][$key] ?? '') ?>
          <?php if ($key === $question['correct_option']): ?><span class="badge badge--ok">correct</span><?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>

    <?php if (($question['explanation'] ?? '') !== ''): ?>
      <div class="alert alert--info mt-2"><span>i</span><span><?= e($question['explanation']) ?></span></div>
    <?php endif; ?>

    <?php if ((int) $question['times_used'] > 0): ?>
      <div class="mt-2 small muted">
        Used <?= (int) $question['times_used'] ?> time(s):
        <?= (int) $question['times_correct'] ?> correct, <?= (int) $question['times_wrong'] ?> wrong
        (<?= (int) $question['times_used'] > 0 ? round((int) $question['times_correct'] / (int) $question['times_used'] * 100) : 0 ?>% success rate).
      </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
