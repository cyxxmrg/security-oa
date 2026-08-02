<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRecord extends Model
{
    use HasFactory;

    /** 状态常量 */
    const STATUS_PENDING     = 'pending';
    const STATUS_APPROVED    = 'approved';
    const STATUS_REJECTED    = 'rejected';
    const STATUS_TRANSFERRED = 'transferred';
    const STATUS_CANCELLED   = 'cancelled';

    /**
     * 实际表名: approval_records_v2 (聚合财务/运营/项目 3 大类审批)
     * 原 approval_records (多态关联) 表继续存在,但本 Model 走 v2 表
     * @see database/migrations/2024_01_05_000004_create_approval_records_v2_table.php
     */
    protected $table = 'approval_records_v2';

    protected $fillable = [
        'code', 'type', 'sub_type', 'title', 'priority', 'status',
        'amount', 'bank_account', 'start_date', 'end_date', 'to_stage',
        'applicant_id', 'current_approver_id', 'payload', 'flow', 'cc', 'comment',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'start_date' => 'date',
        'end_date'   => 'date',
        'payload'    => 'array',
        'flow'       => 'array',
        'cc'         => 'array',
    ];

    public function approvable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'applicant_id'); }
    public function currentApprover(): BelongsTo { return $this->belongsTo(User::class, 'current_approver_id'); }
}
