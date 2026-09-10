<?php

namespace App\Services;

use App\Models\{
    Opportunity, ProjectContract, Quotation, QuotationItem,
    Referrer, Project, ProjectPool, ReferralSettlement,
    SalesFollowUp, SalesFollowUpAttachment, User, ApprovalRecord
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * 销售业务服务 — V1.2.7d 拆自 SalesController
 *
 * 业务域：
 *   - Opportunity  商机（CRUD + 6 阶段状态机 + 标记成单/丢单 + 复活 + 转项目池 + 分配）
 *   - Quotation    报价单（CRUD + 版本化 + 状态 + 关联商机）
 *   - Referrer     推荐人（CRUD）
 *   - ProjectPool  项目池（CRUD + 转项目）
 *   - SalesFollowUp 跟进记录（CRUD + 附件）
 *   - ReferralSettlement 推荐结算（列表 + 审批 + 付款 + 统计）
 *
 * 所有列表方法应用 owner 隔离（同 SalesController 行为）
 */
class SalesService
{
    // ============================================================
    // === 商机 Opportunity ===
    // ============================================================

    public function paginateOpps(Request $request, User $user)
    {
        $query = Opportunity::with(['customer', 'sales', 'presale']);
        $this->applyOwnerScope($query, $user, 'sales_id');

        if ($request->filled('keyword'))  $query->where('name', 'like', "%{$request->keyword}%");
        if ($request->filled('stage'))    $query->where('stage', $request->stage);
        if ($request->filled('sales_id')) $query->where('sales_id', $request->sales_id);

        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 看板轻量数据 — 只用 QueryBuilder 避免 Eloquent 全量序列化
     */
    public function kanbanOpps(Request $request, User $user): array
    {
        $qb = \DB::table('opportunities as o')
            ->leftJoin('customers as c', 'o.customer_id', '=', 'c.id')
            ->leftJoin('users as u', 'o.sales_id', '=', 'u.id')
            ->select([
                'o.id', 'o.opp_no', 'o.name', 'o.stage', 'o.estimated_amount',
                'o.probability', 'o.expected_sign_date', 'o.customer_id',
                'c.name as customer_name', 'u.name as sales_name',
            ]);

        $this->applyOwnerScope($qb, $user, 'o.sales_id');
        if ($request->filled('stage'))    $qb->where('o.stage', $request->stage);
        if ($request->filled('sales_id')) $qb->where('o.sales_id', $request->sales_id);

        $rows = $qb->orderBy('o.created_at', 'desc')->get();

        return array_map(function ($r) {
            return [
                'id'                 => (int) $r->id,
                'opp_no'             => $r->opp_no,
                'name'               => $r->name,
                'stage'              => $r->stage,
                'estimated_amount'   => (float) $r->estimated_amount,
                'probability'        => (int) $r->probability,
                'expected_sign_date' => $r->expected_sign_date,
                'customer'           => ['name' => $r->customer_name ?? ''],
                'sales'              => ['name' => $r->sales_name ?? '未分配'],
            ];
        }, $rows->toArray());
    }

    public function showOpp(Opportunity $opp): Opportunity
    {
        return $opp->load(['customer', 'sales', 'presale', 'quotations.items', 'followUps']);
    }

    public static function oppStageOptions(): array
    {
        return [
            ['value' => 'inquiry',       'label' => '需求确认', 'color' => '#0C447C'],
            ['value' => 'qualification', 'label' => '资质评估', 'color' => '#185FA5'],
            ['value' => 'site_survey',   'label' => '现场地勘', 'color' => '#3A8FCD'],
            ['value' => 'proposal',      'label' => '方案设计', 'color' => '#2680C2'],
            ['value' => 'negotiating',   'label' => '报价谈判', 'color' => '#BA7517'],
            ['value' => 'quoted',        'label' => '已报价',   'color' => '#D4961F'],
            ['value' => 'won',           'label' => '成交',     'color' => '#67C23A'],
            ['value' => 'lost',          'label' => '战败',     'color' => '#A32D2D'],
        ];

        return [
            ['value' => 'requirement',  'label' => '需求确认',   'color' => '#0C447C'],
            ['value' => 'solution',     'label' => '方案制定',   'color' => '#534AB7'],
            ['value' => 'negotiation',  'label' => '报价谈判',   'color' => '#BA7517'],
            ['value' => 'contracting',  'label' => '合同拟定',   'color' => '#1D9E75'],
            ['value' => 'won',          'label' => '成交',       'color' => '#1D9E75'],
            ['value' => 'lost',         'label' => '丢单',       'color' => '#C0392B'],
        ];
    }

    public function oppFunnel(Request $request)
    {
        $user = $request->user();
        $query = Opportunity::query();
        $this->applyOwnerScope($query, $user, 'sales_id');
        $rows = $query->selectRaw('stage, count(*) as cnt, sum(estimated_amount) as amount')
            ->groupBy('stage')
            ->get();
        $result = [];
        foreach (self::oppStageOptions() as $opt) {
            $row = $rows->firstWhere('stage', $opt['value']);
            $result[] = [
                'stage'        => $opt['value'],
                'label'        => $opt['label'],
                'color'        => $opt['color'],
                'count'        => (int)($row->cnt ?? 0),
                'total_amount' => (float)($row->amount ?? 0),
            ];
        }
        return $result;
    }

    public static function oppLostReasons(): array
    {
        return [
            ['value' => 'price',     'label' => '价格过高'],
            ['value' => 'competitor','label' => '对手中标'],
            ['value' => 'budget',    'label' => '客户预算'],
            ['value' => 'delay',     'label' => '客户延期'],
            ['value' => 'other',     'label' => '其他原因'],
        ];
    }

    public function createOpp(Request $request): Opportunity
    {
        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'customer_id'        => 'required|integer|exists:customers,id',
            'type'               => 'nullable|string',
            'estimated_amount'   => 'required|numeric|min:0',
            'expected_sign_date' => 'nullable|date',
            'sales_id'           => 'nullable|integer|exists:users,id',
            'presale_id'         => 'nullable|integer|exists:users,id',
            'remark'             => 'nullable|string',
            'stage'              => 'nullable|string',
        ]);
        // V1.2.10: 自动用当前登录用户当 sales/presale (默认填充, 避免 422)
        $data['sales_id']   = $data['sales_id']   ?? $request->user()->id;
        $data['presale_id'] = $data['presale_id'] ?? $request->user()->id;
        $data['stage'] = $data['stage'] ?? 'inquiry';
        $data['probability'] = $data['probability'] ?? 20;
        $opp = Opportunity::create($data);
        return $opp->load(['customer', 'sales', 'presale']);
    }

    public function updateOpp(Request $request, Opportunity $opp): Opportunity
    {
        $data = $request->validate([
            'name'               => 'sometimes|string|max:200',
            'estimated_amount'   => 'sometimes|numeric|min:0',
            'expected_sign_date' => 'nullable|date',
            'sales_id'           => 'sometimes|integer|exists:users,id',
            'presale_id'         => 'sometimes|integer|exists:users,id',
            'remark'             => 'nullable|string',
        ]);
        $opp->update($data);
        return $opp->fresh()->load(['customer', 'sales', 'presale']);
    }

    public function destroyOpp(Opportunity $opp): void
    {
        if ($opp->stage === 'won') {
            throw new RuntimeException('已成交的商机不能删除');
        }
        $opp->delete();
    }

    public function updateOppStage(Opportunity $opp, string $stage): Opportunity
    {
        $stage = [
            'requirement' => 'inquiry',
            'solution' => 'qualification',
            'negotiation' => 'negotiating',
            'contracting' => 'quoted',
        ][$stage] ?? $stage;
        $valid = array_column(self::oppStageOptions(), 'value');
        if (!in_array($stage, $valid, true)) {
            throw new RuntimeException("未知阶段: {$stage}");
        }
        $opp->update(['stage' => $stage]);
        return $opp->fresh();
    }

    public function markOppWon(Request $request, Opportunity $opp): Opportunity
    {
        $data = $request->validate([
            'final_amount' => 'required|numeric|min:0',
            'signed_at'    => 'nullable|date',
        ]);
        $opp->update([
            'stage'         => 'won',
            'final_amount'  => $data['final_amount'],
            'signed_at'     => $data['signed_at'] ?? now()->toDateString(),
            'closed_at'     => now(),
        ]);
        return $opp->fresh();
    }

    public function markOppLost(Request $request, Opportunity $opp): Opportunity
    {
        $data = $request->validate([
            'lost_reason' => 'required|string|max:500',
        ]);
        $opp->update([
            'stage'       => 'lost',
            'lost_reason' => $data['lost_reason'],
            'closed_at'   => now(),
        ]);
        return $opp->fresh();
    }

    public function reviveOpp(Opportunity $opp): Opportunity
    {
        if ($opp->stage !== 'lost') {
            throw new RuntimeException('只有丢单状态的商机可复活');
        }
        $opp->update(['stage' => 'inquiry', 'lost_reason' => null, 'closed_at' => null]);
        return $opp->fresh();
    }

    public function holdOpp(Request $request, Opportunity $opp): Opportunity
    {
        $data = $request->validate([
            'hold_until' => 'nullable|date',
            'hold_reason'=> 'nullable|string|max:500',
        ]);
        $opp->update([
            'is_held'     => true,
            'hold_until'  => $data['hold_until'] ?? null,
            'hold_reason' => $data['hold_reason'] ?? null,
        ]);
        return $opp->fresh();
    }

    public function moveOppToProjectPool(Request $request, Opportunity $opp): ProjectPool
    {
        $data = $request->validate([
            'project_name' => 'required|string|max:200',
            'remark'       => 'nullable|string',
        ]);
        return DB::transaction(function () use ($opp, $data) {
            $pool = ProjectPool::create([
                'opportunity_id' => $opp->id,
                'customer_id'    => $opp->customer_id,
                'name'           => $data['project_name'],
                'status'         => 'pending',
                'notes'          => $data['remark'] ?? null,
            ]);
            $opp->update(['moved_to_pool_at' => now()]);
            return $pool;
        });
    }

    public function assignOpp(Request $request, Opportunity $opp): Opportunity
    {
        $data = $request->validate([
            'sales_id'   => 'required|integer|exists:users,id',
            'presale_id' => 'nullable|integer|exists:users,id',
        ]);
        $opp->update($data);
        return $opp->fresh();
    }

    public function winOpp(Opportunity $opp): Opportunity
    {
        $opp->update(['stage' => 'won', 'closed_at' => now()]);
        return $opp->fresh();
    }

    public function loseOpp(Opportunity $opp): Opportunity
    {
        $opp->update(['stage' => 'lost', 'closed_at' => now()]);
        return $opp->fresh();
    }

    // ============================================================
    // === 报价单 Quotation ===
    // ============================================================

    public function paginateQuotes(Request $request, User $user)
    {
        $query = Quotation::with(['opportunity', 'createdBy']);
        $this->applyOpportunityOwnerScope($query, $user);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('keyword')) $query->where('quote_no', 'like', "%{$request->keyword}%");
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function showQuote(Quotation $quote): Quotation
    {
        return $quote->load(['opportunity', 'createdBy', 'items']);
    }

    public static function quoteStatusOptions(): array
    {
        return [
            ['value' => 'draft',     'label' => '草稿'],
            ['value' => 'sent',      'label' => '已发送'],
            ['value' => 'accepted',  'label' => '已接受'],
            ['value' => 'rejected',  'label' => '已拒绝'],
            ['value' => 'revising',  'label' => '修订中'],
            ['value' => 'expired',   'label' => '已过期'],
        ];
    }

    public function createQuote(Request $request): Quotation
    {
        $data = $request->validate([
            'opportunity_id'  => 'required|integer|exists:opportunities,id',
            'version'         => 'nullable|string',
            'valid_until'     => 'nullable|date',
            'items'           => 'required|array|min:1',
            'items.*.name'    => 'required|string',
            'items.*.qty'     => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.remark'  => 'nullable|string',
        ]);
        $opportunity = Opportunity::findOrFail($data['opportunity_id']);
        $this->assertOpportunityAccess($opportunity, $request->user());
        $data['code'] = 'Q' . date('Ymd') . strtoupper(Str::random(6));
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;
        $data['total_amount'] = collect($data['items'])->sum(fn($i) => $i['qty'] * $i['unit_price']);
        $items = $data['items']; unset($data['items']);

        return DB::transaction(function () use ($data, $items) {
            $quote = Quotation::create($data);
            foreach ($items as $item) {
                $quote->items()->create($item);
            }
            return $quote->load(['opportunity', 'items', 'createdBy']);
        });
    }

    public function updateQuote(Request $request, Quotation $quote): Quotation
    {
        if ($quote->status === 'accepted') {
            throw new RuntimeException('已接受的报价单不可编辑');
        }
        $data = $request->validate([
            'version'     => 'sometimes|string',
            'valid_until' => 'nullable|date',
            'items'       => 'sometimes|array|min:1',
        ]);
        if (isset($data['items'])) {
            $quote->items()->delete();
            foreach ($data['items'] as $item) {
                $quote->items()->create($item);
            }
            $data['total_amount'] = collect($data['items'])->sum(fn($i) => $i['qty'] * $i['unit_price']);
            unset($data['items']);
        }
        $quote->update($data);
        return $quote->fresh()->load(['items', 'opportunity']);
    }

    public function destroyQuote(Quotation $quote): void
    {
        if ($quote->status === 'accepted') {
            throw new RuntimeException('已接受的报价单不可删除');
        }
        $quote->items()->delete();
        $quote->delete();
    }

    public function storeQuoteItems(Request $request, Quotation $quote): Quotation
    {
        if ($quote->status === 'accepted') {
            throw new RuntimeException('已接受的报价单不可修改明细');
        }
        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.name'       => 'required|string',
            'items.*.qty'        => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);
        $quote->items()->delete();
        foreach ($data['items'] as $item) {
            $quote->items()->create($item);
        }
        $quote->update([
            'total_amount' => collect($data['items'])->sum(fn($i) => $i['qty'] * $i['unit_price']),
        ]);
        return $quote->fresh()->load('items');
    }

    public function updateQuoteStatus(Request $request, Quotation $quote): Quotation
    {
        $data = $request->validate(['status' => 'required|string|in:draft,sent,accepted,rejected,revising,expired']);
        $quote->update(['status' => $data['status']]);
        return $quote->fresh();
    }

    public function newQuoteVersion(Quotation $quote): Quotation
    {
        return DB::transaction(function () use ($quote) {
            $new = $quote->replicate(['code', 'status', 'version']);
            $new->code = 'Q' . date('Ymd') . strtoupper(Str::random(6));
            $new->status = 'draft';
            $new->version = (string)((int)($quote->version ?? 1) + 1);
            $new->parent_id = $quote->id;
            $new->save();
            foreach ($quote->items as $item) {
                $new->items()->create($item->toArray());
            }
            $quote->update(['status' => 'revising']);
            return $new->load('items');
        });
    }

    public function acceptQuote(Quotation $quote): Quotation
    {
        $quote->update(['status' => 'accepted', 'accepted_at' => now()]);
        return $quote->fresh();
    }

    public function rejectQuote(Request $request, Quotation $quote): Quotation
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $quote->update(['status' => 'rejected', 'reject_reason' => $data['reason']]);
        return $quote->fresh();
    }

    public function reviseQuote(Request $request, Quotation $quote): Quotation
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $quote->update(['status' => 'revising', 'revise_reason' => $data['reason']]);
        return $quote->fresh();
    }

    public function oppQuotations(Opportunity $opp)
    {
        return $opp->quotations()->with('items')->orderBy('created_at', 'desc')->get();
    }

    public function createOppQuotation(Request $request, Opportunity $opp): Quotation
    {
        $this->assertOpportunityAccess($opp, $request->user());
        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.name'       => 'required|string',
            'items.*.qty'        => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);
        $data['opportunity_id'] = $opp->id;
        $data['code'] = 'Q' . date('Ymd') . strtoupper(Str::random(6));
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;
        $data['total_amount'] = collect($data['items'])->sum(fn($i) => $i['qty'] * $i['unit_price']);
        $items = $data['items']; unset($data['items']);

        return DB::transaction(function () use ($data, $items) {
            $quote = Quotation::create($data);
            foreach ($items as $item) {
                $quote->items()->create($item);
            }
            return $quote->load('items');
        });
    }

    // ============================================================
    // === 推荐人 Referrer ===
    // ============================================================

    public function paginateReferrers(Request $request, User $user)
    {
        $query = Referrer::with('customer');
        $this->applyOwnerScope($query, $user, 'owner_id');
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('phone', 'like', "%{$kw}%")
                  ->orWhere('bank_account', 'like', "%{$kw}%");
            });
        }
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function showReferrer(Referrer $referrer): Referrer
    {
        return $referrer->load('customer');
    }

    public function createReferrer(Request $request): Referrer
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:20',
            'customer_id'     => 'nullable|integer|exists:customers,id',
            'bank_name'       => 'nullable|string|max:100',
            'bank_account'    => 'nullable|string|max:50',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'total_commission'=> 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);
        $data['commission_rate']  = $data['commission_rate']  ?? 0;
        $data['total_commission'] = $data['total_commission'] ?? 0;
        $data['owner_id']         = $request->user()?->id;
        return Referrer::create($data);
    }

    public function updateReferrer(Request $request, Referrer $referrer): Referrer
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:100',
            'phone'           => 'sometimes|string|max:20',
            'customer_id'     => 'nullable|integer|exists:customers,id',
            'bank_name'       => 'nullable|string|max:100',
            'bank_account'    => 'nullable|string|max:50',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'total_commission'=> 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);
        $referrer->update($data);
        return $referrer->fresh()->load('customer');
    }

    public function destroyReferrer(Referrer $referrer): void
    {
        $referrer->delete();
    }

    // ============================================================
    // === 项目池 ProjectPool ===
    // ============================================================

    public function paginatePool(Request $request, User $user)
    {
        $query = ProjectPool::with(['opportunity', 'customer']);
        $this->applyOpportunityOwnerScope($query, $user);
        if ($request->filled('status')) $query->where('status', $request->status);
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function showPool(ProjectPool $pool): ProjectPool
    {
        return $pool->load(['opportunity', 'customer']);
    }

    public function updatePool(Request $request, ProjectPool $pool): ProjectPool
    {
        $data = $request->validate([
            'name'   => 'sometimes|string|max:200',
            'status' => 'sometimes|in:pending,active,archived,approved,rejected,converted',
            'notes'  => 'nullable|string',
            'remark' => 'nullable|string',
        ]);
        if (array_key_exists('remark', $data) && !array_key_exists('notes', $data)) {
            $data['notes'] = $data['remark'];
        }
        unset($data['remark']);
        $pool->update($data);
        return $pool->fresh();
    }

    public function convertPoolToProject(Request $request, ProjectPool $pool): Project
    {
        $data = $request->validate([
            'project_no'   => 'nullable|string|max:50',
            'name'         => 'required|string|max:200',
            'manager_id'   => 'required|integer|exists:users,id',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'budget'       => 'nullable|numeric|min:0',
            'team_member_ids' => 'nullable|array',
            'team_member_ids.*' => 'integer|exists:users,id',
            'notes'        => 'nullable|string|max:2000',
        ]);
        return DB::transaction(function () use ($pool, $data) {
            // 1) 创建项目
            $project = Project::create([
                'project_no'   => $data['project_no'] ?? ('P' . date('Ymd') . strtoupper(Str::random(4))),
                'name'         => $data['name'],
                'customer_id'  => $pool->customer_id,
                'manager_id'   => $data['manager_id'],
                'start_date'   => $data['start_date'] ?? null,
                'end_date'     => $data['end_date'] ?? null,
                'stage'        => 'mobilization',
                'status'       => 'pending',
                'progress'     => 0,
                'budget_device'   => $data['budget'] ?? $pool->contract_amount ?? 0,
                'budget_material' => 0,
                'budget_labor'    => 0,
                'description'  => $data['notes'] ?? null,
            ]);

            // 2) 添加团队成员
            $memberIds = $data['team_member_ids'] ?? [];
            foreach ($memberIds as $uid) {
                DB::table('project_members')->insert([
                    'project_id' => $project->id, 'user_id' => $uid,
                    'role' => 'worker', 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($data['manager_id'] && !in_array($data['manager_id'], $memberIds)) {
                DB::table('project_members')->insert([
                    'project_id' => $project->id, 'user_id' => $data['manager_id'],
                    'role' => 'manager', 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // 3) 自动创建销售合同（从项目池合同金额）
            $contractAmount = $data['budget'] ?? $pool->contract_amount ?? 0;
            $signedAt = $pool->signed_at ?? $pool->created_at;
            ProjectContract::create([
                'project_id'      => $project->id,
                'customer_id'     => $pool->customer_id,
                'type'            => 'sales',
                'contract_no'     => $this->nextSalesContractNo(),
                'contract_amount' => $contractAmount,
                'contract_start'  => $data['start_date'] ?? today(),
                'contract_end'    => $data['end_date'] ?? today()->addMonths(6),
                'status'          => 'active',
                'signed_at'       => $signedAt,
                'notes'           => '由项目池自动创建',
            ]);

            // 4) 更新项目池
            $pool->update([
                'status'             => 'active',
                'related_project_id' => $project->id,
                'contract_amount'    => $contractAmount,
                'signed_at'          => $signedAt,
            ]);

            return $project->load(['customer', 'manager']);
        });
    }

    // ============================================================
    // === 跟进记录 SalesFollowUp + 附件 ===
    // ============================================================

    public function paginateFollowUps(Request $request, User $user)
    {
        $query = SalesFollowUp::with(['user']);
        if ($request->filled('opportunity_id')) {
            $query->where('target_id', $request->opportunity_id)
                ->whereIn('target_type', ['opp', 'opportunity']);
        }
        if (!$this->canViewTeam($user)) {
            $opportunityIds = Opportunity::query();
            $this->applyOwnerScope($opportunityIds, $user, 'sales_id');
            $quoteIds = Quotation::query()->whereHas('opportunity', function ($q) use ($user) {
                $this->applyOwnerScope($q, $user, 'sales_id');
            })->select('id');
            $query->where(function ($q) use ($user, $opportunityIds, $quoteIds) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($target) use ($opportunityIds) {
                        $target->whereIn('target_type', ['opp', 'opportunity'])
                            ->whereIn('target_id', $opportunityIds->select('id'));
                    })
                    ->orWhere(function ($target) use ($quoteIds) {
                        $target->where('target_type', 'quote')
                            ->whereIn('target_id', $quoteIds);
                    });
            });
        }
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function showFollowUp(SalesFollowUp $followUp): SalesFollowUp
    {
        return $followUp->load(['user', 'attachments']);
    }

    public function createFollowUp(Request $request): SalesFollowUp
    {
        $data = $request->validate([
            'opportunity_id' => 'nullable|integer|exists:opportunities,id',
            'target_type'    => 'nullable|string|in:opp,opportunity,quote,customer',
            'target_id'      => 'nullable|integer',
            'content'        => 'required|string',
            'next_action_at' => 'nullable|date',
            'method'         => 'nullable|string',
            'contact_method' => 'nullable|string',  // V1.2.10 别名
            'next_follow_at' => 'nullable|date',
            'next_action'    => 'nullable|string',
            'result'         => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()->id;

        if ($request->filled('lead_id')) {
            throw ValidationException::withMessages([
                'lead_id' => '线索模块已下线，请改用 opportunity_id',
            ]);
        }

        if (!empty($data['opportunity_id'])) {
            $data['target_type'] = 'opp';
            $data['target_id']   = $data['opportunity_id'];
            unset($data['opportunity_id']);
        } else {
            $data['target_type'] = $data['target_type'] ?? 'opp';
        }
        if (empty($data['target_id'])) {
            throw ValidationException::withMessages([
                'target_id' => '必须指定跟进目标',
            ]);
        }
        $this->assertFollowUpTargetAccess($data['target_type'], (int) $data['target_id'], $request->user());
        // method 映射到 contact_method
        if (!empty($data['method']) && empty($data['contact_method'])) {
            $data['contact_method'] = $data['method'];
            unset($data['method']);
        }
        if (array_key_exists('next_follow_at', $data) && !array_key_exists('next_action_at', $data)) {
            $data['next_action_at'] = $data['next_follow_at'];
        }
        unset($data['next_follow_at']);

        return SalesFollowUp::create($data)->load(['user']);
    }

    public function updateFollowUp(Request $request, SalesFollowUp $followUp): SalesFollowUp
    {
        $data = $request->validate([
            'content'        => 'sometimes|string',
            'next_action_at' => 'sometimes|nullable|date',
            'method'         => 'nullable|string',
            'contact_method' => 'nullable|string',
            'next_follow_at' => 'nullable|date',
            'next_action'    => 'nullable|string',
            'result'         => 'nullable|string',
        ]);
        if (!empty($data['method']) && empty($data['contact_method'])) {
            $data['contact_method'] = $data['method'];
            unset($data['method']);
        }
        if (array_key_exists('next_follow_at', $data) && !array_key_exists('next_action_at', $data)) {
            $data['next_action_at'] = $data['next_follow_at'];
        }
        unset($data['next_follow_at']);
        $followUp->update($data);
        return $followUp->fresh();
    }

    public function destroyFollowUp(SalesFollowUp $followUp): void
    {
        $followUp->delete();
    }

    public function uploadFollowUpAttachment(Request $request, SalesFollowUp $followUp, FileUploadService $uploader): SalesFollowUpAttachment
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($request, $followUp, $uploader, &$storedPath) {
                $lockedFollowUp = SalesFollowUp::lockForUpdate()->findOrFail($followUp->id);
                $result = $uploader->store($request, 'file', [
                    'disk'         => 'attachments',
                    'subdir'       => 'followups/' . $lockedFollowUp->id,
                    'allowed_ext'  => FileUploadService::DEFAULT_ALLOWED_EXT,
                    'allowed_mime' => FileUploadService::DEFAULT_ALLOWED_MIME,
                    'max_size'     => 51200,
                ]);
                $storedPath = $result['path'];

                return $lockedFollowUp->attachments()->create([
                    'name' => $result['original_name'],
                    'path' => $result['path'],
                    'size' => $result['size'],
                    'mime' => $result['mime'],
                ]);
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk('attachments')->delete($storedPath);
            }
            throw $e;
        }
    }

    public function downloadFollowUpAttachment(SalesFollowUpAttachment $att)
    {
        $name = $att->name ?: basename($att->path);
        $privateDisk = Storage::disk('attachments');
        if ($privateDisk->exists($att->path)) {
            return $privateDisk->download($att->path, $name, [
                'Content-Type' => $att->mime ?: 'application/octet-stream',
            ]);
        }

        $legacyDisk = Storage::disk('public');
        if ($legacyDisk->exists($att->path)) {
            return $legacyDisk->download($att->path, $name, [
                'Content-Type' => $att->mime ?: 'application/octet-stream',
            ]);
        }

        abort(404, '附件不存在');
    }

    public function destroyFollowUpAttachment(SalesFollowUpAttachment $att): void
    {
        DB::transaction(function () use ($att) {
            $locked = SalesFollowUpAttachment::lockForUpdate()->findOrFail($att->id);
            $path = $locked->path;
            $locked->delete();
            Storage::disk('attachments')->delete($path);
            Storage::disk('public')->delete($path);
        });
    }

    // ============================================================
    // === 推荐结算 ReferralSettlement ===
    // ============================================================

    public function paginateReferralSettlements(Request $request, User $user)
    {
        $query = ReferralSettlement::with(['referrer', 'opportunity', 'approver']);
        $this->applySettlementScope($query, $user);
        if ($request->filled('status')) $query->where('status', $request->status);
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function showReferralSettlement(ReferralSettlement $settlement, User $user): ReferralSettlement
    {
        $this->assertSettlementView($settlement, $user);
        return $settlement->load(['referrer', 'opportunity', 'approver']);
    }

    public function approveReferralSettlement(Request $request, ReferralSettlement $settlement): ReferralSettlement
    {
        $this->assertSettlementOwner($settlement, $request->user());
        $data = $request->validate(['comment' => 'nullable|string|max:500']);
        return DB::transaction(function () use ($request, $settlement, $data) {
            $settlement = ReferralSettlement::lockForUpdate()->findOrFail($settlement->id);
            if ($settlement->status !== 'pending') {
                throw new RuntimeException('仅待审批状态可提交财务审批');
            }
            $approval = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'referral_settlement')
                ->whereJsonContains('payload->settlement_id', $settlement->id)
                ->whereIn('status', [ApprovalRecord::STATUS_PENDING, ApprovalRecord::STATUS_APPROVED])
                ->first();
            if ($approval) {
                return $settlement->fresh();
            }
            $applicant = User::find($settlement->created_by) ?? $request->user();
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('referral_settlement', 'finance');
            if (!$template) {
                throw new RuntimeException('未找到居间费对应的启用财务审批流程');
            }
            $flowData = $flowService->initFlow(
                $template,
                $applicant,
                $data['comment'] ?? '提交居间费结算审批'
            );
            ApprovalRecord::create([
                'code' => \App\Services\ApprovalNumberService::next('FIN'),
                'type' => 'finance',
                'sub_type' => 'referral_settlement',
                'title' => "销售居间费结算 #{$settlement->id}",
                'priority' => 'normal',
                'status' => ApprovalRecord::STATUS_PENDING,
                'amount' => $settlement->amount,
                'applicant_id' => $applicant->id,
                'current_approver_id' => $flowData['current_approver_id'],
                'payload' => [
                    'settlement_id' => $settlement->id,
                    'opportunity_id' => $settlement->opportunity_id,
                    'referrer_id' => $settlement->referrer_id,
                    'comment' => $data['comment'] ?? null,
                    '_approval_flow' => $flowData['definition'],
                ],
                'flow' => $flowData['flow'],
            ]);
            return $settlement->fresh();
        });
    }

    public function payReferralSettlement(Request $request, ReferralSettlement $settlement): ReferralSettlement
    {
        $data = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
            'pay_voucher' => 'nullable|string',
            'payment_no'  => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);
        return DB::transaction(function () use ($request, $settlement, $data) {
            $settlement = ReferralSettlement::lockForUpdate()->findOrFail($settlement->id);
            if ($settlement->status !== 'approved') {
                throw new RuntimeException('仅已审批状态可付款');
            }
            $hasApprovedFlow = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'referral_settlement')
                ->whereJsonContains('payload->settlement_id', $settlement->id)
                ->where('status', ApprovalRecord::STATUS_APPROVED)
                ->exists();
            if (!$hasApprovedFlow) {
                throw new RuntimeException('未找到已完成的财务审批记录，不能付款');
            }
            $settlement->update([
                'status' => 'paid',
                'paid_by' => $request->user()->id,
                'paid_at' => now(),
                'payment_voucher' => $data['pay_voucher'] ?? null,
                'payment_no' => $data['payment_no'] ?? null,
                'notes' => $data['notes'] ?? $settlement->notes,
            ]);
            return $settlement->fresh();
        });
    }

    public function referralSettlementsStats(Request $request, User $user): array
    {
        $base = ReferralSettlement::query();
        $this->applySettlementScope($base, $user);
        if ($request->filled('referrer_id')) $base->where('referrer_id', $request->referrer_id);
        $paid = (clone $base)->where('status', 'paid');
        return [
            'total'    => (clone $base)->count(),
            'pending'  => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'paid'     => (clone $paid)->count(),
            'amount'   => (float)(clone $paid)->sum('amount'),
        ];
    }

    // ============================================================
    // === 内部辅助 ===
    // ============================================================

    private function canViewTeam(User $user): bool
    {
        return method_exists($user, 'hasRole')
            && ($user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('sales_manager'));
    }

    private function applyOpportunityOwnerScope($query, User $user): void
    {
        if (!$this->canViewTeam($user)) {
            $query->whereHas('opportunity', function ($opportunityQuery) use ($user) {
                $this->applyOwnerScope($opportunityQuery, $user, 'sales_id');
            });
        }
    }

    private function applySettlementScope($query, User $user): void
    {
        if ($this->canViewTeam($user) || (method_exists($user, 'hasRole') && $user->hasRole('finance'))) {
            return;
        }
        $query->whereHas('opportunity', function ($opportunityQuery) use ($user) {
            $this->applyOwnerScope($opportunityQuery, $user, 'sales_id');
        });
    }

    private function assertOpportunityAccess(Opportunity $opp, User $user): void
    {
        if ($this->canViewTeam($user)) {
            return;
        }
        $query = Opportunity::query()->whereKey($opp->getKey());
        $this->applyOwnerScope($query, $user, 'sales_id');
        if (!$query->exists()) {
            throw new RuntimeException('无权操作其他销售负责的商机');
        }
    }

    private function assertFollowUpTargetAccess(string $targetType, int $targetId, User $user): void
    {
        if (in_array($targetType, ['opp', 'opportunity'], true)) {
            $this->assertOpportunityAccess(Opportunity::findOrFail($targetId), $user);
            return;
        }
        if ($targetType === 'quote') {
            $quote = Quotation::with('opportunity')->findOrFail($targetId);
            $this->assertOpportunityAccess($quote->opportunity, $user);
            return;
        }
        if (!DB::table('customers')->where('id', $targetId)->exists()) {
            throw ValidationException::withMessages(['target_id' => '跟进客户不存在']);
        }
    }

    private function assertSettlementOwner(ReferralSettlement $settlement, User $user): void
    {
        if ($this->canViewTeam($user)) {
            return;
        }
        $opportunity = $settlement->opportunity ?: Opportunity::findOrFail($settlement->opportunity_id);
        $this->assertOpportunityAccess($opportunity, $user);
    }

    private function assertSettlementView(ReferralSettlement $settlement, User $user): void
    {
        if ($this->canViewTeam($user)
            || (method_exists($user, 'hasRole') && $user->hasRole('finance'))) {
            return;
        }

        $opportunity = $settlement->relationLoaded('opportunity')
            ? $settlement->opportunity
            : $settlement->load('opportunity')->opportunity;
        if (!$opportunity) {
            throw new \Illuminate\Auth\Access\AuthorizationException('结算单关联的商机不存在');
        }

        try {
            $this->assertOpportunityAccess($opportunity, $user);
        } catch (RuntimeException $e) {
            throw new \Illuminate\Auth\Access\AuthorizationException('无权查看其他销售负责的结算单', previous: $e);
        }
    }

    /**
     * 应用 owner 隔离 (admin/manager/sales_manager 跳过)
     */
    private function applyOwnerScope($query, User $user, string $col): void
    {
        if ($user && method_exists($user, 'hasRole')
            && !($user->hasRole('admin') || $user->hasRole('manager') || $user->hasRole('sales_manager'))) {
            $query->where(function ($q) use ($user, $col) {
                $q->where($col, $user->id);
                if ($user->department_id) {
                    $q->orWhereIn($col, function ($sub) use ($user) {
                        $sub->select('id')->from('users')->where('department_id', $user->department_id);
                    });
                }
            });
        }
    }

    /**
     * V1.4.5 (REVIEW P1-6): 销售合同编号统一走 NumberSequenceService (原项目 id 双轨)
     */
    private function nextSalesContractNo(): string
    {
        $today = now()->format('Ymd');
        $fullPrefix = 'SC-' . $today . '-';
        $seq = \App\Services\NumberSequenceService::next("sales-contract:{$today}", function () use ($fullPrefix): int {
            return (int) \App\Models\ProjectContract::allData()->where('contract_no', 'like', $fullPrefix . '%')
                ->pluck('contract_no')
                ->map(fn (string $n): int => (int) substr($n, strlen($fullPrefix)))
                ->max();
        });
        return $fullPrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
