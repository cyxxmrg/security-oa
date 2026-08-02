<?php

namespace Tests\Unit\Auth;

use App\Support\AuthScope;
use App\Support\FieldMask;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * V0.5.1 - L4 字段级脱敏 + 用户管理 + 权限继承 单元测试
 *
 * FieldMask::apply 是纯函数, 不需要 DB
 */
class FieldMaskTest extends TestCase
{
    private function makeUserWithRoles(array $roles): User
    {
        $u = new User();
        // 模拟 spatie 关系: roles 是 Collection
        $roleMocks = [];
        foreach ($roles as $r) {
            $rMock = new \stdClass();
            $rMock->name = $r;
            $roleMocks[] = $rMock;
        }
        // 用 array access, FieldMask 调用 ->pluck('name')
        $u = $this->getMockBuilder(User::class)->disableOriginalConstructor()->onlyMethods(['__get'])->getMock();
        $u->method('__get')->willReturnCallback(function ($prop) use ($roleMocks) {
            if ($prop === 'roles') {
                return collect($roleMocks);
            }
            return null;
        });
        return $u;
    }

    public function test_admin_sees_amount_unmasked(): void
    {
        $admin = $this->makeUserWithRoles(['admin']);
        $data = ['id' => 1, 'amount' => 100000.5, 'name' => '应收单'];
        $out = FieldMask::apply($data, $admin, '/api/finance/receivables');
        $this->assertSame(100000.5, $out['amount']);
        $this->assertSame('应收单', $out['name']);
    }

    public function test_finance_sees_amount_unmasked(): void
    {
        $fin = $this->makeUserWithRoles(['finance']);
        $data = ['amount' => 9999.99];
        $out = FieldMask::apply($data, $fin, '/api/finance/receivables');
        $this->assertSame(9999.99, $out['amount']);
    }

    public function test_user_role_sees_amount_masked(): void
    {
        $u = $this->makeUserWithRoles(['user']);
        $data = ['id' => 5, 'amount' => 100000, 'received_amount' => 50000, 'name' => '客户A'];
        $out = FieldMask::apply($data, $u, '/api/finance/receivables');
        $this->assertSame('***', $out['amount']);
        $this->assertSame('***', $out['received_amount']);
        $this->assertSame('客户A', $out['name']); // 非敏感字段不 mask
        $this->assertSame(5, $out['id']);
    }

    public function test_manager_role_sees_finance_masked(): void
    {
        $m = $this->makeUserWithRoles(['manager']);
        $data = ['budget' => 500000, 'contract_amount' => 800000, 'name' => '项目X'];
        $out = FieldMask::apply($data, $m, '/api/projects/123');
        $this->assertSame('***', $out['budget']);
        $this->assertSame('***', $out['contract_amount']);
        $this->assertSame('项目X', $out['name']);
    }

    public function test_paginated_list_masks_all_rows(): void
    {
        $u = $this->makeUserWithRoles(['user']);
        $paginated = [
            'data' => [
                ['id' => 1, 'amount' => 100],
                ['id' => 2, 'amount' => 200],
            ],
            'total' => 2,
        ];
        // 分页结构 - FieldMask::apply 应该走 'data' 列表分支
        $out = FieldMask::apply($paginated, $u, '/api/finance/receivables');
        $this->assertSame('***', $out['data'][0]['amount']);
        $this->assertSame('***', $out['data'][1]['amount']);
        $this->assertSame(2, $out['total']);
    }

    public function test_null_value_not_masked(): void
    {
        $u = $this->makeUserWithRoles(['user']);
        $data = ['amount' => null, 'name' => 'X'];
        $out = FieldMask::apply($data, $u, '/api/finance/receivables');
        $this->assertNull($out['amount']);
    }

    public function test_non_protected_endpoint_not_masked(): void
    {
        $u = $this->makeUserWithRoles(['user']);
        $data = ['amount' => 100, 'name' => 'X']; // amount 在非 protected 路径
        $out = FieldMask::apply($data, $u, '/api/projects'); // projects protected
        // projects 路由 protected 包含 amount 吗? 当前没有, 但 contract_amount 有
        // 验证 amount 在 projects 仍可见 (没在列表)
        $this->assertSame(100, $out['amount']);
    }

    public function test_match_module_short_paths(): void
    {
        $this->assertSame('finance', FieldMask::matchModule('/api/receivables'));
        $this->assertSame('finance', FieldMask::matchModule('/api/payables'));
        $this->assertSame('finance', FieldMask::matchModule('/api/expense-claims'));
        $this->assertSame('sales', FieldMask::matchModule('/api/contracts'));
        $this->assertSame('finance', FieldMask::matchModule('/api/finance/receivables'));
        $this->assertNull(FieldMask::matchModule('/api/dashboard'));
        $this->assertNull(FieldMask::matchModule('/api/customers'));
    }

    public function test_no_user_no_mask(): void
    {
        $data = ['amount' => 100];
        $out = FieldMask::apply($data, null, '/api/finance/receivables');
        // null user 不脱敏 (未登录请求由 auth 中间件 401 兜底, 不会到 mask)
        $this->assertSame(100, $out['amount']);
    }

    public function test_user_with_no_roles_masked(): void
    {
        // 兜底: 关系未就绪时 role 列表空, 视为无权, 应当 mask
        $u = $this->makeUserWithRoles([]);
        $data = ['amount' => 999];
        $out = FieldMask::apply($data, $u, '/api/finance/receivables');
        $this->assertSame('***', $out['amount']);
    }
}
