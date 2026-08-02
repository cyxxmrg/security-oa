<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeResignation extends Model
{
    use HasFactory;
    protected $table = 'employee_resignations';
    protected $fillable = [
        'user_id', 'resign_date', 'notice_date', 'last_work_day', 'resign_type',
        'reason', 'handover_to_user_id', 'handover_note',
        'assets_checklist', 'all_assets_returned',
        'final_salary_amount', 'leave_balance_payout', 'severance_pay', 'total_settlement',
        'paid_date', 'paid_method',
        'social_security_cutoff', 'resign_certificate_file_id',
        'status', 'remark', 'approved_by', 'approved_at', 'created_by',
    ];
    protected $casts = [
        'resign_date' => 'date', 'notice_date' => 'date', 'last_work_day' => 'date',
        'paid_date' => 'date', 'social_security_cutoff' => 'date',
        'approved_at' => 'datetime',
        'assets_checklist' => 'array',
        'all_assets_returned' => 'boolean',
        'final_salary_amount' => 'decimal:2',
        'leave_balance_payout' => 'decimal:2',
        'severance_pay' => 'decimal:2',
        'total_settlement' => 'decimal:2',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function handoverTo(): BelongsTo { return $this->belongsTo(User::class, 'handover_to_user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function certificateFile(): BelongsTo { return $this->belongsTo(DiskFile::class, 'resign_certificate_file_id'); }
}
