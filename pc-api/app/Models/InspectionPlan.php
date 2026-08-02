<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.7 巡检计划 (合同维度的排程模板)
 *
 * 状态机: active → paused ↔ active; active/paused → expired/cancelled
 */
class InspectionPlan extends Model
{
    use HasFactory;

    protected $table = 'inspection_plans';

    protected $fillable = [
        'plan_no', 'contract_id', 'customer_id', 'name',
        'frequency', 'cycle_day', 'cycle_weekday', 'custom_interval_days',
        'duration_hours', 'priority', 'assigned_to', 'scope', 'checklist_template',
        'start_date', 'end_date', 'ahead_generate_days', 'status',
        'total_generated', 'total_completed', 'total_issues', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'checklist_template' => 'array',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE    => '启用',
        self::STATUS_PAUSED    => '暂停',
        self::STATUS_EXPIRED   => '到期',
        self::STATUS_CANCELLED => '取消',
    ];

    public const FREQUENCIES = [
        'weekly'     => '每周',
        'biweekly'   => '每两周',
        'monthly'    => '每月',
        'quarterly'  => '每季度',
        'semiannual' => '每半年',
        'yearly'     => '每年',
        'custom'     => '自定义',
    ];

    protected static function booted()
    {
        static::creating(function ($plan) {
            if (empty($plan->plan_no)) {
                $today = date('Ymd');
                $count = self::whereDate('created_at', today())->count() + 1;
                $plan->plan_no = 'IP-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function contract(): BelongsTo { return $this->belongsTo(MaintenanceContract::class, 'contract_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function tasks(): HasMany { return $this->hasMany(InspectionTask::class, 'plan_id'); }
    public function records(): HasMany { return $this->hasMany(InspectionRecord::class, 'plan_id'); }
    public function issues(): HasMany { return $this->hasMany(InspectionIssue::class, 'plan_id'); }
    public function schedule(): HasMany { return $this->hasMany(InspectionSchedule::class, 'plan_id'); }

    /**
     * 计算下一次执行日期 (按 frequency 推算)
     */
    public function calculateNextDate(\DateTimeInterface $from): ?\DateTime
    {
        $date = new \DateTime($from->format('Y-m-d'));
        return match ($this->frequency) {
            'weekly'     => $date->modify('+7 days'),
            'biweekly'   => $date->modify('+14 days'),
            'monthly'    => $date->modify('+1 month'),
            'quarterly'  => $date->modify('+3 months'),
            'semiannual' => $date->modify('+6 months'),
            'yearly'     => $date->modify('+1 year'),
            'custom'     => $date->modify('+' . ($this->custom_interval_days ?? 30) . ' days'),
            default      => null,
        };
    }

    /**
     * 是否需要生成新任务
     */
    public function needsGeneration(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) return false;
        if ($this->end_date && $this->end_date->isPast()) return false;
        $schedule = $this->schedule()->first();
        if (!$schedule) return true; // 从未生成过
        return $schedule->next_scheduled_date?->isPast() ?? true;
    }

    /**
     * 完成率
     */
    public function getCompletionRateAttribute(): float
    {
        if ($this->total_generated === 0) return 0;
        return round(($this->total_completed / $this->total_generated) * 100, 1);
    }

    /**
     * 异常率
     */
    public function getIssueRateAttribute(): float
    {
        if ($this->total_completed === 0) return 0;
        return round(($this->total_issues / $this->total_completed) * 100, 1);
    }
}
