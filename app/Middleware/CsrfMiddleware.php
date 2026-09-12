<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class CsrfMiddleware extends Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): ?Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return null;
        }
        if (Csrf::check($request)) {
            return null;
        }

        if ($request->expectsJson()) {
            return Response::apiError('Security token is invalid or expired. Please reload the page.', 419);
        }

        Session::flash('error', 'Your session expired. Please try again.');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        return Response::redirect($referer !== '' ? $referer : '/admin');
    }
}
