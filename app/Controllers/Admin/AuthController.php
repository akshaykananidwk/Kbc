<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('admin.auth.login');
    }

    public function login(Request $request): Response
    {
        $identifier = $request->string('identifier');
        $password   = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            $this->error('Enter both your email/username and password.');
            return $this->redirect('/admin/login');
        }

        $result = AuthService::attempt($identifier, $password, $request->ip(), $request->userAgent());

        if (!$result['success']) {
            $this->error($result['message']);
            Session::flashInput(['identifier' => $identifier]);
            return $this->redirect('/admin/login');
        }

        Csrf::rotate();
        $this->success($result['message']);

        $intended = Session::get('_intended');
        Session::forget('_intended');

        if (is_string($intended) && $intended !== '' && str_starts_with($intended, '/')) {
            return $this->redirect($intended);
        }

        // Operators land straight on the control screen.
        return $this->redirect(AuthService::hasRole('admin') ? '/admin' : '/operator');
    }

    public function logout(Request $request): Response
    {
        AuthService::logout();
        Session::start($request->isSecure());
        $this->success('You have been signed out.');
        return $this->redirect('/admin/login');
    }

    public function forbidden(Request $request): Response
    {
        return $this->view('errors.403', [
            'status'  => 403,
            'message' => 'Your account does not have permission to open that page.',
        ], 403);
    }
}
