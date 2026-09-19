<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Replace the question bank']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>પ્રશ્નબેંક બદલો</h1>
    <p class="page-head__sub">Replace every question with a fresh bank before a new event.</p>
  </div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/questions')) ?>">← Back to questions</a>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">What this does</h2></div>
  <div class="card__body">
    <div class="alert alert--warning">
      <span>!</span>
      <span>
        This removes <strong><?= (int) $questionCount ?></strong> question(s) and the
        <strong><?= (int) $gameCount ?></strong> game(s) that used them — scores, answers and
        certificates from those games go with them. Participants, prizes, gifts, sponsors,
        settings and user accounts are <strong>not</strong> touched.
        <br>
        A full database backup is taken automatically before anything is deleted, so this
        can be undone from <a href="<?= e(url('/admin/backups')) ?>">Backups</a>.
      </span>
    </div>

    <form method="post" action="<?= e(url('/admin/questions/reset')) ?>"
          data-confirm="બધા પ્રશ્ન કાઢીને નવા નાખવા છે?">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

      <div class="field mb-2">
        <label class="label" for="bank">Which bank to load</label>
        <select id="bank" name="bank">
          <option value="senior">સિનિયર બેંક — 200 questions aimed at the senior half</option>
          <option value="open">ખુલ્લી બેંક — 200 questions that suit every age</option>
          <option value="both">બંને — all 400, nothing repeated between them</option>
        </select>
        <small class="field__help">
          Both banks are five categories of 40. No question appears in both, so loading
          both gives 400 questions with no repeat.
        </small>
      </div>

      <div class="field mb-2">
        <label class="label" for="confirm">Type <code>DELETE</code> to confirm</label>
        <input type="text" id="confirm" name="confirm" autocomplete="off" required
               placeholder="DELETE" style="max-width:220px">
      </div>

      <button type="submit" class="btn btn--danger btn--lg">બધા પ્રશ્ન બદલો</button>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
