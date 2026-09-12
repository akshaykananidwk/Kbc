<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Reports']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Reports</h1><p class="page-head__sub">Everything that happened across all games.</p></div>
  <div class="btn-row">
    <a class="btn btn--ghost" href="<?= e(url('/admin/reports/games.csv')) ?>">Games CSV</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/reports/questions.csv')) ?>">Questions CSV</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/reports/prizes.csv')) ?>">Prizes CSV</a>
    <button type="button" class="btn btn--primary" onclick="window.print()">Print</button>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><div class="stat__label">Total games</div><div class="stat__value"><?= (int) ($gameStats['total_games'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat__label">Completed</div><div class="stat__value"><?= (int) ($gameStats['completed_games'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat__label">Total prize distributed</div><div class="stat__value" style="font-size:1.3rem"><?= e(money($gameStats['total_prize'] ?? 0)) ?></div></div>
  <div class="stat"><div class="stat__label">Average prize</div><div class="stat__value" style="font-size:1.3rem"><?= e(money($gameStats['average_prize'] ?? 0)) ?></div></div>
  <div class="stat"><div class="stat__label">Highest prize</div><div class="stat__value" style="font-size:1.3rem"><?= e(money($gameStats['highest_prize'] ?? 0)) ?></div></div>
  <div class="stat"><div class="stat__label">Avg questions / game</div><div class="stat__value"><?= round((float) ($gameStats['average_questions'] ?? 0), 1) ?></div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Games by result</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($byStatus === []): ?><div class="empty"><p>No games yet.</p></div><?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Result</th><th class="num">Games</th><th class="num">Prize money</th></tr></thead>
        <tbody><?php foreach ($byStatus as $row): ?>
          <tr><td><span class="badge badge--<?= e(status_badge((string) $row['status'])) ?>"><?= e(str_replace('_', ' ', (string) $row['status'])) ?></span></td>
            <td class="num"><?= (int) $row['total'] ?></td><td class="num"><?= e(money($row['prize'])) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Performance by question number</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($byLevel === []): ?><div class="empty"><p>No answers recorded yet.</p></div><?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Q#</th><th class="num">Attempts</th><th class="num">Correct</th><th class="num">Success</th><th class="num">Avg time</th></tr></thead>
        <tbody><?php foreach ($byLevel as $row): ?>
          <?php $rate = (int) $row['attempts'] > 0 ? round((int) $row['correct'] / (int) $row['attempts'] * 100) : 0; ?>
          <tr><td><strong><?= (int) $row['level_no'] ?></strong></td>
            <td class="num"><?= (int) $row['attempts'] ?></td>
            <td class="num"><?= (int) $row['correct'] ?></td>
            <td class="num"><span class="badge badge--<?= $rate >= 60 ? 'ok' : ($rate >= 30 ? 'warn' : 'bad') ?>"><?= $rate ?>%</span></td>
            <td class="num"><?= e((string) ($row['avg_seconds'] ?? 0)) ?>s</td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Hardest questions</h2><span class="badge">lowest success rate</span></div>
    <div class="card__body card__body--flush">
      <?php if ($hardest === []): ?><div class="empty"><p>Not enough data yet.</p></div><?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Question</th><th class="num">Used</th><th class="num">Correct</th><th class="num">Success</th></tr></thead>
        <tbody><?php foreach ($hardest as $row): ?>
          <tr><td><?= e(mb_strimwidth((string) $row['question_text'], 0, 70, '…')) ?>
              <div class="small muted"><?= e($row['difficulty']) ?></div></td>
            <td class="num"><?= (int) $row['times_used'] ?></td>
            <td class="num"><?= (int) $row['times_correct'] ?></td>
            <td class="num"><span class="badge badge--<?= (float) $row['success_rate'] >= 60 ? 'ok' : ((float) $row['success_rate'] >= 30 ? 'warn' : 'bad') ?>"><?= e((string) $row['success_rate']) ?>%</span></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Most used questions</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($mostUsed === []): ?><div class="empty"><p>Not enough data yet.</p></div><?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Question</th><th class="num">Used</th><th class="num">✓</th><th class="num">✕</th></tr></thead>
        <tbody><?php foreach ($mostUsed as $row): ?>
          <tr><td><?= e(mb_strimwidth((string) $row['question_text'], 0, 70, '…')) ?></td>
            <td class="num"><?= (int) $row['times_used'] ?></td>
            <td class="num"><?= (int) $row['times_correct'] ?></td>
            <td class="num"><?= (int) $row['times_wrong'] ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Gift distribution</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($giftStats === []): ?><div class="empty"><p>No gifts configured.</p></div><?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Gift</th><th class="num">Value</th><th class="num">Awarded</th><th class="num">Remaining</th></tr></thead>
        <tbody><?php foreach ($giftStats as $row): ?>
          <tr><td><?= e($row['name']) ?></td>
            <td class="num"><?= e(money($row['value_amount'])) ?></td>
            <td class="num"><?= (int) $row['times_awarded'] ?></td>
            <td class="num"><?= (int) $row['quantity_total'] - (int) $row['quantity_used'] ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Participant winnings</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($winnings === []): ?><div class="empty"><p>No games played yet.</p></div><?php else: ?>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Participant</th><th class="num">Games</th><th class="num">Total won</th><th class="num">Best</th></tr></thead>
        <tbody><?php foreach ($winnings as $row): ?>
          <tr><td><?= e($row['name']) ?><?php if (($row['city'] ?? '') !== ''): ?><div class="small muted"><?= e($row['city']) ?></div><?php endif; ?></td>
            <td class="num"><?= (int) $row['games_played'] ?></td>
            <td class="num"><strong><?= e(money($row['total_won'])) ?></strong></td>
            <td class="num"><?= e(money($row['best_prize'])) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">Lifeline usage</h2></div>
  <div class="card__body card__body--flush">
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Lifeline</th><th class="num">Times used</th></tr></thead>
      <tbody><?php foreach ($lifelineUse as $row): ?>
        <tr><td><?= e($row['name']) ?> <span class="mono small muted"><?= e($row['code']) ?></span></td>
          <td class="num"><?= (int) $row['times_used'] ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
</div>
<?php $view->stop(); ?>
