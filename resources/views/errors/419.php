<?php
/** @var App\Core\View $view */
echo $view->include('errors.layout', [
    'status'    => $status ?? 419,
    'heading'   => 'Session expired',
    'glyph'     => '↻',
    'message'   => $message ?? '',
    'exception' => $exception ?? null,
    'siteName'  => $siteName ?? 'Ganpati Bapa Quiz Show',
]);
