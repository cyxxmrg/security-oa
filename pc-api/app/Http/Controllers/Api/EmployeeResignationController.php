<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeResignation;
use App\Models\EmployeeOnboarding;
use App\Models\ShiftGroupMember;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\ApprovalRecord;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeResignationController extends Controller
{
    /**
     * GET /api/employee-resignations
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'status'  => 'nullable|in:draft,pending,approved,completed,cancelled',
            'resign_type' => 'nullable|in:voluntary,contract_end,retirement,mutual,dismissed,probation_dismissed,other',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $q = EmployeeResignation::with([
            'user:id,name,username,phone',
            'handoverTo:id,name',
            'approver:id,name',
            'creator:id,name',
            'certificateFile:id,name,original_name,size',
        ]);

        if (!empty($data['user_id'])) $q->where('user_id', $data['user_id']);
        if (!empty($data['status'])) $q->where('status', $data['status']);
        if (!empty($data['resign_type'])) $q->where('resign_type', $data['resign_type']);

        $q->orderBy('created_at', 'desc');
        $rows = $q->paginate($data['per_page'] ?? 20);
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /**
     * GET /api/employee-resignations/{id}
     */
    public function show(EmployeeResignation $resignation): JsonResponse
    {
        $resignation->load(['user', 'handoverTo', 'approver', 'creator', 'certificateFile']);
        return response()->json(['code' => 0, 'data' => $resignation]);
    }

    /**
     * POST /api/employee-resignations
     * 创建离职申请 (draft 或 pending)
     */
    public function store(Request $request): JsonResponse
    {
        // V1.2.10: 前端用 employee_id 别名映射到 user_id
        if ($request->has('employee_id') && !$request->has('user_id')) {
            $request->merge(['user_id' => $request->input('employee_id')]);
        }
        // V1.2.10: last_work_day 默认 = resign_date
        if ($request->has('resign_date') && !$request->has('last_work_day')) {
            $request->merge(['last_work_day' => $request->input('resign_date')]);
        }
        $data = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'employee_id'    => 'nullable',  // 别名
            'resign_date'    => 'required|date_format:Y-m-d',
            'notice_date'    => 'nullable|date_format:Y-m-d',
            'last_work_day'  => 'required|date_format:Y-m-d',
            'last_workday'   => 'nullable|date_format:Y-m-d',  // 别名
            'resign_type'    => 'required|in:voluntary,contract_end,retirement,mutual,dismissed,probation_dismissed,other',
            'reason'         => 'required|string|max:2000',
            'handover_to_user_id' => 'nullable|exists:users,id|different:user_id',
            'handover_note'  => 'nullable|string|max:2000',
            'assets_checklist'   => 'nullable|array',
            'final_salary_amount'  => 'nullable|numeric|min:0',
            'leave_balance_payout' => 'nullable|numeric|min:0',
            'severance_pay'        => 'nullable|numeric|min:0',
            'social_security_cutoff' => 'nullable|date_format:Y-m-d',
            'remark'          => 'nullable|string|max:2000',
            'submit'          => 'nullable|boolean',
        ]);
        unset($data['employee_id'], $data['last_workday']);

        $user = User::findOrFail($data['user_id']);
        // V1.2.10: users.status 是 BackedEnum, 用 ->value 判在职
        $statusValue = $user->status instanceof \BackedEnum ? $user->status->value : $user->status;
        $isActive = ($user->is_active ?? true) && ($statusValue === 'active');
        if (!$isActive) {
            return response()->json(['code' => 1001, 'message' => '该用户已是离职状态'], 422);
        }
        // 同一人不能有未完成的离职
        $existing = EmployeeResignation::where('user_id', $data['user_id'])
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->exists();
        if ($existing) {
            return response()->json(['code' => 1002, 'message' => '该员工已有未结的离职申请'], 422);
        }

        try {
            $resignation = DB::transaction(function () use ($data) {
            $lockedUser = User::lockForUpdate()->findOrFail($data['user_id']);
            $existing = EmployeeResignation::where('user_id', $lockedUser->id)
                ->whereIn('status', ['draft', 'pending', 'approved'])
                ->lockForUpdate()
                ->exists();
            if ($existing) {
                throw new \DomainException('该员工已有未结的离职申请');
            }

            $resignation = EmployeeResignation::create([
                'user_id'      => $lockedUser->id,
                'resign_date'  => $data['resign_date'],
                'notice_date'  => $data['notice_date'] ?? Carbon::now()->format('Y-m-d'),
                'last_work_day' => $data['last_work_day'],
                'resign_type'  => $data['resign_type'],
                'reason'       => $data['reason'],
                'handover_to_user_id' => $data['handover_to_user_id'] ?? null,
                'handover_note' => $data['handover_note'] ?? null,
                'assets_checklist' => $data['assets_checklist'] ?? null,
                'final_salary_amount' => $data['final_salary_amount'] ?? null,
                'leave_balance_payout' => $data['leave_balance_payout'] ?? null,
                'severance_pay' => $data['severance_pay'] ?? null,
                'social_security_cutoff' => $data['social_security_cutoff'] ?? null,
                'remark'       => $data['remark'] ?? null,
                'status'       => !empty($data['submit']) ? 'pending' : 'draft',
                'created_by'   => Auth::id(),
            ]);

            if (!empty($data['submit'])) {
                $this->syncApprovalRecord($resignation, 'submit');
            }

                return $resignation;
            });
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['code' => 1002, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'message' => '离职申请已创建', 'data' => $resignation], 201);
    }

    /**
     * PUT /api/employee-resignations/{id}
     * 更新 (仅 draft / pending 可改)
     */
    public function update(Request $request, EmployeeResignation $resignation): JsonResponse
    {
        if (!in_array($resignation->status, ['draft', 'pending'], true)) {
            return response()->json(['code' => 1001, 'message' => '已审批的离职单不能修改'], 422);
        }
        $data = $request->validate([
            'resign_date'   => 'sometimes|date_format:Y-m-d',
            'notice_date'   => 'nullable|date_format:Y-m-d',
            'last_work_day' => 'sometimes|date_format:Y-m-d',
            'resign_type'   => 'sometimes|in:voluntary,contract_end,retirement,mutual,dismissed,probation_dismissed,other',
            'reason'        => 'sometimes|string|max:2000',
            'handover_to_user_id' => 'nullable|exists:users,id|different:user_id',
            'handover_note' => 'nullable|string|max:2000',
            'assets_checklist' => 'nullable|array',
            'final_salary_amount'  => 'nullable|numeric|min:0',
            'leave_balance_payout' => 'nullable|numeric|min:0',
            'severance_pay'        => 'nullable|numeric|min:0',
            'social_security_cutoff' => 'nullable|date_format:Y-m-d',
            'remark' => 'nullable|string|max:2000',
        ]);

        $resignation->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $resignation]);
    }

    /**
     * POST /api/employee-resignations/{id}/submit
     * 草稿 -> 提交审批
     */
    public function submit(EmployeeResignation $resignation): JsonResponse
    {
        try {
            $resignation = DB::transaction(function () use ($resignation) {
            $resignation = EmployeeResignation::lockForUpdate()->findOrFail($resignation->id);
            if ($resignation->status !== 'draft') {
                throw new \DomainException('仅草稿状态可提交');
            }
            $resignation->update(['status' => 'pending']);
            $this->syncApprovalRecord($resignation, 'submit');
                return $resignation;
            });
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'message' => '已提交审批', 'data' => $resignation]);
    }

    /**
     * POST /api/employee-resignations/{id}/approve
     * 审批通过 (pending -> approved)
     */
    public function approve(EmployeeResignation $resignation): JsonResponse
    {
        // P1-8: 离职申请人或目标员工不能审批该申请 (禁止自审)
        if ((int) $resignation->created_by === (int) Auth::id() || (int) $resignation->user_id === (int) Auth::id()) {
            return response()->json(['code' => 1003, 'message' => '离职申请人或目标员工不能审批该申请'], 403);
        }

        return DB::transaction(function () use ($resignation) {
            $resignation = EmployeeResignation::lockForUpdate()->findOrFail($resignation->id);
            if ($resignation->status !== 'pending') {
                return response()->json(['code' => 1001, 'message' => '仅待审批状态可审批'], 422);
            }

            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'resignation')
                ->whereJsonContains('payload->resignation_id', $resignation->id)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (!$approval) {
                return response()->json(['code' => 1001, 'message' => '未找到有效的审批中心记录'], 409);
            }

            $operator = User::findOrFail(Auth::id());
            $result = app(ApprovalFlowService::class)->advanceFlow($approval, $operator, '审批通过');
            $approval->forceFill([
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'flow' => $result['flow'],
                'comment' => '审批通过',
            ])->save();

            if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
                $resignation->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);
            }

            return response()->json([
                'code' => 0,
                'message' => $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已审批' : '已通过，已转交下一节点',
                'data' => $resignation->fresh(),
            ]);
        });
    }

    /**
     * POST /api/employee-resignations/{id}/cancel
     * 撤回 (draft/pending/approved -> cancelled)
     */
    public function cancel(EmployeeResignation $resignation): JsonResponse
    {
        try {
            $resignation = DB::transaction(function () use ($resignation) {
            $resignation = EmployeeResignation::lockForUpdate()->findOrFail($resignation->id);
            if (in_array($resignation->status, ['completed', 'cancelled'], true)) {
                throw new \DomainException('已完成或已取消的单不能再撤回');
            }
            $resignation->update(['status' => 'cancelled']);
            $this->syncApprovalRecord($resignation, 'cancel');
                return $resignation;
            });
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['code' => 1001, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['code' => 0, 'message' => '已撤回']);
    }

    /**
     * V1.2.5: 同步离职审批到审批中心
     */
    private function syncApprovalRecord(EmployeeResignation $resignation, string $action): void
    {
        try {
            $typeLabel = [
                'voluntary'    => '主动辞职',
                'involuntary'  => '被动离职',
                'contract_end' => '合同到期',
                'retirement'   => '退休',
                'mutual'       => '协商解除',
                'dismissed'    => '辞退',
                'probation_dismissed' => '试用期辞退',
                'other'        => '其他',
            ][$resignation->resign_type] ?? $resignation->resign_type;

            if ($action === 'submit') {
                $code = \App\Services\ApprovalNumberService::next('OPS');
                $applicant = User::find(Auth::id());
                $targetUser = User::find($resignation->user_id);
                if (!$applicant) {
                    throw new \RuntimeException('当前申请人不存在');
                }

                $exists = ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'resignation')
                    ->where('payload->resignation_id', $resignation->id)
                    ->exists();
                if ($exists) return;

                $flowService = app(ApprovalFlowService::class);
                $template = $flowService->resolveTemplate('resignation');
                if (!$template) {
                    throw new \RuntimeException('未找到离职对应的启用审批流程');
                }
                $flowData = $flowService->initFlow($template, $applicant, '提交离职申请');

                ApprovalRecord::create([
                    'code'         => $code,
                    'type'         => 'operation',
                    'sub_type'     => 'resignation',
                    'title'        => ($targetUser?->name ?? '—') . '的离职申请 (' . $typeLabel . ' 最后工作日 ' . $resignation->last_work_day . ')',
                    'priority'     => 'high',
                    'status'       => ApprovalRecord::STATUS_PENDING,
                    'start_date'   => $resignation->resign_date,
                    'end_date'     => $resignation->last_work_day,
                    'applicant_id' => Auth::id(),
                    'current_approver_id' => $flowData['current_approver_id'],
                    'payload'      => [
                        'resignation_id'  => $resignation->id,
                        'user_id'         => $resignation->user_id,
                        'resign_type'     => $resignation->resign_type,
                        'resign_type_label' => $typeLabel,
                        'resign_date'     => $resignation->resign_date,
                        'last_work_day'   => $resignation->last_work_day,
                        'reason'          => $resignation->reason,
                        'final_salary_amount' => $resignation->final_salary_amount,
                        'severance_pay'   => $resignation->severance_pay,
                    ],
                    'flow'         => $flowData['flow'],
                ]);
            } else {
                $approval = ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'resignation')
                    ->where('payload->resignation_id', $resignation->id)
                    ->first();
                if (!$approval) return;

                $flow = is_array($approval->flow) ? $approval->flow : [];
                $operatorName = User::find(Auth::id())?->name ?? '—';
                if ($action === 'approve') {
                    $flow[] = ['operator_id' => Auth::id(), 'operator' => $operatorName, 'action' => 'approve', 'time' => now()->toDateTimeString(), 'comment' => '审批通过'];
                    $approval->status = ApprovalRecord::STATUS_APPROVED;
                } elseif ($action === 'cancel') {
                    $flow[] = ['operator_id' => Auth::id(), 'operator' => $operatorName, 'action' => 'cancel', 'time' => now()->toDateTimeString(), 'comment' => '撤销离职申请'];
                    $approval->status = ApprovalRecord::STATUS_CANCELLED;
                }
                $approval->flow = $flow;
                $approval->save();
            }
        } catch (\Throwable $e) {
            Log::error('EmployeeResignationController::syncApprovalRecord failed', [
                'msg' => $e->getMessage(),
                'action' => $action,
            ]);
            throw $e;
        }
    }

    /**
     * POST /api/employee-resignations/{id}/complete
     * 办结 (approved -> completed): 事务内
     *   1) 标记 User.is_active = false, status = 'inactive'
     *   2) 解除所有班组
     *   3) 更新 EmployeeProfile.leave_date
     *   4) 归档 Onboarding
     *   5) 标记资产归还
     *   6) 计算总工资 = 各项之和
     */
    public function complete(Request $request, EmployeeResignation $resignation): JsonResponse
    {
        if ($resignation->status !== 'approved') {
            return response()->json(['code' => 1001, 'message' => '仅已审批的单可办结'], 422);
        }
        $data = $request->validate([
            'all_assets_returned' => 'required|boolean',
            'paid_date'           => 'nullable|date_format:Y-m-d',
            'paid_method'         => 'nullable|string|max:32',
            'resign_certificate_file_id' => 'nullable|exists:disk_files,id',
        ]);

        try {
            $result = DB::transaction(function () use ($resignation, $data) {
                $user = $resignation->user;

                // 1) 冻结账号
                $user->update(['is_active' => false, 'status' => 'inactive']);

                // 2) 解除所有班组
                ShiftGroupMember::where('user_id', $user->id)->delete();

                // 3) 更新 EmployeeProfile.leave_date
                EmployeeProfile::where('user_id', $user->id)
                    ->update(['leave_date' => $resignation->last_work_day]);

                // 4) 归档 Onboarding
                EmployeeOnboarding::where('user_id', $user->id)
                    ->update(['status' => 'archived']);

                // 5) 计算总工资
                $total = ($resignation->final_salary_amount ?? 0)
                       + ($resignation->leave_balance_payout ?? 0)
                       + ($resignation->severance_pay ?? 0);

                $resignation->update([
                    'status' => 'completed',
                    'all_assets_returned' => $data['all_assets_returned'],
                    'paid_date' => $data['paid_date'] ?? Carbon::now()->format('Y-m-d'),
                    'paid_method' => $data['paid_method'] ?? '银行转账',
                    'resign_certificate_file_id' => $data['resign_certificate_file_id'] ?? null,
                    'total_settlement' => $total,
                ]);

                return $resignation;
            });

            return response()->json([
                'code' => 0,
                'message' => '离职办结完成, 账号已冻结',
                'data' => $result->fresh(['user', 'handoverTo', 'certificateFile']),
            ]);
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1002, 'message' => '办结失败: ' . $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/employee-resignations/settlement-preview
     * 离职工资预览 (按用户 + 离职日 自动算)
     * query: user_id, resign_date
     */
    public function settlementPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'resign_date' => 'required|date_format:Y-m-d',
        ]);

        $user = User::findOrFail($data['user_id']);
        $profile = EmployeeProfile::where('user_id', $user->id)->first();

        // 1) 当月已工作天数 (含首日)
        $resign = Carbon::parse($data['resign_date']);
        $monthStart = $resign->copy()->startOfMonth();
        $workedDays = $monthStart->diffInDays($resign) + 1;

        // 2) 应出勤天数 (排除周末)
        $workDays = 0;
        $cursor = $monthStart->copy();
        while ($cursor->lte($resign)) {
            if (!in_array($cursor->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                $workDays++;
            }
            $cursor->addDay();
        }

        // 3) 当月实际打卡天数
        $actualDays = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart->format('Y-m-d'), $resign->format('Y-m-d')])
            ->whereNotNull('clock_in')
            ->count();

        $baseSalary = $profile?->base_salary ?? 0;
        $dailyRate  = $baseSalary > 0 ? round($baseSalary / 21.75, 2) : 0; // 21.75 月计薪日
        $finalSalary = $dailyRate * $actualDays;

        // 4) 未休年假 (从入职日算)
        $unpaidLeave = 0; // 简化: 暂不计算, HR 后台录入

        // 5) 经济补偿金 (N, 1年1个月工资, 不满半年0.5, 满半年1)
        $severance = 0;
        if ($profile && $profile->hire_date) {
            $years = Carbon::parse($profile->hire_date)->diffInYears($resign);
            $months = Carbon::parse($profile->hire_date)->diffInMonths($resign) % 12;
            $n = $years + ($months >= 6 ? 1 : ($months > 0 ? 0.5 : 0));
            $severance = $n * $baseSalary;
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'resign_date' => $data['resign_date'],
                'hire_date' => $profile?->hire_date?->format('Y-m-d'),
                'work_years' => $profile?->hire_date ? round(Carbon::parse($profile->hire_date)->diffInDays($resign) / 365, 1) : 0,
                'base_salary' => $baseSalary,
                'daily_rate' => $dailyRate,
                'month_work_days' => $workDays,
                'month_actual_days' => $actualDays,
                'final_salary_amount' => round($finalSalary, 2),
                'leave_balance_payout' => $unpaidLeave,
                'severance_pay' => round($severance, 2),
                'total_settlement' => round($finalSalary + $unpaidLeave + $severance, 2),
                'social_security_cutoff' => $resign->format('Y-m'),
            ],
        ]);
    }
}
