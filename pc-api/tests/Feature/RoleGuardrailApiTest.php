<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * V1.4.3 RBAC 提权护栏回归测试 (对应 REVIEW_security-audit.md P0-1)
 *
 * 走真实 HTTP 打本地 API (与 UserRoleApiTest 一致, 前置: API 服务在跑 + 业务管理员账号可用)
 * 账号由环境变量注入: OA_TEST_USER / OA_TEST_PASS (V1.4.5 起不再硬编码默认密码)
 *  (示例: OA_TEST_USER=oa_business_admin OA_TEST_PASS='<已脱敏>' php vendor/bin/phpunit tests/Feature/RoleGuardrailApiTest.php)
 *
 * 覆盖的护栏 (RoleController):
 *   1. 内置高权限角色 (admin/system/system_admin) 仅 system 账号可调整
 *   2. 操作者不能调整自己所属角色的权限
 *   3. 操作者不能修改自己账号的角色
 *   4. 非 system 账号不能把 admin 角色授予他人
 *   5. 高敏感权限点 (system.* / user.manage 等) 仅 system 可注入业务角色
 *
 * 注: roles 路由表仅存在 store(POST /roles) 与 saveMenuPermissions(POST /roles/{role}/menu-permissions)
 *     等写入口; RoleController::update/destroy/assignPermissions 未挂 HTTP 路由(不在此测试范围)。
 */
class RoleGuardrailApiTest extends TestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    private static array $tokens = [];
    private static int $lastFlush = 0;
    private static int $loginAttempts = 0;

    private static function testUser(): string
    {
        return getenv('OA_TEST_USER') ?: 'admin';
    }

    private static function testPass(): string
    {
        // V1.4.5 (REVIEW P2-7/P1-4): 密码必须从环境变量注入, 不硬编码入库
        return getenv('OA_TEST_PASS') ?: '';
    }

    public static function setUpBeforeClass(): void
    {
        if (self::testPass() === '') {
            // 静态方法无法 markTestSkipped → 用失败信息引导配置 (同 requireMutationOptIn 风格)
            throw new \RuntimeException('未配置 OA_TEST_PASS 环境变量 (E2E/RoleGuardrail 测试需要真实账号密码)');
        }
        self::doFlush();
        self::$lastFlush = time();
    }

    private static function doFlush(): void
    {
        try {
            $r = new \Redis();
            $r->connect('127.0.0.1', 6379);
            $r->select(0);
            $r->flushDB();
            $r->close();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function login(): ?string
    {
        $username = self::testUser();
        if (isset(self::$tokens[$username])) {
            return self::$tokens[$username];
        }

        self::$loginAttempts++;
        if (self::$loginAttempts > 3) {
            self::doFlush();
            self::$loginAttempts = 0;
        }

        $ch = curl_init(self::API . '/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['username' => $username, 'password' => self::testPass()]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            return null;
        }
        $j = json_decode($r, true);
        if (($j['code'] ?? 1) !== 0) {
            return null;
        }
        return self::$tokens[$username] = $j['data']['token'];
    }

    private function request(string $method, string $token, string $path, array $data = []): array
    {
        $ch = curl_init(self::API . $path);
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer $token",
        ];
        $options = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        curl_setopt_array($ch, $options);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($r, true) ?? []];
    }

    private function ensureLogin(): ?string
    {
        $token = $this->login();
        if (!$token) {
            $this->markTestSkipped(
                '前置条件不满足: 业务管理员 ' . self::testUser() . ' 无法登录 (请确认 API 服务与账号/密码, 或用 OA_TEST_USER/OA_TEST_PASS 覆盖)'
            );
        }
        return $token;
    }

    /**
     * 业务管理员不能通过菜单权限矩阵改写内置 admin 角色权限 (admin 角色只读)
     */
    public function testBusinessAdminCannotSaveMenuPermissionsForAdminRole(): void
    {
        $token = $this->ensureLogin();
        $res = $this->request('POST', $token, '/roles/admin/menu-permissions', ['leaves' => ['system.role']]);
        $this->assertSame(403, $res['code'], '业务账号向 admin 角色写权限应被护栏拦截');
    }

    /**
     * 业务管理员不能给自己批量分配 admin (自助提权路径)
     */
    public function testBusinessAdminCannotBulkAssignAdminToSelf(): void
    {
        $token = $this->ensureLogin();
        $me = $this->request('GET', $token, '/auth/me');
        $ownId = $me['body']['data']['user']['id'] ?? null;
        if (!$ownId) {
            $this->markTestSkipped('无法从 /auth/me 解析当前用户 id');
        }
        $res = $this->request('POST', $token, '/users/bulk-assign-role', [
            'user_ids' => [(int) $ownId],
            'role'     => 'admin',
        ]);
        $this->assertSame(403, $res['code'], '业务账号批量给自己授 admin 应被护栏拦截');
    }

    /**
     * 业务管理员不能修改自己账号的角色 (usersSyncRoles 自改路径)
     */
    public function testBusinessAdminCannotSyncOwnRoles(): void
    {
        $token = $this->ensureLogin();
        $me = $this->request('GET', $token, '/auth/me');
        $ownId = $me['body']['data']['user']['id'] ?? null;
        if (!$ownId) {
            $this->markTestSkipped('无法从 /auth/me 解析当前用户 id');
        }
        $res = $this->request('PUT', $token, "/users/{$ownId}/roles", ['roles' => ['admin']]);
        $this->assertSame(403, $res['code'], '业务账号修改自己账号角色应被护栏拦截');
    }

    /**
     * 业务管理员不能通过"新建角色夹带高敏感权限"绕过 admin 只读限制 (store 加固)
     */
    public function testBusinessAdminCannotCreateRoleWithSensitivePermission(): void
    {
        $token = $this->ensureLogin();
        $res = $this->request('POST', $token, '/roles', [
            'name'        => 'guardrail_tmp_' . time(),
            'description' => 'guardrail test',
            'permissions' => ['system.role'],
        ]);
        $this->assertSame(403, $res['code'], '创建角色夹带 system.role 应被护栏拦截');
    }

    /**
     * 业务管理员不能给"其他角色"注入高敏感权限 (saveMenuPermissions 敏感权限加固)
     * 自包含: 先创建临时业务角色(允许), 再尝试注入 user.manage(应 403), 最后清理
     */
    public function testBusinessAdminCannotInjectSensitivePermissionIntoOtherRole(): void
    {
        $token = $this->ensureLogin();
        $roleName = 'guardrail_tmp_' . time();
        $created = $this->request('POST', $token, '/roles', [
            'name'        => $roleName,
            'description' => 'guardrail test',
        ]);
        if (($created['code'] ?? 0) !== 200) {
            $this->markTestSkipped('前置条件不满足: 业务管理员无法创建临时角色 (' . ($created['code'] ?? '?') . ')');
        }
        try {
            $res = $this->request('POST', $token, "/roles/{$roleName}/menu-permissions", ['leaves' => ['user.manage']]);
            $this->assertSame(403, $res['code'], '向其他角色注入 user.manage 应被护栏拦截');
        } finally {
            $this->request('DELETE', $token, "/roles/{$roleName}");
        }
    }

    // ============================================================
    // 正向用例: 护栏不应误伤正常授权流程 (建普通角色/授普通权限应放行)
    // ============================================================

    /**
     * 业务管理员可以创建普通业务角色 (无高敏感权限)
     */
    public function testBusinessAdminCanCreateNormalRole(): void
    {
        $token = $this->ensureLogin();
        $res = $this->request('POST', $token, '/roles', [
            'name'        => 'guardrail_ok_' . time() . '_' . random_int(100, 999),
            'description' => '正向用例',
        ]);
        $this->assertSame(200, $res['code'], '创建普通角色应被放行');
    }

    /**
     * 业务管理员可以给角色授予普通业务权限 (employee.view 等非敏感点)
     */
    public function testBusinessAdminCanGrantNormalPermission(): void
    {
        $token = $this->ensureLogin();
        $roleName = 'guardrail_ok_' . time() . '_' . random_int(100, 999);
        $created = $this->request('POST', $token, '/roles', ['name' => $roleName, 'description' => '正向用例']);
        if (($created['code'] ?? 0) !== 200) {
            $this->markTestSkipped('前置条件不满足: 无法创建临时角色 (' . ($created['code'] ?? '?') . ')');
        }
        $res = $this->request('POST', $token, "/roles/{$roleName}/menu-permissions", ['leaves' => ['employee.view', 'expense.view']]);
        $this->assertSame(200, $res['code'], '授予普通业务权限应被放行');
    }
}
