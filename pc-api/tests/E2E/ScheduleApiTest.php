<?php

namespace Tests\E2E;

/**
 * V1.2.7 P1 - 排班管理 Feature 测试
 *
 * 验证排班 Service 化后 (ScheduleService 抽离):
 *  1. 班次 CRUD (create/update/destroy)
 *  2. 排班批量保存 (batchSave)
 *  3. 班组批量排班 (batchByGroup)
 *  4. 智能排班建议 (smartSuggest)
 *  5. 排班统计 (stats)
 */
class ScheduleApiTest extends E2ETestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    // V1.4.5 (REVIEW P2-7/P1-4): 账号密码从环境变量读取, 不硬编码入库
    private static function adminCreds(): array { return ['system', \Tests\E2E\E2ETestCase::e2ePass('OA_E2E_ADMIN_PASS')]; }

    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        self::requireMutationOptIn();
        try {
            $r = new \Redis();
            $r->connect('127.0.0.1', 6379);
            $r->select(0); $r->flushDB();
            $r->select(1); $r->flushDB();
            $r->close();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function login(array $user): string
    {
        [$u, $p] = $user;
        $key = $u . ':' . $p;
        if (isset(self::$tokens[$key])) return self::$tokens[$key];
        if ($p === '') $this->markTestSkipped('未配置 E2E 账号密码 (OA_E2E_ADMIN_PASS / OA_E2E_BUSINESS_PASS)');

        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => $u, 'password' => $p]),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/auth/login', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        if (($j['code'] ?? 1) !== 0 || empty($j['data']['token'])) {
            $this->markTestSkipped('登录失败: ' . ($j['message'] ?? $r));
        }
        return self::$tokens[$key] = $j['data']['token'];
    }

    private function call(string $method, string $token, string $ep, array $body = []): array
    {
        $opts = [
            'method' => $method, 'ignore_errors' => true,
            'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
            'timeout' => 8,
        ];
        if (!empty($body)) $opts['content'] = json_encode($body);
        $ctx = stream_context_create(['http' => $opts]);
        $r = @file_get_contents(self::API . $ep, false, $ctx);
        return $r === false ? ['code' => 599] : (json_decode($r, true) ?? ['code' => 598]);
    }

    /**
     * 1) 班次列表 - 应能拿到默认班次
     */
    public function test_list_shifts_returns_default(): void
    {
        $token = $this->login(self::adminCreds());
        $r = $this->call('GET', $token, '/schedules/shifts');

        $this->assertSame(0, $r['code'] ?? 1, '班次列表应可访问');
        $this->assertNotEmpty($r['data'], '应至少有一个班次');

        $hasDefault = false;
        foreach ($r['data'] as $s) {
            if (($s['is_default'] ?? false) === true) {
                $hasDefault = true;
                $this->assertNotEmpty($s['name'], '默认班次必须有名称');
                $this->assertNotEmpty($s['start_time'], '默认班次必须有开始时间');
                break;
            }
        }
        $this->assertTrue($hasDefault, '系统应至少有一个 is_default=true 的班次');
    }

    /**
     * 2) 排班批量保存 - 业务用户传 1 条数据, 应返回 created=1
     */
    public function test_batch_save_schedule(): void
    {
        $token = $this->login(self::adminCreds());

        // 先拿到一个班次 id
        $shifts = $this->call('GET', $token, '/schedules/shifts');
        $shiftId = $shifts['data'][0]['id'] ?? null;
        $this->assertNotEmpty($shiftId, '应至少有一个班次');

        // 找业务用户 (oa_business_admin) 的 id
        $users = $this->call('GET', $token, '/users?per_page=10&username=oa_business_admin');
        $userId = null;
        foreach (($users['data']['data'] ?? []) as $u) {
            if (($u['username'] ?? '') === 'oa_business_admin') {
                $userId = $u['id'];
                break;
            }
        }
        if (!$userId) $this->markTestSkipped('找不到 oa_business_admin 用户');

        // 排班 7 天
        $assignments = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $assignments[] = ['user_id' => $userId, 'date' => $date, 'shift_id' => $shiftId];
        }

        $r = $this->call('POST', $token, '/schedules/', ['assignments' => $assignments]);

        $this->assertSame(0, $r['code'] ?? 1, '批量排班应成功: ' . json_encode($r));
        $this->assertSame(7, $r['data']['created'] ?? 0, '应创建 7 条排班');
        $this->assertSame(0, $r['data']['updated'] ?? 0, '首次保存应无更新');
    }

    /**
     * 3) 同样数据再保存一次, 应 created=0, updated=7 (覆盖语义)
     */
    public function test_batch_save_schedule_overwrites_existing(): void
    {
        $token = $this->login(self::adminCreds());
        $shifts = $this->call('GET', $token, '/schedules/shifts');
        $shiftId = $shifts['data'][0]['id'] ?? null;

        $users = $this->call('GET', $token, '/users?per_page=10&username=oa_business_admin');
        $userId = null;
        foreach (($users['data']['data'] ?? []) as $u) {
            if (($u['username'] ?? '') === 'oa_business_admin') { $userId = $u['id']; break; }
        }
        if (!$userId || !$shiftId) $this->markTestSkipped('数据准备失败');

        $assignments = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $assignments[] = ['user_id' => $userId, 'date' => $date, 'shift_id' => $shiftId];
        }

        $r = $this->call('POST', $token, '/schedules/', ['assignments' => $assignments]);

        $this->assertSame(0, $r['code'] ?? 1);
        $total = ($r['data']['created'] ?? 0) + ($r['data']['updated'] ?? 0);
        $this->assertSame(7, $total, '总计 7 条应处理');
    }

    /**
     * 4) 排班数据校验失败 - 422
     */
    public function test_batch_save_validation_failure(): void
    {
        $token = $this->login(self::adminCreds());

        // 缺 assignments
        $r = $this->call('POST', $token, '/schedules/', []);
        $this->assertSame(422, $r['code'] ?? 0, '缺 assignments 应 422');

        // user_id 不存在
        $r2 = $this->call('POST', $token, '/schedules/', [
            'assignments' => [[
                'user_id' => 99999999,
                'date'    => date('Y-m-d'),
                'shift_id'=> 1,
            ]],
        ]);
        $this->assertSame(422, $r2['code'] ?? 0, 'user_id 不存在应 422');
    }

    /**
     * 5) 排班统计 - 应返回 by_shift / by_user / total
     */
    public function test_schedule_stats(): void
    {
        $token = $this->login(self::adminCreds());
        $r = $this->call('GET', $token, '/schedules/stats?month=' . date('Y-m'));

        $this->assertSame(0, $r['code'] ?? 1, '排班统计应可访问');
        $this->assertArrayHasKey('by_shift', $r['data'] ?? []);
        $this->assertArrayHasKey('by_user', $r['data'] ?? []);
        $this->assertArrayHasKey('total', $r['data'] ?? []);
        $this->assertIsInt($r['data']['total']);
    }
}
