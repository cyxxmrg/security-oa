<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\Project;
use App\Models\ApprovalRecord;
use App\Models\User;
use App\Http\Requests\Finance\StoreExpenseClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // V1.3.1: 缓存响应 JSON, 避免 Eloquent 序列化开销
        $cacheKey = 'expenses:index:' . ($request->user()?->id ?? 0) . ':' . md5(serialize($request->all()));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }

        $query = ExpenseClaim::with(['user:id,name,username', 'project:id,name,project_no', 'approver:id,name', 'items']);
        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('claim_no', 'like', "%{$kw}%")
                  ->orWhere('description', 'like', "%{$kw}%")
                  ->orWhereHas('user', function ($uq) use ($kw) {
                      $uq->where('name', 'like', "%{$kw}%");
                  });
            });
        }
        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('project_id')) $query->where('project_id', $request->project_id);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);

        $list = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        // 加状态/分类的中文 label
        $list->getCollection()->transform(function ($c) {
            $c->status_label   = $this->statusLabel($c->status);
            $c->category_label = $this->categoryLabel($c->category);
            return $c;
        });

        $json = json_encode(['code' => 0, 'data' => $list->toArray()]);
        Cache::put($cacheKey, $json, 30);

        return response()->json(json_decode($json, true));
    }

    public function show(Request $request, ExpenseClaim $claim): JsonResponse
    {
        $claim->load(['user:id,name,username', 'project:id,name,project_no', 'approver:id,name', 'items']);
        $claim->status_label   = $this->statusLabel($claim->status);
        $claim->category_label = $this->categoryLabel($claim->category);
        return response()->json(['code' => 0, 'data' => $claim]);
    }

    public function store(StoreExpenseClaimRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id']       = $request->user()->id;
        $data['status']        = 'submitted';
        $data['total_amount']  = collect($data['items'])->sum('amount');
        $data['claim_no']      = 'EXP' . date('Ymd') . strtoupper(Str::random(6));
        // 兜底: expense_claims.description NOT NULL
        if (empty($data['description'])) {
            $data['description'] = $data['category'] ?? '报销';
        }
        $items = $data['items']; unset($data['items']);
        $claim = ExpenseClaim::create($data);
        // 兜底: expense_items.item_date + description 都是 NOT NULL
        $today = now()->toDateString();
        foreach ($items as $item) {
            if (empty($item['item_date'])) {
                $item['item_date'] = $today;
            }
            if (empty($item['category'])) {
                $item['category'] = $data['category'] ?? '其他';
            }
            if (empty($item['description'])) {
                $item['description'] = $item['category'] ?? '报销明细';
            }
            $claim->items()->create($item);
        }
        $claim->load(['user', 'project', 'items']);

        // V1.2.5: 同步创建审批中心记录 (finance/expense), 按审批流程模板设定审批节点
        try {
            $year = now()->format('Y');
            $seq = ApprovalRecord::where('code', 'like', "FIN-{$year}-%")->count() + 1;
            $code = sprintf('FIN-%s-%04d', $year, $seq);
            $applicant = User::find($request->user()->id);

            // 按模板初始化审批流程
            $flowService = app(\App\Services\ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('expense');
            $flowData = $template
                ? $flowService->initFlow($template, $applicant, '提交报销单: ' . $claim->claim_no)
                : ['current_approver_id' => 1, 'flow' => [[
                    'operator' => $applicant?->name ?? '—',
                    'action'   => 'submit',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => '提交报销单: ' . $claim->claim_no,
                ]]];

            ApprovalRecord::create([
                'code'                => $code,
                'type'                => 'finance',
                'sub_type'            => 'expense',
                'title'               => $applicant?->name . '的报销申请 (' . $this->categoryLabel($data['category']) . ' ¥' . number_format($data['total_amount'], 2) . ')',
                'priority'            => 'normal',
                'status'              => ApprovalRecord::STATUS_PENDING,
                'amount'              => $data['total_amount'],
                'applicant_id'        => $request->user()->id,
                'current_approver_id' => $flowData['current_approver_id'],
                'payload'             => [
                    'claim_id'    => $claim->id,
                    'claim_no'    => $claim->claim_no,
                    'category'    => $data['category'],
                    'description' => $data['description'] ?? '',
                    'total_amount' => $data['total_amount'],
                    'project_id'  => $data['project_id'] ?? null,
                ],
                'flow'                => $flowData['flow'],
            ]);
        } catch (\Throwable $e) {
            \Log::error('ExpenseController::store sync to approval center failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => '报销单已提交', 'data' => $claim]);
    }

    public function update(Request $request, ExpenseClaim $claim): JsonResponse
    {
        if ($claim->status !== 'draft' && $claim->status !== 'submitted') {
            return response()->json(['code' => 1001, 'message' => '只有草稿/待审批状态的报销单可以修改'], 422);
        }
        $data = $request->validate([
            'category'    => 'sometimes|string',
            'description' => 'sometimes|string|max:1000',
            'project_id'  => 'sometimes|nullable|integer',
            'items'       => 'sometimes|array|min:1',
            'items.*.item_date'   => 'required_with:items|date',
            'items.*.description' => 'required_with:items|string|max:200',
            'items.*.amount'      => 'required_with:items|numeric|min:0',
        ]);
        if (isset($data['items'])) {
            $data['total_amount'] = collect($data['items'])->sum('amount');
            $items = $data['items']; unset($data['items']);
            $claim->items()->delete();
            // V1.2.10 修复 N+1: 批量插入替代循环逐条 create
            $claim->items()->createMany($items);
        }
        $claim->fill($data)->save();
        $claim->load(['user', 'project', 'items']);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $claim]);
    }

    public function destroy(Request $request, ExpenseClaim $claim): JsonResponse
    {
        if ($claim->user_id !== $request->user()->id && !$request->user()->can('expense.delete')) {
            return response()->json(['code' => 1001, 'message' => '只能删除自己的报销单'], 403);
        }
        if (in_array($claim->status, ['approved', 'paid'])) {
            return response()->json(['code' => 1002, 'message' => '已审批/已支付的单据不能删除'], 422);
        }
        $claim->items()->delete();
        $claim->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function approve(Request $request, ExpenseClaim $claim): JsonResponse
    {
        $request->validate([
            'action'  => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:500',
        ]);
        // P1-8 修复: 禁止自审 + 校验审批权限
        if ($claim->user_id === $request->user()->id) {
            return response()->json(['code' => 1010, 'message' => '不能审批自己的申请'], 403);
        }
        if (!$request->user()->can('expense.approve')) {
            return response()->json(['code' => 1011, 'message' => '当前账号没有报销审批权限'], 403);
        }
        if (!in_array($claim->status, ['submitted'], true)) {
            return response()->json(['code' => 1001, 'message' => '只能审批待审批状态的报销单'], 422);
        }
        $claim->update([
            'status'        => $request->action,
            'approver_id'   => $request->user()->id,
            'approved_at'   => now(),
            'reject_reason' => $request->action === 'rejected' ? $request->comment : null,
        ]);

        // V1.2.5: 同步更新审批中心记录
        try {
            $approval = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'expense')
                ->where('payload->claim_id', $claim->id)
                ->first();
            if ($approval) {
                $flow = is_array($approval->flow) ? $approval->flow : [];
                $flow[] = [
                    'operator' => User::find($request->user()->id)?->name ?? '—',
                    'action'   => $request->action === 'approved' ? 'approve' : 'reject',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => $request->comment ?? ($request->action === 'approved' ? '同意' : '驳回'),
                ];
                $approval->flow = $flow;
                $approval->status = $request->action === 'approved'
                    ? ApprovalRecord::STATUS_APPROVED
                    : ApprovalRecord::STATUS_REJECTED;
                $approval->comment = $request->comment ?? null;
                $approval->save();
            }
        } catch (\Throwable $e) {
            \Log::error('ExpenseController::approve sync approval failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => '审批完成']);
    }

    public function cancel(Request $request, ExpenseClaim $claim): JsonResponse
    {
        if ($claim->user_id !== $request->user()->id) {
            return response()->json(['code' => 1001, 'message' => '只能撤销自己的报销单'], 403);
        }
        if (in_array($claim->status, ['approved', 'paid'], true)) {
            return response()->json(['code' => 1002, 'message' => '已审批/已支付的单据不能撤销'], 422);
        }
        $claim->update(['status' => 'cancelled']);

        // V1.2.5: 同步撤销审批中心记录
        try {
            $approval = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'expense')
                ->where('payload->claim_id', $claim->id)
                ->first();
            if ($approval && $approval->status === ApprovalRecord::STATUS_PENDING) {
                $flow = is_array($approval->flow) ? $approval->flow : [];
                $flow[] = [
                    'operator' => User::find($request->user()->id)?->name ?? '—',
                    'action'   => 'cancel',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => '申请人撤销报销单',
                ];
                $approval->flow = $flow;
                $approval->status = ApprovalRecord::STATUS_CANCELLED;
                $approval->save();
            }
        } catch (\Throwable $e) {
            \Log::error('ExpenseController::cancel sync approval failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => '已撤销']);
    }

    public function pay(Request $request, ExpenseClaim $claim): JsonResponse
    {
        $data = $request->validate([
            'paid_amount' => 'required|numeric|min:0',
        ]);
        if ($claim->status !== 'approved') {
            return response()->json(['code' => 1001, 'message' => '只有已审批的单据可以标记付款'], 422);
        }
        $claim->update([
            'status'      => 'paid',
            'paid_at'     => now(),
            'paid_amount' => $data['paid_amount'],
        ]);

        // V1.2.7f: 同步付款节点到审批中心 (让审批流能显示"付款成功")
        try {
            $approval = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'expense')
                ->where('payload->claim_id', $claim->id)
                ->first();
            if ($approval) {
                $flow = is_array($approval->flow) ? $approval->flow : [];
                $flow[] = [
                    'operator'     => User::find($request->user()->id)?->name ?? '—',
                    'action'       => 'pay_done',
                    'time'         => now()->toDateTimeString(),
                    'comment'      => '已完成付款, 金额: ¥' . number_format($data['paid_amount'], 2),
                    'paid_amount'  => $data['paid_amount'],
                    'paid_at'      => now()->toDateTimeString(),
                ];
                $approval->flow = $flow;
                $approval->comment = '已付款 ¥' . number_format($data['paid_amount'], 2);
                $approval->save();
            }
        } catch (\Throwable $e) {
            \Log::error('ExpenseController::pay sync approval failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => '已标记付款']);
    }

    public function myClaims(Request $request): JsonResponse
    {
        $list = $request->user()->expenseClaims()
            ->with(['project:id,name,project_no', 'items'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);
        $list->getCollection()->transform(function ($c) {
            $c->status_label   = $this->statusLabel($c->status);
            $c->category_label = $this->categoryLabel($c->category);
            return $c;
        });
        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function stats(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $total    = ExpenseClaim::where('user_id', $uid)->count();
        $pending  = ExpenseClaim::where('user_id', $uid)->where('status', 'submitted')->count();
        $approved = ExpenseClaim::where('user_id', $uid)->where('status', 'approved')->count();
        $paid     = ExpenseClaim::where('user_id', $uid)->where('status', 'paid')->count();
        $totalAmount = ExpenseClaim::where('user_id', $uid)->sum('total_amount');
        $paidAmount  = ExpenseClaim::where('user_id', $uid)->where('status', 'paid')->sum('paid_amount');
        return response()->json(['code' => 0, 'data' => compact('total', 'pending', 'approved', 'paid', 'totalAmount', 'paidAmount')]);
    }

    // 项目下拉
    public function projects(Request $request): JsonResponse
    {
        $projects = Project::select('id', 'name', 'project_no')->orderBy('name')->limit(200)->get();
        return response()->json(['code' => 0, 'data' => $projects]);
    }

    /**
     * V1.2.7g: 报销统计聚合 (供 stats tab 用)
     * GET /api/expenses/stats-group?group_by=user&date_from=2026-01-01&date_to=2026-12-31
     * group_by: user / category / project
     * 返回: { summary, group: [{name, count, amount, months}] }
     */
    public function statsGroup(Request $request): JsonResponse
    {
        $groupBy = $request->input('group_by', 'user');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $base = ExpenseClaim::query();
        if ($dateFrom) $base->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $base->whereDate('created_at', '<=', $dateTo);

        $all = (clone $base)->get(['id', 'user_id', 'category', 'project_id', 'total_amount', 'status', 'created_at']);

        // 汇总 KPI
        $summary = [
            'totalCount'    => $all->count(),
            'totalAmount'   => (float) $all->sum('total_amount'),
            'paidAmount'    => (float) $all->where('status', 'paid')->sum('total_amount'),
            'approvedCount' => $all->whereIn('status', ['approved', 'paid'])->count(),
            'approvedAmount'=> (float) $all->whereIn('status', ['approved', 'paid'])->sum('total_amount'),
            'pendingCount'  => $all->where('status', 'submitted')->count(),
            'pendingAmount' => (float) $all->where('status', 'submitted')->sum('total_amount'),
        ];

        // 分组
        $group = [];
        $keyFn = match($groupBy) {
            'category' => fn($r) => $r->category ?: '其他',
            'project'  => fn($r) => $r->project_id ? ('#' . $r->project_id) : '无项目',
            default    => fn($r) => '#' . ($r->user_id ?? 0),
        };
        $map = [];
        $nameMap = []; // id → 显示名
        foreach ($all as $r) {
            $k = $keyFn($r);
            if (!isset($map[$k])) {
                $map[$k] = ['name' => $k, 'count' => 0, 'amount' => 0.0, 'months' => 0, 'mset' => []];
            }
            $map[$k]['count']++;
            $map[$k]['amount'] += (float) ($r->total_amount ?? 0);
            $m = substr((string) $r->created_at, 0, 7);
            if ($m) $map[$k]['mset'][$m] = true;
        }
        // 拿 name 显示
        if ($groupBy === 'user') {
            $userIds = array_filter(array_map(fn($s) => (int) ltrim($s, '#'), array_keys($map)));
            if (!empty($userIds)) {
                $users = User::whereIn('id', $userIds)->pluck('name', 'id');
                foreach ($map as $k => &$it) {
                    $uid = (int) ltrim($k, '#');
                    $it['name'] = $users[$uid] ?? ('#' . $uid);
                }
            }
        } elseif ($groupBy === 'category') {
            $catLabels = ['travel' => '差旅费', 'hospitality' => '招待费', 'office' => '办公费', 'transport' => '交通费', 'project_cost' => '项目成本', 'other' => '其他'];
            foreach ($map as $k => &$it) {
                $it['name'] = $catLabels[$k] ?? $k;
            }
        } elseif ($groupBy === 'project') {
            $pids = array_filter(array_map(fn($s) => (int) ltrim($s, '#'), array_keys($map)));
            if (!empty($pids)) {
                $projects = Project::whereIn('id', $pids)->pluck('name', 'id');
                foreach ($map as $k => &$it) {
                    $pid = (int) ltrim($k, '#');
                    $it['name'] = $projects[$pid] ?? ('#' . $pid);
                }
            }
        }
        $group = array_values(array_map(function ($it) {
            return ['name' => $it['name'], 'count' => $it['count'], 'amount' => round($it['amount'], 2), 'months' => max(count($it['mset']), 1)];
        }, $map));
        usort($group, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return response()->json(['code' => 0, 'data' => ['summary' => $summary, 'group' => array_slice($group, 0, 50)]]);
    }

    private function statusLabel(string $s): string
    {
        return match($s) {
            'draft'     => '草稿',
            'submitted' => '待审批',
            'approved'  => '已审批',
            'rejected'  => '已驳回',
            'paid'      => '已付款',
            'cancelled' => '已撤销',
            default     => $s,
        };
    }

    private function categoryLabel(string $c): string
    {
        return match($c) {
            'travel'        => '差旅费',
            'hospitality'   => '招待费',
            'office'        => '办公费',
            'transport'     => '交通费',
            'meal'          => '餐饮费',
            'accommodation' => '住宿费',
            'training'      => '培训费',
            'project_cost'  => '项目成本',
            'other'         => '其他',
            default         => $c,
        };
    }
}
