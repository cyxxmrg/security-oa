<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityStageRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpportunityStageRecordController extends Controller
{
    /** 8 段枚举白名单 — V1.2.12b 现场地勘独立成段 */
    private const ALLOWED_STAGES = [
        'inquiry', 'qualification', 'site_survey', 'proposal', 'negotiating', 'quoted', 'won', 'lost',
    ];

    /**
     * 阶段录入字段 schema (按 stage 不同)
     * 集中维护便于前后端同步
     */
    public static function stageFieldSchema(): array
    {
        return [
            'inquiry' => [
                'requirement'    => ['type' => 'textarea', 'label' => '客户需求描述', 'required' => true],
                'decision_maker' => ['type' => 'text',     'label' => '关键决策人',   'required' => false],
                'budget_range'   => ['type' => 'text',     'label' => '客户预算范围', 'required' => false],
                'urgency'        => ['type' => 'select',   'label' => '紧急程度',     'required' => false, 'options' => [
                    ['value' => 'low',    'label' => '不急'],
                    ['value' => 'medium', 'label' => '一般'],
                    ['value' => 'high',   'label' => '紧迫'],
                ]],
                'site_address'   => ['type' => 'text',     'label' => '现场地址',     'required' => false],
                'receiver_id'    => ['type' => 'user',     'label' => '现场对接人',   'required' => false],
            ],
            'qualification' => [
                'qualification_notes' => ['type' => 'textarea', 'label' => '资质评估意见', 'required' => true],
                'tech_requirements'   => ['type' => 'textarea', 'label' => '技术要求',     'required' => false],
                'competitor'          => ['type' => 'text',     'label' => '已知竞争对手', 'required' => false],
                'decision_timeline'   => ['type' => 'text',     'label' => '决策周期',     'required' => false],
                'reviewer_id'         => ['type' => 'user',     'label' => '评审负责人',   'required' => false],
            ],
            'site_survey' => [
                'survey_date'        => ['type' => 'date',     'label' => '地勘日期',     'required' => true],
                'survey_address'     => ['type' => 'text',     'label' => '现场地址',     'required' => true],
                'area_sqm'           => ['type' => 'number',   'label' => '面积(㎡)',     'required' => false],
                'structure_notes'    => ['type' => 'textarea', 'label' => '建筑结构说明', 'required' => false],
                'cabling_notes'      => ['type' => 'textarea', 'label' => '布线走向规划', 'required' => false],
                'power_notes'        => ['type' => 'textarea', 'label' => '供电情况',     'required' => false],
                'photos_url'         => ['type' => 'text',     'label' => '现场照片链接', 'required' => false],
                'survey_lead_id'     => ['type' => 'user',     'label' => '地勘负责人',   'required' => true],
                'surveyor_ids'       => ['type' => 'users',    'label' => '地勘协助人员', 'required' => false],
                'customer_escort_id' => ['type' => 'user',     'label' => '客户现场陪同', 'required' => false],
            ],
            'proposal' => [
                'proposal_summary' => ['type' => 'textarea', 'label' => '方案要点',     'required' => true],
                'design_owner_id'  => ['type' => 'user',     'label' => '设计负责人',   'required' => false],
                'assistant_id'     => ['type' => 'user',     'label' => '设计协助人',   'required' => false],
                'planned_doc_url'  => ['type' => 'text',     'label' => '方案文档链接', 'required' => false],
            ],
            'negotiating' => [
                'quoted_amount'     => ['type' => 'number', 'label' => '本次报价金额', 'required' => true],
                'negotiation_round' => ['type' => 'number', 'label' => '第几轮谈判',   'required' => true],
                'customer_feedback' => ['type' => 'textarea', 'label' => '客户反馈',   'required' => false],
                'discount_rate'     => ['type' => 'number', 'label' => '折扣率(%)',    'required' => false],
                'presenter_id'      => ['type' => 'user',     'label' => '商务主讲',   'required' => false],
            ],
            'quoted' => [
                'final_quote_amount' => ['type' => 'number', 'label' => '最终报价金额', 'required' => true],
                'quote_owner_id'     => ['type' => 'user',   'label' => '报价负责人',   'required' => false],
                'quote_doc_url'      => ['type' => 'text',   'label' => '报价文档链接', 'required' => false],
                'quote_expires_at'   => ['type' => 'date',   'label' => '报价有效期',   'required' => false],
            ],
            'won' => [
                'contract_no'     => ['type' => 'text',     'label' => '合同编号',   'required' => true],
                'contract_amount' => ['type' => 'number',   'label' => '合同金额',   'required' => true],
                'sign_date'       => ['type' => 'date',     'label' => '签约日期',   'required' => false],
                'sign_party'      => ['type' => 'text',     'label' => '签约方',     'required' => false],
                'contract_owner_id' => ['type' => 'user',   'label' => '合同负责人', 'required' => false],
            ],
            'lost' => [
                'lost_reason_id'     => ['type' => 'text',     'label' => '战败原因代码', 'required' => false],
                'lost_reason_detail' => ['type' => 'textarea', 'label' => '详细说明',     'required' => true],
                'lessons_learned'    => ['type' => 'textarea', 'label' => '经验教训',     'required' => false],
                'lost_owner_id'      => ['type' => 'user',     'label' => '复盘负责人',   'required' => false],
            ],
        ];
    }

    /**
     * 列出某商机全部阶段流转记录 (按 entered_at 升序)
     */
    public function index(Request $request, Opportunity $opp): JsonResponse
    {
        $records = OpportunityStageRecord::with(['enteredBy:id,name', 'nextAssignee:id,name'])
            ->where('opportunity_id', $opp->id)
            ->orderBy('entered_at')
            ->get();

        return response()->json([
            'code' => 0,
            'data' => $records,
            'stage_schema' => self::stageFieldSchema(),
        ]);
    }

    /**
     * 新增一条阶段流转记录 (含阶段自定义数据)
     * 同一 stage 允许多条 (不同时间点)
     */
    public function store(Request $request, Opportunity $opp): JsonResponse
    {
        $data = $request->validate([
            'stage'            => 'required|string|in:' . implode(',', self::ALLOWED_STAGES),
            'data'             => 'nullable|array',
            'note'             => 'nullable|string|max:2000',
            'entered_at'       => 'nullable|date',
            'next_assignee_id' => 'nullable|integer|exists:users,id',
            'next_due_at'      => 'nullable|date',
        ]);

        /** @var User $user */
        $user = $request->user();

        // 解析 next_assignee_name (冗余存储，便于列表展示)
        $nextAssigneeName = null;
        if (!empty($data['next_assignee_id'])) {
            $nextAssigneeName = User::whereKey($data['next_assignee_id'])->value('name');
        }

        return DB::transaction(function () use ($opp, $data, $user, $nextAssigneeName) {
            $record = OpportunityStageRecord::create([
                'opportunity_id'     => $opp->id,
                'stage'              => $data['stage'],
                'data'               => $data['data'] ?? [],
                'note'               => $data['note'] ?? null,
                'entered_at'         => $data['entered_at'] ?? now(),
                'entered_by'         => $user->id,
                'next_assignee_id'   => $data['next_assignee_id'] ?? null,
                'next_assignee_name' => $nextAssigneeName,
                'next_due_at'        => $data['next_due_at'] ?? null,
            ])->load(['enteredBy:id,name', 'nextAssignee:id,name']);

            // 同步更新商机当前阶段 (取最新一条记录)
            $opp->update([
                'stage'           => $data['stage'],
                'probability'     => self::stageDefaultProbability($data['stage']),
                'last_contact_at' => now(),
            ]);

            // V1.2.12h: 录入「成交」时自动入项目池（仅当尚无池记录时）
            if ($data['stage'] === 'won') {
                $existingPool = \App\Models\ProjectPool::where('opportunity_id', $opp->id)->exists();
                if (!$existingPool) {
                    \App\Models\ProjectPool::create([
                        'pool_no'        => 'POOL-' . date('YmdHis') . random_int(100, 999),
                        'opportunity_id' => $opp->id,
                        'customer_id'    => $opp->customer_id,
                        'name'           => $opp->name ?: '项目',
                        'status'         => 'pending',
                        'contract_amount' => $data['data']['contract_amount'] ?? $opp->estimated_amount ?? 0,
                    ]);
                }
            }

            return response()->json(['code' => 0, 'data' => $record]);
        });
    }

    public function show(Opportunity $opp, OpportunityStageRecord $record): JsonResponse
    {
        abort_unless($record->opportunity_id === $opp->id, 404);
        return response()->json([
            'code' => 0,
            'data' => $record->load(['enteredBy:id,name', 'nextAssignee:id,name']),
            'stage_schema' => self::stageFieldSchema()[$record->stage] ?? [],
        ]);
    }

    public function update(Request $request, Opportunity $opp, OpportunityStageRecord $record): JsonResponse
    {
        abort_unless($record->opportunity_id === $opp->id, 404);

        $data = $request->validate([
            'data'             => 'nullable|array',
            'note'             => 'nullable|string|max:2000',
            'entered_at'       => 'nullable|date',
            'next_assignee_id' => 'nullable|integer|exists:users,id',
            'next_due_at'      => 'nullable|date',
        ]);

        // 处理 next_assignee_name 同步
        if (array_key_exists('next_assignee_id', $data)) {
            $data['next_assignee_name'] = $data['next_assignee_id']
                ? User::whereKey($data['next_assignee_id'])->value('name')
                : null;
        }

        $record->update($data);
        return response()->json([
            'code' => 0,
            'data' => $record->fresh()->load(['enteredBy:id,name', 'nextAssignee:id,name']),
        ]);
    }

    public function destroy(Opportunity $opp, OpportunityStageRecord $record): JsonResponse
    {
        abort_unless($record->opportunity_id === $opp->id, 404);
        $record->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    /** 阶段 → 默认概率 */
    private static function stageDefaultProbability(string $stage): int
    {
        return match ($stage) {
            'inquiry'       => 10,
            'qualification' => 25,
            'site_survey'   => 35,  // V1.2.12b: 地勘完成通常意味着需求明确
            'proposal'      => 50,
            'negotiating'   => 65,
            'quoted'        => 85,
            'won'           => 100,
            'lost'          => 0,
            default         => 10,
        };
    }
}