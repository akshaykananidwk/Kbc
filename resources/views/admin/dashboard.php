<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Dashboard']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="page-head__sub">Everything you need before the show starts.</p>
  </div>
  <div class="btn-row">
    <a class="btn btn--gold" href="<?= e(url('/operator/setup')) ?>">Start a new game</a>
    <a class="btn btn--ghost" href="<?= e(url('/display')) ?>" target="_blank" rel="noopener">Open display</a>
  </div>
</div>

<?php if (!empty($activeGame)): ?>
<div class="alert alert--warning">
  <span aria-hidden="true">▶</span>
  <span>
    Game <strong><?= e($activeGame['game_code']) ?></strong> is currently
    <strong><?= e($activeGame['status']) ?></strong>
    for <strong><?= e($activeGame['participant_name'] ?? 'a participant') ?></strong>.
    <a href="<?= e(url('/operator')) ?>">Go to the operator screen</a>.
  </span>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat">
    <div class="stat__label">Questions</div>
    <div class="stat__value"><?= (int) $cards['total_questions'] ?></div>
    <div class="stat__meta"><?= (int) $cards['active_questions'] ?> active</div>
  </div>
  <div class="stat">
    <div class="stat__label">Games</div>
    <div class="stat__value"><?= (int) $cards['total_games'] ?></div>
    <div class="stat__meta"><?= (int) $cards['completed_games'] ?> completed</div>
  </div>
  <div class="stat">
    <div class="stat__label">Participants</div>
    <div class="stat__value"><?= (int) $cards['total_participants'] ?></div>
    <div class="stat__meta">registered</div>
  </div>
  <div class="stat">
    <div class="stat__label">Prize money awarded</div>
    <div class="stat__value" style="font-size:1.35rem"><?= e(money($cards['total_prize'])) ?></div>
    <div class="stat__meta">across all games</div>
  </div>
  <div class="stat">
    <div class="stat__label">Gifts</div>
    <div class="stat__value"><?= (int) $cards['total_gifts'] ?></div>
    <div class="stat__meta"><?= (int) $cards['gifts_awarded'] ?> awarded &middot; <?= e(money($cards['gift_value'])) ?> value</div>
  </div>
  <div class="stat">
    <div class="stat__label">Prize ladder</div>
    <div class="stat__value"><?= (int) $cards['ladder_levels'] ?></div>
    <div class="stat__meta">top prize <?= e(money($cards['ladder_top'])) ?></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Show readiness</h2></div>
    <div class="card__body">
      <ul class="check-list">
        <?php foreach ($readiness as $item): ?>
        <li class="<?= $item['ok'] ? '' : 'is-warn' ?>">
          <span class="check-list__state"><?= $item['ok'] ? '✓' : '!' ?></span>
          <span class="check-list__label"><?= e($item['label']) ?></span>
          <span class="check-list__value"><?= e($item['hint']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">System status</h2></div>
    <div class="card__body">
      <ul class="check-list">
        <li><span class="check-list__state">◆</span><span class="check-list__label">PHP version</span><span class="check-list__value"><?= e($system['php_version']) ?></span></li>
        <li><span class="check-list__state">◆</span><span class="check-list__label">Database</span><span class="check-list__value"><?= e($system['database']) ?></span></li>
        <li><span class="check-list__state">◆</span><span class="check-list__label">App version</span><span class="check-list__value">v<?= e($system['app_version']) ?></span></li>
        <li><span class="check-list__state">◆</span><span class="check-list__label">Timezone</span><span class="check-list__value"><?= e($system['timezone']) ?></span></li>
        <li class="<?= $system['debug_mode'] ? 'is-warn' : '' ?>">
          <span class="check-list__state"><?= $system['debug_mode'] ? '!' : '✓' ?></span>
          <span class="check-list__label">Debug mode</span>
          <span class="check-list__value"><?= $system['debug_mode'] ? 'ON — turn off for live events' : 'off' ?></span>
        </li>
        <li class="<?= $system['storage_writable'] ? '' : 'is-bad' ?>">
          <span class="check-list__state"><?= $system['storage_writable'] ? '✓' : '✕' ?></span>
          <span class="check-list__label">Storage writable</span>
          <span class="check-list__value"><?= $system['storage_writable'] ? 'yes' : 'no' ?></span>
        </li>
        <li class="<?= $system['install_locked'] ? '' : 'is-bad' ?>">
          <span class="check-list__state"><?= $system['install_locked'] ? '✓' : '✕' ?></span>
          <span class="check-list__label">Installer locked</span>
          <span class="check-list__value"><?= $system['install_locked'] ? 'yes' : 'NOT LOCKED' ?></span>
        </li>
        <li>
          <span class="check-list__state"><?= $system['last_backup'] ? '✓' : '!' ?></span>
          <span class="check-list__label">Last backup</span>
          <span class="check-list__value"><?= e($system['last_backup'] ? datetime_label((string) $system['last_backup']) : 'never') ?></span>
        </li>
      </ul>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title">Recent games</h2>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/games')) ?>">View all</a>
  </div>
  <div class="card__body card__body--flush">
    <?php if ($recentGames === []): ?>
      <div class="empty">
        <div class="empty__icon">▦</div>
        <p>No games have been played yet.</p>
        <a class="btn btn--primary" href="<?= e(url('/operator/setup')) ?>">Start the first game</a>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Game</th><th>Participant</th><th>Status</th>
            <th class="num">Questions</th><th class="num">Prize</th><th>When</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recentGames as $game): ?>
          <tr>
            <td class="mono"><?= e($game['game_code']) ?></td>
            <td><?= e($game['participant_name'] ?? '—') ?></td>
            <td><span class="badge badge--<?= e(status_badge((string) $game['status'])) ?>"><?= e(str_replace('_', ' ', (string) $game['status'])) ?></span></td>
            <td class="num"><?= (int) $game['questions_attempted'] ?></td>
            <td class="num"><?= e(money($game['final_prize'])) ?></td>
            <td class="nowrap small muted"><?= e(datetime_label((string) $game['created_at'])) ?></td>
            <td class="actions"><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/games/' . (int) $game['id'])) ?>">Report</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
