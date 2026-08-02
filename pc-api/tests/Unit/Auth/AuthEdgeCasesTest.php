<?php

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;

/**
 * V0.4.10 — DataScope 助手 + LoginThrottle 边界
 *
 * 角色判定一致性 + myProjectsByProjectIdSubquery 跨表名正确
 */
class AuthEdgeCasesTest extends TestCase
{
    private function makeUser(string $username): \stdClass
    {
        $u = new \stdClass();
        $u->id = 42;
        $u->username = $username;
        return $u;
    }

    public function test_classify_case_sensitive(): void
    {
        // username 大小写敏感: "ADMIN" 不以 "admin" 开头 → 普通 user
        $this->assertSame('user',  \App\Support\AuthScope::classify($this->makeUser('ADMIN')));
        $this->assertSame('user',  \App\Support\AuthScope::classify($this->makeUser('Sales_yang')));
    }

    public function test_classify_empty_username(): void
    {
        $this->assertSame('user', \App\Support\AuthScope::classify($this->makeUser('')));
    }

    public function test_classify_partial_match_admin_prefix(): void
    {
        // "administration" 开头是 admin → 误判 admin
        $this->assertSame('admin', \App\Support\AuthScope::classify($this->makeUser('administration')));
    }

    public function test_myProjects_subquery_uses_outer_table_alias(): void
    {
        $sql = \App\Support\AuthScope::myProjectsByProjectIdSubquery(99, 'rectifications');
        $this->assertStringContainsString('rectifications.project_id', $sql);
        $this->assertStringContainsString('99', $sql);
        $this->assertStringContainsString("status = 'active'", $sql);
    }

    public function test_myProjects_subquery_uses_manager_id(): void
    {
        $sql = \App\Support\AuthScope::myProjectsByProjectIdSubquery(1, 'warranties');
        // 必须包含 p.manager_id
        $this->assertStringContainsString('p.manager_id', $sql);
    }

    public function test_isUnrestricted_for_finance(): void
    {
        $this->assertTrue(\App\Support\AuthScope::isUnrestricted($this->makeUser('fin_xu')));
    }

    public function test_isUnrestricted_false_for_user_and_manager(): void
    {
        $this->assertFalse(\App\Support\AuthScope::isUnrestricted($this->makeUser('sales_li')));
        $this->assertFalse(\App\Support\AuthScope::isUnrestricted($this->makeUser('eng_sun')));
    }

    public function test_individual_role_helpers(): void
    {
        $this->assertTrue(\App\Support\AuthScope::isAdmin($this->makeUser('admin1')));
        $this->assertTrue(\App\Support\AuthScope::isFinance($this->makeUser('fin_chen')));
        $this->assertTrue(\App\Support\AuthScope::isManager($this->makeUser('sales_zhao')));
        $this->assertFalse(\App\Support\AuthScope::isAdmin($this->makeUser('sales_zhao')));
        $this->assertFalse(\App\Support\AuthScope::isFinance($this->makeUser('admin1')));
    }
}
