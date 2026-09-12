<?php
/** @var App\Core\View $view */
echo $view->include('errors.layout', [
    'status'    => $status ?? 405,
    'heading'   => 'Method not allowed',
    'glyph'     => '!',
    'message'   => $message ?? '',
    'exception' => $exception ?? null,
    'siteName'  => $siteName ?? 'Ganpati Bapa Quiz Show',
]);
