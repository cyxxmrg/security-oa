<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * V0.7 巡检任务 (按排程自动生成的具体执行单)
 *
 * 状态机: pending → in_progress → completed
 *                  ↘ overdue (超 scheduled_at 24h)
 *                  ↘ skipped/cancelled
 */
class InspectionTask extends Model
{
    use HasFactory;

    protected $table = 'inspection_tasks';

    protected $fillable = [
        'task_no', 'plan_id', 'contract_id', 'customer_id',
        'scheduled_date', 'scheduled_hour', 'scheduled_at',
        'assigned_to', 'status', 'started_at', 'completed_at', 'duration_minutes',
        'equipment_count', 'issue_count', 'overdue_notified', 'overdue_notified_at', 'remark',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'overdue_notified' => 'boolean',
    ];

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_OVERDUE     = 'overdue';
    public const STATUS_SKIPPED     = 'skipped';
    public const STATUS_CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING     => '待执行',
        self::STATUS_IN_PROGRESS => '执行中',
        self::STATUS_COMPLETED   => '已完成',
        self::STATUS_OVERDUE     => '已逾期',
        self::STATUS_SKIPPED     => '已跳过',
        self::STATUS_CANCELLED   => '已取消',
    ];

    protected static function booted()
    {
        static::creating(function ($task) {
            if (empty($task->task_no)) {
                $today = date('Ymd');
                $count = self::whereDate('created_at', today())->count() + 1;
                $task->task_no = 'IT-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
            if ($task->scheduled_date && $task->scheduled_hour !== null) {
                $dateStr = $task->scheduled_date instanceof \DateTimeInterface
                    ? $task->scheduled_date->format('Y-m-d')
                    : (string) $task->scheduled_date;
                $task->scheduled_at = (new \DateTime($dateStr))->setTime($task->scheduled_hour, 0, 0);
            }
        });
    }

    public function plan(): BelongsTo     { return $this->belongsTo(InspectionPlan::class, 'plan_id'); }
    public function contract(): BelongsTo { return $this->belongsTo(MaintenanceContract::class, 'contract_id'); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function record(): HasOne      { return $this->hasOne(InspectionRecord::class, 'task_id'); }
    public function issues(): HasMany     { return $this->hasMany(InspectionIssue::class, 'task_id'); }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS])
            && $this->scheduled_at
            && $this->scheduled_at->diffInHours(now()) > 24;
    }

    public function isToday(): bool
    {
        return $this->scheduled_date && $this->scheduled_date->isToday();
    }
}
