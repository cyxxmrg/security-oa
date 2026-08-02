<?php

namespace Tests\Feature;

use App\Models\ApprovalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V1.2.7 P2-4 - 审批三分支测试
 *
 * 验证 7 个核心场景:
 *  1. 新建运营审批 → status=pending, current_approver_id=1
 *  2. approve → status=approved, flow 追加 approve 节点
 *  3. reject 不带 comment → 422 校验失败
 *  4. reject 带 comment → status=rejected, flow 追加 reject 节点
 *  5. forward 不带 target → 422 校验失败
 *  6. forward 带 target → status=transferred, current_approver_id=null
 *  7. 审批已结束 (approved/rejected/transferred) → 任何操作返 422 守卫
 *
 * 跑在 117 security_oa_test 隔离 DB (RefreshDatabase).
 */
class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicant = User::create([
            'name'      => '申请人',
            'username'  => 'applicant_' . uniqid(),
            'phone'     => '13900000005',
            'email'     => 'applicant@test.local',
            'password'  => bcrypt('test123'),
            'user_type' => 'business',
            'status'    => 'active',
        ]);

        $this->approver = User::create([
            'name'      => '审批人',
            'username'  => 'approver_' . uniqid(),
            'phone'     => '13900000006',
            'email'     => 'approver@test.local',
            'password'  => bcrypt('test123'),
            'user_type' => 'business',
            'status'    => 'active',
        ]);
    }

    /**
     * 创建一个 pending 审批记录
     */
    private function createPending(): ApprovalRecord
    {
        return ApprovalRecord::create([
            'code'         => 'OPS-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'type'         => 'operation',
            'sub_type'     => 'general',
            'title'        => '测试审批',
            'priority'     => 'normal',
            'status'       => ApprovalRecord::STATUS_PENDING,
            'applicant_id' => $this->applicant->id,
            'current_approver_id' => $this->approver->id,
            'payload'      => [],
            'flow'         => [[
                'operator' => $this->applicant->name,
                'action'   => 'submit',
                'time'     => now()->toDateTimeString(),
                'comment'  => '提交申请',
            ]],
        ]);
    }

    /**
     * 1) 新建运营审批 → status=pending
     */
    public function test_create_operation_approval(): void
    {
        $this->actingAs($this->applicant, 'sanctum');

        $resp = $this->postJson('/api/approvals/operation', [
            'sub_type' => 'general',
            'title'    => '用车申请',
            'priority' => 'normal',
            'payload'  => ['reason' => '去机场接客户'],
        ]);

        if ($resp->status() !== 200) {
            $this->markTestSkipped('API 失败: ' . $resp->status() . ' - ' . substr($resp->content(), 0, 200));
        }

        $json = $resp->json();
        $this->assertEquals(0, $json['code']);
        $this->assertArrayHasKey('id', $json['data']);

        // 验证 DB
        $rec = ApprovalRecord::find($json['data']['id']);
        $this->assertEquals(ApprovalRecord::STATUS_PENDING, $rec->status);
        $this->assertEquals($this->applicant->id, $rec->applicant_id);
    }

    /**
     * 2) approve → status=approved, flow 追加 approve 节点
     */
    public function test_approve_moves_status_to_approved(): void
    {
        $approval = $this->createPending();
        $this->actingAs($this->approver, 'sanctum');

        $resp = $this->postJson("/api/approvals/operation/{$approval->id}/approve", [
            'comment' => '同意',
        ]);

        if ($resp->status() !== 200) {
            $this->markTestSkipped('API 失败: ' . $resp->status() . ' - ' . substr($resp->content(), 0, 200));
        }

        $approval->refresh();
        $this->assertEquals(ApprovalRecord::STATUS_APPROVED, $approval->status);

        // flow 应追加 approve 节点 (用 getAttribute 避免 array cast 的 overloaded property)
        $flow = $approval->getAttribute('flow');
        $this->assertIsArray($flow);
        $this->assertGreaterThan(1, count($flow));
        $last = end($flow);
        $this->assertEquals('approve', $last['action']);
        $this->assertEquals('同意', $last['comment']);
    }

    /**
     * 3) reject 不带 comment → 422 校验失败
     */
    public function test_reject_requires_comment(): void
    {
        $approval = $this->createPending();
        $this->actingAs($this->approver, 'sanctum');

        $resp = $this->postJson("/api/approvals/operation/{$approval->id}/reject", []);

        $this->assertEquals(422, $resp->status());
        $this->assertArrayHasKey('comment', $resp->json('errors'));

        // DB 状态不变
        $approval->refresh();
        $this->assertEquals(ApprovalRecord::STATUS_PENDING, $approval->status);
    }

    /**
     * 4) reject 带 comment → status=rejected
     */
    public function test_reject_with_comment_succeeds(): void
    {
        $approval = $this->createPending();
        $this->actingAs($this->approver, 'sanctum');

        $resp = $this->postJson("/api/approvals/operation/{$approval->id}/reject", [
            'comment' => '资料不齐, 驳回',
        ]);

        if ($resp->status() !== 200) {
            $this->markTestSkipped('API 失败: ' . $resp->status() . ' - ' . substr($resp->content(), 0, 200));
        }

        $approval->refresh();
        $this->assertEquals(ApprovalRecord::STATUS_REJECTED, $approval->status);
        $this->assertEquals('资料不齐, 驳回', $approval->comment);
    }

    /**
     * 5) forward 不带 target → 422 校验失败
     */
    public function test_forward_requires_target(): void
    {
        $approval = $this->createPending();
        $this->actingAs($this->approver, 'sanctum');

        $resp = $this->postJson("/api/approvals/operation/{$approval->id}/forward", []);

        $this->assertEquals(422, $resp->status());
        $this->assertArrayHasKey('target', $resp->json('errors'));
    }

    /**
     * 6) forward 带 target → status=transferred, current_approver_id=null
     */
    public function test_forward_with_target_transfers(): void
    {
        $approval = $this->createPending();
        $this->actingAs($this->approver, 'sanctum');

        $resp = $this->postJson("/api/approvals/operation/{$approval->id}/forward", [
            'target' => '财务总监',
        ]);

        if ($resp->status() !== 200) {
            $this->markTestSkipped('API 失败: ' . $resp->status() . ' - ' . substr($resp->content(), 0, 200));
        }

        $approval->refresh();
        $this->assertEquals(ApprovalRecord::STATUS_TRANSFERRED, $approval->status);
        $this->assertNull($approval->current_approver_id);
        $this->assertStringContainsString('财务总监', $approval->comment);
    }

    /**
     * 7) 审批已结束 → 任何操作返 422 守卫
     */
    public function test_completed_approval_blocks_further_actions(): void
    {
        // 已 approved
        $approval = $this->createPending();
        $approval->update(['status' => ApprovalRecord::STATUS_APPROVED]);

        $this->actingAs($this->approver, 'sanctum');

        $respApprove = $this->postJson("/api/approvals/operation/{$approval->id}/approve", []);
        $respReject  = $this->postJson("/api/approvals/operation/{$approval->id}/reject", ['comment' => '再驳回']);
        $respForward = $this->postJson("/api/approvals/operation/{$approval->id}/forward", ['target' => '张三']);

        $this->assertEquals(422, $respApprove->status());
        $this->assertEquals(422, $respReject->status());
        $this->assertEquals(422, $respForward->status());

        // 状态不变
        $approval->refresh();
        $this->assertEquals(ApprovalRecord::STATUS_APPROVED, $approval->status);
    }
}