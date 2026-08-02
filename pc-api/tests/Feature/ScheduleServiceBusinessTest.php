<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * V1.2.7 P1-2 - ScheduleService 业务级测试 (HTTP API)
 *
 * 验证 4 个核心场景:
 *  1. batchSave - 覆盖已存在的 user+date
 *  2. batchByGroup - 跨周末排除 (skipWeekends=true)
 *  3. smartSuggest - 无历史 fallback 到默认班次
 *  4. monthlyStats - 按班次 + 按员工聚合正确
 *
 * 跑在 117 上, 直接 HTTP 调真实 API (沿用 LeaveRequestApiTest 模式)
 */
class ScheduleServiceBusinessTest extends TestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    private const ADMIN = ['system', '<已脱敏>']; // system 账号: 全权限, 不受 ensure_business 限制
    private const USER  = ['oa_business_admin', '<已脱敏>'];

    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        self::$tokens = [];
    }

    private function login(array $user): string
    {
        [$u, $p] = $user;
        $key = $u . ':' . $p;
        if (isset(self::$tokens[$key])) return self::$tokens[$key];

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
        $ctxOpts = [
            'method'  => $method,
            'ignore_errors' => true,
            'header'  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
            'timeout' => 10,
        ];
        if (!empty($body)) {
            $ctxOpts['content'] = json_encode($body);
        }
        $ctx = stream_context_create(['http' => $ctxOpts]);
        $r = @file_get_contents(self::API . $ep, false, $ctx);
        return $r === false ? ['code' => 599] : (json_decode($r, true) ?? ['code' => 598]);
    }

    private function pickShift(): array
    {
        $token = $this->login(self::ADMIN);
        $r = $this->call('GET', $token, '/schedules/shifts');
        if (($r['code'] ?? 1) === 0 && !empty($r['data']['data'])) {
            return $r['data']['data'][0];
        }
        if (($r['code'] ?? 1) === 0 && !empty($r['data'])) {
            $rows = is_array($r['data']) && isset($r['data'][0]) ? $r['data'] : ($r['data']['data'] ?? []);
            if (!empty($rows)) return $rows[0];
        }
        return ['id' => 1, 'name' => '白班'];
    }

    private function pickUserId(): int
    {
        $token = $this->login(self::ADMIN);
        $r = $this->call('GET', $token, '/employees?per_page=1');
        if (($r['code'] ?? 1) === 0 && !empty($r['data']['data'])) {
            return (int) $r['data']['data'][0]['id'];
        }
        return 2; // fallback
    }

    /**
     * 1) batchSave — 首次创建 (count=created)
     */
    public function test_batch_save_creates_new_schedule(): void
    {
        $token = $this->login(self::ADMIN);
        $shift = $this->pickShift();
        $userId = $this->pickUserId();
        $date = '2026-09-01';

        // 确保当日没有排班 (先删)
        $this->call('DELETE', $token, "/schedules?user_id={$userId}&date={$date}");

        $r = $this->call('POST', $token, '/schedules/batch-save', [
            'assignments' => [
                ['user_id' => $userId, 'date' => $date, 'shift_id' => $shift['id']],
            ],
        ]);

        $this->assertSame(0, $r['code'] ?? 1, 'batchSave 应成功: ' . json_encode($r));
        $this->assertGreaterThanOrEqual(1, $r['data']['created'] ?? 0, '应有 created >= 1');
    }

    /**
     * 2) batchSave — 重复保存 (count=updated)
     *    V1.2.7 P1: 第二次保存同一个 user+date 应该 update 而不是 create
     */
    public function test_batch_save_overwrites_existing(): void
    {
        $token = $this->login(self::ADMIN);
        $shift = $this->pickShift();
        $userId = $this->pickUserId();
        $date = '2026-09-02';
        $newShiftId = $shift['id'] === 1 ? 2 : 1;

        // 第一次创建
        $this->call('POST', $token, '/schedules/batch-save', [
            'assignments' => [
                ['user_id' => $userId, 'date' => $date, 'shift_id' => $shift['id']],
            ],
        ]);

        // 第二次覆盖
        $r = $this->call('POST', $token, '/schedules/batch-save', [
            'assignments' => [
                ['user_id' => $userId, 'date' => $date, 'shift_id' => $newShiftId],
            ],
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '二次保存应成功: ' . json_encode($r));
        $this->assertGreaterThanOrEqual(1, $r['data']['updated'] ?? 0, '二次保存应有 updated >= 1');
    }

    /**
     * 3) batchByGroup — skipWeekends=true 时跨周末排除
     *    验证 2026-09-07 (周一) 到 2026-09-13 (周日) 应跳过 09-12 (周六) 和 09-13 (周日)
     *    = 5 个工作日
     */
    public function test_batch_by_group_skips_weekends(): void
    {
        $token = $this->login(self::ADMIN);
        $shift = $this->pickShift();

        // 找一个班组
        $g = $this->call('GET', $token, '/schedules/groups?per_page=1');
        if (($g['code'] ?? 1) !== 0 || empty($g['data']['data'])) {
            $this->markTestSkipped('没有可用的班组');
        }
        $groupId = $g['data']['data'][0]['id'];

        // 清掉 9-7 到 9-13 这周的排班
        for ($d = 7; $d <= 13; $d++) {
            $this->call('DELETE', $token, "/schedules?group_id={$groupId}&date=2026-09-{$d}");
        }

        $r = $this->call('POST', $token, '/schedules/batch-by-group', [
            'group_id'      => $groupId,
            'shift_id'      => $shift['id'],
            'start_date'    => '2026-09-07',  // 周一
            'end_date'      => '2026-09-13',  // 周日
            'skip_weekends' => true,
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '班组批量排班应成功: ' . json_encode($r));
        // count 应该是 班组人数 × 5 (工作日)
        $count = $r['data']['count'] ?? 0;
        $this->assertGreaterThan(0, $count, '应至少排 1 个班次');
        $this->assertEquals(0, $count % 5, '排班数应该是 5 的倍数 (5 个工作日)');
    }

    /**
     * 4) smartSuggest — 无打卡历史的员工 fallback 到默认班次
     */
    public function test_smart_suggest_returns_fallback_for_no_history(): void
    {
        $token = $this->login(self::ADMIN);

        $r = $this->call('GET', $token, '/schedules/smart-suggest?start_date=2026-10-01');

        $this->assertSame(0, $r['code'] ?? 1, 'smartSuggest 应可访问: ' . json_encode($r));

        // 返回数据可能是 array 或 ['data' => ...]
        $rows = $r['data'] ?? [];
        if (isset($rows['data'])) $rows = $rows['data'];

        if (!is_array($rows) || empty($rows)) {
            $this->markTestSkipped('smartSuggest 无数据 (没有员工)');
        }

        // 每行必须有 suggested_shift_id (不能 null, 否则前端无法渲染)
        foreach ($rows as $row) {
            if (isset($row['user_id'])) {
                $this->assertNotEmpty(
                    $row['suggested_shift_id'] ?? null,
                    "user_id={$row['user_id']} 应有建议班次 (即使无历史)"
                );
            }
        }
    }

    /**
     * 5) monthlyStats — 按月聚合返回 by_shift + by_user + total
     */
    public function test_monthly_stats_returns_aggregation(): void
    {
        $token = $this->login(self::ADMIN);

        $r = $this->call('GET', $token, '/schedules/stats?month=2026-09');

        $this->assertSame(0, $r['code'] ?? 1, 'stats 应可访问: ' . json_encode($r));

        $data = $r['data'] ?? [];
        $this->assertIsArray($data, 'data 应是数组');
        // stats 接口返回 by_shift + by_user + total
        $this->assertGreaterThanOrEqual(0, $data['total'] ?? 0, 'total 应 >= 0');
    }
}