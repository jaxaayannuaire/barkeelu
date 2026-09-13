<?php

namespace Tests\Feature\Identity;

use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformRbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_rbac_is_seeded_with_the_expected_web_guard_and_mapping(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('barkeelu_test', DB::scalar('select current_database()'));
        $this->assertFalse(config('permission.teams'));

        $this->seed(PlatformRbacSeeder::class);

        $this->assertDatabaseCount('permissions', 6);
        $this->assertDatabaseCount('roles', 5);
        $this->assertSame('web', Role::query()->where('name', 'platform_admin')->value('guard_name'));

        $platformAdmin = Role::findByName('platform_admin', 'web');
        $this->assertEqualsCanonicalizing([
            'platform.access',
            'organizations.manage_all',
            'moderation.manage',
            'compliance.manage',
        ], $platformAdmin->permissions->pluck('name')->all());
        $this->assertFalse($platformAdmin->hasPermissionTo('finance.operate'));
        $this->assertFalse($platformAdmin->hasPermissionTo('finance.approve'));
        $this->assertTrue(Role::findByName('finance_operator', 'web')->hasPermissionTo('finance.operate'));
        $this->assertTrue(Role::findByName('finance_approver', 'web')->hasPermissionTo('finance.approve'));
    }
}
