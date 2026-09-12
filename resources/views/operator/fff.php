<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Fastest Finger First']);
$view->start('content');
?>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">

<div class="page-head">
  <div>
    <h1>Fastest Finger First</h1>
    <p class="page-head__sub">Decide who takes the hot seat. Contenders answer on their own phones and the server does the timing.</p>
  </div>
  <a class="btn btn--ghost" href="<?= e(url('/operator')) ?>">← Operator screen</a>
</div>

<?php if (!$enabled): ?>
  <div class="alert alert--warning"><span>!</span><span>
    Fastest Finger First is switched off in Settings → Game.
  </span></div>
<?php endif; ?>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">1. Set up the round</h2></div>
    <div class="card__body">
      <div class="field mb-2">
        <label class="label" for="fffQuestion">Question <span class="required">*</span></label>
        <input type="text" id="fffQuestion" placeholder="Put these four festivals in the order they fall in the year">
      </div>

      <div class="form-grid mb-2">
        <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
          <div class="field field--6">
            <label class="label" for="fffItem<?= $key ?>">Item <?= $key ?> <span class="required">*</span></label>
            <input type="text" id="fffItem<?= $key ?>">
          </div>
        <?php endforeach; ?>
      </div>

      <div class="field mb-2">
        <label class="label">Correct order <span class="required">*</span></label>
        <p class="field__help mb-1">Tap the items in the right order.</p>
        <div class="btn-row" id="orderPicker">
          <?php foreach (['A', 'B', 'C', 'D'] as $key): ?>
            <button type="button" class="btn btn--ghost" data-order-key="<?= $key ?>"><?= $key ?></button>
          <?php endforeach; ?>
          <button type="button" class="btn btn--ghost btn--sm" id="orderClear">Clear</button>
        </div>
        <div class="mono mt-1" id="orderPreview" style="font-size:1.3rem;letter-spacing:.3em">— — — —</div>
      </div>

      <div class="field mb-2">
        <label class="label" for="fffTime">Time limit (seconds)</label>
        <input type="number" id="fffTime" min="5" max="300" value="<?= (int) $defaultTime ?>">
      </div>

      <div class="field mb-2">
        <label class="label">Contenders <span class="required">*</span> <span class="label__hint">(at least two)</span></label>
        <div style="max-height:220px;overflow:auto;border:1px solid var(--ink-100);border-radius:9px;padding:.5rem">
          <?php foreach ($participants as $participant): ?>
            <label class="check mb-1">
              <input type="checkbox" class="fff-contender" value="<?= (int) $participant['id'] ?>">
              <span><?= e($participant['name']) ?>
                <small><?= e(trim(($participant['registration_no'] ?? '') . ' ' . ($participant['city'] ?? ''))) ?></small>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="button" class="btn btn--primary btn--block btn--lg" id="fffCreate">Create the round</button>
    </div>
  </div>

  <div class="card">
    <div class="card__head">
      <h2 class="card__title">2. Run it</h2>
      <span class="badge" id="fffStatus">no round</span>
    </div>
    <div class="card__body" id="fffRunBox">
      <p class="muted" id="fffEmpty">Create a round on the left to begin.</p>

      <div id="fffLive" hidden>
        <div class="alert alert--info">
          <span>📱</span>
          <span>
            Contenders open <strong id="fffUrl"></strong> on their phones and enter their own code.
            <button type="button" class="btn btn--ghost btn--sm" id="fffCopyUrl" data-copy="">Copy link</button>
          </span>
        </div>

        <div class="flex" style="gap:1rem;align-items:flex-start">
          <img id="fffQr" alt="" style="width:132px;height:132px;background:#fff;border-radius:10px;padding:5px;border:1px solid var(--ink-100)">
          <div style="flex:1;min-width:200px">
            <div class="stat" style="margin:0">
              <div class="stat__label">Time remaining</div>
              <div class="stat__value" id="fffClock">—</div>
              <div class="stat__meta"><span id="fffAnswered">0</span> of <span id="fffTotal">0</span> answered</div>
            </div>
          </div>
        </div>

        <div class="table-wrap mt-2">
          <table class="data">
            <thead><tr><th>Contender</th><th>Code</th><th>Answered</th><th class="num">Time</th><th>Result</th></tr></thead>
            <tbody id="fffTable"></tbody>
          </table>
        </div>

        <div class="btn-row mt-2">
          <button type="button" class="btn btn--ok btn--lg" id="fffStart">Start the clock</button>
          <button type="button" class="btn btn--gold btn--lg" id="fffClose">Close &amp; reveal</button>
          <button type="button" class="btn btn--danger" id="fffCancel">Cancel</button>
        </div>

        <div id="fffResult" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card__head"><h2 class="card__title">Recent rounds</h2></div>
  <div class="card__body card__body--flush">
    <?php if ($history === []): ?>
      <div class="empty"><div class="empty__icon">⚡</div><p>No rounds yet.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>Question</th><th class="num">Contenders</th><th>Winner</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= (int) $row['id'] ?></td>
            <td><?= e(mb_strimwidth(explode("\n", (string) $row['question_text'])[0], 0, 60, '…')) ?></td>
            <td class="num"><?= (int) $row['contenders'] ?></td>
            <td><?= e($row['winner_name'] ?? '—') ?></td>
            <td><span class="badge badge--<?= $row['status'] === 'closed' ? 'ok' : 'warn' ?>"><?= e($row['status']) ?></span></td>
            <td class="small nowrap"><?= e(datetime_label((string) $row['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>

<?php $view->start('scripts'); ?>
<script src="<?= e(asset('assets/js/fff-operator.js')) ?>"></script>
<?php $view->stop(); ?>
