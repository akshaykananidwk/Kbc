<?php
/** @var App\Core\View $view */
echo $view->include('errors.layout', [
    'status'    => $status ?? 500,
    'heading'   => 'Server error',
    'glyph'     => '!',
    'message'   => $message ?? '',
    'exception' => $exception ?? null,
    'siteName'  => $siteName ?? 'Ganpati Bapa Quiz Show',
]);
