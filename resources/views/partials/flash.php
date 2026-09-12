<?php
/** @var array<int,array{type:string,message:string}> $flash */
foreach (($flash ?? []) as $message):
    $type = $message['type'] ?? 'info';
    $class = match ($type) {
        'success' => 'alert--success',
        'error'   => 'alert--error',
        'warning' => 'alert--warning',
        default   => 'alert--info',
    };
    $icon = match ($type) {
        'success' => '✓',
        'error'   => '✕',
        'warning' => '!',
        default   => 'i',
    };
?>
<div class="alert <?= $class ?>" role="alert">
  <span aria-hidden="true"><?= $icon ?></span>
  <span><?= e($message['message'] ?? '') ?></span>
  <button type="button" class="alert__close" data-dismiss aria-label="Dismiss">&times;</button>
</div>
<?php endforeach; ?>
