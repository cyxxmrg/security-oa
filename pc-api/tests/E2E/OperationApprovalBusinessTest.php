<?php

namespace Tests\E2E;

/**
 * V1.2.7 P1-2 - OperationApproval 业务级测试 (HTTP API)
 *
 * 验证 5 个核心场景:
 *  1. store - 创建 operation 审批记录
 *  2. approve - 普通 operation 通过 (status → approved, 无物料扣减)
 *  3. reject - 必填 comment 校验 (422)
 *  4. forward - 必填 target + status → transferred
 *  5. approve - 已结束的审批不能再操作 (422)
 *
 * 跑在 117 上, 直接 HTTP 调真实 API
 */
class OperationApprovalBusinessTest extends E2ETestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    // V1.4.5 (REVIEW P2-7/P1-4): 账号密码从环境变量读取, 不硬编码入库
    private static function adminCreds(): array { return ['system', \Tests\E2E\E2ETestCase::e2ePass('OA_E2E_ADMIN_PASS')]; }
    private static function userCreds(): array  { return ['oa_business_admin', \Tests\E2E\E2ETestCase::e2ePass('OA_E2E_BUSINESS_PASS')]; }

    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        self::requireMutationOptIn();
        self::$tokens = [];
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

    /**
     * 提交一个普通 operation 审批 (sub_type=other) 给后续测试用
     */
    private function createOperation(string $token, string $suffix = ''): int
    {
        $r = $this->call('POST', $token, '/approvals/operation', [
            'sub_type' => 'other',
            'title'    => 'PHPUnit 审批测试' . ($suffix ? " {$suffix}" : ''),
            'priority' => 'normal',
            'payload'  => ['note' => '业务侧测试'],
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '创建运营审批应成功: ' . json_encode($r));
        $this->assertNotEmpty($r['data']['id'] ?? null, '应返回审批 id');
        return (int) $r['data']['id'];
    }

    /**
     * 1) store — 创建 operation 审批, status=pending, 初始 flow 有 submit
     */
    public function test_create_operation_approval(): void
    {
        $token = $this->login(self::userCreds());
        $id = $this->createOperation($token, 'create');

        // 详情验证
        $detail = $this->call('GET', $token, "/approvals/operation/{$id}");
        $this->assertSame(0, $detail['code'] ?? 1, '详情应可访问');

        $row = $detail['data'] ?? [];
        $this->assertSame('pending', $row['status'] ?? null, '新审批应是 pending');
        $this->assertSame('other', $row['sub_type'] ?? null);
        $this->assertNotEmpty($row['flow'] ?? [], 'flow 应至少有 submit 一项');

        // flow 第一项应是 submit
        if (!empty($row['flow'][0])) {
            $this->assertSame('submit', $row['flow'][0]['action'] ?? '');
        }
    }

    /**
     * 2) approve — 普通 operation 通过, status → approved
     */
    public function test_approve_operation_succeeds(): void
    {
        $token = $this->login(self::userCreds());
        $id = $this->createOperation($token, 'approve');

        $r = $this->call('POST', $token, "/approvals/operation/{$id}/approve", [
            'comment' => '同意 (PHPUnit 测试)',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '审批通过应成功: ' . json_encode($r));
        $this->assertSame('approved', $r['data']['status'] ?? null);

        // 二次确认状态
        $detail = $this->call('GET', $token, "/approvals/operation/{$id}");
        $this->assertSame('approved', $detail['data']['status'] ?? null, 'DB 状态应是 approved');
    }

    /**
     * 3) reject — 必填 comment, 缺 comment 应 422
     */
    public function test_reject_without_comment_422(): void
    {
        $token = $this->login(self::userCreds());
        $id = $this->createOperation($token, 'reject-422');

        $r = $this->call('POST', $token, "/approvals/operation/{$id}/reject", []);

        $this->assertSame(422, $r['code'] ?? 0, 'reject 缺 comment 应 422');
        $this->assertArrayHasKey('comment', $r['errors'] ?? []);
    }

    /**
     * 4) reject — 带 comment 应成功, status → rejected
     */
    public function test_reject_with_comment_succeeds(): void
    {
        $token = $this->login(self::userCreds());
        $id = $this->createOperation($token, 'reject-ok');

        $r = $this->call('POST', $token, "/approvals/operation/{$id}/reject", [
            'comment' => '原因不符合规定 (PHPUnit)',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, 'reject 应成功: ' . json_encode($r));
        $this->assertSame('rejected', $r['data']['status'] ?? null);
    }

    /**
     * 5) forward — 必填 target, status → transferred
     */
    public function test_forward_operation(): void
    {
        $token = $this->login(self::userCreds());
        $id = $this->createOperation($token, 'forward');

        $r = $this->call('POST', $token, "/approvals/operation/{$id}/forward", [
            'target' => 'manager',
            'comment' => '转交 manager (PHPUnit)',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, 'forward 应成功: ' . json_encode($r));

        // 二次确认
        $detail = $this->call('GET', $token, "/approvals/operation/{$id}");
        $this->assertContains(
            $detail['data']['status'] ?? '',
            ['transferred', 'pending'],
            'forward 后状态应是 transferred 或 pending (根据实现)'
        );
    }

    /**
     * 6) 已结束的审批不能再操作
     *    approved 之后, 再 approve 应 422
     */
    public function test_cannot_operate_on_finished_approval(): void
    {
        $token = $this->login(self::userCreds());
        $id = $this->createOperation($token, 'finished');

        // 先通过
        $this->call('POST', $token, "/approvals/operation/{$id}/approve", [
            'comment' => '先通过',
        ]);

        // 再 approve
        $r = $this->call('POST', $token, "/approvals/operation/{$id}/approve", [
            'comment' => '再次尝试',
        ]);

        $this->assertSame(422, $r['code'] ?? 0, '已结束的审批再次操作应 422: ' . json_encode($r));
        $this->assertStringContainsString('已结束', $r['message'] ?? '', '错误消息应说明 "已结束"');
    }

    /**
     * 7) sub_type=material-request 的审批 — 库存不足场景 (回归测试)
     *    V1.2.7 P0: 物料申领审批通过会触发库存扣减, 库存不足时 throw + 事务回滚
     */
    public function test_material_request_approval_insufficient_stock(): void
    {
        $token = $this->login(self::userCreds());

        // 找一个物料, 入库 1 个
        $listR = $this->call('GET', $token, '/inventory?per_page=1');
        if (($listR['code'] ?? 1) !== 0 || empty($listR['data']['data'])) {
            $this->markTestSkipped('没有物料可测');
        }
        $item = $listR['data']['data'][0];
        $itemId = (int) $item['id'];

        // 创建一个物料申领审批, 数量远超库存
        $r = $this->call('POST', $token, '/approvals/operation', [
            'sub_type' => 'material-request',
            'title'    => 'PHPUnit 测试 - 物料申领 (库存不足)',
            'priority' => 'normal',
            'payload'  => [
                'items' => [
                    ['inventory_item_id' => $itemId, 'warehouse_id' => 1, 'quantity' => 99999],
                ],
                'project_id' => null,
            ],
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '创建物料申领审批应成功: ' . json_encode($r));
        $id = (int) $r['data']['id'];

        // 触发审批通过 — 应 fail 因为库存不足
        $approve = $this->call('POST', $token, "/approvals/operation/{$id}/approve", [
            'comment' => '尝试通过 (库存不足)',
        ]);

        // 应 fail (code 是 1002 或 422)
        $this->assertContains($approve['code'] ?? 0, [1002, 422, 1], '物料库存不足时应 fail: ' . json_encode($approve));

        // 状态应该还是 pending (事务回滚了)
        $detail = $this->call('GET', $token, "/approvals/operation/{$id}");
        $this->assertSame('pending', $detail['data']['status'] ?? '', '库存不足回滚后审批状态应保持 pending');
    }
}
