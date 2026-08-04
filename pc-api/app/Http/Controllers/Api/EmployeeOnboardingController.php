<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeOnboarding;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use App\Support\TemporaryPassword;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EmployeeOnboardingController extends Controller
{
    /**
     * GET /api/employee-onboardings
     * 入职档案列表
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'nullable|in:active,archived',
            'contract_expiring' => 'nullable|boolean',
            'probation_expiring' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $q = EmployeeOnboarding::with([
            'user:id,name,username,phone,email,status',
            'department:id,name',
            'position:id,name',
            'mentor:id,name',
            'onboarder:id,name',
        ]);

        if (!empty($data['user_id'])) $q->where('user_id', $data['user_id']);
        if (!empty($data['department_id'])) $q->where('department_id', $data['department_id']);
        if (!empty($data['status'])) $q->where('status', $data['status']);

        if (!empty($data['contract_expiring'])) {
            $q->whereNotNull('contract_end')
              ->where('contract_end', '<=', Carbon::now()->addDays(30)->format('Y-m-d'))
              ->where('contract_end', '>=', Carbon::now()->format('Y-m-d'));
        }

        if (!empty($data['probation_expiring'])) {
            $q->whereNotNull('probation_end_date')
              ->where('probation_end_date', '<=', Carbon::now()->addDays(7)->format('Y-m-d'))
              ->where('probation_end_date', '>=', Carbon::now()->format('Y-m-d'));
        }

        $q->orderBy('hire_date', 'desc')->orderBy('id', 'desc');

        $rows = $q->paginate($data['per_page'] ?? 20);
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /**
     * GET /api/employee-onboardings/{id}
     */
    public function show(EmployeeOnboarding $onboarding): JsonResponse
    {
        $onboarding->load([
            'user', 'department', 'position', 'mentor', 'onboarder',
            'idCardFile:id,name,original_name,size,path',
            'driverLicenseFile:id,name,original_name,size,path',
            'educationFile:id,name,original_name,size,path',
            'contractFile:id,name,original_name,size,path',
        ]);
        return response()->json(['code' => 0, 'data' => $onboarding]);
    }

    /**
     * POST /api/employee-onboardings
     * 员工入职: 1 个事务内创建 User + EmployeeProfile + Onboarding
     * body: {
     *   user: { username, name, phone, email, password? },
     *   onboarding: { hire_date, department_id, position_id, mentor_id?, probation_months?, contract_start?, contract_end?,
     *                 id_card_no?, id_card_file_id?, driver_license_no?, driver_license_expire?, driver_license_file_id?,
     *                 education_level?, education_school?, education_major?, education_file_id?, contract_file_id?, remark? }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        // V1.2.10: 前端可能平铺字段, 兼容 username/name/phone/email 顶层
        if (!$request->has('user.username') && $request->has('username')) {
            foreach (['username', 'name', 'phone', 'email', 'password'] as $f) {
                if ($request->has($f)) $request->merge(["user.$f" => $request->input($f)]);
            }
        }
        $data = $request->validate([
            'user.username' => 'required|string|max:50|unique:users,username',
            'user.name'     => 'required|string|max:50',
            'user.phone'    => 'nullable|string|max:20',
            'user.email'    => 'nullable|email|max:100',
            'user.password' => [
                'nullable',
                'string',
                'min:12',
                'max:64',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            ],

            'onboarding.hire_date'           => 'required|date_format:Y-m-d',
            'onboarding.department_id'       => 'nullable|exists:departments,id',
            'onboarding.position_id'         => 'nullable|exists:positions,id',
            'onboarding.mentor_id'           => 'nullable|exists:users,id',
            'onboarding.probation_months'    => 'nullable|integer|min:0|max:24',
            'onboarding.contract_start'      => 'nullable|date_format:Y-m-d',
            'onboarding.contract_end'        => 'nullable|date_format:Y-m-d|after_or_equal:onboarding.contract_start',

            'onboarding.id_card_no'          => 'nullable|string|max:32',
            'onboarding.id_card_file_id'     => 'nullable|exists:disk_files,id',
            'onboarding.driver_license_no'   => 'nullable|string|max:32',
            'onboarding.driver_license_expire' => 'nullable|date_format:Y-m-d',
            'onboarding.driver_license_file_id' => 'nullable|exists:disk_files,id',
            'onboarding.education_level'     => 'nullable|string|max:32',
            'onboarding.education_school'    => 'nullable|string|max:200',
            'onboarding.education_major'     => 'nullable|string|max:100',
            'onboarding.education_file_id'   => 'nullable|exists:disk_files,id',
            'onboarding.contract_file_id'    => 'nullable|exists:disk_files,id',
            'onboarding.remark'              => 'nullable|string|max:1000',
        ]);

        $generatedPassword = empty($data['user']['password']);
        $temporaryPassword = $data['user']['password'] ?? TemporaryPassword::generate();
        try {
            $result = DB::transaction(function () use ($data, $temporaryPassword) {
                $u = $data['user'];
                $o = $data['onboarding'];

                // 弱密码黑名单 — 防止 admin 设置的初始密码太弱
                if (!empty($u['password'])) {
                    $weak = ['12345678', '123456789', '1234567890', 'password', 'admin123', 'qwerty', '11111111', '00000000', '87654321', 'abcdefgh'];
                    if (in_array(strtolower($u['password']), $weak, true)) {
                        throw new \RuntimeException('密码过于简单,请使用字母+数字组合 (8-32 位)');
                    }
                }

                // 1) 建 User 账号
                // V1.2.10: phone NOT NULL UNIQUE — 不填时生成唯一占位 (限20字符)
                $userPhone = $u['phone'] ?? null;
                if (empty($userPhone)) {
                    $userPhone = mb_substr('TEL-' . $u['username'] . '-' . bin2hex(random_bytes(2)), 0, 20);
                }
                $user = User::create([
                    'username' => $u['username'],
                    'name'     => $u['name'],
                    'phone'    => $userPhone,
                    'email'    => $u['email'] ?? null,
                    'password' => Hash::make($temporaryPassword),
                    'must_change_password' => true,
                    'is_active' => true,
                    'status'   => 'active',
                ]);

                // 2) 建 EmployeeProfile (employee_no 必填, 自动生成 EMP-yyyymmdd-NNN)
                $seq = EmployeeProfile::whereDate('created_at', today())->count() + 1;
                $employeeNo = 'EMP-' . date('Ymd') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
                EmployeeProfile::create([
                    'user_id'     => $user->id,
                    'employee_no' => $employeeNo,
                    'hire_date'   => $o['hire_date'],
                ]);

                // 3) 建 Onboarding
                $probEnd = null;
                if (!empty($o['probation_months']) && $o['probation_months'] > 0) {
                    $probEnd = Carbon::parse($o['hire_date'])->addMonths($o['probation_months'])->format('Y-m-d');
                }
                $onboarding = EmployeeOnboarding::create([
                    'user_id'                => $user->id,
                    'hire_date'              => $o['hire_date'],
                    'department_id'          => $o['department_id'] ?? null,
                    'position_id'            => $o['position_id']   ?? null,
                    'mentor_id'              => $o['mentor_id']     ?? null,
                    'probation_months'       => $o['probation_months'] ?? 3,
                    'probation_end_date'     => $probEnd,
                    'contract_start'         => $o['contract_start'] ?? $o['hire_date'],
                    'contract_end'           => $o['contract_end']   ?? null,
                    'id_card_no'             => $o['id_card_no']             ?? null,
                    'id_card_file_id'        => $o['id_card_file_id']        ?? null,
                    'driver_license_no'      => $o['driver_license_no']      ?? null,
                    'driver_license_expire'  => $o['driver_license_expire']  ?? null,
                    'driver_license_file_id' => $o['driver_license_file_id'] ?? null,
                    'education_level'        => $o['education_level']        ?? null,
                    'education_school'       => $o['education_school']       ?? null,
                    'education_major'        => $o['education_major']        ?? null,
                    'education_file_id'      => $o['education_file_id']      ?? null,
                    'contract_file_id'       => $o['contract_file_id']       ?? null,
                    'onboarded_by'           => Auth::id(),
                ]);

                return ['user' => $user, 'onboarding' => $onboarding];
            });

            return response()->json([
                'code' => 0,
                'message' => '入职办理成功',
                'data' => $result,
                'temporary_password' => $generatedPassword ? $temporaryPassword : null,
            ], 201);
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['code' => 1001, 'message' => '入职失败: ' . $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/employee-onboardings/{id}
     * 更新入职档案 (含续签合同)
     */
    public function update(Request $request, EmployeeOnboarding $onboarding): JsonResponse
    {
        $data = $request->validate([
            'department_id'        => 'nullable|exists:departments,id',
            'position_id'          => 'nullable|exists:positions,id',
            'mentor_id'            => 'nullable|exists:users,id',
            'probation_months'     => 'nullable|integer|min:0|max:24',
            'probation_end_date'   => 'nullable|date_format:Y-m-d',
            'contract_start'       => 'nullable|date_format:Y-m-d',
            'contract_end'         => 'nullable|date_format:Y-m-d',
            'id_card_no'           => 'nullable|string|max:32',
            'id_card_file_id'      => 'nullable|exists:disk_files,id',
            'driver_license_no'    => 'nullable|string|max:32',
            'driver_license_expire' => 'nullable|date_format:Y-m-d',
            'driver_license_file_id' => 'nullable|exists:disk_files,id',
            'education_level'      => 'nullable|string|max:32',
            'education_school'     => 'nullable|string|max:200',
            'education_major'      => 'nullable|string|max:100',
            'education_file_id'    => 'nullable|exists:disk_files,id',
            'contract_file_id'     => 'nullable|exists:disk_files,id',
            'remark'               => 'nullable|string|max:1000',
            'status'               => 'nullable|in:active,archived',
        ]);

        $onboarding->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $onboarding]);
    }

    /**
     * DELETE /api/employee-onboardings/{id}
     * 归档 (不真删)
     */
    public function destroy(EmployeeOnboarding $onboarding): JsonResponse
    {
        $onboarding->update(['status' => 'archived']);
        return response()->json(['code' => 0, 'message' => '已归档']);
    }
}
