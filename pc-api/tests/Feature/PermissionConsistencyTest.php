<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\RoleController;
use App\Http\Middleware\CheckPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_endpoint_returns_effective_exact_permissions_for_business_users(): void
    {
        $permissionName = 'test_inventory.view.' . uniqid();
        $permission = $this->createPermission($permissionName, 'inventory');
        $this->createPermission('test_inventory.edit.' . uniqid(), 'inventory');

        $roleName = 'inventory_viewer_' . uniqid();
        $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = $this->createUser('permission_viewer');
        $user->assignRole($role);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/permissions/my');

        $response->assertOk()
            ->assertJsonFragment(['roles' => [$roleName]])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $permissionName);
    }

    public function test_system_user_receives_all_registered_permissions(): void
    {
        $systemPermission = $this->createPermission('test_system.settings.' . uniqid(), 'system');
        $businessPermission = $this->createPermission('test_inventory.view.' . uniqid(), 'inventory');
        $user = $this->createUser('system_user', 'system');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/permissions/my');

        $response->assertOk()
            ->assertJsonFragment(['name' => $systemPermission->name])
            ->assertJsonFragment(['name' => $businessPermission->name]);
    }

    public function test_permission_registry_changes_rotate_only_candidate_cache_namespace(): void
    {
        $permission = $this->createPermission('test_candidate.view.' . uniqid(), 'candidate');
        Cache::forget('perm:candidates:version');

        $middleware = new CheckPermission();
        $resolve = new \ReflectionMethod(CheckPermission::class, 'resolveCandidates');
        $resolve->setAccessible(true);

        $this->assertSame([$permission->name], $resolve->invoke($middleware, $permission->name));
        DB::table('permissions')->where('id', $permission->id)->delete();
        $this->assertSame([$permission->name], $resolve->invoke($middleware, $permission->name));

        $controller = new RoleController();
        $clear = new \ReflectionMethod(RoleController::class, 'clearPermissionCaches');
        $clear->setAccessible(true);
        $clear->invoke($controller);

        $this->assertSame([], $resolve->invoke($middleware, $permission->name));
    }

    public function test_profile_endpoint_persists_only_allowlisted_and_masked_audit_data(): void
    {
        $user = $this->createUser('profile_audit');

        $this->actingAs($user, 'sanctum')->putJson('/api/auth/profile', [
            'name' => 'Alice',
            'phone' => '13812345678',
            'email' => 'alice@example.com',
            'avatar' => '/avatars/a.png',
            'secret_token' => 'must-not-be-logged',
        ])->assertOk();

        $log = DB::table('system_logs')
            ->where('user_id', $user->id)
            ->where('action', 'update_profile')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $auditData = json_decode($log->request_data, true);
        $this->assertSame([
            'name' => 'Alice',
            'phone' => '138****5678',
            'email' => 'a****@example.com',
            'avatar' => '/avatars/a.png',
        ], $auditData);
        $this->assertStringNotContainsString('must-not-be-logged', $log->request_data);
    }

    private function createUser(string $username, string $userType = 'business'): User
    {
        return User::create([
            'name' => $username,
            'username' => $username . '_' . uniqid(),
            'email' => $username . '@test.local',
            'phone' => 'TEL-' . substr(md5($username), 0, 8),
            'password' => bcrypt('test-password'),
            'user_type' => $userType,
            'status' => 'active',
        ]);
    }

    private function createPermission(string $name, string $module): Permission
    {
        return Permission::create([
            'name' => $name,
            'display_name' => $name,
            'description' => $name,
            'guard_name' => 'web',
            'module' => $module,
        ]);
    }
}
