<?php
/** @var App\Core\View $view */
echo $view->include('errors.layout', [
    'status'    => $status ?? 403,
    'heading'   => 'Access denied',
    'glyph'     => '✕',
    'message'   => $message ?? '',
    'exception' => $exception ?? null,
    'siteName'  => $siteName ?? 'Ganpati Bapa Quiz Show',
]);
