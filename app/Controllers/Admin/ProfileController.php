<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditService;
use App\Services\AuthService;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return $this->view('admin.profile', ['user' => AuthService::user()]);
    }

    public function update(Request $request): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return $this->redirect('/admin/login');
        }

        $data = Validator::validate($request->all(), [
            'name'  => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:30',
        ]);

        $db = Database::instance();
        $taken = (int) ($db->scalar('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$data['email'], (int) $user['id']]) ?? 0);
        if ($taken > 0) {
            throw new \App\Core\Exceptions\ValidationException(['email' => 'That email address is already registered.'], 'That email address is already registered.');
        }

        $db->update('users', [
            'name'       => $data['name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $user['id']]);

        AuditService::log('profile.updated', 'Updated their own profile.', 'user', (int) $user['id']);
        $this->success('Profile updated.');
        return $this->redirect('/admin/profile');
    }

    public function changePassword(Request $request): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return $this->redirect('/admin/login');
        }

        $current = (string) $request->input('current_password', '');
        $new     = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');

        $db = Database::instance();
        $hash = (string) ($db->scalar('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]) ?? '');

        if (!password_verify($current, $hash)) {
            $this->error('Your current password is not correct.');
            return $this->redirect('/admin/profile');
        }

        $policy = AuthService::passwordPolicy($new);
        if (!$policy['ok']) {
            $this->error($policy['message']);
            return $this->redirect('/admin/profile');
        }
        if ($new !== $confirm) {
            $this->error('The new passwords do not match.');
            return $this->redirect('/admin/profile');
        }

        $db->update('users', [
            'password_hash'        => AuthService::hashPassword($new),
            'must_change_password' => 0,
            'updated_at'           => date('Y-m-d H:i:s'),
        ], ['id' => (int) $user['id']]);

        AuditService::log('profile.password_changed', 'Changed their own password.', 'user', (int) $user['id']);
        $this->success('Password changed.');
        return $this->redirect('/admin/profile');
    }
}
