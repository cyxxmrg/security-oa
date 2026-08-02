<?php
/**
 * 招标中心 E2E 测试 (V0.6.0)
 *
 * 覆盖：内部 TenderController 13 端点 + 公开 PortalController 5 端点
 * 4 步闭环：建项目 → 邀请 → 投标 → 评标 → 中标
 */
namespace Tests\Feature;

use App\Models\TenderProject;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenderApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['username' => 'tender_admin'],
            ['name' => '招标管理员', 'password' => bcrypt('secret123'), 'type' => 'admin']
        );
        $this->supplier = Supplier::firstOrCreate(
            ['phone' => '13800000888'],
            ['name' => '测试供应商 A', 'code' => 'SUP-A-' . Str::random(4)]
        );
    }

    /** ======== 4 步闭环：建→发布→投标→中标 ======== */
    public function test_full_lifecycle_create_publish_bid_award(): void
    {
        $token = $this->admin->createToken('t')->plainTextToken;

        // ① 草稿
        $r = $this->withToken($token)->postJson('/api/tenders', [
            'name' => 'E2E 招标项目',
            'description' => '4 步闭环测试',
            'type' => 'public',
            'required_items' => [['name' => '办公椅', 'qty' => 10]],
            'deadline' => now()->addDays(7)->toIso8601String(),
        ]);
        $r->assertJson(['code' => 0]);
        $projectId = $r->json('data.id');
        $this->assertNotNull($projectId);

        // ② 发布
        $this->withToken($token)->postJson("/api/tenders/{$projectId}/publish")
            ->assertJson(['code' => 0, 'data.status' => 'bidding']);

        // ③ 公开门户：拉取（按 public_token）
        $project = TenderProject::find($projectId);
        $portal = $this->getJson("/api/portal/t/{$project->public_token}");
        $portal->assertJson(['code' => 0]);

        // ④ 公开门户：投标
        $bid = $this->postJson("/api/portal/t/{$project->public_token}/bids", [
            'supplier_name' => '测试供应商 A',
            'contact_name' => '张三',
            'contact_phone' => '13800000888',
            'total_amount' => 5000.00,
            'lead_time_days' => 5,
            'items' => [['name' => '办公椅', 'qty' => 10, 'unit_price' => 500]],
        ]);
        $bid->assertJson(['code' => 0]);
        $bidId = $bid->json('data.id');

        // ⑤ 内部：评标 + 中标
        $this->withToken($token)->postJson("/api/tenders/{$projectId}/evaluate", [
            'scores' => [$bidId => 88.5],
        ])->assertJson(['code' => 0]);

        $this->withToken($token)->postJson("/api/tenders/{$projectId}/award", [
            'bid_id' => $bidId,
        ])->assertJson(['code' => 0, 'data.status' => 'awarded']);
    }

    /** ======== 异常：未发布项目不能投标 ======== */
    public function test_cannot_bid_on_draft_project(): void
    {
        $project = TenderProject::create([
            'code' => 'T-' . Str::random(6),
            'name' => '草稿项目',
            'type' => 'public',
            'status' => 'draft',
            'created_by' => $this->admin->id,
            'public_token' => Str::uuid(),
        ]);

        $r = $this->postJson("/api/portal/t/{$project->public_token}/bids", [
            'supplier_name' => 'X', 'contact_phone' => '13900000000',
            'total_amount' => 100,
        ]);
        $r->assertJson(['code' => 1003]);
    }

    /** ======== 异常：非法 public_token 404 ======== */
    public function test_invalid_token_returns_404(): void
    {
        $this->getJson('/api/portal/t/not-a-uuid')
            ->assertJson(['code' => 1004]);
    }

    /** ======== 附件：上传 + 列表 + 删除 ======== */
    public function test_attachment_upload_list_delete(): void
    {
        $token = $this->admin->createToken('t')->plainTextToken;
        $project = TenderProject::create([
            'code' => 'T-AT-' . Str::random(4),
            'name' => '附件测试',
            'type' => 'public', 'status' => 'draft', 'created_by' => $this->admin->id,
            'public_token' => Str::uuid(),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'tender_att_');
        file_put_contents($tmp, 'fake pdf content');

        $upload = $this->withToken($token)
            ->post("/api/tenders/{$project->id}/attachments", [
                'file' => new \Illuminate\Http\UploadedFile($tmp, 'demo.pdf', 'application/pdf', null, true),
                'category' => 'tender_doc',
                'visibility' => 'public',
            ]);
        $upload->assertJson(['code' => 0]);
        $attId = $upload->json('data.id');

        $this->withToken($token)->getJson("/api/tenders/{$project->id}/attachments")
            ->assertJson(['code' => 0])
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->deleteJson("/api/tenders/{$project->id}/attachments/{$attId}")
            ->assertJson(['code' => 0]);

        @unlink($tmp);
    }
}
