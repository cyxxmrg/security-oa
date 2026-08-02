<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog; // V1.2.10 替代 SystemLog::
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.9 C1 - 审计报表
 *
 * 提供 4 个端点:
 *  - GET /api/audit/data-scope-denied    拦截记录 (分页 + 聚合)
 *  - GET /api/audit/data-scope-summary   7 天/30 天聚合
 *  - GET /api/audit/data-scope-stats     按 user/table 维度统计
 *  - GET /api/audit/role-changes         V0.5.4 权限/角色变更流水 (role_changed/temporary_role_granted/role_revoked)
 */
class AuditController extends Controller
{
    /**
     * data_scope_denied 记录列表
     */
    public function dataScopeDenied(Request $request): JsonResponse
    {
        $perPage = (int) ($request->per_page ?? 20);
        $perPage = max(1, min($perPage, 200));

        $query = SystemLog::where('action', 'data_scope_denied');

        // 过滤
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('days')) {
            $days = max(1, min((int) $request->days, 90));
            $query->where('created_at', '>=', Carbon::now()->subDays($days));
        }

        $total = (clone $query)->count();
        $list  = $query->orderBy('id', 'desc')
            ->paginate($perPage)
            ->through(function ($r) {
                return [
                    'id'         => (int) $r->id,
                    'user_id'    => (int) $r->user_id,
                    'module'     => $r->module,
                    'action'     => $r->action,
                    'record_id'  => null,  // 旧 schema 无 record_id 字段
                    'description' => $r->description,
                    'ip'         => $r->ip,
                    'user_agent' => substr((string) $r->user_agent, 0, 200),
                    'created_at' => $r->created_at,
                ];
            });

        return response()->json(['code' => 0, 'data' => [
            'list'  => $list->items(),
            'total' => $total,
            'page'  => $list->currentPage(),
            'per_page' => $perPage,
        ]]);
    }

    /**
     * 7 天聚合 (按天)
     */
    public function dataScopeSummary(Request $request): JsonResponse
    {
        $days = (int) ($request->input('days', 7));
        $days = max(1, min($days, 90));

        $rows = SystemLog::query()
            ->select(DB::raw("to_char(created_at, 'YYYY-MM-DD') as day"), DB::raw('count(*) as cnt'))
            ->where('action', 'data_scope_denied')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('day')
            ->orderBy('day', 'asc')
            ->get();

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r->day] = (int) $r->cnt;
        }

        // 补齐所有天 (前端画图用)
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->format('Y-m-d');
            $out[] = ['day' => $day, 'count' => $byDay[$day] ?? 0];
        }

        return response()->json(['code' => 0, 'data' => [
            'days'        => $days,
            'daily'       => $out,
            'total'       => array_sum(array_column($out, 'count')),
            'unique_users' => (int) SystemLog::query()
                ->where('action', 'data_scope_denied')
                ->where('created_at', '>=', Carbon::now()->subDays($days))
                ->distinct('user_id')
                ->count('user_id'),
        ]]);
    }

    /**
     * 按 user/table 聚合 Top 10
     */
    public function dataScopeStats(Request $request): JsonResponse
    {
        $days = (int) ($request->input('days', 30));
        $days = max(1, min($days, 90));

        $byUser = DB::table('system_logs as sl')
            ->leftJoin('users as u', 'u.id', '=', 'sl.user_id')
            ->select('sl.user_id', 'u.username', DB::raw('count(*) as cnt'))
            ->where('sl.action', 'data_scope_denied')
            ->where('sl.created_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('sl.user_id', 'u.username')
            ->orderBy('cnt', 'desc')
            ->limit(10)
            ->get();

        $byModule = SystemLog::query()
            ->select('module', DB::raw('count(*) as cnt'))
            ->where('action', 'data_scope_denied')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('module')
            ->groupBy('module')
            ->orderBy('cnt', 'desc')
            ->get();

        return response()->json(['code' => 0, 'data' => [
            'days'      => $days,
            'by_user'   => $byUser,
            'by_module' => $byModule,
        ]]);
    }

    /**
     * V0.4.10 - 审计日志列表 (兼容 routes/api.php 中 audit-logs/* 路由)
     * 复用 system_logs 表, 支持 type/action/user_id 过滤
     */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('system_logs as sl')
            ->leftJoin('users as u', 'u.id', '=', 'sl.user_id')
            ->select('sl.*', 'u.username as operator_name')
            ->orderByDesc('sl.id');

        if ($request->filled('action')) $q->where('sl.action', $request->action);
        if ($request->filled('module')) $q->where('sl.module',  $request->module);
        if ($request->filled('user_id')) $q->where('sl.user_id', $request->user_id);
        if ($request->filled('days')) {
            $days = max(1, min((int) $request->days, 90));
            $q->where('sl.created_at', '>=', Carbon::now()->subDays($days));
        }

        return response()->json(['code' => 0, 'data' => $q->paginate($request->input('per_page', 20))]);
    }

    public function show(int $id): JsonResponse
    {
        $row = DB::table('system_logs as sl')
            ->leftJoin('users as u', 'u.id', '=', 'sl.user_id')
            ->where('sl.id', $id)
            ->select('sl.*', 'u.username as operator_name')
            ->first();
        if (!$row) return response()->json(['code' => 404, 'message' => '日志不存在'], 404);
        return response()->json(['code' => 0, 'data' => $row]);
    }

    /**
     * V0.5.4 权限/角色变更流水
     * GET /api/audit/role-changes?per_page=20&days=30&action=role_changed&q=xxx
     *
     * 涵盖 3 类 action:
     *   - role_changed             (V0.5.1 usersSyncRoles)
     *   - temporary_role_granted   (V0.5.3 usersGrantTemporary)
     *   - role_revoked             (V0.5.3 usersRevokeRole)
     */
    public function roleChanges(Request $request): JsonResponse
    {
        $perPage = (int) ($request->per_page ?? 20);
        $perPage = max(1, min($perPage, 200));

        $q = DB::table('system_logs as sl')
            ->leftJoin('users as u', 'u.id', '=', 'sl.user_id')
            ->whereIn('sl.action', ['role_changed', 'temporary_role_granted', 'role_revoked'])
            ->orderBy('sl.created_at', 'desc')
            ->select('sl.*', 'u.username as operator_name', 'u.name as operator_realname');

        if ($request->filled('action')) {
            $q->where('sl.action', $request->action);
        }
        if ($request->filled('user_id')) {
            // 匹配操作人或被操作人 (description 含 "用户「xxx」(#id)")
            $needle = "(#{$request->user_id})";
            $q->where('sl.description', 'ilike', '%' . $needle . '%');
        }
        if ($request->filled('days')) {
            $days = max(1, min((int) $request->days, 365));
            $q->where('sl.created_at', '>=', Carbon::now()->subDays($days));
        }
        if ($request->filled('q')) {
            $kw = trim($request->q);
            $q->where('sl.description', 'ilike', '%' . $kw . '%');
        }

        // 拉全部 (前 N 端点按时间排, 不分页) — 前端瀑布流
        $rows = $q->limit(500)->get()->map(function ($r) {
            // 解析 description 抽取 target_user_id (如果有)
            $target = null;
            if (preg_match('/\(#(\d+)\)/', (string) $r->description, $m)) {
                $target = (int) $m[1];
            }
            return [
                'id'           => $r->id,
                'action'       => $r->action,
                'operator_id'  => $r->user_id,
                'operator'     => $r->operator_realname ?: $r->operator_name ?: '系统',
                'description'  => $r->description,
                'target_user_id' => $target,
                'ip'           => $r->ip,
                'created_at'   => $r->created_at,
                'module'       => $r->module,
            ];
        });

        // 关键: 批量查被操作人 username 避免前端二次 join
        $targetIds = collect($rows)->pluck('target_user_id')->filter()->unique()->all();
        $targetMap = [];
        if (!empty($targetIds)) {
            $targetMap = DB::table('users')
                ->whereIn('id', $targetIds)
                ->select('id', 'username', 'name')
                ->get()
                ->keyBy('id')
                ->map(fn ($u) => ['username' => $u->username, 'name' => $u->name])
                ->all();
        }
        $rows = $rows->map(function ($r) use ($targetMap) {
            if ($r['target_user_id'] && isset($targetMap[$r['target_user_id']])) {
                $r['target_username'] = $targetMap[$r['target_user_id']]['username'];
                $r['target_name']     = $targetMap[$r['target_user_id']]['name'];
            }
            return $r;
        });

        return response()->json(['code' => 0, 'data' => $rows->values()->all()]);
    }

    /**
     * V0.5.4 权限变更 — 7 天聚合
     * GET /api/audit/role-changes/summary?days=7
     */
    public function roleChangesSummary(Request $request): JsonResponse
    {
        $days = max(1, min((int) ($request->days ?? 7), 365));
        $rows = SystemLog::query()
            ->whereIn('action', ['role_changed', 'temporary_role_granted', 'role_revoked'])
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->all();

        // 按天 7 天分布 (前端 sparkline 用)
        $dailyRows = SystemLog::query()
            ->whereIn('action', ['role_changed', 'temporary_role_granted', 'role_revoked'])
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, action, COUNT(*) as count")
            ->groupBy('day', 'action')
            ->orderBy('day')
            ->get()
            ->groupBy('day');

        $dailySeries = [];
        foreach ($dailyRows as $day => $items) {
            $entry = ['day' => $day];
            foreach ($items as $r) {
                $entry[$r->action] = (int) $r->count;
            }
            $dailySeries[] = $entry;
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'days'         => $days,
                'by_action'    => [
                    'role_changed'           => (int) ($rows['role_changed'] ?? 0),
                    'temporary_role_granted' => (int) ($rows['temporary_role_granted'] ?? 0),
                    'role_revoked'           => (int) ($rows['role_revoked'] ?? 0),
                ],
                'total'        => array_sum($rows),
                'daily_series' => $dailySeries,
            ],
        ]);
    }
}
