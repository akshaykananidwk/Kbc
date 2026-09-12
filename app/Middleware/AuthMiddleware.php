<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AuthMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (AuthService::check()) {
            return null;
        }
        if ($request->expectsJson()) {
            return Response::apiError('Authentication required.', 401);
        }
        Session::put('_intended', $request->path());
        Session::flash('error', 'Please sign in to continue.');
        return Response::redirect('/admin/login');
    }
}
