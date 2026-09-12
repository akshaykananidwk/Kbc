<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Users']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Users</h1><p class="page-head__sub">Administrators manage everything; operators only run the live show.</p></div>
  <a class="btn btn--primary" href="<?= e(url('/admin/users/create')) ?>">+ Add user</a>
</div>

<div class="card">
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><strong><?= e($user['name']) ?></strong>
              <?php if ((int) $user['id'] === (int) ($currentUser['id'] ?? 0)): ?><span class="badge badge--info">you</span><?php endif; ?></td>
            <td class="small"><?= e($user['email']) ?></td>
            <td class="small mono"><?= e($user['username'] ?? '—') ?></td>
            <td><span class="badge badge--<?= $user['role_slug'] === 'admin' ? 'gold' : 'info' ?>"><?= e($user['role_name']) ?></span></td>
            <td>
              <span class="badge badge--<?= e(status_badge((string) $user['status'])) ?>"><?= e($user['status']) ?></span>
              <?php if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()): ?>
                <div class="small muted">locked until <?= e(datetime_label((string) $user['locked_until'], 'H:i')) ?></div>
              <?php endif; ?>
            </td>
            <td class="small muted nowrap"><?= e(datetime_label($user['last_login_at'])) ?></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/users/' . (int) $user['id'] . '/edit')) ?>">Edit</a>
              <?php if ((int) $user['id'] !== (int) ($currentUser['id'] ?? 0)): ?>
              <form method="post" action="<?= e(url('/admin/users/' . (int) $user['id'] . '/delete')) ?>" style="display:inline"
                    data-confirm="Delete this user account?">
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
  </div>
</div>
<?php $view->stop(); ?>
