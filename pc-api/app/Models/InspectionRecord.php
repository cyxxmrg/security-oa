<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.7 现场打卡 (工程师到达现场, 含 GPS + 照片 + 检查项答案)
 */
class InspectionRecord extends Model
{
    use HasFactory;

    protected $table = 'inspection_records';

    protected $fillable = [
        'record_no', 'task_id', 'plan_id', 'user_id',
        'checkin_at', 'checkin_location', 'checkin_lat', 'checkin_lng', 'checkin_photos',
        'checkout_at', 'checkout_location', 'checkout_lat', 'checkout_lng',
        'checklist_answers', 'normal_count', 'abnormal_count', 'summary', 'rating', 'status',
    ];

    protected $casts = [
        'checkin_at'         => 'datetime',
        'checkout_at'        => 'datetime',
        'checkin_lat'        => 'decimal:7',
        'checkin_lng'        => 'decimal:7',
        'checkout_lat'       => 'decimal:7',
        'checkout_lng'       => 'decimal:7',
        'checkin_photos'     => 'array',
        'checklist_answers'  => 'array',
    ];

    public const STATUS_CHECKED_IN  = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';

    protected static function booted()
    {
        static::creating(function ($record) {
            if (empty($record->record_no)) {
                $today = date('Ymd');
                $count = self::whereDate('created_at', today())->count() + 1;
                $record->record_no = 'IR-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function task(): BelongsTo     { return $this->belongsTo(InspectionTask::class, 'task_id'); }
    public function plan(): BelongsTo     { return $this->belongsTo(InspectionPlan::class, 'plan_id'); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class, 'user_id'); }
    public function issues(): HasMany     { return $this->hasMany(InspectionIssue::class, 'record_id'); }

    /**
     * 是否完全正常
     */
    public function isAllNormal(): bool
    {
        return $this->abnormal_count === 0 && $this->normal_count > 0;
    }
}
