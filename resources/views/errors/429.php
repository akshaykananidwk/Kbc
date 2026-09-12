<?php
/** @var App\Core\View $view */
echo $view->include('errors.layout', [
    'status'    => $status ?? 429,
    'heading'   => 'Too many requests',
    'glyph'     => '⏱',
    'message'   => $message ?? '',
    'exception' => $exception ?? null,
    'siteName'  => $siteName ?? 'Ganpati Bapa Quiz Show',
]);
