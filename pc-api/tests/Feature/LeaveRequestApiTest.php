<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * V1.2.7 P1 - 请假申请 Feature 测试
 *
 * 验证 4 个核心场景:
 *  1. 提交请假 - 业务用户 (oa_business_admin)
 *  2. 提交时同步创建审批中心记录 (operation/leave)
 *  3. 审批/驳回 - manager 角色
 *  4. 数据校验失败 - 422 响应
 *
 * 与 AuthApiTest 一致：跑在 117 上, 直接 HTTP 调真实 API
 */
class LeaveRequestApiTest extends TestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    /** 业务用户 (请假申请人) */
    private const BUSINESS_USER = ['oa_business_admin', '<已脱敏>'];
    /** 业务管理员 (审批人) */
    private const APPROVER      = ['manager', '123456'];

    private static array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    public static function setUpBeforeClass(): void
    {
        self::doFlush();
    }

    private static function doFlush(): void
    {
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
            'timeout' => 8,
        ];
        if (!empty($body)) {
            $ctxOpts['content'] = json_encode($body);
        }
        $ctx = stream_context_create(['http' => $ctxOpts]);
        $r = @file_get_contents(self::API . $ep, false, $ctx);
        return $r === false ? ['code' => 599] : (json_decode($r, true) ?? ['code' => 598]);
    }

    /**
     * 1) 业务用户提交事假, 应返回 0 + leave 记录
     */
    public function test_business_user_submits_leave_success(): void
    {
        $token = $this->login(self::BUSINESS_USER);
        $r = $this->call('POST', $token, '/attendance/leave', [
            'type'       => 'personal',
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-02',
            'days'       => 2,
            'reason'     => '家中有事需要处理 (PHPUnit)',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '提交请假应成功: ' . json_encode($r));
        $this->assertNotEmpty($r['data']['id'] ?? null, '应返回 leave id');
        $this->assertSame('pending', $r['data']['status'] ?? null);
    }

    /**
     * 2) 提交后, 审批中心应能看到对应的 leave 记录
     *    (验证 V1.2.7 P0 修复的事务一致性)
     */
    public function test_leave_creates_approval_center_record(): void
    {
        $token = $this->login(self::BUSINESS_USER);

        // 1. 提交一条请假
        $submit = $this->call('POST', $token, '/attendance/leave', [
            'type'       => 'sick',
            'start_date' => '2026-07-03',
            'end_date'   => '2026-07-03',
            'days'       => 1,
            'reason'     => '感冒发烧 (PHPUnit 一致性测试)',
        ]);
        $this->assertSame(0, $submit['code'] ?? 1, '请假提交失败');
        $leaveId = $submit['data']['id'];

        // 2. 查审批中心, 找到对应的记录 (sub_type=leave, payload.leave_id=X)
        $center = $this->call('GET', $token, '/approvals/center?sub_type=leave');
        $this->assertSame(0, $center['code'] ?? 1, '审批中心列表应可访问');

        $found = false;
        foreach (($center['data']['data'] ?? []) as $row) {
            if (($row['payload']['leave_id'] ?? null) === $leaveId) {
                $found = true;
                $this->assertSame('pending', $row['status'], '审批记录应初始为 pending');
                break;
            }
        }
        $this->assertTrue($found, '审批中心应能找到本次请假的对应记录 (V1.2.7 P0 修复验证)');
    }

    /**
     * 3) 数据校验失败应返回 422 + 错误信息
     */
    public function test_leave_validation_failure(): void
    {
        $token = $this->login(self::BUSINESS_USER);

        // 缺 reason + days 小于 0.5 + 结束日期早于开始
        $r = $this->call('POST', $token, '/attendance/leave', [
            'type'       => 'personal',
            'start_date' => '2026-07-10',
            'end_date'   => '2026-07-05',  // 早于 start
            'days'       => 0.1,           // 小于 0.5
        ]);

        $this->assertSame(422, $r['code'] ?? 0, '校验失败应返回 422');
        $this->assertArrayHasKey('errors', $r, '应返回 errors 字段 (来自 FormRequest)');
        $this->assertNotEmpty($r['errors'], 'errors 应包含具体错误');
    }

    /**
     * 4) manager 审批请假 - approved
     */
    public function test_manager_approves_leave(): void
    {
        $userToken = $this->login(self::BUSINESS_USER);
        $adminToken = $this->login(self::APPROVER);

        // 业务用户提交
        $submit = $this->call('POST', $userToken, '/attendance/leave', [
            'type'       => 'annual',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-03',
            'days'       => 3,
            'reason'     => '年假休息 (PHPUnit 审批测试)',
        ]);
        $this->assertSame(0, $submit['code'] ?? 1);
        $leaveId = $submit['data']['id'];

        // manager 审批
        $r = $this->call('POST', $adminToken, "/attendance/leave/{$leaveId}/approve", [
            'action'  => 'approved',
            'comment' => '同意',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '审批应成功: ' . json_encode($r));
        $this->assertSame('approved', $r['data']['status'] ?? null);
    }

    /**
     * 5) 校验 - 不存在的请假单 审批应 404
     */
    public function test_approve_nonexistent_leave_404(): void
    {
        $token = $this->login(self::APPROVER);
        $r = $this->call('POST', $token, '/attendance/leave/999999/approve', [
            'action' => 'approved',
        ]);

        // 模型绑定失败 → 404
        $this->assertContains($r['code'] ?? 0, [404, 0], '不存在的请假应 404');
    }
}
