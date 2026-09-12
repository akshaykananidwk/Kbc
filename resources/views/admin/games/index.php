<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Game history']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Game history</h1><p class="page-head__sub"><?= (int) $pagination['total'] ?> game(s) recorded.</p></div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/reports/games.csv')) ?>">Export all as CSV</a>
</div>

<div class="stat-grid">
  <div class="stat"><div class="stat__label">Total games</div><div class="stat__value"><?= (int) ($stats['total_games'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat__label">Completed</div><div class="stat__value"><?= (int) ($stats['completed_games'] ?? 0) ?></div></div>
  <div class="stat"><div class="stat__label">Total awarded</div><div class="stat__value" style="font-size:1.3rem"><?= e(money($stats['total_prize'] ?? 0)) ?></div></div>
  <div class="stat"><div class="stat__label">Highest prize</div><div class="stat__value" style="font-size:1.3rem"><?= e(money($stats['highest_prize'] ?? 0)) ?></div></div>
  <div class="stat"><div class="stat__label">Avg questions</div><div class="stat__value"><?= round((float) ($stats['average_questions'] ?? 0), 1) ?></div></div>
</div>

<div class="card">
  <div class="card__body">
    <form method="get" action="<?= e(url('/admin/games')) ?>" class="filters">
      <div><label class="label" for="search">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Participant or game code"></div>
      <div><label class="label" for="status">Status</label>
        <select id="status" name="status" data-auto-submit>
          <option value="">Any</option>
          <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="label" for="from">From</label><input type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
      <div><label class="label" for="to">To</label><input type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
      <div><button type="submit" class="btn btn--primary btn--block">Filter</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card__body card__body--flush">
    <?php if ($games === []): ?>
      <div class="empty"><div class="empty__icon">☰</div><p>No games match your filters.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr>
          <th>Game</th><th>Participant</th><th>Date</th><th>Status</th>
          <th class="num">Questions</th><th class="num">Lifelines</th><th class="num">Gifts</th>
          <th class="num">Prize</th><th class="num">Duration</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($games as $game): ?>
          <tr>
            <td class="mono small"><?= e($game['game_code']) ?></td>
            <td>
              <strong><?= e($game['participant_name'] ?? '—') ?></strong>
              <?php if (($game['participant_city'] ?? '') !== ''): ?><div class="small muted"><?= e($game['participant_city']) ?></div><?php endif; ?>
            </td>
            <td class="small nowrap"><?= e(datetime_label((string) $game['created_at'])) ?></td>
            <td><span class="badge badge--<?= e(status_badge((string) $game['status'])) ?>"><?= e(str_replace('_', ' ', (string) $game['status'])) ?></span></td>
            <td class="num"><?= (int) $game['questions_correct'] ?>/<?= (int) $game['questions_attempted'] ?></td>
            <td class="num"><?= (int) $game['lifelines_used'] ?></td>
            <td class="num"><?= (int) $game['gifts_won'] ?></td>
            <td class="num"><strong><?= e(money($game['final_prize'])) ?></strong></td>
            <td class="num small"><?= e(duration_label((int) ($game['duration_seconds'] ?? 0))) ?></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/games/' . (int) $game['id'])) ?>">Report</a>
              <?php if (($currentUser['role_slug'] ?? '') === 'admin'): ?>
              <form method="post" action="<?= e(url('/admin/games/' . (int) $game['id'] . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this game record permanently?">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $view->include('partials.pagination', ['pagination' => $pagination, 'baseUrl' => '/admin/games']) ?>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
