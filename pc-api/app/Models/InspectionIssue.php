<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.7 巡检异常 (单设备问题点, 可自动转工单)
 *
 * 状态机: open → work_order_created → resolved
 *                              ↘ ignored
 */
class InspectionIssue extends Model
{
    use HasFactory;

    protected $table = 'inspection_issues';

    protected $fillable = [
        'issue_no', 'record_id', 'task_id', 'plan_id', 'contract_id', 'customer_id',
        'inventory_item_id', 'equipment_name', 'equipment_location',
        'issue_type', 'severity', 'title', 'description', 'photos',
        'status', 'work_order_id', 'resolved_at', 'resolved_by', 'resolution',
    ];

    protected $casts = [
        'photos'      => 'array',
        'resolved_at' => 'datetime',
    ];

    public const STATUS_OPEN              = 'open';
    public const STATUS_WORK_ORDER_CREATED = 'work_order_created';
    public const STATUS_RESOLVED          = 'resolved';
    public const STATUS_IGNORED           = 'ignored';

    public const STATUSES = [
        self::STATUS_OPEN               => '待处理',
        self::STATUS_WORK_ORDER_CREATED => '已转工单',
        self::STATUS_RESOLVED           => '已解决',
        self::STATUS_IGNORED            => '已忽略',
    ];

    public const ISSUE_TYPES = [
        'hardware'    => '硬件故障',
        'software'    => '软件问题',
        'network'     => '网络异常',
        'power'       => '供电问题',
        'environment' => '环境异常',
        'other'       => '其他',
    ];

    public const SEVERITIES = [
        'low'      => '轻微',
        'medium'   => '一般',
        'high'     => '严重',
        'critical' => '紧急',
    ];

    protected static function booted()
    {
        static::creating(function ($issue) {
            if (empty($issue->issue_no)) {
                $today = date('Ymd');
                $count = self::whereDate('created_at', today())->count() + 1;
                $issue->issue_no = 'II-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function record(): BelongsTo        { return $this->belongsTo(InspectionRecord::class, 'record_id'); }
    public function task(): BelongsTo          { return $this->belongsTo(InspectionTask::class, 'task_id'); }
    public function plan(): BelongsTo          { return $this->belongsTo(InspectionPlan::class, 'plan_id'); }
    public function contract(): BelongsTo      { return $this->belongsTo(MaintenanceContract::class, 'contract_id'); }
    public function customer(): BelongsTo      { return $this->belongsTo(Customer::class); }
    public function equipment(): BelongsTo     { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
    public function workOrder(): BelongsTo     { return $this->belongsTo(WorkOrder::class, 'work_order_id'); }
    public function resolver(): BelongsTo      { return $this->belongsTo(User::class, 'resolved_by'); }
}
