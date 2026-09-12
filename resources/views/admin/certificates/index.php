<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Certificates']);
$view->start('content');
?>
<div class="page-head">
  <div>
    <h1>Winner certificates</h1>
    <p class="page-head__sub"><?= count($certificates) ?> issued. A reprint keeps the same serial number.</p>
  </div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/settings')) ?>#group-certificate">Certificate design</a>
</div>

<?php if (!$enabled): ?>
  <div class="alert alert--warning"><span>!</span><span>
    Certificates are switched off. Turn them on in Settings → Winner Certificate.
  </span></div>
<?php endif; ?>

<div class="card">
  <div class="card__body card__body--flush">
    <?php if ($certificates === []): ?>
      <div class="empty">
        <div class="empty__icon">🏅</div>
        <p>No certificates issued yet. Finish a game, then open its report and press “Certificate”.</p>
        <a class="btn btn--primary" href="<?= e(url('/admin/games')) ?>">Go to game history</a>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Serial</th><th>Participant</th><th>Game</th><th class="num">Prize</th><th>Gifts</th><th>Issued</th><th class="num">Prints</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($certificates as $row): ?>
          <tr>
            <td class="mono small"><?= e($row['serial_no']) ?></td>
            <td><strong><?= e($row['participant_name']) ?></strong></td>
            <td class="mono small"><?= e($row['game_code'] ?? '—') ?></td>
            <td class="num"><?= e(money($row['prize_amount'])) ?></td>
            <td class="small"><?= e($row['gifts'] ?? '—') ?></td>
            <td class="small nowrap"><?= e(datetime_label((string) $row['issued_at'])) ?>
              <div class="small muted"><?= e($row['issued_by_name'] ?? 'system') ?></div></td>
            <td class="num"><?= (int) $row['print_count'] ?></td>
            <td class="actions">
              <a class="btn btn--gold btn--sm" href="<?= e(url('/admin/certificates/' . (int) $row['game_id'])) ?>" target="_blank" rel="noopener">Print</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
