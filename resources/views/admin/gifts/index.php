<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Gifts']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Gifts</h1><p class="page-head__sub"><?= (int) $pagination['total'] ?> gift(s). Attach them to prize levels without touching any code.</p></div>
  <a class="btn btn--primary" href="<?= e(url('/admin/gifts/create')) ?>">+ Add gift</a>
</div>

<div class="card">
  <div class="card__body">
    <form method="get" action="<?= e(url('/admin/gifts')) ?>" class="filters">
      <div><label class="label" for="search">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Gift name or code"></div>
      <div><label class="label" for="status">Status</label>
        <select id="status" name="status" data-auto-submit>
          <option value="">Any</option>
          <?php foreach (['active', 'inactive', 'out_of_stock'] as $status): ?>
            <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><button type="submit" class="btn btn--primary btn--block">Filter</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card__body card__body--flush">
    <?php if ($gifts === []): ?>
      <div class="empty"><div class="empty__icon">✦</div><p>No gifts yet.</p>
        <a class="btn btn--primary" href="<?= e(url('/admin/gifts/create')) ?>">Add the first gift</a></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th></th><th>Gift</th><th class="num">Value</th><th class="num">Stock</th><th>Prize levels</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($gifts as $gift): ?>
          <?php $remaining = (int) $gift['quantity_total'] - (int) $gift['quantity_used']; ?>
          <tr>
            <td>
              <?php if (($gift['image_path'] ?? '') !== ''): ?>
                <img class="thumb" src="<?= e(upload_url($gift['image_path'])) ?>" alt="">
              <?php else: ?>
                <div class="thumb" style="display:grid;place-items:center">✦</div>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e($gift['name']) ?></strong>
              <?php if (($gift['description'] ?? '') !== ''): ?>
                <div class="small muted"><?= e(mb_strimwidth((string) $gift['description'], 0, 70, '…')) ?></div>
              <?php endif; ?>
              <?php if (($gift['serial_code'] ?? '') !== ''): ?><div class="small mono"><?= e($gift['serial_code']) ?></div><?php endif; ?>
            </td>
            <td class="num"><?= e(money($gift['value_amount'])) ?></td>
            <td class="num">
              <?= $remaining ?> / <?= (int) $gift['quantity_total'] ?>
              <?php if ($remaining === 0): ?><div><span class="badge badge--bad">out of stock</span></div><?php endif; ?>
            </td>
            <td class="small"><?= ($gift['levels'] ?? '') !== '' ? 'Q' . e(str_replace(',', ', Q', (string) $gift['levels'])) : '—' ?></td>
            <td><span class="badge badge--<?= e(status_badge((string) $gift['status'])) ?>"><?= e(str_replace('_', ' ', (string) $gift['status'])) ?></span></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/gifts/' . (int) $gift['id'] . '/edit')) ?>">Edit</a>
              <form method="post" action="<?= e(url('/admin/gifts/' . (int) $gift['id'] . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this gift?">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $view->include('partials.pagination', ['pagination' => $pagination, 'baseUrl' => '/admin/gifts']) ?>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
