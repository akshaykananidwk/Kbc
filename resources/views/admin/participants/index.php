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

<?php if (setting('public_registration', true)): ?>
<div class="card">
  <div class="card__head">
    <h2 class="card__title">Self-registration QR</h2>
    <span class="badge badge--ok">open</span>
  </div>
  <div class="card__body">
    <div class="flex" style="gap:1.2rem;align-items:center">
      <img src="<?= e(url('/qr?for=register&scale=6')) ?>" alt="Registration QR code"
           style="width:160px;height:160px;background:#fff;border-radius:12px;padding:6px;border:1px solid var(--ink-100)">
      <div style="flex:1;min-width:220px">
        <p class="mb-1">
          Print this code or show it on a screen at the venue. People scan it,
          fill in their name and mobile, and appear in this list straight away
          with a registration number.
        </p>
        <p class="small muted mb-1">
          Link: <code><?= e(url('/register')) ?></code>
        </p>
        <div class="btn-row">
          <a class="btn btn--ghost btn--sm" href="<?= e(url('/register')) ?>" target="_blank" rel="noopener">Open the form</a>
          <a class="btn btn--ghost btn--sm" href="<?= e(url('/qr?for=register&scale=12')) ?>" target="_blank" rel="noopener">Large QR for printing</a>
          <button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e(url('/register')) ?>">Copy link</button>
        </div>
        <p class="small muted mt-1 mb-0">
          Switch this off any time in Settings → General → Allow Public QR Registration.
        </p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

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
