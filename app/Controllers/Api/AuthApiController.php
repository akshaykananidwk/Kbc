<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthApiController extends Controller
{
    public function me(Request $request): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return $this->fail('Not signed in.', 401);
        }
        return $this->ok('Signed in.', [
            'user' => [
                'id'    => (int) $user['id'],
                'name'  => (string) $user['name'],
                'email' => (string) $user['email'],
                'role'  => (string) $user['role_slug'],
            ],
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function logout(Request $request): Response
    {
        AuthService::logout();
        return $this->ok('Signed out.');
    }
}
