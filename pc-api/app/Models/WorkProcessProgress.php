<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.4.3 工序进度（实绩）
 *
 * 表: work_process_progress  (注意：表名单数 progress)
 * 主键: id
 *
 * 关系:
 *  - process_id → work_processes.id
 *  - project_id → projects.id
 *  - updated_by → users.id
 *
 * 触发：
 *  - CommencementOrderObserver: 开工时批量创建 (completed_quantity=0)
 *  - ConstructionLogObserver: 日志更新时累加/重算
 *  - ConstructionLogService::updateProgress 手动调用
 */
class WorkProcessProgress extends Model
{
    protected $table = 'work_process_progress';

    public $timestamps = true;

    protected $fillable = [
        'process_id', 'project_id', 'team_id',
        'planned_quantity', 'completed_quantity',
        'progress_percentage', 'status',
        'last_log_id', 'last_log_date', 'updated_by', 'remark',
    ];

    protected $casts = [
        'planned_quantity'    => 'decimal:2',
        'completed_quantity'  => 'decimal:2',
        'progress_percentage' => 'decimal:2',
        'last_log_date'       => 'date',
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    // ========== 关联 ==========

    public function process(): BelongsTo
    {
        return $this->belongsTo(WorkProcess::class, 'process_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function commencementOrder(): BelongsTo
    {
        return $this->belongsTo(ProjectCommencementOrder::class, 'commencement_order_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NOT_STARTED => '未开工',
            self::STATUS_IN_PROGRESS => '进行中',
            self::STATUS_COMPLETED   => '已完成',
            default                  => $this->status,
        };
    }
}
