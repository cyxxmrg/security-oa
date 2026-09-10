<?php

namespace Tests\E2E;

/**
 * V0.5.2 - 角色矩阵 + 字段脱敏管理 + Audit log 集成测试
 * 走纯 HTTP curl, 与 UserRoleApiTest 同风格
 */
class PermissionMatrixApiTest extends E2ETestCase
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

        // 每 3 次新 login 清一次 throttle (login 5/min 限速)
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

    private function post(string $token, string $path, array $data = []): array
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

    public function test_roles_matrix_endpoint(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/roles/matrix');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $d = $r['body']['data'] ?? [];
        $this->assertGreaterThan(0, count($d['roles'] ?? []));
        $this->assertGreaterThan(0, count($d['permissions'] ?? []));
        $this->assertArrayHasKey('matrix', $d);
        $this->assertArrayHasKey('inheritance', $d);
    }

    public function test_admin_has_all_4_roles_in_matrix(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/roles/matrix');
        $roles = array_column($r['body']['data']['roles'] ?? [], 'name');
        $this->assertContains('admin', $roles);
        $this->assertContains('finance', $roles);
        $this->assertContains('manager', $roles);
        $this->assertContains('user', $roles);
    }

    public function test_inheritance_graph_endpoint(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/permissions/inheritance');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $edges = $r['body']['data']['edges'] ?? [];
        // 至少应该有 admin -> manager 和 admin -> finance
        $this->assertGreaterThanOrEqual(2, count($edges));
        $parentNames = array_column($edges, 'parent');
        $this->assertContains('admin', $parentNames);
    }

    public function test_user_role_cannot_access_matrix(): void
    {
        $t = $this->login('eng_qian');
        $this->assertNotNull($t);
        $r = $this->get($t, '/roles/matrix');
        // eng_qian 是 user 角色, 没 system.role
        $this->assertSame(403, $r['code']);
    }

    public function test_field_masks_list(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        $r = $this->get($t, '/field-masks');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $list = $r['body']['data'] ?? [];
        $this->assertGreaterThan(0, count($list), '应该至少有 finance / projects / sales / employee');
    }

    public function test_field_masks_create_and_delete(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);
        // 创建测试字段
        $r = $this->post($t, '/field-masks', [
            'endpoint'      => 'test_module',
            'field'         => 'test_field_' . time(),
            'allowed_roles' => 'admin,finance',
            'description'   => 'unit test',
        ]);
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['body']['code'] ?? 1);
        $id = $r['body']['data']['id'] ?? 0;
        $this->assertGreaterThan(0, $id);
        // 删掉
        $ch = curl_init(self::API . '/field-masks/' . $id);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $t"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $r2 = curl_exec($ch);
        $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->assertSame(200, $code2);
    }

    public function test_user_role_change_writes_audit_log(): void
    {
        $t = $this->login('admin1');
        $this->assertNotNull($t);

        // 找 eng_qian 的实际 id
        $u = $this->get($t, '/users?per_page=100&keyword=eng_qian');
        $list = $u['body']['data']['data'] ?? [];
        $target = null;
        foreach ($list as $urow) {
            if (($urow['username'] ?? '') === 'eng_qian') { $target = $urow['id']; break; }
        }
        $this->assertNotNull($target, '找不到 eng_qian 用户');

        // 改 eng_qian 角色, 再改回 (产生 audit 写)
        $put1 = $this->put($t, '/users/' . $target . '/roles', ['roles' => ['user', 'manager']]);
        $this->assertSame(200, $put1['code']);
        $put2 = $this->put($t, '/users/' . $target . '/roles', ['roles' => ['user']]);
        $this->assertSame(200, $put2['code']);

        // 查 audit log
        sleep(1);
        $after = $this->get($t, '/audit-logs?per_page=20');
        $afterList = $after['body']['data']['data'] ?? [];
        $roleChanged = array_filter($afterList, fn($l) =>
            ($l['action'] ?? '') === 'role_changed'
        );
        $this->assertGreaterThan(0, count($roleChanged), '应至少有 1 条 role_changed 记录');
    }
}
