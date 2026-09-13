<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlatformRbacSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'platform.access',
            'organizations.manage_all',
            'moderation.manage',
            'compliance.manage',
            'finance.operate',
            'finance.approve',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, self::GUARD)]);

        $mappings = [
            'platform_admin' => [
                'platform.access',
                'organizations.manage_all',
                'moderation.manage',
                'compliance.manage',
            ],
            'moderator' => ['moderation.manage'],
            'compliance_officer' => ['compliance.manage'],
            'finance_operator' => ['finance.operate'],
            'finance_approver' => ['finance.approve'],
        ];

        foreach ($mappings as $name => $permissionNames) {
            Role::findOrCreate($name, self::GUARD)
                ->syncPermissions($permissions->only($permissionNames));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
