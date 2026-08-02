<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.7 排程触发器 (用于 cron 增量生成)
 */
class InspectionSchedule extends Model
{
    use HasFactory;

    protected $table = 'inspection_schedules';

    protected $fillable = [
        'plan_id', 'last_generated_date', 'next_scheduled_date',
        'generated_count', 'last_run_at',
    ];

    protected $casts = [
        'last_generated_date' => 'date',
        'next_scheduled_date' => 'date',
        'last_run_at'         => 'datetime',
    ];

    public function plan(): BelongsTo { return $this->belongsTo(InspectionPlan::class, 'plan_id'); }
}
