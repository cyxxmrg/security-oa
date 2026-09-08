<?php
/**
 * 招标中心 Feature 测试 (V0.6.0)
 *
 * 覆盖：内部 TenderController 13 端点 + 公开 PortalController 5 端点
 * 4 步闭环：建项目 → 邀请 → 投标 → 评标 → 中标
 */
namespace Tests\Feature;

use App\Models\TenderProject;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PortalInviteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenderApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $creator;
    private User $reviewer;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = $this->createTenderUser('tender_creator', '13900000888');
        $this->reviewer = $this->createTenderUser('tender_reviewer', '13900000889');

        $permissionNames = [
            'purchase.tender',
            'tender.create',
            'tender.approve',
            'tender.award',
        ];
        foreach ($permissionNames as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'module' => '招标中心',
                'description' => $name,
                'display_name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $permissions = Permission::whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->get();
        $role = Role::findOrCreate('tender_test', 'web');
        $role->syncPermissions($permissions);
        $this->creator->assignRole($role);
        $this->reviewer->assignRole($role);

        $this->supplier = Supplier::create([
            'name' => '测试供应商 A',
            'code' => 'SUP-A-' . Str::random(4),
            'phone' => '13800000888',
            'contact_person' => '测试联系人',
            'category' => 'material',
        ]);
    }

    private function createTenderUser(string $username, string $phone): User
    {
        return User::withoutEvents(fn () => User::create([
            'name' => '招标测试用户',
            'username' => $username,
            'phone' => $phone,
            'password' => bcrypt('secret123'),
            'user_type' => 'business',
            'status' => 'active',
        ]));
    }

    private function issuePortalToken(): string
    {
        return app(PortalInviteService::class)
            ->issue($this->supplier, '8888')['token'];
    }

    /** ======== 4 步闭环：建→发布→投标→中标 ======== */
    public function test_full_lifecycle_create_publish_bid_award(): void
    {
        // ① 草稿
        $r = $this->actingAs($this->creator, 'sanctum')->postJson('/api/tenders', [
            'name' => 'E2E 招标项目',
            'description' => '4 步闭环测试',
            'type' => 'tender',
            'required_items' => [['name' => '办公椅', 'qty' => 10]],
            'invited_supplier_ids' => [$this->supplier->id],
            'deadline' => now()->addDays(7)->toIso8601String(),
        ]);
        $r->assertJson(['code' => 0]);
        $projectId = $r->json('data.id');
        $this->assertNotNull($projectId);

        // ② 发布
        $this->actingAs($this->reviewer, 'sanctum')->postJson("/api/tenders/{$projectId}/publish")
            ->assertJson(['code' => 0, 'data' => ['status' => 'bidding']]);

        // ③ 公开门户：拉取（按 public_token）
        $project = TenderProject::find($projectId);
        $portal = $this->getJson("/api/portal/t/{$project->public_token}");
        $portal->assertJson(['code' => 0]);

        // ④ 公开门户：投标
        $bid = $this->withToken($this->issuePortalToken())
            ->postJson("/api/portal/t/{$project->public_token}/bids", [
                'supplier_id' => $this->supplier->id,
                'phone_suffix' => '8888',
                'total_amount' => 5000.00,
                'lead_time_days' => 5,
                'items' => [['name' => '办公椅', 'quantity' => 10, 'unit_price' => 500]],
            ]);
        $bid->assertJson(['code' => 0]);
        $bidId = $bid->json('data.id');

        // ⑤ 内部：评标 + 中标
        $this->actingAs($this->reviewer, 'sanctum')->postJson("/api/tenders/{$projectId}/evaluate", [
            'evaluations' => [[
                'bid_id' => $bidId,
                'technical' => 90,
                'price' => 88,
                'business' => 85,
            ]],
        ])->assertJson(['code' => 0]);

        $this->actingAs($this->reviewer, 'sanctum')->postJson("/api/tenders/{$projectId}/award", [
            'bid_id' => $bidId,
        ])->assertJson(['code' => 0, 'data' => ['tender' => ['status' => 'awarded']]]);
    }

    /** ======== 异常：未发布项目不能投标 ======== */
    public function test_cannot_bid_on_draft_project(): void
    {
        $project = TenderProject::create([
            'code' => 'T-' . Str::random(6),
            'name' => '草稿项目',
            'type' => 'tender',
            'status' => 'draft',
            'created_by' => $this->creator->id,
            'public_token' => Str::uuid(),
        ]);

        $r = $this->withToken($this->issuePortalToken())
            ->postJson("/api/portal/t/{$project->public_token}/bids", [
                'supplier_id' => $this->supplier->id,
                'phone_suffix' => '8888',
                'total_amount' => 100,
            ]);
        $r->assertStatus(422)->assertJson(['code' => 1001]);
    }

    /** ======== 异常：非法 public_token 404 ======== */
    public function test_invalid_token_returns_404(): void
    {
        $this->getJson('/api/portal/t/not-a-uuid')
            ->assertNotFound()
            ->assertJson(['code' => 404]);
    }

    /** ======== 附件：上传 + 列表 + 删除 ======== */
    public function test_attachment_upload_list_delete(): void
    {
        $project = TenderProject::create([
            'code' => 'T-AT-' . Str::random(4),
            'name' => '附件测试',
            'type' => 'tender', 'status' => 'draft', 'created_by' => $this->creator->id,
            'public_token' => Str::uuid(),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'tender_att_');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

        $upload = $this->actingAs($this->creator, 'sanctum')
            ->post("/api/tenders/{$project->id}/attachments", [
                'file' => new \Illuminate\Http\UploadedFile($tmp, 'demo.pdf', 'application/pdf', null, true),
                'category' => 'tender_doc',
                'visibility' => 'public',
            ]);
        $upload->assertJson(['code' => 0]);
        $attId = $upload->json('data.id');

        $this->actingAs($this->creator, 'sanctum')->getJson("/api/tenders/{$project->id}/attachments")
            ->assertJson(['code' => 0])
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->creator, 'sanctum')->deleteJson("/api/tenders/{$project->id}/attachments/{$attId}")
            ->assertJson(['code' => 0]);

        @unlink($tmp);
    }
}
