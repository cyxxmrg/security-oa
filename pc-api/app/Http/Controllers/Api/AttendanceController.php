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
use App\Models\User;
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
        $request->validated();

        $today = today()->format('Y-m-d');
        $now   = now();
        $record = AttendanceRecord::firstOrCreate(['user_id' => Auth::id(), 'date' => $today]);

        // 联动排班: 找今日排班, 用排班的 start_time + late_threshold 判定
        $schedule = \App\Models\Schedule::with('shift')
            ->where('user_id', Auth::id())
            ->where('date', $today)
            ->first();

        $status = 'normal';
        $shiftInfo = null;
        if ($schedule && $schedule->shift) {
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
            // 如果 schedule.status = rest/leave/sick, 保持原状态不覆盖
            if (in_array($schedule->status, ['rest', 'sick', 'leave'], true)) {
                $status = $schedule->status === 'rest' ? 'normal' : $schedule->status;
            }
        } else {
            // 无排班: 用默认 9:00 + 5min
            $cutoff = \Carbon\Carbon::parse($today . ' 09:05:00');
            if ($now->gt($cutoff)) {
                $status = 'late';
            }
        }

        $record->update([
            'clock_in' => $now->format('H:i:s'),
            'clock_in_location' => $request->location,
            'clock_in_lat' => $request->latitude,
            'clock_in_lng' => $request->longitude,
            'project_id' => $request->project_id,
            'remark' => $request->remark,
            'status' => $status,
        ]);

        return response()->json([
            'code' => 0,
            'message' => $status === 'late' ? '签到成功（迟到）' : '签到成功',
            'data' => $record,
            'shift' => $shiftInfo,
        ]);
    }

    public function clockOut(\App\Http\Requests\Attendance\ClockInRequest $request): JsonResponse
    {
        $request->validated();

        $today = today()->format('Y-m-d');
        $now   = now();
        $record = AttendanceRecord::where('user_id', Auth::id())->where('date', $today)->firstOrFail();
        $record->update([
            'clock_out' => $now->format('H:i:s'),
            'clock_out_location' => $request->location,
            'clock_out_lat' => $request->latitude,
            'clock_out_lng' => $request->longitude,
        ]);

        // 联动排班: 早退判定
        $schedule = \App\Models\Schedule::with('shift')
            ->where('user_id', Auth::id())
            ->where('date', $today)
            ->first();

        $newStatus = $record->status;
        if ($schedule && $schedule->shift) {
            $shift = $schedule->shift;
            // 早退: 早于 end_time - early_leave_threshold
            $cutoff = \Carbon\Carbon::parse($today . ' ' . $shift->end_time)
                ->subMinutes($shift->early_leave_threshold_minutes);
            if ($now->lt($cutoff) && $record->status === 'normal') {
                $newStatus = 'early_leave';
            }
        }
        $record->status = $newStatus;

        // 计算工时
        if ($record->clock_in && $record->clock_out) {
            $start = \Carbon\Carbon::parse($today . ' ' . $record->clock_in);
            $end   = \Carbon\Carbon::parse($today . ' ' . $record->clock_out);
            // 跨夜班处理
            if ($end->lt($start)) $end->addDay();
            $record->work_hours = $start->diffInMinutes($end) / 60;
        }
        $record->save();

        return response()->json([
            'code' => 0,
            'message' => $newStatus === 'early_leave' ? '签退成功（早退）' : '签退成功',
            'data' => $record,
        ]);
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
            'time'     => 'required|date_format:H:i:s',
            'clock_time' => 'nullable|date_format:H:i:s',  // V1.2.10 别名
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

        $isField = str_starts_with($data['type'], 'field_');
        $type    = $isField ? substr($data['type'], 6) : $data['type']; // field_in -> in

        $record = AttendanceRecord::firstOrCreate(
            ['user_id' => Auth::id(), 'date' => $data['date']],
            ['status' => $isField ? 'field_work' : 'late']
        );

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

        // 状态判定: 外勤补卡一律 mark field_work, 普通补卡 9:00 后 mark late
        if ($isField) {
            $record->status = 'field_work';
        } elseif ($type === 'in' && $data['time'] > '09:00:00') {
            $record->status = 'late';
        } else {
            $record->status = $record->status ?: 'normal';
        }

        // 重算工时
        if ($record->clock_in && $record->clock_out) {
            $start = \Carbon\Carbon::parse($data['date'] . ' ' . $record->clock_in);
            $end   = \Carbon\Carbon::parse($data['date'] . ' ' . $record->clock_out);
            $record->work_hours = $start->diffInMinutes($end) / 60;
        }
        $record->save();

        return response()->json(['code' => 0, 'message' => '补卡成功', 'data' => $record]);
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

        $date = today()->format('Y-m-d');
        $time = $data['time'] ?? now()->format('H:i:s');

        $record = AttendanceRecord::firstOrCreate(
            ['user_id' => Auth::id(), 'date' => $date],
            ['status' => 'field_work']
        );

        if ($data['type'] === 'in') {
            $record->clock_in = $time;
            $record->clock_in_location = $data['location'] ?? null;
        } else {
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
    }

    public function records(Request $request): JsonResponse
    {
        $query = AttendanceRecord::with(['user', 'project']);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('start_date')) $query->where('date', '>=', $request->start_date);
        if ($request->filled('end_date')) $query->where('date', '<=', $request->end_date);
        if ($request->filled('status')) $query->where('status', $request->status);

        $records = $query->orderBy('date', 'desc')->paginate($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $records]);
    }

    public function leaveRequests(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['user', 'approver']);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type')) $query->where('type', $request->type);

        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15)]);
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
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';

        // V1.2.7 P0 修复: 把 LeaveRequest + ApprovalRecord 包在一个事务里,确保要么都成功要么都失败
        $leave = DB::transaction(function () use ($data) {
            $leave = LeaveRequest::create($data);

            // V1.2.4v: 同步创建审批中心记录 (operation/leave), 让审批中心能列出请假申请
            $typeLabel = [
                'personal' => '事假', 'sick' => '病假', 'annual' => '年假',
                'marriage' => '婚假', 'maternity' => '产假', 'paternity' => '陪产假',
                'compassionate' => '丧假', 'other' => '其他',
            ][$data['type']] ?? $data['type'];

            $year = now()->format('Y');
            $seq = \App\Models\ApprovalRecord::where('code', 'like', "OPS-{$year}-%")->count() + 1;
            $code = sprintf('OPS-%s-%04d', $year, $seq);

            $applicant = \App\Models\User::find(Auth::id());

            // 按模板初始化审批流程
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('leave');
            $flowData = $template
                ? $flowService->initFlow($template, $applicant, '提交请假申请: ' . $data['reason'])
                : ['current_approver_id' => 1, 'flow' => [[
                    'operator' => $applicant?->name ?? '—',
                    'action'   => 'submit',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => '提交请假申请: ' . $data['reason'],
                ]]];

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
                ],
                'flow'                => $flowData['flow'],
            ]);

            return $leave;
        });

        return response()->json(['code' => 0, 'message' => '申请成功', 'data' => $leave]);
    }

    public function approveLeave(ApproveLeaveRequest $request, LeaveRequest $leave): JsonResponse
    {
        // P1-8 修复: 禁止自审 + 校验审批权限
        if ($leave->user_id === Auth::id()) {
            return response()->json(['code' => 1010, 'message' => '不能审批自己的请假申请'], 403);
        }
        if (!\Illuminate\Support\Facades\Auth::user()->can('attendance.approve')) {
            return response()->json(['code' => 1011, 'message' => '当前账号没有考勤审批权限'], 403);
        }

        $isApproved = $request->action === 'approved';
        $comment = $request->comment ?? ($isApproved ? '同意' : '驳回');

        $leave->update([
            'status' => $request->action, 'approver_id' => Auth::id(),
            'approved_at' => now(), 'reject_reason' => !$isApproved ? $comment : null,
        ]);

        // V1.2.4v: 同步更新审批中心记录（按模板推进/驳回）
        try {
            $approval = \App\Models\ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'leave')
                ->where('payload->leave_id', $leave->id)
                ->first();
            if ($approval) {
                $user = \App\Models\User::find(Auth::id());
                $flowService = app(ApprovalFlowService::class);

                if ($isApproved) {
                    $result = $flowService->advanceFlow($approval, $user, $comment);
                } else {
                    $result = $flowService->rejectFlow($approval, $user, $comment);
                }

                $approval->flow = $result['flow'];
                $approval->status = $result['status'];
                $approval->current_approver_id = $result['current_approver_id'];
                $approval->comment = $comment;
                $approval->save();
            }
        } catch (\Throwable $e) {
            \Log::error('approveLeave sync to approval center failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => $request->action === 'approved' ? '已批准' : '已驳回']);
    }

    public function overtimeRequests(Request $request): JsonResponse
    {
        $query = OvertimeRequest::with(['user', 'approver']);
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15)]);
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
        $overtime = OvertimeRequest::create($data);

        // V1.2.5: 同步创建审批中心记录 (operation/overtime)
        try {
            $compLabel = ['pay' => '加班费', 'leave' => '调休', 'default_pay' => '默认加班费'][$data['compensation_type']] ?? $data['compensation_type'];
            $year = now()->format('Y');
            $seq = \App\Models\ApprovalRecord::where('code', 'like', "OPS-{$year}-%")->count() + 1;
            $code = sprintf('OPS-%s-%04d', $year, $seq);
            $applicant = \App\Models\User::find(Auth::id());

            // 按模板初始化审批流程
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('overtime');
            $flowData = $template
                ? $flowService->initFlow($template, $applicant, '提交加班申请: ' . $data['reason'])
                : ['current_approver_id' => 1, 'flow' => [[
                    'operator' => $applicant?->name ?? '—',
                    'action'   => 'submit',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => '提交加班申请: ' . $data['reason'],
                ]]];

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
                ],
                'flow'                => $flowData['flow'],
            ]);
        } catch (\Throwable $e) {
            \Log::error('storeOvertimeRequest sync to approval center failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => '申请成功', 'data' => $overtime]);
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtime): JsonResponse
    {
        $request->validate(['action' => 'required|in:approved,rejected', 'comment' => 'nullable|string']);
        // P1-8 修复: 禁止自审 + 校验审批权限
        if ($overtime->user_id === Auth::id()) {
            return response()->json(['code' => 1010, 'message' => '不能审批自己的加班申请'], 403);
        }
        if (!\Illuminate\Support\Facades\Auth::user()->can('attendance.approve')) {
            return response()->json(['code' => 1011, 'message' => '当前账号没有考勤审批权限'], 403);
        }
        $overtime->update([
            'status' => $request->action,
            'approver_id' => Auth::id(),
            'approved_at' => now(),
        ]);

        // V1.2.5: 同步更新审批中心记录（按模板推进/驳回）
        try {
            $approval = \App\Models\ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'overtime')
                ->where('payload->overtime_id', $overtime->id)
                ->first();
            if ($approval) {
                $user = \App\Models\User::find(Auth::id());
                $isApproved = $request->action === 'approved';
                $comment = $request->comment ?? ($isApproved ? '同意' : '驳回');
                $flowService = app(ApprovalFlowService::class);

                if ($isApproved) {
                    $result = $flowService->advanceFlow($approval, $user, $comment);
                } else {
                    $result = $flowService->rejectFlow($approval, $user, $comment);
                }

                $approval->flow = $result['flow'];
                $approval->status = $result['status'];
                $approval->current_approver_id = $result['current_approver_id'];
                $approval->comment = $comment;
                $approval->save();
            }
        } catch (\Throwable $e) {
            \Log::error('approveOvertime sync approval failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'message' => $request->action === 'approved' ? '已批准' : '已驳回']);
    }

    public function destroyLeaveRequest(LeaveRequest $leave): JsonResponse
    {
        if ($leave->status !== 'pending') {
            return response()->json(['code' => 1001, 'message' => '已审批的请假申请不允许撤销'], 422);
        }
        if ($leave->user_id !== Auth::id()) {
            return response()->json(['code' => 1003, 'message' => '只能撤销自己的请假申请'], 403);
        }
        $leave->delete();
        return response()->json(['code' => 0, 'message' => '已撤销']);
    }

    public function destroyOvertimeRequest(OvertimeRequest $overtime): JsonResponse
    {
        if ($overtime->status !== 'pending') {
            return response()->json(['code' => 1001, 'message' => '已审批的加班申请不允许撤销'], 422);
        }
        if ($overtime->user_id !== Auth::id()) {
            return response()->json(['code' => 1003, 'message' => '只能撤销自己的加班申请'], 403);
        }
        $overtime->delete();
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
        $month = $request->get('month', now()->format('Y-m'));
        $start = $month . '-01';
        $end = \Carbon\Carbon::parse($start)->endOfMonth()->format('Y-m-t');

        $records = AttendanceRecord::whereBetween('date', [$start, $end])->get();
        $total = User::where('status', 'active')->count();

        return response()->json([
            'code' => 0,
            'data' => [
                'month' => $month,
                'total_employees' => $total,
                'attendance_count' => $records->where('status', 'normal')->count(),
                'late_count' => $records->where('status', 'late')->count(),
                'absent_count' => $records->where('status', 'absent')->count(),
                'leave_count' => $records->where('status', 'leave')->count(),
                'overtime_count' => $records->where('status', 'overtime')->count(),
                'attendance_rate' => $total > 0 ? round($records->where('status', 'normal')->count() / $total * 100, 1) : 0,
                'pending_leave' => LeaveRequest::where('status', 'pending')->count(),
                'pending_overtime' => OvertimeRequest::where('status', 'pending')->count(),
            ],
        ]);
    }
}
