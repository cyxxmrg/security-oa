<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_creator_cannot_assign_admin_role(): void
    {
        $operator = $this->createOperator();
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $response = $this->actingAs($operator, 'sanctum')->postJson('/api/employees', [
            'name' => 'New Employee',
            'username' => 'new_employee_admin',
            'role_id' => $adminRole->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'new_employee_admin']);
    }

    public function test_employee_creator_without_role_management_permission_cannot_assign_any_role(): void
    {
        $operator = $this->createOperator();
        $role = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

        $response = $this->actingAs($operator, 'sanctum')->postJson('/api/employees', [
            'name' => 'New Employee',
            'username' => 'new_employee_finance',
            'role_id' => $role->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'new_employee_finance']);
    }

    public function test_business_role_manager_can_assign_non_privileged_role(): void
    {
        $operator = $this->createOperator(true);
        $role = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

        $response = $this->actingAs($operator, 'sanctum')->postJson('/api/employees', [
            'name' => 'New Employee',
            'username' => 'new_employee_finance_manager',
            'role_id' => $role->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_id' => User::where('username', 'new_employee_finance_manager')->value('id'),
            'model_type' => User::class,
        ]);
    }

    public function test_business_admin_cannot_assign_admin_role_to_new_employee(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $operator = $this->createUser('business', 'business_admin_operator');
        $operator->assignRole($adminRole);

        $response = $this->actingAs($operator, 'sanctum')->postJson('/api/employees', [
            'name' => 'New Employee',
            'username' => 'new_employee_from_business_admin',
            'role_id' => $adminRole->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'new_employee_from_business_admin']);
    }

    public function test_business_role_manager_cannot_assign_system_admin_role(): void
    {
        $operator = $this->createOperator(true);
        $systemAdminRole = Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);

        $response = $this->actingAs($operator, 'sanctum')->postJson('/api/employees', [
            'name' => 'New Employee',
            'username' => 'new_employee_system_admin',
            'role_id' => $systemAdminRole->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'new_employee_system_admin']);
    }

    private function createOperator(bool $canManageRoles = false): User
    {
        $username = 'employee_creator_' . uniqid();
        $role = Role::create([
            'name' => $username,
            'guard_name' => 'web',
        ]);

        foreach (['employee.view', 'employee.create'] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'display_name' => $name,
                    'description' => $name,
                    'module' => 'employee',
                ]
            );
            $role->givePermissionTo($permission);
        }

        if ($canManageRoles) {
            $permission = Permission::firstOrCreate(
                ['name' => 'system.role', 'guard_name' => 'web'],
                [
                    'display_name' => 'Role management',
                    'description' => 'Role management',
                    'module' => 'system',
                ]
            );
            $role->givePermissionTo($permission);
        }

        $operator = $this->createUser('business', $username);
        $operator->assignRole($role);

        return $operator;
    }

    private function createUser(string $userType, string $username): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username . '@test.local',
            'phone' => 'TEL-' . substr(md5($username), 0, 8),
            'password' => bcrypt('test-password'),
            'user_type' => $userType,
            'status' => 'active',
        ]);
    }
}
