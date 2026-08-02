<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InspectionPlan;
use App\Models\InspectionTask;
use App\Models\InspectionRecord;
use App\Models\InspectionIssue;
use App\Models\MaintenanceContract;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * V0.7 巡检计划
 *
 * 端点：
 * - 计划: list / show / store / update / destroy / toggle / cancel
 * - 任务: tasks / myTasks / taskDetail / skip / generate
 * - 打卡: checkin / checkout
 * - 异常: issues / issueDetail / resolve / ignore / convertToWorkOrder
 * - 统计: stats / overview
 */
class InspectionController extends Controller
{
    public function __construct(private InspectionService $svc) {}

    // ========== 计划 ==========

    public function index(Request $request): JsonResponse
    {
        $q = InspectionPlan::with(['contract', 'customer']);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('frequency')) $q->where('frequency', $request->frequency);
        if ($request->filled('contract_id')) $q->where('contract_id', $request->contract_id);
        if ($request->filled('customer_id')) $q->where('customer_id', $request->customer_id);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('name', 'like', "%{$kw}%")
                  ->orWhere('plan_no', 'like', "%{$kw}%");
            });
        }
        return response()->json([
            'code' => 0,
            'data' => $q->orderByDesc('created_at')->paginate($request->per_page ?? 15),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $plan = InspectionPlan::with(['contract', 'customer', 'tasks' => fn($q) => $q->orderByDesc('scheduled_date')->limit(20)])
            ->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $plan]);
    }

    public function store(Request $request): JsonResponse
    {
        // V1.2.10: 前端表单用 'next_date', 别名为 start_date
        if ($request->has('next_date') && !$request->has('start_date')) {
            $request->merge(['start_date' => $request->input('next_date')]);
        }
        $validated = $request->validate([
            // V1.2.10: contract_id 改可选, 没有合同的客户也可单独建巡检计划
            'contract_id'           => 'nullable|integer|exists:maintenance_contracts,id',
            'customer_id'           => 'nullable|integer|exists:customers,id',
            'name'                  => 'required|string|max:100',
            'frequency'             => 'required|in:weekly,biweekly,monthly,quarterly,semiannual,yearly,custom',
            'cycle_day'             => 'nullable|integer|min:1|max:31',
            'cycle_weekday'         => 'nullable|integer|min:1|max:7',
            'custom_interval_days'  => 'nullable|integer|min:1|max:365',
            'duration_hours'        => 'nullable|integer|min:1|max:24',
            'priority'              => 'nullable|integer|min:1|max:4',
            'assigned_to'           => 'nullable',
            'scope'                 => 'nullable|string',
            'checklist_template'    => 'nullable|array',
            'start_date'            => 'required|date',
            'next_date'             => 'sometimes|date',  // 别名兼容
            'end_date'              => 'nullable|date|after:start_date',
            'ahead_generate_days'   => 'nullable|integer|min:1|max:180',
        ]);

        if (isset($validated['assigned_to']) && is_array($validated['assigned_to'])) {
            $validated['assigned_to'] = json_encode($validated['assigned_to']);
        }

        $plan = $this->svc->createPlan($validated, $request->user());
        return response()->json(['code' => 0, 'data' => $plan->fresh()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = InspectionPlan::findOrFail($id);
        $validated = $request->validate([
            'name'                => 'sometimes|string|max:100',
            'scope'               => 'nullable|string',
            'checklist_template'  => 'nullable|array',
            'cycle_day'           => 'nullable|integer|min:1|max:31',
            'cycle_weekday'       => 'nullable|integer|min:1|max:7',
            'custom_interval_days' => 'nullable|integer|min:1|max:365',
            'duration_hours'      => 'nullable|integer|min:1|max:24',
            'priority'            => 'nullable|integer|min:1|max:4',
            'assigned_to'         => 'nullable',
            'end_date'            => 'nullable|date',
            'ahead_generate_days' => 'nullable|integer|min:1|max:180',
        ]);
        if (isset($validated['assigned_to']) && is_array($validated['assigned_to'])) {
            $validated['assigned_to'] = json_encode($validated['assigned_to']);
        }
        $plan->update($validated);
        return response()->json(['code' => 0, 'data' => $plan->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = InspectionPlan::findOrFail($id);
        $plan->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function toggle(int $id): JsonResponse
    {
        $plan = $this->svc->toggleStatus($id);
        return response()->json(['code' => 0, 'data' => $plan]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $plan = $this->svc->cancelPlan($id, $request->input('reason'));
        return response()->json(['code' => 0, 'data' => $plan]);
    }

    // ========== 任务 ==========

    public function tasks(Request $request): JsonResponse
    {
        $q = InspectionTask::with(['plan', 'customer', 'assignee', 'record']);
        if ($request->filled('plan_id')) $q->where('plan_id', $request->plan_id);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('assigned_to')) $q->where('assigned_to', $request->assigned_to);
        if ($request->filled('contract_id')) $q->where('contract_id', $request->contract_id);
        if ($request->filled('date_from')) $q->where('scheduled_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->where('scheduled_date', '<=', $request->date_to);
        return response()->json([
            'code' => 0,
            'data' => $q->orderBy('scheduled_at')->paginate($request->per_page ?? 20),
        ]);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $q = InspectionTask::with(['plan', 'customer', 'record'])
            ->where(function ($w) use ($userId) {
                $w->where('assigned_to', $userId)
                  ->orWhereNull('assigned_to');
            });
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->boolean('today')) {
            $q->whereDate('scheduled_date', today());
        }
        return response()->json([
            'code' => 0,
            'data' => $q->orderBy('scheduled_at')->paginate($request->per_page ?? 20),
        ]);
    }

    public function taskDetail(int $id): JsonResponse
    {
        $task = InspectionTask::with(['plan', 'contract', 'customer', 'assignee', 'record', 'issues'])
            ->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $task]);
    }

    public function skip(Request $request, int $id): JsonResponse
    {
        $task = $this->svc->skipTask($id, $request->input('reason'));
        return response()->json(['code' => 0, 'data' => $task]);
    }

    public function generate(Request $request, int $id): JsonResponse
    {
        $result = $this->svc->generateIncremental($id);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    // ========== 打卡 ==========

    public function checkin(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'checkin_location' => 'nullable|string|max:200',
            'checkin_lat'      => 'nullable|numeric',
            'checkin_lng'      => 'nullable|numeric',
            'checkin_photos'   => 'nullable|array',
        ]);
        $record = $this->svc->checkin($id, $validated, $request->user());
        return response()->json(['code' => 0, 'data' => $record], 201);
    }

    /**
     * 巡检记录列表 (V0.7.1 补 — 详情/打卡/结单/异常联动)
     * GET /api/inspections/records
     * Query: plan_id / task_id / inspector_id / status / start_date / end_date / keyword
     */
    public function records(Request $request): JsonResponse
    {
        try {
            $q = \App\Models\InspectionRecord::query()
                ->with([
                    'task:id,code,plan_id,scheduled_date,inspector_id',
                    'task.plan:id,code,name,contract_id',
                    'task.plan.contract:id,code,name,customer_id',
                    'task.plan.contract.customer:id,name',
                    'inspector:id,name',
                ]);
            if ($request->filled('plan_id'))       $q->where('plan_id', $request->plan_id);
            if ($request->filled('task_id'))       $q->where('task_id', $request->task_id);
            if ($request->filled('inspector_id'))  $q->where('inspector_id', $request->inspector_id);
            if ($request->filled('status'))        $q->where('status', $request->status);
            if ($request->filled('start_date'))    $q->whereDate('checkin_at', '>=', $request->start_date);
            if ($request->filled('end_date'))      $q->whereDate('checkin_at', '<=', $request->end_date);
            if ($request->filled('keyword')) {
                $kw = $request->keyword;
                $q->where(function ($w) use ($kw) {
                    $w->where('record_no', 'like', "%{$kw}%")
                      ->orWhere('summary', 'like', "%{$kw}%");
                });
            }
            $page = (int) $request->input('per_page', 15);
            return response()->json([
                'code' => 0,
                'data' => $q->orderByDesc('checkin_at')->paginate($page),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 脏数据容错: 比如 inspection_records 表某个 smallint 字段被塞了 '0.4' (历史 E2E bug)
            if (($e->errorInfo[0] ?? '') === '22P02') {
                return response()->json([
                    'code' => 500,
                    'message' => '巡检记录数据存在脏数据, 请联系管理员清理 (22P02: ' . substr($e->getMessage(), 0, 100) . ')',
                ], 500);
            }
            throw $e;
        }
    }

    /** GET /api/inspections/records/{id} */
    public function recordDetail(int $id): JsonResponse
    {
        $record = \App\Models\InspectionRecord::with([
            'task', 'task.plan', 'task.plan.contract', 'task.plan.contract.customer',
            'inspector', 'issues', 'issues.workOrder',
        ])->find($id);
        if (!$record) {
            return response()->json(['code' => 404, 'message' => '巡检记录不存在'], 404);
        }
        return response()->json(['code' => 0, 'data' => $record]);
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'checkout_location' => 'nullable|string|max:200',
            'checkout_lat'      => 'nullable|numeric',
            'checkout_lng'      => 'nullable|numeric',
            'checklist_answers' => 'nullable|array',
            'summary'           => 'nullable|string',
            'rating'            => 'nullable|integer|min:1|max:5',
            'issues'            => 'nullable|array',
            'issues.*.equipment_name'     => 'required|string',
            'issues.*.issue_type'         => 'required|in:hardware,software,network,power,environment,other',
            'issues.*.severity'           => 'required|in:low,medium,high,critical',
            'issues.*.title'              => 'required|string|max:200',
            'issues.*.description'        => 'required|string',
            'issues.*.equipment_location' => 'nullable|string',
            'issues.*.inventory_item_id'  => 'nullable|integer',
        ]);
        $record = $this->svc->checkout($id, $validated, $request->user());
        return response()->json(['code' => 0, 'data' => $record]);
    }

    // ========== 异常 ==========

    public function issues(Request $request): JsonResponse
    {
        $q = InspectionIssue::with(['record', 'task', 'plan', 'contract', 'customer', 'equipment', 'workOrder']);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('severity')) $q->where('severity', $request->severity);
        if ($request->filled('plan_id')) $q->where('plan_id', $request->plan_id);
        if ($request->filled('contract_id')) $q->where('contract_id', $request->contract_id);
        if ($request->filled('task_id')) $q->where('task_id', $request->task_id);
        if ($request->filled('record_id')) $q->where('record_id', $request->record_id);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('title', 'like', "%{$kw}%")
                  ->orWhere('equipment_name', 'like', "%{$kw}%")
                  ->orWhere('issue_no', 'like', "%{$kw}%");
            });
        }
        return response()->json([
            'code' => 0,
            'data' => $q->orderByDesc('created_at')->paginate($request->per_page ?? 15),
        ]);
    }

    public function issueDetail(int $id): JsonResponse
    {
        $issue = InspectionIssue::with(['record', 'task', 'plan', 'contract', 'customer', 'equipment', 'workOrder', 'resolver'])
            ->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $issue]);
    }

    public function resolveIssue(Request $request, int $id): JsonResponse
    {
        $request->validate(['resolution' => 'required|string']);
        $issue = $this->svc->resolveIssue($id, $request->input('resolution'), $request->user());
        return response()->json(['code' => 0, 'data' => $issue]);
    }

    public function ignoreIssue(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);
        $issue = $this->svc->ignoreIssue($id, $request->input('reason'));
        return response()->json(['code' => 0, 'data' => $issue]);
    }

    public function convertIssue(Request $request, int $id): JsonResponse
    {
        $issue = InspectionIssue::findOrFail($id);
        $wo = $this->svc->convertIssueToWorkOrder($issue);
        return response()->json(['code' => 0, 'data' => $wo], 201);
    }

    // ========== 统计 ==========

    public function stats(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->getStats()]);
    }

    public function overview(): JsonResponse
    {
        $stats = $this->svc->getStats();
        $recentTasks = InspectionTask::with(['plan', 'customer', 'assignee'])
            ->orderByDesc('created_at')->limit(10)->get();
        $recentIssues = InspectionIssue::with(['plan', 'contract', 'customer'])
            ->orderByDesc('created_at')->limit(10)->get();
        $upcomingTasks = InspectionTask::with(['plan', 'customer', 'assignee'])
            ->whereIn('status', [InspectionTask::STATUS_PENDING, InspectionTask::STATUS_IN_PROGRESS])
            ->where('scheduled_date', '>=', today())
            ->orderBy('scheduled_date')->limit(10)->get();
        return response()->json([
            'code' => 0,
            'data' => compact('stats', 'recentTasks', 'recentIssues', 'upcomingTasks'),
        ]);
    }

    public function activeContracts(): JsonResponse
    {
        $contracts = MaintenanceContract::with('customer')
            ->where(function ($q) {
                $q->whereIn('status', ['active', 'signed', 'in_progress'])
                  ->orWhereNull('status');
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', today());
            })
            ->orderBy('contract_no')
            ->get(['id', 'contract_no', 'customer_id', 'inspection_frequency', 'start_date', 'end_date']);
        return response()->json(['code' => 0, 'data' => $contracts]);
    }

    /**
     * 创建维保合同 (V0.7 dev: 给 E2E + 演示用, 真实业务走 /api/service/maintenance-contracts POST)
     */
    public function createContract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_no'           => 'required|string|max:64|unique:maintenance_contracts,contract_no',
            'customer_id'           => 'required|integer|exists:customers,id',
            'amount'                => 'required|numeric|min:0',
            'start_date'            => 'required|date',
            'end_date'              => 'nullable|date|after:start_date',
            'inspection_frequency'  => 'nullable|in:weekly,biweekly,monthly,quarterly,semiannual,yearly,custom',
            'scope'                 => 'nullable|string',
            'status'                => 'nullable|string',
            'contract_file'         => 'nullable|string', // base64 扫描件
            'contract_file_name'    => 'nullable|string|max:255',
        ]);
        $data['status'] = $data['status'] ?? 'active';
        $contract = MaintenanceContract::create($data);
        return response()->json(['code' => 0, 'data' => $contract], 201);
    }
}
