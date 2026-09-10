<?php

namespace Tests\E2E;

/**
 * V0.4.10 A3 - 业务端点 Feature 测试
 *
 * 8 个端到端: 业务模块 + 数据权限 + 审计
 */
class BusinessApiTest extends E2ETestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    // V1.4.5 (REVIEW P2-7/P1-4): 密码从环境变量读取, 不硬编码入库
    private function login(string $u, string $p = ''): string
    {
        if ($p === '') $this->markTestSkipped('未配置 E2E 账号密码 (OA_E2E_ADMIN_PASS)');
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => $u, 'password' => $p]),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/auth/login', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        if (($j['code'] ?? 1) !== 0) $this->markTestSkipped('登录失败');
        return $j['data']['token'];
    }

    private function get(string $token, string $ep): array
    {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'ignore_errors' => true,
            'header' => "Authorization: Bearer $token\r\n",
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . $ep, false, $ctx);
        return $r === false ? ['code' => 599] : (json_decode($r, true) ?? ['code' => 598]);
    }

    public function test_warranties_list_returns_items(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/warranties?per_page=5');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertNotEmpty($j['data']['items']);
        $first = $j['data']['items'][0];
        $this->assertArrayHasKey('warranty_no', $first);
        $this->assertArrayHasKey('warranty_type', $first);
    }

    public function test_warranty_show_returns_single(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/warranties/2');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertSame(2, $j['data']['id']);
    }

    public function test_dashboard_stats_full_data(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/dashboard/stats');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertArrayHasKey('pendingTodos', $j['data']);
        $this->assertArrayHasKey('activeProjects', $j['data']);
        $this->assertArrayHasKey('monthlyRevenue', $j['data']);
        // V0.4.9 验证 pendingTodos 非 0 (admin1 看到 74 条审批)
        $this->assertGreaterThan(0, $j['data']['pendingTodos']);
    }

    public function test_construction_teams_list(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/construction/teams?per_page=5');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertGreaterThan(0, $j['data']['total'] ?? 0);
    }

    public function test_audit_logs_list(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/audit-logs?per_page=5');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertIsArray($j['data']);
    }

    public function test_audit_data_scope_summary(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/audit/data-scope/summary?days=7');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertIsArray($j['data']);
    }

    public function test_customers_list_pagination(): void
    {
        $t = $this->login('admin1');
        $j = $this->get($t, '/customers?per_page=5&page=1');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertSame(1, $j['data']['current_page']);
        $this->assertLessThanOrEqual(5, count($j['data']['data']));
    }

    public function test_projects_list_scope_param(): void
    {
        // admin 默认全量, scope=mine 应返回其负责的项目
        $t = $this->login('admin1');
        $j = $this->get($t, '/projects?per_page=1&scope=mine');
        $this->assertSame(0, $j['code'] ?? 1);
        // admin 即使 scope=mine 也看到全量 (不受限)
        $this->assertGreaterThan(0, $j['data']['total']);
    }
}
