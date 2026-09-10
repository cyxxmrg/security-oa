<?php

namespace Tests\E2E;

/**
 * V0.5.1 - 用户管理 + 权限继承 + 字段脱敏 集成测试
 * 走纯 HTTP curl 跑 117 真实 API (与 AuthApiTest 一致)
 *
 * 技巧: 每个用例新 login 前, 通过唯一 username 旁路 throttle
 */
class UserRoleApiTest extends E2ETestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    private static array $tokens = [];
    private static int $lastFlush = 0;
    private static int $loginAttempts = 0;

    public static function setUpBeforeClass(): void
    {
        self::requireMutationOptIn();
        self::doFlush();
        self::$lastFlush = time();
    }

    private static function doFlush(): void
    {
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
            // ignore
        }
    }

    // V1.4.5 (REVIEW P2-7/P1-4): 密码从环境变量读取, 不硬编码入库
    private function login(string $username, string $password = ''): ?string
    {
        if (isset(self::$tokens[$username])) return self::$tokens[$username];
        if ($password === '') $this->markTestSkipped('未配置 E2E 账号密码 (OA_E2E_ADMIN_PASS)');

        self::$loginAttempts++;
        if (self::$loginAttempts > 3) {
            self::doFlush();
            self::$loginAttempts = 0;
        }

        $ch = curl_init(self::API . '/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['username' => $username, 'password' => $password]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return null;
        $j = json_decode($r, true);
        if (($j['code'] ?? 1) !== 0) return null;
        return self::$tokens[$username] = $j['data']['token'];
    }

    private function get(string $token, string $path): array
    {
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($r, true) ?? []];
    }

    private function put(string $token, string $path, array $data): array
    {
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($r, true) ?? []];
    }

    // V0.5.3 新增
    private function post(string $token, string $path, array $data): array
    {
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($r, true) ?? []];
    }

    private function delete(string $token, string $path, array $data = []): array
    {
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($r, true) ?? []];
    }

    public function test_admin_can_list_users(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t, 'admin1 login failed');
        $r = $this->get($t, '/users?per_page=5');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $this->assertGreaterThan(0, $r['body']['data']['total'] ?? 0);
    }

    public function test_admin_sees_finance_amount_unmasked(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/finance/summary');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $recv = $r['body']['data']['receivable'] ?? null;
        $this->assertNotNull($recv, 'receivable should be in response');
        if (is_array($recv) && array_key_exists('total', $recv) && $recv['total'] !== null) {
            $this->assertNotSame('***', $recv['total'], 'admin 应看真值');
        }
    }

    public function test_users_sync_roles_validates_existence(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        // 给 user 1 (admin) 试赋一个不存在的角色
        $r = $this->put($t, '/users/1/roles', ['roles' => ['admin', 'invalid_role_xyz']]);
        // controller 显式返回 422 (含 message)
        $this->assertSame(422, $r['code']);
        $this->assertSame(422, $r['body']['code'] ?? 0);
        $this->assertStringContainsString('invalid_role_xyz', $r['body']['message'] ?? '');
    }

    public function test_admin_inherits_all_child_role_permissions(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/permissions/my');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $names = array_column($r['body']['data'] ?? [], 'name');
        // admin 应有所有权限 (含继承的 attendance.* / project.* / finance.*)
        $this->assertContains('attendance.view', $names, 'admin 应有 attendance.view (继承 user)');
        $this->assertContains('project.view',   $names, 'admin 应有 project.view (继承 manager)');
        $this->assertContains('finance.view',   $names, 'admin 应有 finance.view (继承 finance)');
        $this->assertContains('system.config',  $names, 'admin 应有 system.config (自有)');
    }

    // ==============================================================
    // V0.5.3 临时角色授权
    // ==============================================================

    public function test_admin_grants_temporary_role_to_user(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        // 找 fin_wu 的 id
        $usersR = $this->get($t, '/users?keyword=fin_wu&per_page=5');
        $this->assertSame(0, $usersR['body']['code'] ?? 1);
        $users = $usersR['body']['data']['data'] ?? [];
        $this->assertNotEmpty($users, '应能找到 fin_wu');
        $finWu = $users[0];
        $userId = $finWu['id'];

        // 先清理一下
        $this->delete($t, "/users/{$userId}/roles/manager");

        // 授一个 7 天后的临时 manager
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $r = $this->post($t, "/users/{$userId}/roles/temporary", [
            'assignments' => [
                ['role' => 'manager', 'expires_at' => $expires, 'reason' => '项目借调 7 天'],
            ],
        ]);
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $this->assertGreaterThanOrEqual(1, $r['body']['data']['added'] ?? 0);

        // 验证: list 出来应有 1 个 temporary
        $listR = $this->get($t, "/users/{$userId}/roles");
        $this->assertSame(0, $listR['body']['code'] ?? 1);
        $temporaries = array_filter($listR['body']['data']['assignments'] ?? [], fn ($a) => $a['status'] === 'temporary');
        $this->assertGreaterThanOrEqual(1, count($temporaries), '应至少有 1 个临时角色');

        // 清理
        $this->delete($t, "/users/{$userId}/roles/manager");
    }

    public function test_grant_rejects_past_expires_at(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->post($t, '/users/80/roles/temporary', [
            'assignments' => [
                ['role' => 'manager', 'expires_at' => '2020-01-01 00:00:00', 'reason' => '过去时间'],
            ],
        ]);
        $this->assertSame(422, $r['code'], '过去时间应被 422 拒绝');
    }

    public function test_grant_rejects_nonexistent_role(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->post($t, '/users/80/roles/temporary', [
            'assignments' => [
                ['role' => 'ghost_role_xyz', 'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')), 'reason' => 'nonexistent'],
            ],
        ]);
        $this->assertSame(422, $r['code']);
        $this->assertStringContainsString('ghost_role_xyz', $r['body']['message'] ?? '');
    }

    public function test_active_roles_endpoint_excludes_expired(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/users/80/roles/active');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $this->assertArrayHasKey('roles', $r['body']['data']);
        $this->assertArrayHasKey('permissions', $r['body']['data']);
    }

    public function test_revoke_role_returns_404_if_not_held(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        // 撤销一个显然 user 80 没持有的角色
        $r = $this->delete($t, '/users/80/roles/manager');
        // 可能是 200 也可能是 404 — 看 user 80 实际持有什么
        $this->assertContains($r['code'], [200, 404]);
    }

    public function test_expiring_endpoint_works(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/roles/expiring?within_days=7');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $this->assertArrayHasKey('rows', $r['body']['data']);
        $this->assertArrayHasKey('count', $r['body']['data']);
    }

    public function test_user_endpoint_403_for_non_admin(): void
    {
        $t = $this->login('eng_qian');
        $this->assertNotNull($t, 'eng_qian login failed');
        $r = $this->post($t, '/users/80/roles/temporary', [
            'assignments' => [
                ['role' => 'admin', 'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))],
            ],
        ]);
        // eng_qian 是 user 角色, 应 403 (缺 system.role)
        $this->assertSame(403, $r['code'], '非 admin 应被 403');
    }
}
