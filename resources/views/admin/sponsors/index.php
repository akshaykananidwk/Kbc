<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Sponsors']);
$view->start('content');
$tiers = ['title' => 'Title sponsor', 'gold' => 'Gold', 'silver' => 'Silver', 'supporter' => 'Supporter'];
?>
<div class="page-head">
  <div>
    <h1>Sponsors</h1>
    <p class="page-head__sub">Shown on the display screen between games. <?= count($sponsors) ?> added.</p>
  </div>
  <?php if (!$showing): ?>
    <span class="badge badge--warn">Sponsor screen is switched off in Settings → Display</span>
  <?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Add a sponsor</h2></div>
    <form method="post" action="<?= e(url('/admin/sponsors')) ?>" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="form-grid">
          <div class="field field--8">
            <label class="label" for="name">Sponsor name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required>
          </div>
          <div class="field field--4">
            <label class="label" for="tier">Tier</label>
            <select id="tier" name="tier" required>
              <?php foreach ($tiers as $key => $label): ?>
                <option value="<?= $key ?>" <?= $key === 'supporter' ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="label" for="tagline">Tagline <span class="label__hint">(optional, shown under the name)</span></label>
            <input type="text" id="tagline" name="tagline">
          </div>
          <div class="field field--6">
            <label class="label" for="website">Website</label>
            <input type="text" id="website" name="website" placeholder="example.com">
          </div>
          <div class="field field--3">
            <label class="label" for="sort_order">Sort order</label>
            <input type="number" id="sort_order" name="sort_order" value="0" min="0">
          </div>
          <div class="field field--3">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="field">
            <label class="label" for="logo">Logo</label>
            <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg" data-preview="#logoPreview">
            <img id="logoPreview" class="thumb thumb--lg mt-1" style="display:none" alt="">
          </div>
        </div>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Add sponsor</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title">Current sponsors</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($sponsors === []): ?>
        <div class="empty"><div class="empty__icon">★</div><p>No sponsors yet.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th></th><th>Name</th><th>Tier</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($sponsors as $sponsor): ?>
            <tr>
              <td>
                <?php if (($sponsor['logo_path'] ?? '') !== ''): ?>
                  <img class="thumb" src="<?= e(upload_url($sponsor['logo_path'])) ?>" alt="">
                <?php else: ?>
                  <div class="thumb" style="display:grid;place-items:center">★</div>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" action="<?= e(url('/admin/sponsors/' . (int) $sponsor['id'])) ?>" enctype="multipart/form-data" class="flex" style="gap:.4rem">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <input type="text" name="name" value="<?= e($sponsor['name']) ?>" required style="flex:1;min-width:130px">
                  <input type="hidden" name="tagline" value="<?= e($sponsor['tagline'] ?? '') ?>">
                  <input type="hidden" name="website" value="<?= e($sponsor['website'] ?? '') ?>">
                  <input type="hidden" name="sort_order" value="<?= (int) $sponsor['sort_order'] ?>">
                  <select name="tier" style="width:auto;min-width:110px">
                    <?php foreach ($tiers as $key => $label): ?>
                      <option value="<?= $key ?>" <?= $sponsor['tier'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="status" style="width:auto;min-width:96px">
                    <option value="active" <?= $sponsor['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $sponsor['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                  <button type="submit" class="btn btn--ghost btn--sm">Save</button>
                </form>
                <?php if (($sponsor['tagline'] ?? '') !== ''): ?>
                  <div class="small muted"><?= e($sponsor['tagline']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge--<?= $sponsor['tier'] === 'title' ? 'gold' : 'muted' ?>"><?= e($tiers[$sponsor['tier']] ?? $sponsor['tier']) ?></span></td>
              <td><span class="badge badge--<?= e(status_badge((string) $sponsor['status'])) ?>"><?= e($sponsor['status']) ?></span></td>
              <td class="actions">
                <form method="post" action="<?= e(url('/admin/sponsors/' . (int) $sponsor['id'] . '/delete')) ?>"
                      data-confirm="Delete this sponsor?">
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
</div>
<?php $view->stop(); ?>
