<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class GuestMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!AuthService::check()) {
            return null;
        }
        return Response::redirect('/admin');
    }
}
