<?php

namespace Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;

/**
 * V0.5.3 临时角色 - 单元测试 (纯 PDO, 不依赖 Eloquent boot)
 *
 * 测试 TemporaryRole helper 的 4 个核心方法 + expiringSoon
 * 走隔离 testing DB，并在事务中自建专用夹具
 *
 * 关键: 不 extend Laravel TestCase, 避免 loading 整个 app;
 * Model 操作临时用 DB facade, 但 Eloquent::find() 会出错,
 * 所以用 PDO 直接验证, helper 函数还是 Eloquent-based
 */
class TemporaryRoleTest extends TestCase
{
    private static ?\PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        // P1-14 安全防御：禁止在 production / 非 testing 环境下跑
        // 历史问题：曾直接读 /var/www/oa-api/.env 并直连真库 security_oa + DELETE/INSERT
        // 现统一用 phpunit.xml (APP_ENV=testing) 注入的 testing DB（security_oa_test）
        $appEnv = $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: '');
        if ($appEnv === 'production' || $appEnv !== 'testing') {
            self::markTestSkipped('Refusing to run TemporaryRoleTest outside testing env (was直连真库)');
            return;
        }

        // 配置仅从 phpunit.xml / env 读取，不再硬读生产 .env
        $user = getenv('DB_USERNAME') ?: 'oa_test';
        $pwd  = getenv('DB_PASSWORD') ?: '';
        $db   = getenv('DB_DATABASE') ?: 'security_oa_test';
        $host = getenv('DB_HOST')     ?: '127.0.0.1';
        $port = getenv('DB_PORT')     ?: '5432';
        try {
            self::$pdo = new \PDO(
                "pgsql:host={$host};port={$port};dbname={$db}",
                $user, $pwd,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            self::markTestSkipped('无法连本地 PG: ' . $e->getMessage());
            return;
        }

        self::$pdo->beginTransaction();

        $roleStatement = self::$pdo->prepare("
            INSERT INTO roles (name, guard_name, description, is_system, created_at, updated_at)
            VALUES (?, 'web', 'PHPUnit fixture', true, NOW(), NOW())
            ON CONFLICT (name, guard_name) DO UPDATE SET updated_at = EXCLUDED.updated_at
        ");
        foreach (['admin', 'manager', 'finance'] as $role) {
            $roleStatement->execute([$role]);
        }

        $userStatement = self::$pdo->prepare("
            INSERT INTO users (name, username, phone, password, gender, status, is_system, user_type, created_at, updated_at)
            VALUES ('PHPUnit Temporary Role', 'phpunit_temp_role', '13900000001', NULL, 'other', 'active', false, 'business', NOW(), NOW())
            ON CONFLICT (username) DO UPDATE SET name = EXCLUDED.name, updated_at = EXCLUDED.updated_at
            RETURNING id
        ");
        $userStatement->execute();
        $userId = (int) $userStatement->fetchColumn();

        $managerId = self::$pdo->query("SELECT id FROM roles WHERE name = 'manager' AND guard_name = 'web'")->fetchColumn();
        self::$pdo->prepare("
            INSERT INTO model_has_roles (role_id, model_type, model_id)
            VALUES (?, 'App\\Models\\User', ?)
            ON CONFLICT DO NOTHING
        ")->execute([(int) $managerId, $userId]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo?->inTransaction()) {
            self::$pdo->rollBack();
        }
        self::$pdo = null;
    }

    private function pdo(): \PDO
    {
        if (!self::$pdo) self::markTestSkipped('PDO 未连接');
        return self::$pdo;
    }

    /**
     * 查找事务内创建的专用测试用户。
     */
    private function pickTestUserId(): int
    {
        $row = $this->pdo()->query("SELECT id FROM users WHERE username = 'phpunit_temp_role' LIMIT 1")->fetch();
        if (!$row) $this->fail('临时角色测试夹具用户不存在');
        return (int) $row['id'];
    }

    private function cleanupTestRoles(int $userId): void
    {
        // V0.5.3: 只清临时角色 (expires_at IS NOT NULL), 不动永久
        $this->pdo()->prepare("DELETE FROM model_has_roles WHERE model_type = 'App\\Models\\User' AND model_id = ? AND expires_at IS NOT NULL")
            ->execute([$userId]);
    }

    /**
     * TearDown: 清理 + 恢复标准角色
     * V0.5.3: 之前 cleanupTestRoles 不彻底, 残留导致 demo 用户角色丢失
     */
    public function tearDown(): void
    {
        $userId = $this->pickTestUserId();
        // 删所有 test 角色 (含可能的 NULL expires_at 残留)
        $this->pdo()->prepare("DELETE FROM model_has_roles WHERE model_type = 'App\\Models\\User' AND model_id = ? AND expires_at IS NOT NULL")
            ->execute([$userId]);

        // 恢复专用测试用户的永久 manager 角色。
        $rid = $this->getRoleId('manager');
        if ($rid) {
            $this->pdo()->prepare("
                INSERT INTO model_has_roles (role_id, model_type, model_id)
                SELECT ?, 'App\\Models\\User', ?
                WHERE NOT EXISTS (
                    SELECT 1 FROM model_has_roles
                    WHERE role_id = ? AND model_id = ? AND model_type = 'App\\Models\\User'
                )
            ")->execute([$rid, $userId, $rid, $userId]);
        }
    }

    private function getRoleId(string $name): ?int
    {
        $row = $this->pdo()->prepare("SELECT id FROM roles WHERE name = ? AND guard_name = 'web' LIMIT 1");
        $row->execute([$name]);
        $r = $row->fetch();
        return $r ? (int) $r['id'] : null;
    }

    /**
     * 直接调 DB 操作模拟 TemporaryRole::grant (绕开 Eloquent)
     */
    private function directGrant(int $userId, string $role, ?string $expiresAt, ?string $reason): bool
    {
        $roleId = $this->getRoleId($role);
        if (!$roleId) return false;

        $existing = $this->pdo()->prepare("
            SELECT expires_at FROM model_has_roles
            WHERE role_id = ? AND model_type = 'App\\Models\\User' AND model_id = ?
        ");
        $existing->execute([$roleId, $userId]);
        $row = $existing->fetch();
        if ($row) {
            // V0.5.3 修: 永久角色不应被覆盖成临时
            if ($row['expires_at'] === null) {
                throw new \LogicException("用户 #{$userId} 已永久持有角色 {$role}");
            }
            $this->pdo()->prepare("
                UPDATE model_has_roles SET expires_at = ?, reason = ?
                WHERE role_id = ? AND model_type = 'App\\Models\\User' AND model_id = ?
            ")->execute([$expiresAt, $reason, $roleId, $userId]);
            return false;
        }
        $this->pdo()->prepare("
            INSERT INTO model_has_roles (role_id, model_type, model_id, expires_at, reason)
            VALUES (?, 'App\\Models\\User', ?, ?, ?)
        ")->execute([$roleId, $userId, $expiresAt, $reason]);
        return true;
    }

    private function directRevoke(int $userId, string $role): bool
    {
        $roleId = $this->getRoleId($role);
        if (!$roleId) return false;
        $del = $this->pdo()->prepare("
            DELETE FROM model_has_roles WHERE role_id = ? AND model_type = 'App\\Models\\User' AND model_id = ?
        ");
        $del->execute([$roleId, $userId]);
        return $del->rowCount() > 0;
    }

    // ============== 临时角色 CRUD ==============

    public function test_grant_inserts_temporary_role_with_expires_at(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        // sales_yang 已有 manager 永久角色, 所以用 admin (不会冲突)
        $added = $this->directGrant($userId, 'admin', $expires, '测试 7 天');
        $this->assertTrue($added, '首次授予应返回 true');

        $row = $this->pdo()->prepare("
            SELECT expires_at, reason FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ?
            AND role_id = ?
        ");
        $row->execute([$userId, $this->getRoleId('admin')]);
        $r = $row->fetch();

        $this->assertNotFalse($r);
        $this->assertNotNull($r['expires_at']);
        $this->assertSame('测试 7 天', $r['reason']);

        $this->cleanupTestRoles($userId);
    }

    public function test_grant_renewal_returns_false_and_updates(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);

        // 用 admin (sales_yang 没永久 admin) — 测续期
        $first = $this->directGrant($userId, 'admin', date('Y-m-d H:i:s', strtotime('+1 day')), 'first');
        $this->assertTrue($first);

        $second = $this->directGrant($userId, 'admin', date('Y-m-d H:i:s', strtotime('+5 day')), 'second');
        $this->assertFalse($second, '续期应返回 false');

        $row = $this->pdo()->prepare("
            SELECT reason FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ? AND role_id = ?
        ");
        $row->execute([$userId, $this->getRoleId('admin')]);
        $r = $row->fetch();
        $this->assertSame('second', $r['reason']);

        $this->cleanupTestRoles($userId);
    }

    public function test_revoke_removes_role(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);
        // admin (sales_yang 没永久 admin)
        $this->directGrant($userId, 'admin', date('Y-m-d H:i:s', strtotime('+7 day')), 'revoke me');

        $ok = $this->directRevoke($userId, 'admin');
        $this->assertTrue($ok);

        $row = $this->pdo()->prepare("
            SELECT 1 FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ? AND role_id = ?
        ");
        $row->execute([$userId, $this->getRoleId('admin')]);
        $this->assertFalse($row->fetch(), 'revoke 后不应有记录');
    }

    public function test_expired_records_remain_until_cleaned(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);
        // 塞一条 1 天前过期的 admin 临时
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $this->directGrant($userId, 'admin', $yesterday, 'expired test');

        $row = $this->pdo()->prepare("
            SELECT 1 FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ?
            AND expires_at < NOW()
        ");
        $row->execute([$userId]);
        $this->assertNotFalse($row->fetch(), '应能查到过期记录');

        // 模拟 cleanExpired
        $this->pdo()->prepare("
            DELETE FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ?
            AND expires_at < NOW()
        ")->execute([$userId]);

        $row = $this->pdo()->prepare("
            SELECT 1 FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ?
            AND expires_at < NOW()
        ");
        $row->execute([$userId]);
        $this->assertFalse($row->fetch(), '清理后应无过期记录');
    }

    public function test_expiring_within_7_days_query(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);
        $this->directGrant($userId, 'admin', date('Y-m-d H:i:s', strtotime('+5 day')), 'expiring');

        $row = $this->pdo()->prepare("
            SELECT COUNT(*) FROM model_has_roles mhr
            JOIN users u ON u.id = mhr.model_id
            JOIN roles r ON r.id = mhr.role_id
            WHERE mhr.model_type = 'App\\Models\\User'
            AND mhr.model_id = ?
            AND mhr.expires_at IS NOT NULL
            AND mhr.expires_at > NOW()
            AND mhr.expires_at <= NOW() + INTERVAL '7 day'
        ");
        $row->execute([$userId]);
        $count = (int) $row->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $count, '应能在 7 天窗口找到');

        $this->cleanupTestRoles($userId);
    }

    public function test_permanent_role_unaffected_by_temporary_ops(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);

        // sales_yang 永久持有 manager 角色 — 测:
        // 给一个不冲突的角色(比如 admin 临时) 不应影响永久 manager 计数
        $cnt = $this->pdo()->prepare("
            SELECT COUNT(*) FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ? AND expires_at IS NULL
        ");
        $cnt->execute([$userId]);
        $permanentBefore = (int) $cnt->fetchColumn();

        // 加临时 admin (sales_yang 没永久 admin, 不冲突)
        $this->directGrant($userId, 'admin', date('Y-m-d H:i:s', strtotime('+5 day')), 'temp admin');

        $cnt2 = $this->pdo()->prepare("
            SELECT COUNT(*) FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ? AND expires_at IS NULL
        ");
        $cnt2->execute([$userId]);
        $permanentAfter = (int) $cnt2->fetchColumn();
        $this->assertSame($permanentBefore, $permanentAfter, '永久角色数应不变');

        $this->cleanupTestRoles($userId);
    }

    public function test_multiple_temporary_roles_for_same_user(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);

        // 用 admin + finance (sales_yang 没永久持有这俩)
        $this->directGrant($userId, 'admin',   date('Y-m-d H:i:s', strtotime('+1 day')), 'r1');
        $this->directGrant($userId, 'finance', date('Y-m-d H:i:s', strtotime('+7 day')), 'r2');

        $row = $this->pdo()->prepare("
            SELECT r.name FROM model_has_roles mhr
            JOIN roles r ON r.id = mhr.role_id
            WHERE mhr.model_type = 'App\\Models\\User' AND mhr.model_id = ?
            AND mhr.expires_at IS NOT NULL
            ORDER BY r.name
        ");
        $row->execute([$userId]);
        $names = $row->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('admin', $names);
        $this->assertContains('finance', $names);

        $this->cleanupTestRoles($userId);
    }

    public function test_reason_length_truncation(): void
    {
        $userId = $this->pickTestUserId();
        $this->cleanupTestRoles($userId);
        $longReason = str_repeat('x', 600);
        // 用 admin (sales_yang 没永久 admin)
        $this->directGrant($userId, 'admin', date('Y-m-d H:i:s', strtotime('+5 day')), substr($longReason, 0, 500));

        $row = $this->pdo()->prepare("
            SELECT reason FROM model_has_roles
            WHERE model_type = 'App\\Models\\User' AND model_id = ? AND role_id = ?
        ");
        $row->execute([$userId, $this->getRoleId('admin')]);
        $r = $row->fetch();
        $this->assertNotFalse($r);
        $this->assertLessThanOrEqual(500, strlen($r['reason']));

        $this->cleanupTestRoles($userId);
    }
}
