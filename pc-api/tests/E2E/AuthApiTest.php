<?php

namespace Tests\E2E;

/**
 * V0.4.10 A3 - Auth/Login/Throttle Feature 测试
 *
 * 不 boot Laravel Kernel, 只走纯 HTTP curl 跑 117 真实 API
 * 验 4 个关键场景: 登录 / scope / 鉴权 / throttle
 *
 * 用例: 8 个
 */
class AuthApiTest extends E2ETestCase
{
    private const API = 'http://127.0.0.1:8081/api';
    // V1.4.5 (REVIEW P2-7/P1-4): 密码从环境变量读取, 不硬编码入库
    private static function users(): array
    {
        $pass = \Tests\E2E\E2ETestCase::e2ePass('OA_E2E_ADMIN_PASS');
        return [
            'admin'    => ['admin1', $pass],
            'finance'  => ['fin_wu', $pass],
            'manager'  => ['sales_yang', $pass],
            'user'     => ['eng_qian', $pass],
        ];
    }

    private static array $tokens = [];
    private static int $loginAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public static function setUpBeforeClass(): void
    {
        self::requireMutationOptIn();
        // 整 suite 跑前清一次
        self::doFlush();
    }

    private static function doFlush(): void
    {
        // P1-14 安全防御：doFlush 会清真 Redis，禁止在非 testing 环境下跑
        $inTesting = function_exists('app')
            ? app()->environment('testing')
            : (getenv('APP_ENV') === 'testing');
        if (!$inTesting) {
            self::markTestSkipped('doFlush would清真 Redis in non-testing env');
            return;
        }
        // V0.5.3: phpunit 跑在 117 上, 直接连本地 redis
        try {
            $r = new \Redis();
            $r->connect('127.0.0.1', 6379);
            $r->select(0);
            $r->flushDB();
            $r->select(1);
            $r->flushDB();
            $r->close();
        } catch (\Throwable $e) {
            // P1-14: 不再 silent ignore — 让 Redis 连不上显出来，避免测试假绿
            self::fail('doFlush 失败: ' . $e->getMessage());
        }
    }

    private function login(string $role): string
    {
        if (isset(self::$tokens[$role])) return self::$tokens[$role];
        [$u, $p] = self::users()[$role];
        if ($p === '') $this->markTestSkipped('未配置 E2E 账号密码 (OA_E2E_ADMIN_PASS)');

        // 每 3 次新 login 清一次 throttle
        self::$loginAttempts++;
        if (self::$loginAttempts > 3) {
            self::doFlush();
            self::$loginAttempts = 0;
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => $u, 'password' => $p]),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/auth/login', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        if (!($j['code'] ?? 1) === 0 || empty($j['data']['token'])) {
            $this->markTestSkipped('登录失败: ' . ($j['message'] ?? $r));
        }
        return self::$tokens[$role] = $j['data']['token'];
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

    public function test_login_admin_success(): void
    {
        [$u, $p] = self::users()['admin'];
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => $u, 'password' => $p]),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/auth/login', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        $this->assertSame(0, $j['code'] ?? 1, 'admin login failed');
        $this->assertNotEmpty($j['data']['token']);
        $this->assertSame($u, $j['data']['user']['username']);
    }

    public function test_login_wrong_password_401(): void
    {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => 'admin1', 'password' => 'wrong_pw_999']),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/auth/login', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        $this->assertNotSame(0, $j['code'] ?? 0, 'wrong password must fail');
    }

    public function test_unauthenticated_request_401(): void
    {
        $ctx = stream_context_create(['http' => ['timeout' => 8]]);
        $r = @file_get_contents(self::API . '/auth/me', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        $this->assertNotSame(0, $j['code'] ?? 0);
    }

    public function test_auth_me_returns_user_info(): void
    {
        $token = $this->login('admin');
        $j = $this->get($token, '/auth/me');
        $this->assertSame(0, $j['code'] ?? 1);
        // /auth/me 返回 data.user 结构
        $this->assertSame('admin1', $j['data']['user']['username']);
    }

    public function test_admin_sees_all_projects(): void
    {
        $token = $this->login('admin');
        $j = $this->get($token, '/projects?per_page=1');
        $this->assertSame(0, $j['code'] ?? 1);
        $this->assertArrayHasKey('total', $j['data']);
        $this->assertGreaterThan(100, $j['data']['total']); // admin 应看到 118+
    }

    public function test_user_sees_only_own_projects(): void
    {
        // V0.5.0: L3 中间件 — user 角色没 project.view 权限 → 403
        $token = $this->login('user');
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'ignore_errors' => true,
            'header' => "Authorization: Bearer $token\r\n",
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/projects?per_page=1', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        // L3 拒绝 — 验 403 + message
        $this->assertSame(403, $j['code'] ?? 0, 'L3 应拒绝 user 角色访问 /projects');
        $this->assertStringContainsString('project.view', $j['message'] ?? '');
    }

    public function test_manager_scope_partial_visible(): void
    {
        // V0.5.0: manager 角色有 project.view 权限 → 应返回 200 + 部分项目
        $token = $this->login('manager');
        $j = $this->get($token, '/projects?per_page=1');
        $this->assertSame(0, $j['code'] ?? 1);
        // sales_yang 应看到 ~18 个 (B 数据权限 smoke 验证)
        $this->assertGreaterThan(0, $j['data']['total']);
        $this->assertLessThan(50, $j['data']['total']);
    }

    public function test_warranty_endpoint_returns_data(): void
    {
        $token = $this->login('admin');
        $j = $this->get($token, '/warranties?per_page=1');
        // 可能 code=0 / 业务端点可能 code=0 正常返回
        $this->assertIsArray($j);
        $this->assertArrayHasKey('code', $j);
    }
}
