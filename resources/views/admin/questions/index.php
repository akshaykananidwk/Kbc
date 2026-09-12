<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Questions']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Questions</h1>
    <p class="page-head__sub"><?= (int) $pagination['total'] ?> question(s) in the bank.</p>
  </div>
  <div class="btn-row">
    <a class="btn btn--primary" href="<?= e(url('/admin/questions/create')) ?>">+ Add question</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/questions/import')) ?>">Import CSV</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/questions/export')) ?>">Export CSV</a>
  </div>
</div>

<div class="card">
  <div class="card__body">
    <form method="get" action="<?= e(url('/admin/questions')) ?>" class="filters">
      <div>
        <label class="label" for="search">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Question text…">
      </div>
      <div>
        <label class="label" for="category_id">Category</label>
        <select id="category_id" name="category_id" data-auto-submit>
          <option value="">All categories</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int) $category['id'] ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label" for="difficulty">Difficulty</label>
        <select id="difficulty" name="difficulty" data-auto-submit>
          <option value="">Any</option>
          <?php foreach (['easy', 'medium', 'hard', 'expert'] as $level): ?>
            <option value="<?= $level ?>" <?= $filters['difficulty'] === $level ? 'selected' : '' ?>><?= ucfirst($level) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" data-auto-submit>
          <option value="">Any</option>
          <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div>
        <label class="label" for="prize_level">Prize level</label>
        <select id="prize_level" name="prize_level" data-auto-submit>
          <option value="">Any</option>
          <?php for ($i = 1; $i <= max(1, (int) $maxLevel); $i++): ?>
            <option value="<?= $i ?>" <?= (int) $filters['prize_level'] === $i ? 'selected' : '' ?>>Q<?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn btn--primary btn--block">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card__body card__body--flush">
    <?php if ($questions === []): ?>
      <div class="empty">
        <div class="empty__icon">?</div>
        <p>No questions match your filters.</p>
        <a class="btn btn--primary" href="<?= e(url('/admin/questions/create')) ?>">Add the first question</a>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th style="width:46%">Question</th>
            <th>Category</th>
            <th>Difficulty</th>
            <th class="num">Level</th>
            <th class="num">Time</th>
            <th class="num">Used</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($questions as $question): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= e(mb_strimwidth((string) $question['question_text'], 0, 110, '…')) ?></div>
              <div class="small muted">
                Answer: <strong><?= e($question['correct_option']) ?></strong>
                <?php if (($question['options'][$question['correct_option']] ?? '') !== ''): ?>
                  — <?= e(mb_strimwidth((string) $question['options'][$question['correct_option']], 0, 50, '…')) ?>
                <?php endif; ?>
                <?php if (($question['image_path'] ?? '') !== ''): ?><span class="badge badge--info">image</span><?php endif; ?>
                <?php if (($question['audio_path'] ?? '') !== ''): ?><span class="badge badge--info">audio</span><?php endif; ?>
              </div>
            </td>
            <td class="small"><?= e($question['category_name'] ?? '—') ?></td>
            <td><span class="badge"><?= e($question['difficulty']) ?></span></td>
            <td class="num"><?= $question['prize_level'] !== null ? 'Q' . (int) $question['prize_level'] : '—' ?></td>
            <td class="num"><?= (int) $question['time_limit'] ?>s</td>
            <td class="num">
              <?= (int) $question['times_used'] ?>
              <?php if ((int) $question['times_used'] > 0): ?>
                <div class="small muted"><?= (int) $question['times_correct'] ?>✓ / <?= (int) $question['times_wrong'] ?>✕</div>
              <?php endif; ?>
            </td>
            <td><span class="badge badge--<?= e(status_badge((string) $question['status'])) ?>"><?= e($question['status']) ?></span></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/questions/' . (int) $question['id'])) ?>">Preview</a>
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/questions/' . (int) $question['id'] . '/edit')) ?>">Edit</a>
              <form method="post" action="<?= e(url('/admin/questions/' . (int) $question['id'] . '/toggle')) ?>" style="display:inline">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button class="btn btn--ghost btn--sm" type="submit"><?= (string) $question['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
              </form>
              <form method="post" action="<?= e(url('/admin/questions/' . (int) $question['id'] . '/duplicate')) ?>" style="display:inline">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button class="btn btn--ghost btn--sm" type="submit">Copy</button>
              </form>
              <form method="post" action="<?= e(url('/admin/questions/' . (int) $question['id'] . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this question? This cannot be undone.">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button class="btn btn--danger btn--sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $view->include('partials.pagination', ['pagination' => $pagination, 'baseUrl' => '/admin/questions']) ?>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
