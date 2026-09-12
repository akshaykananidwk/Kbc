<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

/**
 * Role gate. "admin" implies every lower privilege, "operator" only allows
 * the live game screens.
 */
final class RoleMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!AuthService::check()) {
            if ($request->expectsJson()) {
                return Response::apiError('Authentication required.', 401);
            }
            Session::put('_intended', $request->path());
            return Response::redirect('/admin/login');
        }

        $required = $this->argument ?? 'admin';
        if (AuthService::hasRole($required)) {
            return null;
        }

        if ($request->expectsJson()) {
            return Response::apiError('You are not allowed to perform this action.', 403);
        }
        return Response::redirect('/admin/forbidden');
    }
}
