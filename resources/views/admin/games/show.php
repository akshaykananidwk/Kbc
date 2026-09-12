<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Game ' . $game['game_code']]);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Game <?= e($game['game_code']) ?></h1>
    <p class="page-head__sub">
      <?= e($game['participant_name'] ?? 'Unknown participant') ?>
      &middot; <?= e(datetime_label((string) $game['created_at'])) ?>
      &middot; operator: <?= e($game['operator_name'] ?? '—') ?>
    </p>
  </div>
  <div class="btn-row">
    <?php if (in_array((string) $game['status'], ['completed','wrong_answer','time_up','quit'], true) && (int) ($game['is_rehearsal'] ?? 0) === 0): ?>
      <a class="btn btn--gold" href="<?= e(url('/admin/certificates/' . (int) $game['id'])) ?>" target="_blank" rel="noopener">🏅 Certificate</a>
    <?php endif; ?>
    <a class="btn btn--ghost" href="<?= e(url('/admin/games/' . (int) $game['id'] . '/export')) ?>">Export CSV</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/games')) ?>">← Back</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><div class="stat__label">Result</div>
    <div class="stat__value" style="font-size:1.15rem"><span class="badge badge--<?= e(status_badge((string) $game['status'])) ?>"><?= e(str_replace('_', ' ', (string) $game['status'])) ?></span></div></div>
  <div class="stat"><div class="stat__label">Final prize</div><div class="stat__value" style="font-size:1.35rem"><?= e(money($game['final_prize'])) ?></div></div>
  <div class="stat"><div class="stat__label">Questions</div><div class="stat__value"><?= (int) $game['questions_correct'] ?>/<?= (int) $game['questions_attempted'] ?></div><div class="stat__meta">correct / attempted</div></div>
  <div class="stat"><div class="stat__label">Guaranteed</div><div class="stat__value" style="font-size:1.2rem"><?= e(money($game['guaranteed_amount'])) ?></div></div>
  <div class="stat"><div class="stat__label">Duration</div><div class="stat__value" style="font-size:1.2rem">
    <?= e(duration_label($game['started_at'] && $game['ended_at'] ? strtotime((string) $game['ended_at']) - strtotime((string) $game['started_at']) : 0)) ?></div></div>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">Every question and answer</h2></div>
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Q#</th><th style="width:38%">Question</th><th>Selected</th><th>Correct</th><th>Result</th><th class="num">Time</th><th class="num">Prize</th><th>Gift</th></tr></thead>
        <tbody>
        <?php foreach ($questions as $row): ?>
          <tr>
            <td><strong><?= (int) $row['level_no'] ?></strong></td>
            <td>
              <?= e(mb_strimwidth((string) $row['question_text'], 0, 90, '…')) ?>
              <div class="small muted"><?= e($row['category_name'] ?? '') ?> &middot; <?= e($row['difficulty']) ?></div>
            </td>
            <td><strong><?= e($row['selected_option'] ?? '—') ?></strong>
              <?php if ((int) ($row['was_overridden'] ?? 0) === 1): ?><span class="badge badge--warn">overridden</span><?php endif; ?>
            </td>
            <td><strong><?= e($row['correct_option']) ?></strong></td>
            <td>
              <?php $result = (string) ($row['result'] ?? ''); ?>
              <?php if ($result === 'correct'): ?><span class="badge badge--ok">correct</span>
              <?php elseif ($result === 'wrong'): ?><span class="badge badge--bad">wrong</span>
              <?php elseif ($result === 'timeout'): ?><span class="badge badge--warn">time up</span>
              <?php elseif ($result === ''): ?><span class="badge badge--muted">not answered</span>
              <?php else: ?><span class="badge"><?= e($result) ?></span><?php endif; ?>
            </td>
            <td class="num small"><?= $row['time_taken_ms'] !== null ? round((int) $row['time_taken_ms'] / 1000, 1) . 's' : '—' ?></td>
            <td class="num"><?= e(money($row['prize_awarded'] ?? 0)) ?></td>
            <td class="small"><?= e($row['gift_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Lifelines used (<?= count($lifelines) ?>)</h2></div>
    <div class="card__body">
      <?php if ($lifelines === []): ?><p class="muted mb-0">No lifelines were used.</p><?php else: ?>
        <ul class="check-list">
        <?php foreach ($lifelines as $lifeline): ?>
          <?php $payload = json_decode((string) $lifeline['payload'], true) ?: []; ?>
          <li>
            <span class="check-list__state">♥</span>
            <span class="check-list__label"><?= e($lifeline['lifeline_name']) ?>
              <small class="muted">at question <?= (int) $lifeline['level_no'] ?></small></span>
            <span class="check-list__value">
              <?php if (isset($payload['removed'])): ?>removed <?= e(implode(', ', (array) $payload['removed'])) ?>
              <?php elseif (isset($payload['percentages'])): ?>
                <?php foreach ((array) $payload['percentages'] as $key => $pct): ?><?= e($key) ?>:<?= (int) $pct ?>% <?php endforeach; ?>
              <?php elseif (isset($payload['suggested_option'])): ?>suggested <?= e($payload['suggested_option']) ?> (<?= (int) ($payload['confidence'] ?? 0) ?>%)
              <?php else: ?>—<?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Gifts won (<?= count($gifts) ?>)</h2></div>
    <div class="card__body">
      <?php if ($gifts === []): ?><p class="muted mb-0">No gifts were won.</p><?php else: ?>
        <ul class="check-list">
        <?php foreach ($gifts as $gift): ?>
          <li>
            <span class="check-list__state">✦</span>
            <span class="check-list__label"><?= e($gift['name']) ?> <small class="muted">at question <?= (int) $gift['level_no'] ?></small></span>
            <span class="check-list__value"><?= e(money($gift['value_amount'])) ?></span>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <h2 class="card__title">Event timeline</h2>
    <button type="button" class="btn btn--ghost btn--sm" data-toggle-target="#eventLog" aria-expanded="false">Show / hide</button>
  </div>
  <div class="card__body card__body--flush" id="eventLog" hidden>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>When</th><th>Event</th><th>State</th><th>Q#</th><th>Details</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
          <tr>
            <td class="small nowrap"><?= e(datetime_label((string) $event['created_at'], 'H:i:s')) ?></td>
            <td class="mono small"><?= e($event['event_type']) ?></td>
            <td class="small"><?= e($event['state'] ?? '—') ?></td>
            <td class="num"><?= $event['level_no'] !== null ? (int) $event['level_no'] : '—' ?></td>
            <td class="small mono"><?= e(mb_strimwidth((string) ($event['payload'] ?? '—'), 0, 70, '…')) ?></td>
            <td class="small"><?= e($event['user_name'] ?? 'system') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
