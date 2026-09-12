<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Participants']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Participants</h1><p class="page-head__sub"><?= (int) $pagination['total'] ?> registered.</p></div>
  <div class="btn-row">
    <a class="btn btn--primary" href="<?= e(url('/admin/participants/create')) ?>">+ Add participant</a>
    <a class="btn btn--gold" href="<?= e(url('/operator/setup')) ?>">Start a game</a>
  </div>
</div>

<div class="card">
  <div class="card__body">
    <form method="get" action="<?= e(url('/admin/participants')) ?>" class="filters">
      <div>
        <label class="label" for="search">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, mobile, city, reg. no.">
      </div>
      <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" data-auto-submit>
          <option value="">Any</option>
          <?php foreach (['active', 'played', 'inactive'] as $status): ?>
            <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><button type="submit" class="btn btn--primary btn--block">Filter</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card__body card__body--flush">
    <?php if ($participants === []): ?>
      <div class="empty"><div class="empty__icon">☻</div><p>No participants found.</p>
        <a class="btn btn--primary" href="<?= e(url('/admin/participants/create')) ?>">Add the first participant</a></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th></th><th>Name</th><th>Reg. no.</th><th>Mobile</th><th>City</th><th class="num">Games</th><th class="num">Best prize</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($participants as $participant): ?>
          <tr>
            <td>
              <?php if (($participant['photo_path'] ?? '') !== ''): ?>
                <img class="thumb" src="<?= e(upload_url($participant['photo_path'])) ?>" alt="">
              <?php else: ?>
                <div class="thumb" style="display:grid;place-items:center;font-weight:800;color:var(--ink-500)"><?= e(mb_substr((string) $participant['name'], 0, 1)) ?></div>
              <?php endif; ?>
            </td>
            <td><strong><?= e($participant['name']) ?></strong><?php if ($participant['age']): ?><div class="small muted"><?= (int) $participant['age'] ?> years</div><?php endif; ?></td>
            <td class="mono small"><?= e($participant['registration_no'] ?? '—') ?></td>
            <td class="small"><?= e($participant['mobile'] ?? '—') ?></td>
            <td class="small"><?= e($participant['city'] ?? '—') ?></td>
            <td class="num"><?= (int) $participant['games_played'] ?></td>
            <td class="num"><?= e(money($participant['best_prize'])) ?></td>
            <td><span class="badge badge--<?= e(status_badge((string) $participant['status'])) ?>"><?= e($participant['status']) ?></span></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/participants/' . (int) $participant['id'] . '/edit')) ?>">Edit</a>
              <form method="post" action="<?= e(url('/admin/participants/' . (int) $participant['id'] . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this participant?">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $view->include('partials.pagination', ['pagination' => $pagination, 'baseUrl' => '/admin/participants']) ?>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
