<?php

namespace Tests\Unit\Scopes;

use PHPUnit\Framework\TestCase;

/**
 * V0.4.7 — AuthScope 角色判定逻辑单测
 *
 * 复用后端 username 前缀策略 (8 个用例)
 * 不依赖 Laravel boot, 只测纯函数
 */
class AuthScopeTest extends TestCase
{
    private function makeUser(string $username, array $roles = []): \stdClass
    {
        $u = new \stdClass();
        $u->id = 1;
        $u->username = $username;
        $u->roles = $roles;
        return $u;
    }

    public function test_admin_classify(): void
    {
        $this->assertSame('admin', \App\Support\AuthScope::classify($this->makeUser('admin')));
        $this->assertSame('admin', \App\Support\AuthScope::classify($this->makeUser('admin1')));
        $this->assertSame('admin', \App\Support\AuthScope::classify($this->makeUser('admin_zheng')));
    }

    public function test_finance_classify(): void
    {
        $this->assertSame('finance', \App\Support\AuthScope::classify($this->makeUser('fin_wu')));
        $this->assertSame('finance', \App\Support\AuthScope::classify($this->makeUser('fin_zhou')));
        $this->assertSame('finance', \App\Support\AuthScope::classify($this->makeUser('fin_mgr')));
    }

    public function test_manager_classify(): void
    {
        $this->assertSame('manager', \App\Support\AuthScope::classify($this->makeUser('sales_yang')));
        $this->assertSame('manager', \App\Support\AuthScope::classify($this->makeUser('sales_chen')));
        $this->assertSame('manager', \App\Support\AuthScope::classify($this->makeUser('tech_mgr')));
        $this->assertSame('manager', \App\Support\AuthScope::classify($this->makeUser('proj_mgr')));
        $this->assertSame('manager', \App\Support\AuthScope::classify($this->makeUser('sales_mgr')));
    }

    public function test_user_classify(): void
    {
        $this->assertSame('user', \App\Support\AuthScope::classify($this->makeUser('eng_qian')));
        $this->assertSame('user', \App\Support\AuthScope::classify($this->makeUser('eng_zhao')));
        $this->assertSame('user', \App\Support\AuthScope::classify($this->makeUser('worker_xyz'))); // 不匹配任何已知前缀
    }

    public function test_null_user_is_user(): void
    {
        $this->assertSame('user', \App\Support\AuthScope::classify(null));
    }

    public function test_isUnrestricted_admin_and_finance(): void
    {
        $this->assertTrue(\App\Support\AuthScope::isUnrestricted($this->makeUser('admin1')));
        $this->assertTrue(\App\Support\AuthScope::isUnrestricted($this->makeUser('fin_wu')));
        $this->assertFalse(\App\Support\AuthScope::isUnrestricted($this->makeUser('sales_yang')));
        $this->assertFalse(\App\Support\AuthScope::isUnrestricted($this->makeUser('eng_qian')));
    }

    public function test_canViewAll_only_for_unrestricted(): void
    {
        // canViewAll 等价 isUnrestricted (admin/finance → true)
        $this->assertTrue(\App\Support\AuthScope::isUnrestricted($this->makeUser('admin1')));
        $this->assertFalse(\App\Support\AuthScope::isUnrestricted($this->makeUser('sales_yang')));
    }

    public function test_myProjectsByProjectIdSubquery_sql_contains_user_id_and_active(): void
    {
        $sql = \App\Support\AuthScope::myProjectsByProjectIdSubquery(86, 'warranties');
        $this->assertStringContainsString('86', $sql);
        $this->assertStringContainsString('warranties.project_id', $sql);
        $this->assertStringContainsString("status = 'active'", $sql);
        $this->assertStringContainsString('EXISTS', $sql);
    }
}
