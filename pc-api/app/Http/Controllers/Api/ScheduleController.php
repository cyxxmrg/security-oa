<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\BatchByGroupRequest;
use App\Http\Requests\Schedule\BatchSaveScheduleRequest;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Models\ShiftGroupMember;
use App\Models\Schedule;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    /**
     * 注入 ScheduleService (P1 抽离的复杂业务逻辑)
     */
    public function __construct(private ScheduleService $scheduleService) {}

    // ========== 班次管理 ==========

    public function listShifts(Request $request): JsonResponse
    {
        $q = Shift::query();
        if ($request->filled('is_active')) $q->where('is_active', $request->boolean('is_active'));
        $shifts = $q->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(['code' => 0, 'data' => $shifts]);
    }

    public function storeShift(\App\Http\Requests\Schedule\StoreShiftRequest $request): JsonResponse
    {
        $data = $request->validated();
        // 自动判断跨夜班
        if (!isset($data['is_overnight'])) {
            $data['is_overnight'] = $data['end_time'] < $data['start_time'];
        }
        $shift = Shift::create($data);
        return response()->json(['code' => 0, 'message' => '班次已创建', 'data' => $shift]);
    }

    public function updateShift(\App\Http\Requests\Schedule\UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $start = $data['start_time'] ?? $shift->start_time;
            $end   = $data['end_time']   ?? $shift->end_time;
            $data['is_overnight'] = $end < $start;
        }
        $shift->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $shift]);
    }

    public function destroyShift(Shift $shift): JsonResponse
    {
        // V1.2.4v: 系统默认班次不可删除 (保证新员工自动有班次)
        if ($shift->is_default) {
            return response()->json(['code' => 1001, 'message' => '系统默认班次不可删除, 可手动修改时间/阈值'], 422);
        }
        if ($shift->schedules()->exists()) {
            return response()->json(['code' => 1001, 'message' => '该班次已被排班使用, 不能删除(可停用)'], 422);
        }
        $shift->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /**
     * 拿到系统默认班次 (新员工入职自动排这个班)
     * GET /api/schedules/default-shift
     */
    public function defaultShift(Request $request): JsonResponse
    {
        $shift = Shift::where('is_default', true)->where('is_active', true)->first()
            ?? Shift::where('code', 'day')->first()
            ?? Shift::where('is_active', true)->orderBy('sort_order')->first();

        return response()->json(['code' => 0, 'data' => $shift]);
    }

    // ========== 班组管理 ==========

    public function listGroups(Request $request): JsonResponse
    {
        $groups = ShiftGroup::with(['leader:id,name,username', 'members.user:id,name,username'])
            ->withCount('members')
            ->orderBy('id')
            ->get();
        return response()->json(['code' => 0, 'data' => $groups]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:shift_groups,code',
            'leader_id' => 'nullable|exists:users,id',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);
        $group = ShiftGroup::create($data);
        return response()->json(['code' => 0, 'message' => '班组已创建', 'data' => $group]);
    }

    public function updateGroup(Request $request, ShiftGroup $group): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'code' => 'sometimes|string|max:20|unique:shift_groups,code,' . $group->id,
            'leader_id' => 'nullable|exists:users,id',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);
        $group->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $group]);
    }

    public function destroyGroup(ShiftGroup $group): JsonResponse
    {
        if ($group->members()->exists()) {
            return response()->json(['code' => 1001, 'message' => '班组还有成员, 请先移除'], 422);
        }
        $group->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /**
     * 替换班组所有成员
     * body: { user_ids: [1,2,3] }
     */
    public function syncGroupMembers(Request $request, ShiftGroup $group): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        DB::transaction(function () use ($group, $data) {
            ShiftGroupMember::where('group_id', $group->id)->delete();
            foreach ($data['user_ids'] as $uid) {
                ShiftGroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $uid,
                    'joined_at' => today(),
                ]);
            }
        });
        return response()->json(['code' => 0, 'message' => '成员已更新 (' . count($data['user_ids']) . ' 人)']);
    }

    public function addGroupMember(Request $request, ShiftGroup $group): JsonResponse
    {
        $data = $request->validate(['user_id' => 'required|exists:users,id']);
        $exists = ShiftGroupMember::where('group_id', $group->id)->where('user_id', $data['user_id'])->exists();
        if ($exists) return response()->json(['code' => 1001, 'message' => '该员工已在班组中'], 422);
        ShiftGroupMember::create(['group_id' => $group->id, 'user_id' => $data['user_id'], 'joined_at' => today()]);
        return response()->json(['code' => 0, 'message' => '已加入班组']);
    }

    public function removeGroupMember(ShiftGroup $group, User $user): JsonResponse
    {
        ShiftGroupMember::where('group_id', $group->id)->where('user_id', $user->id)->delete();
        return response()->json(['code' => 0, 'message' => '已移出班组']);
    }

    // ========== 排班计划 ==========

    /**
     * 排班日历视图: 一段时间范围内所有排班
     * GET /api/schedules?start=2026-06-20&end=2026-06-26
     * 返回: { [date]: [{ user_id, user_name, shift_id, shift_name, color, group_id, group_name, status }] }
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => 'required|date_format:Y-m-d',
            'end'   => 'required|date_format:Y-m-d|after_or_equal:start',
            'user_id' => 'nullable|exists:users,id',
            'group_id' => 'nullable|exists:shift_groups,id',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        $q = Schedule::with(['user:id,name,username', 'shift:id,name,color,start_time,end_time,is_overnight', 'group:id,name,color']);
        $q->whereBetween('date', [$data['start'], $data['end']]);
        if (!empty($data['user_id'])) $q->where('user_id', $data['user_id']);
        if (!empty($data['group_id'])) $q->where('group_id', $data['group_id']);
        if (!empty($data['shift_id'])) $q->where('shift_id', $data['shift_id']);

        $rows = $q->orderBy('date')->orderBy('user_id')->get();

        // 按日期分组
        $byDate = [];
        foreach ($rows as $r) {
            $d = $r->date->format('Y-m-d');
            $byDate[$d][] = [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'user_name' => $r->user->name ?? $r->user->username ?? null,
                'group_id' => $r->group_id,
                'group_name' => $r->group->name ?? null,
                'group_color' => $r->group->color ?? null,
                'shift_id' => $r->shift_id,
                'shift_name' => $r->shift->name ?? null,
                'shift_color' => $r->shift->color ?? null,
                'start_time' => $r->shift->start_time ?? null,
                'end_time' => $r->shift->end_time ?? null,
                'is_overnight' => $r->shift->is_overnight ?? false,
                'status' => $r->status,
                'note' => $r->note,
            ];
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'start' => $data['start'],
                'end'   => $data['end'],
                'by_date' => $byDate,
                'total' => $rows->count(),
            ],
        ]);
    }

    /**
     * 我的排班 (供个人用, 移动端友好)
     * GET /api/schedules/my?month=2026-06
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);
        $month = $data['month'] ?? today()->format('Y-m');
        $start = $month . '-01';
        $end   = Carbon::parse($start)->endOfMonth()->format('Y-m-t');

        $rows = Schedule::with('shift')
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        // 获取系统默认班次 (用于未排班的日子)
        $defaultShift = Shift::where('is_default', true)->where('is_active', true)->first()
            ?? Shift::where('code', 'day')->first()
            ?? Shift::where('is_active', true)->orderBy('sort_order')->first();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->date->format('Y-m-d')] = [
                'shift_id' => $r->shift_id,
                'shift_name' => $r->shift->name ?? null,
                'shift_color' => $r->shift->color ?? null,
                'start_time' => $r->shift->start_time ?? null,
                'end_time' => $r->shift->end_time ?? null,
                'is_overnight' => $r->shift->is_overnight ?? false,
                'status' => $r->status,
            ];
        }

        // V1.2.16: 未排班的日子使用默认班次
        if ($defaultShift) {
            $current = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            while ($current->lte($endDate)) {
                $iso = $current->format('Y-m-d');
                if (!isset($byDate[$iso])) {
                    $byDate[$iso] = [
                        'shift_id' => $defaultShift->id,
                        'shift_name' => $defaultShift->name,
                        'shift_color' => $defaultShift->color ?? '#909399',
                        'start_time' => $defaultShift->start_time,
                        'end_time' => $defaultShift->end_time,
                        'is_overnight' => $defaultShift->is_overnight ?? false,
                        'status' => 'default',
                    ];
                }
                $current->addDay();
            }
        }

        return response()->json(['code' => 0, 'data' => ['month' => $month, 'by_date' => $byDate]]);
    }

    /**
     * 批量排班 (核心: 周排班表一次保存)
     * POST /api/schedules/batch
     * body: { assignments: [{ user_id, group_id?, date, shift_id, note? }] }
     * 已存在的 (user+date) 会覆盖 shift, 适合排班表点格子后批量提交
     */
    public function batchSave(BatchSaveScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->scheduleService->batchSave($data['assignments']);

        return response()->json([
            'code'    => 0,
            'message' => "排班保存成功 (新建 {$result['created']}, 更新 {$result['updated']})",
            'data'    => $result,
        ]);
    }

    /**
     * 班组批量排班 (整组一起设)
     */
    public function batchByGroup(BatchByGroupRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->scheduleService->batchByGroup(
                groupId: (int) $data['group_id'],
                shiftId: (int) $data['shift_id'],
                startDate: $data['start_date'],
                endDate: $data['end_date'],
                skipWeekends: (bool) ($data['skip_weekends'] ?? false),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'code'    => 0,
            'message' => "已为 {$result['count']} 个班次分配",
            'data'    => $result,
        ]);
    }

    /**
     * 删除单条排班
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();
        return response()->json(['code' => 0, 'message' => '已删除该排班']);
    }

    /**
     * 智能排班建议: 给定日期范围, 自动从历史考勤中推断常见班次
     * GET /api/schedules/smart-suggest?user_id=1&start_date=2026-06-20&end_date=2026-06-26
     * 从过去 30 天 attendance_records 的 clock_in 时间聚类, 找最常见的 shift
     */
    public function smartSuggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'    => 'nullable|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        try {
            $suggestions = $this->scheduleService->smartSuggest(
                userId: $data['user_id'] ?? null,
                startDate: $data['start_date'],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'data' => $suggestions]);
    }

    /**
     * 下一班次提醒
     */
    public function nextReminder(Request $request): JsonResponse
    {
        $next = $this->scheduleService->nextReminder();

        if (!$next) {
            return response()->json(['code' => 0, 'data' => null, 'message' => '近 7 天无排班']);
        }

        return response()->json(['code' => 0, 'data' => $next]);
    }

    /**
     * 排班统计
     */
    public function stats(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = $data['month'] ?? today()->format('Y-m');

        $stats = $this->scheduleService->monthlyStats($month);

        return response()->json(['code' => 0, 'data' => $stats]);
    }
}
