<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ApproveLeaveRequest;
use App\Http\Requests\Attendance\ClockInRequest;
use App\Http\Requests\Attendance\StoreLeaveRequest;
use App\Http\Requests\Attendance\StoreOvertimeRequest;
use App\Models\ApprovalRecord;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Project;
use App\Models\User;
use App\Scopes\DataScope;
use App\Services\ApprovalFlowService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\RequestBody;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Group('attendance', '考勤/排班/请假 模块, 包括打卡记录、请假申请、加班申请')]
class AttendanceController extends Controller
{
    /**
     * 考勤概览 (今日统计)
     */
    #[Endpoint(title: '考勤概览', description: '返回今日全局考勤统计: 在岗/迟到/缺勤/外勤/总人数')]
    public function overview(Request $request): JsonResponse
    {
        $today = today()->format('Y-m-d');
        $totalUsers = User::where('status', 'active')->count();
        $present = AttendanceRecord::where('date', $today)->where('status', 'normal')->count();
        $late = AttendanceRecord::where('date', $today)->where('status', 'late')->count();
        $absent = AttendanceRecord::where('date', $today)->where('status', 'absent')->count();
        $fieldWork = AttendanceRecord::where('date', $today)->where('status', 'field_work')->count();

        return response()->json(['code' => 0, 'data' => compact('totalUsers', 'present', 'late', 'absent', 'fieldWork')]);
    }

    /**
     * GET /api/attendance/calendar?month=2026-06
     * 返回当月每天的考勤摘要: { present, late, absent, fieldWork, leave }
     * 用于工作台/考勤总览的"考勤日历"显示每日数据
     */
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);
        $month = $request->input('month') ?: now()->format('Y-m');
        $start = $month . '-01';
        $end   = \Carbon\Carbon::parse($start)->endOfMonth()->format('Y-m-t');

        // 1) 每天考勤记录各状态人数
        $records = AttendanceRecord::whereBetween('date', [$start, $end])
            ->selectRaw("date,
                SUM(CASE WHEN status = 'normal'     THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN status = 'late'       THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN status = 'absent'     THEN 1 ELSE 0 END) AS absent,
                SUM(CASE WHEN status = 'field_work' THEN 1 ELSE 0 END) AS field_work
            ")
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // 2) 每天请假人数 (approved 的请假按起止日均摊到每天, 当天 start<=day<=end)
        $leaveDays = [];
        $leaves = LeaveRequest::where('status', 'approved')
            ->where(function ($q) use ($start, $end) {
                $q->where('start_date', '<=', $end)
                  ->where('end_date',   '>=', $start);
            })
            ->get(['start_date', 'end_date', 'user_id']);

        foreach ($leaves as $lv) {
            $cursor = max((string)$lv->start_date, $start);
            $last   = min((string)$lv->end_date,   $end);
            while ($cursor <= $last) {
                $leaveDays[$cursor] = ($leaveDays[$cursor] ?? 0) + 1;
                $cursor = \Carbon\Carbon::parse($cursor)->addDay()->format('Y-m-d');
            }
        }

        // 3) 拼成 {date: stats} map
        $calendar = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $r = $records->get($cursor);
            $calendar[$cursor] = [
                'present'   => (int)($r->present   ?? 0),
                'late'      => (int)($r->late      ?? 0),
                'absent'    => (int)($r->absent    ?? 0),
                'fieldWork' => (int)($r->field_work ?? 0),
                'leave'     => (int)($leaveDays[$cursor] ?? 0),
            ];
            $cursor = \Carbon\Carbon::parse($cursor)->addDay()->format('Y-m-d');
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'month'    => $month,
                'days'     => $calendar,
            ],
        ]);
    }

    public function clockIn(\App\Http\Requests\Attendance\ClockInRequest $request): JsonResponse
    {
        $data = $request->validated();
        $projectError = $this->projectAccessError($data['project_id'] ?? null, $request->user());
        if ($projectError) {
            return $projectError;
        }

        $today = today()->format('Y-m-d');
        $now   = now();

        // 联动排班: 找今日排班, 用排班的 start_time + late_threshold 判定
        $schedule = \App\Models\Schedule::with('shift')
            ->where('user_id', Auth::id())
            ->where('date', $today)
            ->first();

        $status = 'normal';
        $shiftInfo = null;
        if ($schedule && $schedule->shift) {
            if (in_array($schedule->status, ['rest', 'sick', 'leave'], true)) {
                return response()->json(['code' => 1001, 'message' => '今日排班状态为休息/请假, 不能直接签到'], 409);
            }
            $shift = $schedule->shift;
            $shiftInfo = [
                'shift_id' => $shift->id,
                'shift_name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'late_threshold' => $shift->late_threshold_minutes,
            ];
            // 班次有 start_time: 晚于 start+threshold → late
            $threshold = $shift->start_time . ' +' . $shift->late_threshold_minutes . ' minutes';
            $cutoff = \Carbon\Carbon::parse($today . ' ' . $threshold);
            if ($now->gt($cutoff)) {
                $status = 'late';
            }
        } else {
            // 无排班: 用默认 9:00 + 5min
            $cutoff = \Carbon\Carbon::parse($today . ' 09:05:00');
            if ($now->gt($cutoff)) {
                $status = 'late';
            }
        }

        return DB::transaction(function () use ($data, $today, $now, $status, $shiftInfo) {
            $record = AttendanceRecord::where('user_id', Auth::id())
                ->where('date', $today)
                ->lockForUpdate()
                ->first();
            if (!$record) {
                $record = AttendanceRecord::create([
                    'user_id' => Auth::id(),
                    'date' => $today,
                    'status' => 'normal',
                ]);
            }
            if ($record->status === 'leave') {
                return response()->json(['code' => 1001, 'message' => '今日已标记为请假, 不能直接签到'], 409);
            }
            if ($record->clock_in) {
                return response()->json(['code' => 1001, 'message' => '今日已完成签到, 不能重复签到'], 409);
            }

            $record->update([
                'clock_in' => $now->format('H:i:s'),
                'clock_in_location' => $data['location'] ?? null,
                'clock_in_lat' => $data['latitude'] ?? $data['lat'] ?? null,
                'clock_in_lng' => $data['longitude'] ?? $data['lng'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'remark' => $data['remark'] ?? null,
                'status' => $status,
            ]);

            return response()->json([
                'code' => 0,
                'message' => $status === 'late' ? '签到成功（迟到）' : '签到成功',
                'data' => $record,
                'shift' => $shiftInfo,
            ]);
        });
    }

    public function clockOut(\App\Http\Requests\Attendance\ClockInRequest $request): JsonResponse
    {
        $data = $request->validated();

        $today = today()->format('Y-m-d');
        $now   = now();
        return DB::transaction(function () use ($data, $today, $now) {
            $recordDate = $today;
            $record = AttendanceRecord::where('user_id', Auth::id())
                ->where('date', $today)
                ->whereNotNull('clock_in')
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->first();
            $schedule = null;

            if (!$record) {
                $yesterday = today()->subDay()->format('Y-m-d');
                $overnightSchedule = \App\Models\Schedule::with('shift')
                    ->where('user_id', Auth::id())
                    ->where('date', $yesterday)
                    ->first();
                if ($overnightSchedule?->shift?->is_overnight) {
                    $record = AttendanceRecord::where('user_id', Auth::id())
                        ->where('date', $yesterday)
                        ->whereNotNull('clock_in')
                        ->whereNull('clock_out')
                        ->lockForUpdate()
                        ->first();
                    if ($record) {
                        $recordDate = $yesterday;
                        $schedule = $overnightSchedule;
                    }
                }
            }

            if (!$record) {
                $completed = AttendanceRecord::where('user_id', Auth::id())->where('date', $today)->whereNotNull('clock_out')->exists();
                return response()->json([
                    'code' => 1001,
                    'message' => $completed ? '今日已完成签退, 不能重复签退' : '未找到可签退的签到记录',
                ], $completed ? 409 : 422);
            }

            $schedule ??= \App\Models\Schedule::with('shift')
                ->where('user_id', Auth::id())
                ->where('date', $recordDate)
                ->first();
            $record->clock_out = $now->format('H:i:s');
            $record->clock_out_location = $data['location'] ?? null;
            $record->clock_out_lat = $data['latitude'] ?? $data['lat'] ?? null;
            $record->clock_out_lng = $data['longitude'] ?? $data['lng'] ?? null;

            $newStatus = $record->status;
            if ($schedule?->shift) {
                $shift = $schedule->shift;
                $cutoff = \Carbon\Carbon::parse($recordDate . ' ' . $shift->end_time);
                if ($shift->is_overnight) $cutoff->addDay();
                $cutoff->subMinutes($shift->early_leave_threshold_minutes);
                if ($now->lt($cutoff) && $record->status === 'normal') {
                    $newStatus = 'early_leave';
                }
            }
            $record->status = $newStatus;

            $start = \Carbon\Carbon::parse($recordDate . ' ' . $record->clock_in);
            $record->work_hours = round($start->diffInMinutes($now) / 60, 1);
            $record->save();

            return response()->json([
                'code' => 0,
                'message' => $newStatus === 'early_leave' ? '签退成功（早退）' : '签退成功',
                'data' => $record,
            ]);
        });
    }

    /**
     * POST /api/attendance/today
     * 获取今日打卡状态 (供打卡记录页用, 即使没打卡也返回空 record)
     */
    public function today(Request $request): JsonResponse
    {
        $today = today()->format('Y-m-d');
        $record = AttendanceRecord::where('user_id', Auth::id())->where('date', $today)->first();
        return response()->json(['code' => 0, 'data' => $record]);
    }

    /**
     * POST /api/attendance/supplement
     * body: { date, type: 'in'|'out', time: 'HH:mm:ss', location?, reason }
     * 补卡申请：直接写入 attendance_records, status='late' 标记
     * 后续可扩展走审批流
     */
    public function supplement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'     => 'required|date_format:Y-m-d',
            'type'     => 'required|in:in,out,field_in,field_out,clock_in,clock_out',
            'time'     => 'nullable|date_format:H:i:s|required_without:clock_time',
            'clock_time' => 'nullable|date_format:H:i:s|required_without:time',  // V1.2.10 别名
            'location' => 'nullable|string|max:200',
            'reason'   => 'required|string|max:500',
        ]);
        // V1.2.10: clock_in/clock_out 别名 + clock_time 别名
        if (!empty($data['clock_time']) && empty($data['time'])) {
            $data['time'] = $data['clock_time'];
        }
        $typeAlias = ['clock_in' => 'in', 'clock_out' => 'out'];
        if (isset($typeAlias[$data['type']])) {
            $data['type'] = $typeAlias[$data['type']];
        }

        $today = today()->format('Y-m-d');
        if ($data['date'] > $today) {
            return response()->json(['code' => 1001, 'message' => '补卡日期不能晚于今天'], 422);
        }
        if ($data['date'] === $today && $data['time'] > now()->format('H:i:s')) {
            return response()->json(['code' => 1001, 'message' => '补卡时间不能晚于当前时间'], 422);
        }

        $isField = str_starts_with($data['type'], 'field_');
        $type    = $isField ? substr($data['type'], 6) : $data['type']; // field_in -> in

        return DB::transaction(function () use ($data, $isField, $type) {
            $record = AttendanceRecord::where('user_id', Auth::id())
                ->where('date', $data['date'])
                ->lockForUpdate()
                ->first();
            if (!$record) {
                $record = AttendanceRecord::create([
                    'user_id' => Auth::id(),
                    'date' => $data['date'],
                    'status' => $isField ? 'field_work' : 'late',
                ]);
            }
            if ($record->status === 'leave') {
                return response()->json(['code' => 1001, 'message' => '已标记为请假的记录不能补卡'], 409);
            }

            if ($type === 'in') {
                if ($record->clock_in) {
                    return response()->json(['code' => 1001, 'message' => '该日上班卡已存在, 无需补卡'], 422);
                }
                $record->clock_in = $data['time'];
                $record->clock_in_location = $data['location'] ?? null;
                $record->remark = ($record->remark ? $record->remark . '; ' : '') . ($isField ? '外勤补卡' : '补卡') . ': ' . $data['reason'];
            } else {
                if ($record->clock_out) {
                    return response()->json(['code' => 1001, 'message' => '该日下班卡已存在, 无需补卡'], 422);
                }
                $record->clock_out = $data['time'];
                $record->clock_out_location = $data['location'] ?? null;
                $record->remark = ($record->remark ? $record->remark . '; ' : '') . ($isField ? '外勤补卡' : '补卡') . ': ' . $data['reason'];
            }

            if ($isField) {
                $record->status = 'field_work';
            } elseif ($type === 'in' && $data['time'] > '09:00:00') {
                $record->status = 'late';
            } else {
                $record->status = $record->status ?: 'normal';
            }

            if ($record->clock_in && $record->clock_out) {
                $start = \Carbon\Carbon::parse($data['date'] . ' ' . $record->clock_in);
                $end   = \Carbon\Carbon::parse($data['date'] . ' ' . $record->clock_out);
                if ($end->lt($start)) $end->addDay();
                $record->work_hours = round($start->diffInMinutes($end) / 60, 1);
            }
            $record->save();

            return response()->json(['code' => 0, 'message' => '补卡成功', 'data' => $record]);
        });
    }

    /**
     * POST /api/attendance/field-clock
     * 即时外勤打卡（今日，type: 'in'|'out'）
     * body: { type, time?, location?, project_id?, remark? }
     */
    public function fieldClock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'       => 'required|in:in,out',
            'time'       => 'nullable|date_format:H:i:s',
            'location'   => 'nullable|string|max:200',
            'project_id' => 'nullable|exists:projects,id',
            'remark'     => 'nullable|string|max:500',
        ]);
        $projectError = $this->projectAccessError($data['project_id'] ?? null, $request->user());
        if ($projectError) {
            return $projectError;
        }

        $date = today()->format('Y-m-d');
        $time = now()->format('H:i:s');

        return DB::transaction(function () use ($data, $date, $time) {
            $record = AttendanceRecord::where('user_id', Auth::id())
                ->where('date', $date)
                ->lockForUpdate()
                ->first();
            if (!$record) {
                $record = AttendanceRecord::create([
                    'user_id' => Auth::id(),
                    'date' => $date,
                    'status' => 'field_work',
                ]);
            }
            if ($record->status === 'leave') {
                return response()->json(['code' => 1001, 'message' => '今日已标记为请假, 不能外勤打卡'], 409);
            }

            if ($data['type'] === 'in') {
                if ($record->clock_in) {
                    return response()->json(['code' => 1001, 'message' => '今日已完成签到, 不能重复签到'], 409);
                }
                $record->clock_in = $time;
                $record->clock_in_location = $data['location'] ?? null;
            } else {
                if (!$record->clock_in) {
                    return response()->json(['code' => 1001, 'message' => '请先完成外勤签到再签退'], 422);
                }
                if ($record->clock_out) {
                    return response()->json(['code' => 1001, 'message' => '今日已完成签退, 不能重复签退'], 409);
                }
                if ($time < $record->clock_in) {
                    return response()->json(['code' => 1001, 'message' => '外勤签退时间不能早于签到时间'], 422);
                }
                $record->clock_out = $time;
                $record->clock_out_location = $data['location'] ?? null;
            }
            $record->status = 'field_work';
            if (!empty($data['project_id'])) $record->project_id = $data['project_id'];
            $record->remark = ($record->remark ? $record->remark . '; ' : '') . ($data['remark'] ?? '外勤打卡');

            if ($record->clock_in && $record->clock_out) {
                $start = \Carbon\Carbon::parse($date . ' ' . $record->clock_in);
                $end   = \Carbon\Carbon::parse($date . ' ' . $record->clock_out);
                $record->work_hours = $start->diffInMinutes($end) / 60;
            }
            $record->save();

            return response()->json([
                'code'    => 0,
                'message' => '外勤' . ($data['type'] === 'in' ? '签到' : '签退') . '成功',
                'data'    => $record,
            ]);
        });
    }

    public function records(Request $request): JsonResponse
    {
        $query = AttendanceRecord::with(['user', 'project']);
        if ($this->canManageAttendance($request, 'attendance.report')) {
            if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        } else {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('start_date')) $query->where('date', '>=', $request->start_date);
        if ($request->filled('end_date')) $query->where('date', '<=', $request->end_date);
        if ($request->filled('status')) $query->where('status', $request->status);

        $records = $query->orderBy('date', 'desc')->paginate($this->perPage($request));
        return response()->json(['code' => 0, 'data' => $records]);
    }

    public function leaveRequests(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['user', 'approver']);
        if (!$this->canManageAttendance($request, 'attendance.leave')) {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type')) $query->where('type', $request->type);

        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($this->perPage($request))]);
    }

    public function storeLeaveRequest(\App\Http\Requests\Attendance\StoreLeaveRequest $request): JsonResponse
    {
        $data = $request->validated();
        // V1.2.10: days 缺失时自动按 start/end 计算
        if (empty($data['days']) && !empty($data['start_date']) && !empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $end   = \Carbon\Carbon::parse($data['end_date']);
            $data['days'] = $start->diffInDays($end) + 1;
        }
        // 兼容前端类型 → DB enum
        $typeMap = ['funeral' => 'compassionate'];
        if (isset($data['type']) && isset($typeMap[$data['type']])) {
            $data['type'] = $typeMap[$data['type']];
        }
        $calendarDays = (int) \Carbon\Carbon::parse($data['start_date'])
            ->diffInDays(\Carbon\Carbon::parse($data['end_date'])) + 1;
        $requestedDays = (float) $data['days'];
        $validDays = $calendarDays === 1
            ? in_array($requestedDays, [0.5, 1.0], true)
            : abs($requestedDays - $calendarDays) < 0.01;
        if (!$validDays) {
            return response()->json(['code' => 1001, 'message' => '请假天数必须与起止日期范围一致（单日可填 0.5 天）'], 422);
        }
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';

        // V1.2.7 P0 修复: 把 LeaveRequest + ApprovalRecord 包在一个事务里,确保要么都成功要么都失败
        $leave = DB::transaction(function () use ($data) {
            $applicant = \App\Models\User::whereKey(Auth::id())->lockForUpdate()->first();
            if (!$applicant) {
                throw new \RuntimeException('当前申请人不存在');
            }
            $overlappingLeave = LeaveRequest::where('user_id', $applicant->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('start_date', '<=', $data['end_date'])
                ->where('end_date', '>=', $data['start_date'])
                ->exists();
            if ($overlappingLeave) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_date' => ['该时间段已有待审批或已批准的请假申请'],
                ]);
            }
            $leave = LeaveRequest::create($data);

            // V1.2.4v: 同步创建审批中心记录 (operation/leave), 让审批中心能列出请假申请
            $typeLabel = [
                'personal' => '事假', 'sick' => '病假', 'annual' => '年假',
                'marriage' => '婚假', 'maternity' => '产假', 'paternity' => '陪产假',
                'compassionate' => '丧假', 'other' => '其他',
            ][$data['type']] ?? $data['type'];

            $code = \App\Services\ApprovalNumberService::next('OPS');

            // 按模板初始化审批流程
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('leave');
            if (!$template) {
                throw new \RuntimeException('未找到请假对应的启用审批流程');
            }
            $flowData = $flowService->initFlow($template, $applicant, '提交请假申请: ' . $data['reason']);

            \App\Models\ApprovalRecord::create([
                'code'                => $code,
                'type'                => 'operation',
                'sub_type'            => 'leave',
                'title'               => $applicant?->name . '的请假申请 (' . $typeLabel . ' ' . $data['days'] . '天)',
                'priority'            => 'normal',
                'status'              => \App\Models\ApprovalRecord::STATUS_PENDING,
                'start_date'          => $data['start_date'],
                'end_date'            => $data['end_date'],
                'applicant_id'        => Auth::id(),
                'current_approver_id' => $flowData['current_approver_id'],
                'payload'             => [
                    'leave_id' => $leave->id,
                    'leave_type' => $data['type'],
                    'leave_type_label' => $typeLabel,
                    'days' => $data['days'],
                    'reason' => $data['reason'],
                    '_approval_flow' => $flowData['definition'],
                ],
                'flow'                => $flowData['flow'],
            ]);

            return $leave;
        });

        return response()->json(['code' => 0, 'message' => '申请成功', 'data' => $leave]);
    }

    public function approveLeave(ApproveLeaveRequest $request, LeaveRequest $leave): JsonResponse
    {
        if ((int) $leave->user_id === (int) Auth::id()) {
            return response()->json(['code' => 1010, 'message' => '不能审批自己的请假申请'], 403);
        }
        if (!$this->canManageAttendance($request, 'attendance.leave')) {
            return response()->json(['code' => 1011, 'message' => '当前账号没有考勤审批权限'], 403);
        }
        return DB::transaction(function () use ($request, $leave) {
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leave->id);
            if ($leave->status !== 'pending') {
                return response()->json(['code' => 1001, 'message' => '该请假申请已处理'], 409);
            }

            $isApproved = $request->action === 'approved';
            $comment = $request->comment ?? ($isApproved ? '同意' : '驳回');
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'leave')
                ->whereJsonContains('payload->leave_id', $leave->id)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (!$approval) {
                return response()->json(['code' => 1001, 'message' => '未找到有效的审批中心记录'], 409);
            }

            $user = User::findOrFail(Auth::id());
            $flowService = app(ApprovalFlowService::class);
            try {
                $result = $isApproved
                    ? $flowService->advanceFlow($approval, $user, $comment)
                    : $flowService->rejectFlow($approval, $user, $comment);
            } catch (\DomainException $e) {
                $status = str_starts_with($e->getMessage(), '当前用户不是')
                    || str_starts_with($e->getMessage(), '申请人不能') ? 403 : 422;
                return response()->json(['code' => 1001, 'message' => $e->getMessage()], $status);
            }

            $approval->forceFill([
                'flow' => $result['flow'],
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'comment' => $comment,
            ])->save();

            if (in_array($result['status'], [ApprovalRecord::STATUS_APPROVED, ApprovalRecord::STATUS_REJECTED], true)) {
                $leave->update([
                    'status' => $result['status'] === ApprovalRecord::STATUS_APPROVED ? 'approved' : 'rejected',
                    'approver_id' => Auth::id(),
                    'approved_at' => now(),
                    'reject_reason' => $result['status'] === ApprovalRecord::STATUS_REJECTED ? $comment : null,
                ]);
            }

            $message = match ($result['status']) {
                ApprovalRecord::STATUS_APPROVED => '已批准',
                ApprovalRecord::STATUS_REJECTED => '已驳回',
                default => '已通过，已转交下一节点',
            };
            return response()->json(['code' => 0, 'message' => $message]);
        });
    }

    public function overtimeRequests(Request $request): JsonResponse
    {
        $query = OvertimeRequest::with(['user', 'approver']);
        if (!$this->canManageAttendance($request, 'attendance.overtime')) {
            $query->where('user_id', $request->user()->id);
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($this->perPage($request))]);
    }

    public function storeOvertimeRequest(\App\Http\Requests\Attendance\StoreOvertimeRequest $request): JsonResponse
    {
        $data = $request->validated();
        // V1.2.10: date 别名同步到 overtime_date
        if (empty($data['overtime_date']) && !empty($data['date'])) {
            $data['overtime_date'] = $data['date'];
        }
        if (empty($data['overtime_date'])) {
            return response()->json(['code' => 422, 'message' => '加班日期不能为空', 'errors' => ['overtime_date' => ['加班日期必填']]], 422);
        }
        $durationHours = \Carbon\Carbon::parse($data['start_time'])
            ->diffInMinutes(\Carbon\Carbon::parse($data['end_time'])) / 60;
        if ((float) $data['hours'] > $durationHours) {
            return response()->json(['code' => 1001, 'message' => '加班时长不能超过起止时间范围'], 422);
        }
        unset($data['date']);
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';
        // 兼容前端 time_off/overtime_pay → 后端 leave/pay
        if (isset($data['compensation_type'])) {
            $map = ['time_off' => 'leave', 'overtime_pay' => 'pay'];
            $data['compensation_type'] = $map[$data['compensation_type']] ?? $data['compensation_type'];
        } else {
            $data['compensation_type'] = 'leave';
        }
        $overtime = DB::transaction(function () use ($data) {
            $applicant = \App\Models\User::whereKey(Auth::id())->lockForUpdate()->first();
            if (!$applicant) {
                throw new \RuntimeException('当前申请人不存在');
            }
            $overlappingOvertime = OvertimeRequest::where('user_id', $applicant->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('overtime_date', $data['overtime_date'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->exists();
            if ($overlappingOvertime) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_time' => ['该时间段已有待审批或已批准的加班申请'],
                ]);
            }
            $compLabel = ['pay' => '加班费', 'leave' => '调休', 'default_pay' => '默认加班费'][$data['compensation_type']] ?? $data['compensation_type'];
            $code = \App\Services\ApprovalNumberService::next('OPS');

            // 按模板初始化审批流程
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('overtime');
            if (!$template) {
                throw new \RuntimeException('未找到加班对应的启用审批流程');
            }
            $flowData = $flowService->initFlow($template, $applicant, '提交加班申请: ' . $data['reason']);
            $overtime = OvertimeRequest::create($data);

            \App\Models\ApprovalRecord::create([
                'code'                => $code,
                'type'                => 'operation',
                'sub_type'            => 'overtime',
                'title'               => $applicant?->name . '的加班申请 (' . $data['overtime_date'] . ' ' . $data['hours'] . '小时 ' . $compLabel . ')',
                'priority'            => 'normal',
                'status'              => \App\Models\ApprovalRecord::STATUS_PENDING,
                'start_date'          => $data['overtime_date'],
                'applicant_id'        => Auth::id(),
                'current_approver_id' => $flowData['current_approver_id'],
                'payload'             => [
                    'overtime_id'       => $overtime->id,
                    'overtime_date'     => $data['overtime_date'],
                    'start_time'        => $data['start_time'],
                    'end_time'          => $data['end_time'],
                    'hours'             => $data['hours'],
                    'reason'            => $data['reason'],
                    'compensation_type' => $data['compensation_type'],
                    'compensation_label'=> $compLabel,
                    '_approval_flow'   => $flowData['definition'],
                ],
                'flow'                => $flowData['flow'],
            ]);
            return $overtime;
        });

        return response()->json(['code' => 0, 'message' => '申请成功', 'data' => $overtime]);
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtime): JsonResponse
    {
        $request->validate(['action' => 'required|in:approved,rejected', 'comment' => 'nullable|string']);
        if ((int) $overtime->user_id === (int) Auth::id()) {
            return response()->json(['code' => 1010, 'message' => '不能审批自己的加班申请'], 403);
        }
        if (!$this->canManageAttendance($request, 'attendance.overtime')) {
            return response()->json(['code' => 1011, 'message' => '当前账号没有考勤审批权限'], 403);
        }
        return DB::transaction(function () use ($request, $overtime) {
            $overtime = OvertimeRequest::lockForUpdate()->findOrFail($overtime->id);
            if ($overtime->status !== 'pending') {
                return response()->json(['code' => 1001, 'message' => '该加班申请已处理'], 409);
            }

            $isApproved = $request->action === 'approved';
            $comment = $request->comment ?? ($isApproved ? '同意' : '驳回');
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'overtime')
                ->whereJsonContains('payload->overtime_id', $overtime->id)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (!$approval) {
                return response()->json(['code' => 1001, 'message' => '未找到有效的审批中心记录'], 409);
            }

            $user = User::findOrFail(Auth::id());
            $flowService = app(ApprovalFlowService::class);
            try {
                $result = $isApproved
                    ? $flowService->advanceFlow($approval, $user, $comment)
                    : $flowService->rejectFlow($approval, $user, $comment);
            } catch (\DomainException $e) {
                $status = str_starts_with($e->getMessage(), '当前用户不是')
                    || str_starts_with($e->getMessage(), '申请人不能') ? 403 : 422;
                return response()->json(['code' => 1001, 'message' => $e->getMessage()], $status);
            }

            $approval->forceFill([
                'flow' => $result['flow'],
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'comment' => $comment,
            ])->save();

            if (in_array($result['status'], [ApprovalRecord::STATUS_APPROVED, ApprovalRecord::STATUS_REJECTED], true)) {
                $overtime->update([
                    'status' => $result['status'] === ApprovalRecord::STATUS_APPROVED ? 'approved' : 'rejected',
                    'approver_id' => Auth::id(),
                    'approved_at' => now(),
                ]);
            }

            $message = match ($result['status']) {
                ApprovalRecord::STATUS_APPROVED => '已批准',
                ApprovalRecord::STATUS_REJECTED => '已驳回',
                default => '已通过，已转交下一节点',
            };
            return response()->json(['code' => 0, 'message' => $message]);
        });
    }

    public function destroyLeaveRequest(LeaveRequest $leave): JsonResponse
    {
        try {
            DB::transaction(function () use ($leave) {
                $leave = LeaveRequest::lockForUpdate()->findOrFail($leave->id);
                if ($leave->status !== 'pending') {
                    throw new \DomainException('已审批的请假申请不允许撤销');
                }
                if ((int) $leave->user_id !== (int) Auth::id()) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, '只能撤销自己的请假申请');
                }
                $approval = ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'leave')
                    ->whereJsonContains('payload->leave_id', $leave->id)
                    ->where('status', ApprovalRecord::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();
                if ($approval) {
                    $flow = is_array($approval->flow) ? $approval->flow : [];
                    $flow[] = [
                        'operator_id' => Auth::id(),
                        'operator' => Auth::user()?->name ?? '—',
                        'action' => 'cancel',
                        'time' => now()->toDateTimeString(),
                        'comment' => '申请人撤回请假申请',
                    ];
                    $approval->forceFill([
                        'status' => ApprovalRecord::STATUS_CANCELLED,
                        'current_approver_id' => null,
                        'comment' => '申请人撤回请假申请',
                        'flow' => $flow,
                    ])->save();
                }
                $leave->delete();
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'message' => '已撤销']);
    }

    public function destroyOvertimeRequest(OvertimeRequest $overtime): JsonResponse
    {
        try {
            DB::transaction(function () use ($overtime) {
                $overtime = OvertimeRequest::lockForUpdate()->findOrFail($overtime->id);
                if ($overtime->status !== 'pending') {
                    throw new \DomainException('已审批的加班申请不允许撤销');
                }
                if ((int) $overtime->user_id !== (int) Auth::id()) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, '只能撤回自己的加班申请');
                }
                $approval = ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'overtime')
                    ->whereJsonContains('payload->overtime_id', $overtime->id)
                    ->where('status', ApprovalRecord::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();
                if ($approval) {
                    $flow = is_array($approval->flow) ? $approval->flow : [];
                    $flow[] = [
                        'operator_id' => Auth::id(),
                        'operator' => Auth::user()?->name ?? '—',
                        'action' => 'cancel',
                        'time' => now()->toDateTimeString(),
                        'comment' => '申请人撤回加班申请',
                    ];
                    $approval->forceFill([
                        'status' => ApprovalRecord::STATUS_CANCELLED,
                        'current_approver_id' => null,
                        'comment' => '申请人撤回加班申请',
                        'flow' => $flow,
                    ])->save();
                }
                $overtime->delete();
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'message' => '已撤销']);
    }

    public function report(Request $request): JsonResponse
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->month;
        // V1.2.4t: 考勤报表排除 system 账号 (超管不打考勤, 不应出现在报表里)
        $users = User::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('user_type')->orWhere('user_type', '!=', 'system');
            })
            ->with(['attendanceRecords' => function ($q) use ($month) {
                $q->where('date', 'like', "$month%");
            }])->get();

        $data = $users->map(fn($u) => [
            'user' => $u, 'total_days' => $u->attendanceRecords->count(),
            'late_count' => $u->attendanceRecords->where('status', 'late')->count(),
            'absent_count' => $u->attendanceRecords->where('status', 'absent')->count(),
            'overtime_hours' => $u->attendanceRecords->sum('overtime_hours'),
        ]);

        return response()->json(['code' => 0, 'data' => $data]);
    }

    /**
     * 考勤统计概览 — 兼容前端 /attendance/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $month = $request->validate(['month' => 'nullable|date_format:Y-m'])['month'] ?? now()->format('Y-m');
        $start = $month . '-01';
        $end = \Carbon\Carbon::parse($start)->endOfMonth()->format('Y-m-t');

        $records = AttendanceRecord::whereBetween('date', [$start, $end])->get();
        $total = User::where('status', 'active')->count();
        $recordCount = $records->count();
        $normalCount = $records->where('status', 'normal')->count();

        return response()->json([
            'code' => 0,
            'data' => [
                'month' => $month,
                'total_employees' => $total,
                'attendance_count' => $normalCount,
                'late_count' => $records->where('status', 'late')->count(),
                'absent_count' => $records->where('status', 'absent')->count(),
                'leave_count' => $records->where('status', 'leave')->count(),
                'overtime_count' => OvertimeRequest::where('status', 'approved')->whereBetween('overtime_date', [$start, $end])->count(),
                'attendance_rate' => $recordCount > 0 ? round($normalCount / $recordCount * 100, 1) : 0,
                'pending_leave' => LeaveRequest::where('status', 'pending')->count(),
                'pending_overtime' => OvertimeRequest::where('status', 'pending')->count(),
            ],
        ]);
    }

    private function canManageAttendance(Request $request, string $permission): bool
    {
        $user = $request->user();
        if (!$user) return false;
        if (($user->user_type ?? 'business') === 'system') return true;
        try {
            return $user->hasActivePermissionTo($permission);
        } catch (\Throwable $e) {
            Log::warning('attendance permission check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 15), 1), 100);
    }

    private function projectAccessError(?int $projectId, ?User $user): ?JsonResponse
    {
        if (!$projectId || !$user) {
            return null;
        }

        $project = Project::withoutGlobalScope(DataScope::class)->find($projectId);
        if (!$project) {
            return response()->json(['code' => 1001, 'message' => '无权将打卡关联到该项目'], 403);
        }

        $isProjectMember = (int) $project->manager_id === (int) $user->id
            || $project->members()
                ->where('users.id', $user->id)
                ->where('project_members.status', 'active')
                ->exists();
        if (!$user->hasRole('admin') && !$isProjectMember) {
            return response()->json(['code' => 1001, 'message' => '无权将打卡关联到该项目'], 403);
        }

        return null;
    }
}
