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

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = Database::instance()->select(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             ORDER BY r.level DESC, u.name'
        );
        return $this->view('admin.users.index', ['users' => $users, 'roles' => $this->roles()]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin.users.form', ['user' => null, 'roles' => $this->roles()]);
    }

    public function edit(Request $request): Response
    {
        $user = Database::instance()->selectOne('SELECT * FROM users WHERE id = ?', [$request->intParam('id')]);
        if ($user === null) {
            $this->error('That user no longer exists.');
            return $this->redirect('/admin/users');
        }
        unset($user['password_hash']);
        return $this->view('admin.users.form', ['user' => $user, 'roles' => $this->roles()]);
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:190',
            'username' => 'nullable|string|max:60|alpha_dash',
            'role_id'  => 'required|int',
            'status'   => 'required|in:active,inactive,locked',
        ]);

        $password = (string) $request->input('password', '');
        $policy = AuthService::passwordPolicy($password);
        if (!$policy['ok']) {
            throw new \App\Core\Exceptions\ValidationException(['password' => $policy['message']], $policy['message']);
        }
        if ($password !== (string) $request->input('password_confirmation', '')) {
            throw new \App\Core\Exceptions\ValidationException(['password' => 'The passwords do not match.'], 'The passwords do not match.');
        }
        if ($this->emailExists($data['email'], null)) {
            throw new \App\Core\Exceptions\ValidationException(['email' => 'That email address is already registered.'], 'That email address is already registered.');
        }

        $now = date('Y-m-d H:i:s');
        $id = Database::instance()->insert('users', [
            'role_id'       => (int) $data['role_id'],
            'name'          => $data['name'],
            'email'         => $data['email'],
            'username'      => ($data['username'] ?? '') !== '' ? $data['username'] : null,
            'password_hash' => AuthService::hashPassword($password),
            'status'        => $data['status'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        AuditService::log('user.created', 'Created user "' . $data['name'] . '"', 'user', $id);
        $this->success('User created.');
        return $this->redirect('/admin/users');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $db = Database::instance();
        $user = $db->selectOne('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            $this->error('That user no longer exists.');
            return $this->redirect('/admin/users');
        }

        $data = Validator::validate($request->all(), [
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:190',
            'username' => 'nullable|string|max:60|alpha_dash',
            'role_id'  => 'required|int',
            'status'   => 'required|in:active,inactive,locked',
        ]);

        if ($this->emailExists($data['email'], $id)) {
            throw new \App\Core\Exceptions\ValidationException(['email' => 'That email address is already registered.'], 'That email address is already registered.');
        }

        // Never let the last active administrator lock themselves out.
        if ($this->wouldRemoveLastAdmin($id, (int) $data['role_id'], (string) $data['status'])) {
            $this->error('This is the last active administrator - change another account to administrator first.');
            return $this->redirect('/admin/users/' . $id . '/edit');
        }

        $update = [
            'role_id'  => (int) $data['role_id'],
            'name'     => $data['name'],
            'email'    => $data['email'],
            'username' => ($data['username'] ?? '') !== '' ? $data['username'] : null,
            'status'   => $data['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $password = (string) $request->input('password', '');
        if ($password !== '') {
            $policy = AuthService::passwordPolicy($password);
            if (!$policy['ok']) {
                throw new \App\Core\Exceptions\ValidationException(['password' => $policy['message']], $policy['message']);
            }
            if ($password !== (string) $request->input('password_confirmation', '')) {
                throw new \App\Core\Exceptions\ValidationException(['password' => 'The passwords do not match.'], 'The passwords do not match.');
            }
            $update['password_hash'] = AuthService::hashPassword($password);
            $update['failed_attempts'] = 0;
            $update['locked_until'] = null;
        }

        if ((string) $data['status'] === 'active' && (string) $user['status'] === 'locked') {
            $update['failed_attempts'] = 0;
            $update['locked_until'] = null;
        }

        $db->update('users', $update, ['id' => $id]);

        AuditService::log('user.updated', 'Updated user #' . $id, 'user', $id);
        $this->success('User updated.');
        return $this->redirect('/admin/users');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        if ($id === AuthService::id()) {
            $this->error('You cannot delete your own account.');
            return $this->redirect('/admin/users');
        }
        if ($this->wouldRemoveLastAdmin($id, 0, 'inactive')) {
            $this->error('This is the last active administrator and cannot be deleted.');
            return $this->redirect('/admin/users');
        }

        Database::instance()->delete('users', ['id' => $id]);
        AuditService::log('user.deleted', 'Deleted user #' . $id, 'user', $id);
        $this->success('User deleted.');
        return $this->redirect('/admin/users');
    }

    /** @return array<int,array<string,mixed>> */
    private function roles(): array
    {
        return Database::instance()->select('SELECT id, name, slug, description FROM roles ORDER BY level DESC');
    }

    private function emailExists(string $email, ?int $ignoreId): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $bindings = [$email];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $ignoreId;
        }
        return (int) (Database::instance()->scalar($sql, $bindings) ?? 0) > 0;
    }

    private function wouldRemoveLastAdmin(int $userId, int $newRoleId, string $newStatus): bool
    {
        $db = Database::instance();
        $adminRoleId = (int) ($db->scalar("SELECT id FROM roles WHERE slug = 'admin'") ?? 0);
        $user = $db->selectOne('SELECT role_id, status FROM users WHERE id = ?', [$userId]);
        if ($user === null || (int) $user['role_id'] !== $adminRoleId || (string) $user['status'] !== 'active') {
            return false;
        }
        // The change keeps them an active admin - nothing to worry about.
        if ($newRoleId === $adminRoleId && $newStatus === 'active') {
            return false;
        }
        $activeAdmins = (int) ($db->scalar(
            "SELECT COUNT(*) FROM users WHERE role_id = ? AND status = 'active'",
            [$adminRoleId]
        ) ?? 0);
        return $activeAdmins <= 1;
    }
}
