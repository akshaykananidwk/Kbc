<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Import questions']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Import questions</h1><p class="page-head__sub">Bulk add questions from a CSV file.</p></div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/questions')) ?>">← Back</a>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Upload a CSV file</h2></div>
    <form method="post" action="<?= e(url('/admin/questions/import')) ?>" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="field">
          <label class="label" for="csv">CSV file <span class="required">*</span></label>
          <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
          <div class="field__help">Maximum 5 MB. Save as "CSV UTF-8" so Gujarati and Hindi text imports correctly.</div>
        </div>
      </div>
      <div class="card__foot">
        <button type="submit" class="btn btn--primary" data-busy="Importing…">Import questions</button>
        <a class="btn btn--ghost" href="<?= e(url('/admin/questions/export')) ?>">Download current questions as a template</a>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Required format</h2></div>
    <div class="card__body">
      <p class="small">The first row must be a header row. These columns are required:</p>
      <p class="mono small">question, option_a, option_b, option_c, option_d, correct</p>
      <p class="small mt-2">These columns are optional:</p>
      <p class="mono small">category, explanation, difficulty, time_limit, prize_level, lifelines, status, sort_order</p>
      <ul class="small muted">
        <li><strong>correct</strong> must be A, B, C or D.</li>
        <li><strong>difficulty</strong> must be easy, medium, hard or expert (defaults to medium).</li>
        <li><strong>category</strong> must match an existing category name, otherwise the question is left uncategorised.</li>
        <li>Rows with a missing question or an invalid correct answer are skipped and reported.</li>
      </ul>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
