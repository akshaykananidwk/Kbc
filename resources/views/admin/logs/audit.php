<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Audit log']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Audit log</h1><p class="page-head__sub"><?= (int) $pagination['total'] ?> recorded action(s).</p></div>
  <div class="btn-row">
    <a class="btn btn--ghost" href="<?= e(url('/admin/logs/audit.csv')) ?>?<?= e(http_build_query($filters)) ?>">Export CSV</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/logs')) ?>">System error log</a>
  </div>
</div>

<div class="card">
  <div class="card__body">
    <form method="get" action="<?= e(url('/admin/logs/audit')) ?>" class="filters">
      <div><label class="label" for="search">Search</label>
        <input type="search" id="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Description or user"></div>
      <div><label class="label" for="action">Action</label>
        <select id="action" name="action" data-auto-submit>
          <option value="">All actions</option>
          <?php foreach ($actions as $action): ?>
            <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= e($action) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="label" for="user_id">User</label>
        <select id="user_id" name="user_id" data-auto-submit>
          <option value="">All users</option>
          <?php foreach ($users as $user): ?>
            <option value="<?= (int) $user['id'] ?>" <?= (int) $filters['user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option>
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
    <?php if ($logs === []): ?>
      <div class="empty"><div class="empty__icon">▤</div><p>No log entries match your filters.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="small nowrap"><?= e(datetime_label((string) $log['created_at'], 'd M Y H:i:s')) ?></td>
            <td class="small"><?= e($log['user_name'] ?? 'system') ?></td>
            <td><span class="badge"><?= e($log['action']) ?></span></td>
            <td class="small muted"><?= e(trim(($log['entity_type'] ?? '') . ' ' . ($log['entity_id'] ?? ''))) ?: '—' ?></td>
            <td class="small"><?= e($log['description'] ?? '') ?></td>
            <td class="small mono muted"><?= e($log['ip_address'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $view->include('partials.pagination', ['pagination' => $pagination, 'baseUrl' => '/admin/logs/audit']) ?>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
