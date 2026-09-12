<?php
/**
 * @var array{total:int,page:int,pages:int,from:int,to:int,filters:array<string,mixed>} $pagination
 * @var string $baseUrl
 */
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'pages' => 1, 'from' => 0, 'to' => 0, 'filters' => []];
if (($pagination['total'] ?? 0) === 0) {
    return;
}
$page = (int) $pagination['page'];
$pages = (int) $pagination['pages'];

$link = static function (int $target) use ($baseUrl, $pagination): string {
    $query = array_merge($pagination['filters'] ?? [], ['page' => $target]);
    return url($baseUrl) . '?' . http_build_query($query);
};

$window = 2;
$start = max(1, $page - $window);
$end = min($pages, $page + $window);
?>
<div class="pagination">
  <span class="pagination__info">
    Showing <?= (int) $pagination['from'] ?>&ndash;<?= (int) $pagination['to'] ?> of <?= (int) $pagination['total'] ?>
  </span>
  <?php if ($page > 1): ?>
    <a href="<?= e($link(1)) ?>" aria-label="First page">&laquo;</a>
    <a href="<?= e($link($page - 1)) ?>" aria-label="Previous page">&lsaquo;</a>
  <?php endif; ?>

  <?php if ($start > 1): ?><span class="muted">…</span><?php endif; ?>
  <?php for ($i = $start; $i <= $end; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= e($link($i)) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($end < $pages): ?><span class="muted">…</span><?php endif; ?>

  <?php if ($page < $pages): ?>
    <a href="<?= e($link($page + 1)) ?>" aria-label="Next page">&rsaquo;</a>
    <a href="<?= e($link($pages)) ?>" aria-label="Last page">&raquo;</a>
  <?php endif; ?>
</div>
