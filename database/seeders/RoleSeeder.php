<?php
declare(strict_types=1);

namespace Database\Seeders;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'admin',    'name' => 'Administrator', 'level' => 100, 'description' => 'Full access to every module.'],
            ['slug' => 'operator', 'name' => 'Quiz Operator', 'level' => 50,  'description' => 'Runs the live show from the operator screen.'],
            ['slug' => 'viewer',   'name' => 'Viewer',        'level' => 10,  'description' => 'Read-only access to reports.'],
        ];

        foreach ($roles as $role) {
            $this->firstOrCreate('roles', ['slug' => $role['slug']], [
                'name'        => $role['name'],
                'level'       => $role['level'],
                'description' => $role['description'],
                'created_at'  => $this->now,
                'updated_at'  => $this->now,
            ]);
        }

        $permissions = [
            'questions.manage'    => ['Manage questions', 'content'],
            'categories.manage'   => ['Manage categories', 'content'],
            'participants.manage' => ['Manage participants', 'content'],
            'prizes.manage'       => ['Manage prize ladder', 'game'],
            'gifts.manage'        => ['Manage gifts', 'game'],
            'lifelines.manage'    => ['Manage lifelines', 'game'],
            'games.operate'       => ['Operate live games', 'game'],
            'games.manage'        => ['Manage game history', 'game'],
            'reports.view'        => ['View reports', 'reports'],
            'settings.manage'     => ['Change settings', 'system'],
            'users.manage'        => ['Manage users', 'system'],
            'backups.manage'      => ['Create and restore backups', 'system'],
            'updates.manage'      => ['Check and install updates', 'system'],
            'logs.view'           => ['View audit and error logs', 'system'],
        ];

        foreach ($permissions as $slug => [$name, $group]) {
            $this->firstOrCreate('permissions', ['slug' => $slug], [
                'name'       => $name,
                'group_name' => $group,
                'created_at' => $this->now,
            ]);
        }

        $roleIds = [];
        foreach ($this->db->select('SELECT id, slug FROM roles') as $row) {
            $roleIds[(string) $row['slug']] = (int) $row['id'];
        }
        $permissionIds = [];
        foreach ($this->db->select('SELECT id, slug FROM permissions') as $row) {
            $permissionIds[(string) $row['slug']] = (int) $row['id'];
        }

        $grants = [
            'admin'    => array_keys($permissions),
            'operator' => ['games.operate', 'participants.manage', 'reports.view'],
            'viewer'   => ['reports.view'],
        ];

        foreach ($grants as $roleSlug => $slugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }
            foreach ($slugs as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;
                if ($permissionId === null) {
                    continue;
                }
                $exists = $this->db->selectOne(
                    'SELECT 1 AS x FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                    [$roleId, $permissionId]
                );
                if ($exists === null) {
                    $this->db->insert('role_permissions', [
                        'role_id'       => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }
}
