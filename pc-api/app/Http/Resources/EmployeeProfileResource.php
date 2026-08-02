<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * P1-11 修复: 员工档案字段裁剪
 *
 * 默认输出 (业务普通用户可见):
 *   - 基础字段: employee_no, hire_date, leave_date, contract_*, contract_start/end
 *   - 不暴露: 薪资 base_salary / salary_allowance, 银行卡 bank_*, 紧急联系人 emergency_*
 *
 * 完整输出 (自己/system 角色可见):
 *   - 上述全部 + 薪资 + 银行卡 + 紧急联系人
 *
 * 调用方约定:
 *   - 业务管理员/同事: (new EmployeeProfileResource($profile))->toArray($request)  → 脱敏
 *   - 自己/system 角色: EmployeeProfileResource::full($profile, $request)  → 完整
 *
 * 权限守门放在 Controller (EmployeeController::index/show/update), Resource 只负责序列化。
 */
class EmployeeProfileResource extends JsonResource
{
    /**
     * 是否请求"完整字段" (自己 / system 角色)
     * 由 Controller 通过 withFullAccess(true) 注入
     */
    protected bool $fullAccess = false;

    public function withFullAccess(bool $full): self
    {
        $this->fullAccess = $full;
        return $this;
    }

    public static function full($resource, ?Request $request = null): array
    {
        $instance = (new self($resource))->withFullAccess(true);
        return $instance->toArray($request ?? request());
    }

    public function toArray(Request $request): array
    {
        $base = [
            'employee_no'    => $this->employee_no,
            'hire_date'      => $this->hire_date?->toDateString(),
            'leave_date'     => $this->leave_date?->toDateString(),
            'contract_type'  => $this->contract_type,
            'contract_start' => $this->contract_start?->toDateString(),
            'contract_end'   => $this->contract_end?->toDateString(),
        ];

        if (!$this->fullAccess) {
            return $base;
        }

        return array_merge($base, [
            'base_salary'      => $this->base_salary,
            'salary_allowance' => $this->salary_allowance,
            'bank_name'        => $this->bank_name,
            'bank_account'     => $this->bank_account,
            'emergency_contact' => $this->emergency_contact,
            'emergency_phone'  => $this->emergency_phone,
            'notes'            => $this->notes,
        ]);
    }
}