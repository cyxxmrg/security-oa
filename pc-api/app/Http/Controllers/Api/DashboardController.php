<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AuthScope;
use App\Enums\WorkOrderStatus;
use App\Models\ApprovalRecord;
use App\Models\Certificate;
use App\Models\ConstructionTeam;
use App\Models\EmployeeProfile;
use App\Models\ExpenseClaim;
use App\Models\ExternalConstructionWork;
use App\Models\LeaveRequest;
use App\Models\MaintenanceContract;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Receivable;
use App\Models\Rectification;
use App\Models\ServiceOrder;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\WorkProcess;
use App\Models\WorkOrder;
use App\Services\CacheHelper;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    /**
     * ????????????
     * ??????????????????????????????????
     */
    public function workbench(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user?->id;

        if (!$userId) {
            return response()->json(['code' => 401, 'message' => "\u{672A}\u{767B}\u{5F55}"], 401);
        }

        $cacheKey = 'dashboard:workbench:' . $userId;
        $data = CacheHelper::remember($cacheKey, 30, ['dashboard'], function () use ($userId) {
            $activeWorkOrderStatuses = [
                WorkOrderStatus::PENDING->value,
                WorkOrderStatus::ASSIGNED->value,
                WorkOrderStatus::IN_PROGRESS->value,
            ];

            $approvalTodos = ApprovalRecord::query()
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->where('current_approver_id', $userId)
                ->orderByDesc('id')
                ->take(8)
                ->get(['id', 'code', 'type', 'sub_type', 'title', 'created_at'])
                ->map(fn ($row) => [
                    'id' => 'approval-' . $row->id,
                    'type' => "\u{5BA1}\u{6279}",
                    'content' => $row->title ?: ($row->code ?: "\u{5F85}\u{5BA1}\u{6279}\u{4E8B}\u{9879}"),
                    'time' => optional($row->created_at)->diffForHumans() ?: "\u{8BF7}\u{53CA}\u{65F6}\u{5904}\u{7406}",
                    'link' => '/approval/' . $this->approvalRouteSegment((string) $row->type) . '?id=' . $row->id,
                    'source' => 'approval',
                    'sort_at' => optional($row->created_at)->timestamp ?? 0,
                ]);

            $workOrderTodos = WorkOrder::query()
                ->whereIn('status', $activeWorkOrderStatuses)
                ->where(function ($query) use ($userId) {
                    $query->where('assigned_to', $userId)
                        ->orWhere('created_by', $userId);
                })
                ->orderByDesc('id')
                ->take(8)
                ->get(['id', 'code', 'fault_description', 'status', 'assigned_to', 'created_by', 'created_at'])
                ->map(fn ($row) => [
                    'id' => 'work-order-' . $row->id,
                    'type' => "\u{5DE5}\u{5355}",
                    'content' => ($row->code ?: ("\u{5DE5}\u{5355} #" . $row->id)) . ' - ' . mb_substr((string) ($row->fault_description ?: "\u{5F85}\u{5904}\u{7406}\u{5DE5}\u{5355}"), 0, 40),
                    'time' => optional($row->created_at)->diffForHumans() ?: "\u{8BF7}\u{53CA}\u{65F6}\u{5904}\u{7406}",
                    'link' => '/maintenance/work-orders/' . $row->id,
                    'source' => 'work_order',
                    'sort_at' => optional($row->created_at)->timestamp ?? 0,
                ]);

            $messageTodos = Notification::query()
                ->where('notifiable_id', $userId)
                ->where('notifiable_type', User::class)
                ->whereNull('read_at')
                ->orderByDesc('id')
                ->take(8)
                ->get(['id', 'title', 'content', 'created_at'])
                ->map(fn ($row) => [
                    'id' => 'notification-' . $row->id,
                    'type' => "\u{6D88}\u{606F}",
                    'content' => $row->title ?: mb_substr((string) ($row->content ?: "\u{672A}\u{8BFB}\u{6D88}\u{606F}"), 0, 40),
                    'time' => optional($row->created_at)->diffForHumans() ?: "\u{8BF7}\u{67E5}\u{770B}",
                    'link' => '/message/list',
                    'source' => 'notification',
                    'sort_at' => optional($row->created_at)->timestamp ?? 0,
                ]);

            $todos = $approvalTodos
                ->concat($workOrderTodos)
                ->concat($messageTodos)
                ->sortByDesc('sort_at')
                ->values()
                ->take(12)
                ->map(function (array $item) {
                    unset($item['sort_at']);
                    return $item;
                })
                ->all();

            $myActiveProjects = Project::query()
                ->where('status', 'in_progress')
                ->where(function ($query) use ($userId) {
                    $query->where('manager_id', $userId)
                        ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('users.id', $userId));
                })
                ->count();

            $myPendingOrders = WorkOrder::query()
                ->whereIn('status', $activeWorkOrderStatuses)
                ->where(function ($query) use ($userId) {
                    $query->where('assigned_to', $userId)
                        ->orWhere('created_by', $userId);
                })
                ->count();

            return [
                'todos' => $todos,
                'summary' => [
                    'todo_count' => count($todos),
                    'my_active_projects' => $myActiveProjects,
                    'my_pending_work_orders' => $myPendingOrders,
                ],
                'meta' => [
                    'scope' => 'current_user',
                    'generated_at' => now()->toIso8601String(),
                ],
            ];
        });

        return response()->json(['code' => 0, 'data' => $data]);
    }

    private function approvalRouteSegment(string $type): string
    {
        return match ($type) {
            'finance' => 'finance',
            'project' => 'project',
            default => 'operation',
        };
    }

    public function stats(): JsonResponse
    {
        $request = request();
        $cacheKey = 'dashboard:stats:' . ($request->user() ? $request->user()->id : 'guest');
        // V1.2.7 P2-3: 用 CacheHelper 统一标签, 用户/角色变动时一键清
        $data = CacheHelper::remember($cacheKey, 60, ['dashboard'], function () use ($request) {
            $today = today();
            // V0.4.8 A3: 改真实 PG 查 (待办数 = pending_approvals + open_service_orders + pending_rectifications)
            $pendingTodos = (int) (ApprovalRecord::where('status', ApprovalRecord::STATUS_PENDING)->count()
                + ServiceOrder::whereIn('status', ['pending', 'assigned'])->count()
                + DB::table('rectifications')->where('status', 'pending')->count());
            $activeProjects = Project::where('status', 'in_progress')->count();
            $pendingServiceOrders = ServiceOrder::whereIn('status', ['pending', 'assigned'])->count();
            $monthlyRevenue = Receivable::whereMonth('received_date', $month = now()->month)->whereYear('received_date', now()->year)->sum('received_amount');
            $todayAttendance = AttendanceRecord::where('date', $today)->count();
            $leaveRequests = LeaveRequest::where('status', 'pending')->count();
            $expensePending = ExpenseClaim::where('status', 'submitted')->count();
            // V0.4.7 收口: 标志位, 前端可读 isFull 决定是否展示"全量"标签
            $isFull = AuthScope::isUnrestricted($request->user());

            return compact('pendingTodos', 'activeProjects', 'pendingServiceOrders', 'monthlyRevenue', 'todayAttendance', 'leaveRequests', 'expensePending', 'isFull');
        });

        return response()->json(['code' => 0, 'data' => $data]);
    }

    public function recentProjects(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => Project::with(['customer', 'manager'])->where('status', 'in_progress')->orderBy('updated_at', 'desc')->take(10)->get()]);
    }

    public function recentServiceOrders(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => ServiceOrder::with(['customer', 'assignedUser'])->whereIn('status', ['pending', 'assigned', 'in_progress'])->orderBy('created_at', 'desc')->take(10)->get()]);
    }

    /**
     * 工作台顶部"待办" (待审批+待派单+待回款)
     * GET /api/dashboard/todo
     */
    public function todo(): JsonResponse
    {
        // V1.2.7 P2-3: 走 CacheHelper, tag=dashboard
        $data = CacheHelper::remember('dashboard:todo', 60, ['dashboard'], fn () => $this->dashboardService->todos());
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * 项目进度概览 (项目列表)
     * GET /api/dashboard/project-progress
     */
    public function projectProgress(): JsonResponse
    {
        // V0.4.8 A3: 改真实查询 (前 10 个 in_progress 项目的 stage + progress + manager)
        $projects = Project::with(['manager:id,name'])
            ->whereNotNull('manager_id')
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get(['id', 'name', 'stage', 'progress', 'manager_id', 'end_date']);

        $data = $projects->map(fn ($p) => [
            'id'       => $p->id,
            'name'     => $p->name,
            'stage'    => $p->stage?->label() ?? (string) $p->stage,
            'progress' => (int) ($p->progress ?? 0),
            'manager'  => $p->manager?->name,
            'deadline' => $p->end_date?->toDateString(),
        ]);

        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * 售后关键指标 (SLA / 平均响应 / 满意度)
     * GET /api/dashboard/service-stats
     */
    public function serviceStats(): JsonResponse
    {
        // V1.2.7 P2-3: 走 CacheHelper, tag=dashboard
        $data = CacheHelper::remember('dashboard:service_stats', 120, ['dashboard'], fn () => $this->dashboardService->serviceMetrics());
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * 营收趋势 (近 12 月)
     * GET /api/dashboard/revenue-trend
     */
    public function revenueTrend(): JsonResponse
    {
        $data = CacheHelper::remember('dashboard:revenue_trend', 300, ['dashboard'], fn () => $this->dashboardService->revenueChart());
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * C3: 大屏驾驶舱一次性接口
     * GET /api/dashboard/screen
     */
    public function screen(): JsonResponse
    {
        $data = CacheHelper::remember('dashboard:screen', 120, ['dashboard'], fn () => $this->dashboardService->getScreenData());
        return response()->json(['code' => 0, 'data' => $data]);
    }

    // ============================================================
    // V0.4.5 Dashboard 重构 — 新增端点
    //   GET /api/dashboard/overview        — 一次性聚合 8 图块
    //   GET /api/dashboard/warranty-stats  — 质保单专项统计
    //
    // 适配说明（与 V0.4.5 任务书的差异已在实现里修正）：
    //  - Warranty 没有 Eloquent Model   → 走 DB::table('warranties')
    //  - CustomerReceivable 无 received_date → 月营收用 Receivable(老表)
    //  - ApprovalInstance 不存在           → pending 审批走 ApprovalRecord
    //  - Notification 是 morphTo 形态     → 收件人用 notifiable_id + notifiable_type=User::class
    //  - DeviceSerialNumber.status 实际为  in_stock/installed/in_repair/scrapped
    //                                  → 映射成 normal/fault/maintaining/scrapped
    //  - WorkProcess.status 是 active/disabled（不是 in_progress）→ 工序"进行中"用 active
    // ============================================================

    /**
     * V0.4.5 Dashboard 一次性聚合接口
     * GET /api/dashboard/overview
     *
     * 缓存 5 分钟（dashboard:overview），命中率高时 DB 几乎不被打扰。
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = 'dashboard:overview:' . ($user ? $user->id : 'guest');
        $data = CacheHelper::remember($cacheKey, 300, ['dashboard'], fn () => $this->dashboardService->getOverviewData($user));

        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * V0.4.5 质保单专项统计
     * GET /api/dashboard/warranty-stats
     */
    public function warrantyStats(): JsonResponse
    {
        $data = CacheHelper::remember('dashboard:warranty_stats', 300, ['dashboard'], fn () => $this->dashboardService->getWarrantyStats());
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * GET /api/dashboard/maintenance-stats
     * V0.5.5.2 A3 — 维修中心看板数据: 工单统计 + 返修统计 + 本周转返修率
     */
    public function maintenanceStats(): JsonResponse
    {
        $data = CacheHelper::remember('dashboard:maintenance_stats', 120, ['dashboard'], fn () => $this->dashboardService->getMaintenanceStats());
        return response()->json(['code' => 0, 'data' => $data]);
    }
}
