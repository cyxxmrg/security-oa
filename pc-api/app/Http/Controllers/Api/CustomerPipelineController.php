<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerPipelineController extends Controller
{
    /**
     * 5 个阶段 + 1 隐藏流失列
     * 阶段顺序固定: lead → contacted → quoted → negotiating → won, 末尾 lost
     */
    public const STAGES = ['lead', 'contacted', 'quoted', 'negotiating', 'won', 'lost'];

    public const STAGE_META = [
        'lead'         => ['label' => '线索',     'color' => '#94A3B8'],
        'contacted'    => ['label' => '首次拜访', 'color' => '#0C447C'],
        'quoted'       => ['label' => '方案报价', 'color' => '#534AB7'],
        'negotiating'  => ['label' => '商务谈判', 'color' => '#BA7517'],
        'won'          => ['label' => '成交',     'color' => '#1D9E75'],
        'lost'         => ['label' => '流失',     'color' => '#A32D2D'],
    ];

    /**
     * 看板用, 返回 6 列分组 + 4 个 KPI
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->with('assignedUser:id,name,avatar');

        // 排除非活跃? 默认全显示，主管视角都看
        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', (int) $request->input('assigned_user_id'));
        }
        if ($request->filled('keyword')) {
            $kw = trim((string) $request->input('keyword'));
            $query->where('name', 'like', "%{$kw}%");
        }

        $rows = $query->orderBy('last_activity_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // 6 列分组
        $columns = [];
        foreach (self::STAGES as $s) {
            $columns[$s] = [
                'stage'  => $s,
                'label'  => self::STAGE_META[$s]['label'],
                'color'  => self::STAGE_META[$s]['color'],
                'count'  => 0,
                'total'  => 0.0,
                'cards'  => [],
            ];
        }

        foreach ($rows as $c) {
            $stage = $c->pipeline_stage ?: 'lead';
            if (!in_array($stage, self::STAGES, true)) {
                $stage = 'lead';
            }
            $amount = (float) ($c->expected_amount ?: 0);
            $columns[$stage]['count']++;
            $columns[$stage]['total'] += $amount;
            $columns[$stage]['cards'][] = $this->serializeCard($c);
        }

        // 转成保留顺序的数组
        $columnList = array_values($columns);

        // KPI
        $activeCards = array_merge(
            $columns['lead']['cards'],
            $columns['contacted']['cards'],
            $columns['quoted']['cards'],
            $columns['negotiating']['cards'],
            $columns['won']['cards'],
        );
        $activeAmount =
            $columns['lead']['total'] +
            $columns['contacted']['total'] +
            $columns['quoted']['total'] +
            $columns['negotiating']['total'] +
            $columns['won']['total'];

        $newThisMonth = Customer::where('created_at', '>=', now()->startOfMonth())->count();

        // 平均成交周期: won 客户 created_at -> updated_at 天数中位数
        $wonDays = Customer::where('pipeline_stage', 'won')
            ->whereNotNull('created_at')
            ->whereNotNull('updated_at')
            ->get(['created_at', 'updated_at'])
            ->map(function ($c) {
                return Carbon::parse($c->created_at)->diffInDays(Carbon::parse($c->updated_at));
            })
            ->sort()
            ->values()
            ->all();
        $avgWonDays = null;
        if (count($wonDays) > 0) {
            $mid = (int) floor((count($wonDays) - 1) / 2);
            $avgWonDays = $wonDays[$mid];
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'columns'   => $columnList,
                'kpi'       => [
                    'total_opportunities' => count($activeCards),
                    'total_amount'        => round($activeAmount, 2),
                    'new_this_month'      => $newThisMonth,
                    'avg_won_days'        => $avgWonDays,
                ],
                'stages'    => self::STAGE_META,
            ],
        ]);
    }

    /**
     * 拖拽改阶段
     */
    public function updateStage(Request $request, Customer $customer): JsonResponse
    {
        // V0.5.8.2 修复: 前端 send {pipeline_stage: '...'}, 不能强制要求 'stage' 必填
        // 兼容两种字段名
        $data = $request->validate([
            'stage'          => ['sometimes', 'string', Rule::in(self::STAGES)],
            'pipeline_stage' => ['sometimes', 'string', Rule::in(self::STAGES)],
        ]);
        $newStage = $data['stage'] ?? $data['pipeline_stage'] ?? null;
        if (!$newStage) {
            return response()->json([
                'code' => 422,
                'message' => '数据校验失败',
                'errors' => ['stage' => ['缺少 stage 或 pipeline_stage 字段']],
            ], 422);
        }

        $customer->pipeline_stage  = $newStage;
        $customer->last_activity_at = now();
        // 改到 won / lost 时同步 updated_at, 方便成交周期计算
        $customer->save();

        return response()->json([
            'code' => 0,
            'data' => [
                'id'              => $customer->id,
                'pipeline_stage'  => $customer->pipeline_stage,
                'last_activity_at'=> $customer->last_activity_at?->toIso8601String(),
            ],
            'message' => '阶段已更新',
        ]);
    }

    /**
     * 4 周趋势: [{week: 'W1', new: 3, won: 1, lost: 0}, ...]
     */
    public function weeklyTrend(Request $request): JsonResponse
    {
        $now = now();
        $weeks = [];
        for ($i = 3; $i >= 0; $i--) {
            $start = $now->copy()->subWeeks($i)->startOfWeek();   // 周一
            $end   = $now->copy()->subWeeks($i)->endOfWeek();     // 周日
            $weeks[] = [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'label' => 'W' . (4 - $i),
            ];
        }

        $result = [];
        foreach ($weeks as $w) {
            $newCount = Customer::whereBetween('created_at', [$w['start'] . ' 00:00:00', $w['end'] . ' 23:59:59'])->count();
            $wonCount = Customer::where('pipeline_stage', 'won')
                ->whereBetween('updated_at', [$w['start'] . ' 00:00:00', $w['end'] . ' 23:59:59'])
                ->count();
            $lostCount = Customer::where('pipeline_stage', 'lost')
                ->whereBetween('updated_at', [$w['start'] . ' 00:00:00', $w['end'] . ' 23:59:59'])
                ->count();
            $result[] = [
                'week'      => $w['label'],
                'new_count' => $newCount,
                'won_count' => $wonCount,
                'lost_count'=> $lostCount,
            ];
        }

        return response()->json([
            'code' => 0,
            'data' => $result,
        ]);
    }

    private function serializeCard(Customer $c): array
    {
        $lastFollow = $c->followUps()->orderBy('created_at', 'desc')->first();
        $lastFollowAt = $lastFollow?->created_at;
        $lastActivity = $c->last_activity_at ? Carbon::parse($c->last_activity_at) : null;
        $expectedClose = $c->expected_close_date ? Carbon::parse($c->expected_close_date) : null;

        return [
            'id'                  => $c->id,
            'name'                => $c->name,
            'industry'            => $c->industry,
            'category'            => $c->category,
            'tags'                => $c->tags ?: [],
            'expected_amount'     => (float) ($c->expected_amount ?: 0),
            'expected_close_date' => $expectedClose?->toDateString(),
            'last_activity_at'    => $lastActivity?->toIso8601String(),
            'last_follow_at'      => $lastFollowAt?->toIso8601String(),
            'last_follow_ago'     => $lastFollowAt ? Carbon::parse($lastFollowAt)->diffForHumans(null, true, false) : null,
            'assigned_user'       => $c->assignedUser ? [
                'id'     => $c->assignedUser->id,
                'name'   => $c->assignedUser->name,
                'avatar' => $c->assignedUser->avatar,
            ] : null,
        ];
    }
}
