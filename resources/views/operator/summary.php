<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Game report']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Game report &middot; <?= e($game['game_code']) ?></h1>
    <p class="page-head__sub"><?= e($game['participant_name'] ?? '—') ?> &middot; <?= e(datetime_label((string) $game['created_at'])) ?></p>
  </div>
  <div class="btn-row">
    <a class="btn btn--gold" href="<?= e(url('/operator/setup')) ?>">Start the next game</a>
    <?php if ((int) ($game['is_rehearsal'] ?? 0) === 0): ?>
      <a class="btn btn--primary" href="<?= e(url('/admin/certificates/' . (int) $game['id'])) ?>" target="_blank" rel="noopener">🏅 Print certificate</a>
    <?php endif; ?>
    <a class="btn btn--ghost" href="<?= e(url('/admin/games/' . (int) $game['id'])) ?>">Full report</a>
    <button type="button" class="btn btn--ghost" onclick="window.print()">Print</button>
  </div>
</div>

<div class="card">
  <div class="card__body text-center" style="padding:2rem 1rem">
    <?php if ((string) $game['status'] === 'completed'): ?>
      <div style="font-size:3rem">🎉</div>
      <h2 style="font-size:1.5rem">Congratulations, <?= e($game['participant_name'] ?? 'champion') ?>!</h2>
    <?php elseif (in_array((string) $game['status'], ['wrong_answer', 'time_up'], true)): ?>
      <div style="font-size:3rem">🙏</div>
      <h2 style="font-size:1.5rem">Well played, <?= e($game['participant_name'] ?? 'friend') ?>!</h2>
    <?php else: ?>
      <div style="font-size:3rem">🎬</div>
      <h2 style="font-size:1.5rem">Game finished</h2>
    <?php endif; ?>
    <p class="muted">Final winnings</p>
    <div style="font-size:2.6rem;font-weight:900;color:var(--brand-primary)"><?= e(money($game['final_prize'])) ?></div>
    <?php if ($gifts !== []): ?>
      <p class="mt-2"><strong>Gifts won:</strong>
        <?= e(implode(', ', array_map(static fn ($g) => $g['name'], $gifts))) ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><div class="stat__label">Questions reached</div><div class="stat__value"><?= (int) $game['questions_attempted'] ?></div></div>
  <div class="stat"><div class="stat__label">Correct answers</div><div class="stat__value"><?= (int) $game['questions_correct'] ?></div></div>
  <div class="stat"><div class="stat__label">Lifelines used</div><div class="stat__value"><?= count($lifelines) ?></div></div>
  <div class="stat"><div class="stat__label">Guaranteed level</div><div class="stat__value" style="font-size:1.2rem"><?= e(money($game['guaranteed_amount'])) ?></div></div>
  <div class="stat"><div class="stat__label">Result</div><div class="stat__value" style="font-size:1.05rem">
    <span class="badge badge--<?= e(status_badge((string) $game['status'])) ?>"><?= e(str_replace('_', ' ', (string) $game['status'])) ?></span></div></div>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">Question by question</h2></div>
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Q#</th><th>Question</th><th>Answered</th><th>Correct</th><th>Result</th><th class="num">Prize</th><th>Gift</th></tr></thead>
        <tbody>
        <?php foreach ($questions as $row): ?>
          <tr>
            <td><strong><?= (int) $row['level_no'] ?></strong></td>
            <td><?= e(mb_strimwidth((string) $row['question_text'], 0, 80, '…')) ?></td>
            <td><?= e($row['selected_option'] ?? '—') ?></td>
            <td><?= e($row['correct_option']) ?></td>
            <td>
              <?php $result = (string) ($row['result'] ?? ''); ?>
              <?php if ($result === 'correct'): ?><span class="badge badge--ok">correct</span>
              <?php elseif ($result === 'wrong'): ?><span class="badge badge--bad">wrong</span>
              <?php elseif ($result === 'timeout'): ?><span class="badge badge--warn">time up</span>
              <?php else: ?><span class="badge badge--muted">—</span><?php endif; ?>
            </td>
            <td class="num"><?= e(money($row['prize_awarded'] ?? 0)) ?></td>
            <td class="small"><?= e($row['gift_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
