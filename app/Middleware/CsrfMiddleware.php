<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Uploader;

final class CsrfMiddleware extends Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): ?Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return null;
        }

        // A POST larger than post_max_size reaches PHP with $_POST and $_FILES
        // emptied - the CSRF token included. Without this check the admin sees
        // "your session expired" while uploading a perfectly good song, and
        // never learns the real reason.
        if (self::bodyWasDiscarded()) {
            $message = 'That file is too big for this server, so nothing was received. '
                . Uploader::serverLimitAdvice();

            if ($request->expectsJson() || $request->isAjax()) {
                return Response::apiError($message, 413);
            }
            Session::flash('error', $message);
            $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            return Response::redirect($referer !== '' ? $referer : '/admin');
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

    /**
     * True when the browser sent a body but PHP dropped it for exceeding
     * post_max_size - the one case where an empty $_POST is not the user's
     * fault.
     */
    private static function bodyWasDiscarded(): bool
    {
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length <= 0 || $_POST !== [] || $_FILES !== []) {
            return false;
        }

        $postMax = Uploader::iniBytes((string) ini_get('post_max_size'));
        return $postMax > 0 && $length > $postMax;
    }
}
