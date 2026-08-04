<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'employee_no', 'hire_date', 'leave_date',
        'contract_type', 'contract_start', 'contract_end',
        'base_salary', 'salary_allowance', 'emergency_contact', 'emergency_phone',
        'bank_name', 'bank_account', 'notes',
    ];

    /**
     * P1-11 修复: 默认隐藏敏感字段
     * 默认 toArray() 序列化时不再自动暴露薪资/银行卡/紧急联系人等
     * 业务控制器若需完整字段, 应通过 EmployeeProfileResource::full($profile) 显式获取
     */
    protected $hidden = [
        'base_salary',
        'salary_allowance',
        'bank_name',
        'bank_account',
        'emergency_contact',
        'emergency_phone',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date', 'leave_date' => 'date',
        'contract_start' => 'date', 'contract_end' => 'date',
        'base_salary' => 'decimal:2', 'salary_allowance' => 'decimal:2',
        'bank_account' => 'encrypted',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function certificates(): HasMany { return $this->hasMany(Certificate::class); }
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(SkillTag::class, 'employee_skills')
            ->withPivot('proficiency')
            ->withTimestamps();
    }
}
