<?php

namespace App\Models;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * V0.5.5 维修工单 (利旧 service_orders, 新表新设计, 移动友好)
 *
 * 状态机见 App\Enums\WorkOrderStatus
 */
class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'work_orders';

    protected $fillable = [
        'code', 'customer_id', 'project_id', 'equipment_id',
        'contact_name', 'contact_phone', 'address',
        'service_type', 'priority',
        'fault_description', 'equipment_brand', 'equipment_model', 'serial_no',
        'assigned_to', 'scheduled_at', 'started_at', 'completed_at',
        'status',
        'is_billable', 'service_fee', 'parts_cost', 'total_cost',
        'charge_type', 'contract_id', 'min_charge',
        'result_notes', 'customer_signature',
        'converted_repair_id', 'is_locked',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'is_billable'  => 'boolean',
        'is_locked'    => 'boolean',
        'service_fee'  => 'decimal:2',
        'parts_cost'   => 'decimal:2',
        'total_cost'   => 'decimal:2',
        'status'       => WorkOrderStatus::class,
        'priority'     => WorkOrderPriority::class,
    ];

    public function customer()  { return $this->belongsTo(Customer::class); }
    public function project()   { return $this->belongsTo(Project::class); }
    public function contract()  { return $this->belongsTo(ProjectContract::class); }
    public function assignee()  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function repairOrder() { return $this->hasOne(RepairOrder::class, 'source_id')->where('source_type', 'work_order'); }

    /** 锁单 (转换后 / 取消后) */
    public function lock(): void
    {
        $this->is_locked = true;
        $this->save();
    }

    /** 是否可编辑 */
    public function isEditable(): bool
    {
        return !$this->is_locked && !$this->status->isTerminal();
    }
}
