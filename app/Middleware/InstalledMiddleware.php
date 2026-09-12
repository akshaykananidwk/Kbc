<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;

/** Blocks the application until the /install wizard has been completed. */
final class InstalledMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (Application::instance()->isInstalled()) {
            return null;
        }
        if ($request->expectsJson()) {
            return Response::apiError('The application is not installed yet.', 503);
        }
        return Response::redirect('/install');
    }
}
