<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.4.3 日志日报需求单 (强制日志)
 *
 * 表: rectification_daily_required (单数 required，PG 表名约定)
 * 主键: id
 *
 * 流程：
 *  - 创建开工单时，按 planned_start_date ~ planned_end_date 每天生成一条 status=pending
 *  - 施工员提交日志 (construction_logs) 时，upsert 对应行 status=submitted
 *  - 22:00 Command 扫描 pending 且 work_date < today → status=overdue
 *  - 连续 3 天 overdue → 触发 V0.4.4 整改流程
 */
class RectificationDailyRequired extends Model
{
    protected $table = 'rectification_daily_required';

    public $timestamps = true;

    protected $fillable = [
        'project_id', 'commencement_order_id', 'work_date',
        'status', 'submitted_log_id', 'overdue_days',
        'is_rectification', 'rectification_order_id', 'remark',
    ];

    protected $casts = [
        'work_date'           => 'date',
        'is_rectification'    => 'boolean',
        'overdue_days'        => 'integer',
    ];

    public const STATUS_PENDING   = 'pending';   // 待提交
    public const STATUS_SUBMITTED = 'submitted'; // 已提交
    public const STATUS_OVERDUE   = 'overdue';   // 逾期
    public const STATUS_EXCUSED   = 'excused';   // 请假/休

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function commencementOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectCommencementOrder::class, 'commencement_order_id');
    }

    public function submittedLog(): BelongsTo
    {
        return $this->belongsTo(ConstructionLog::class, 'submitted_log_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => '待提交',
            self::STATUS_SUBMITTED => '已提交',
            self::STATUS_OVERDUE   => '已逾期',
            self::STATUS_EXCUSED   => '已豁免',
            default                => $this->status,
        };
    }
}
