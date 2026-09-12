<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Prize ladder']);
$view->start('content');
$errors = $errors ?? [];
?>
<div class="page-head">
  <div>
    <h1>Prize ladder</h1>
    <p class="page-head__sub">
      <?= count($ladder) ?> level(s) &middot; total <?= e(money($totalValue)) ?> &middot;
      <?= count($guaranteed) ?> guaranteed level(s). Nothing here is hard-coded — add as many levels as you like.
    </p>
  </div>
</div>

<?php if (isset($errors['level_no'])): ?>
  <div class="alert alert--error"><span>✕</span><span><?= e($errors['level_no']) ?></span></div>
<?php endif; ?>

<div class="card">
  <div class="card__head">
    <h2 class="card__title">Current ladder</h2>
    <span class="badge badge--gold">Top prize <?= e(money($ladder === [] ? 0 : max(array_column($ladder, 'amount')))) ?></span>
  </div>
  <div class="card__body card__body--flush">
    <?php if ($ladder === []): ?>
      <div class="empty"><div class="empty__icon">₹</div><p>No prize levels yet. Add the first one below.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Q#</th><th>Amount</th><th>Label</th><th>Guaranteed</th><th>Gift</th><th>Time</th><th>Difficulty</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($ladder as $level): ?>
          <tr class="<?= (int) $level['is_guaranteed'] === 1 ? 'is-guaranteed' : '' ?>">
            <form method="post" action="<?= e(url('/admin/prizes/' . (int) $level['id'])) ?>">
              <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
              <td style="width:70px"><input type="number" name="level_no" value="<?= (int) $level['level_no'] ?>" min="1" required style="width:66px"></td>
              <td style="width:150px"><input type="number" name="amount" value="<?= e((string) (float) $level['amount']) ?>" min="0" step="0.01" required style="width:140px"></td>
              <td><input type="text" name="label" value="<?= e($level['label'] ?? '') ?>"></td>
              <td style="width:96px" class="text-center">
                <input type="checkbox" name="is_guaranteed" value="1" <?= (int) $level['is_guaranteed'] === 1 ? 'checked' : '' ?>
                       style="width:20px;height:20px;accent-color:var(--brand-primary)">
              </td>
              <td style="width:190px">
                <select name="gift_id">
                  <option value="">No gift</option>
                  <?php foreach ($gifts as $gift): ?>
                    <option value="<?= (int) $gift['id'] ?>" <?= (int) $level['gift_id'] === (int) $gift['id'] ? 'selected' : '' ?>>
                      <?= e($gift['name']) ?> (<?= (int) $gift['quantity_total'] - (int) $gift['quantity_used'] ?> left)
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="width:92px"><input type="number" name="time_limit" value="<?= (int) $level['time_limit'] ?>" min="5" max="600" required style="width:82px"></td>
              <td style="width:130px">
                <select name="difficulty">
                  <?php foreach (['any', 'easy', 'medium', 'hard', 'expert'] as $difficulty): ?>
                    <option value="<?= $difficulty ?>" <?= $level['difficulty'] === $difficulty ? 'selected' : '' ?>><?= ucfirst($difficulty) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="category_id" value="<?= (int) ($level['category_id'] ?? 0) ?>">
              </td>
              <td style="width:120px">
                <select name="status">
                  <option value="active" <?= $level['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $level['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </td>
              <td class="actions">
                <button type="submit" class="btn btn--ghost btn--sm">Save</button>
            </form>
                <form method="post" action="<?= e(url('/admin/prizes/' . (int) $level['id'] . '/delete')) ?>" style="display:inline"
                      data-confirm="Delete question level <?= (int) $level['level_no'] ?>? The ladder will be renumbered.">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                </form>
              </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Add a level</h2></div>
    <form method="post" action="<?= e(url('/admin/prizes')) ?>">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="form-grid">
          <div class="field field--4">
            <label class="label" for="new_level">Question number <span class="required">*</span></label>
            <input type="number" id="new_level" name="level_no" value="<?= (int) $nextLevel ?>" min="1" required>
          </div>
          <div class="field field--8">
            <label class="label" for="new_amount">Prize amount <span class="required">*</span></label>
            <input type="number" id="new_amount" name="amount" min="0" step="0.01" required placeholder="e.g. 500000">
          </div>
          <div class="field field--6">
            <label class="label" for="new_time">Time limit (seconds) <span class="required">*</span></label>
            <input type="number" id="new_time" name="time_limit" value="<?= (int) $defaultTime ?>" min="5" max="600" required>
          </div>
          <div class="field field--6">
            <label class="label" for="new_gift">Gift for this level</label>
            <select id="new_gift" name="gift_id">
              <option value="">No gift</option>
              <?php foreach ($gifts as $gift): ?>
                <option value="<?= (int) $gift['id'] ?>"><?= e($gift['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field field--6">
            <label class="label" for="new_difficulty">Preferred difficulty</label>
            <select id="new_difficulty" name="difficulty">
              <?php foreach (['any', 'easy', 'medium', 'hard', 'expert'] as $difficulty): ?>
                <option value="<?= $difficulty ?>"><?= ucfirst($difficulty) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field field--6">
            <label class="label" for="new_category">Restrict to category</label>
            <select id="new_category" name="category_id">
              <option value="">Any category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="check">
              <input type="checkbox" name="is_guaranteed" value="1">
              <span>Guaranteed (safe) level
                <small>If the participant answers a later question wrongly they still take home this amount.</small>
              </span>
            </label>
          </div>
          <input type="hidden" name="status" value="active">
        </div>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Add level</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">How guaranteed levels work</h2></div>
    <div class="card__body">
      <p class="small">
        A guaranteed (safe) level locks in the prize money once the participant answers that
        question correctly. If they get a later question wrong, they leave with the highest
        guaranteed amount they have already banked.
      </p>
      <?php if ($guaranteed !== []): ?>
        <h3 class="mt-2">Current guaranteed levels</h3>
        <ul class="ladder-mini">
          <?php foreach ($guaranteed as $level): ?>
            <li class="is-guaranteed">
              <span>Question <?= (int) $level['level_no'] ?></span>
              <strong><?= e(money($level['amount'])) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="small muted mt-2">
          Example: a participant who reaches question <?= (int) ($ladder[count($ladder) - 1]['level_no'] ?? 10) ?>
          and answers wrongly takes home
          <strong><?= e(money(max(array_column($guaranteed, 'amount')))) ?></strong>.
        </p>
      <?php else: ?>
        <div class="alert alert--warning"><span>!</span><span>No guaranteed levels are set, so a wrong answer means the participant leaves with nothing.</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
