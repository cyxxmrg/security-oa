<?php

namespace App\Models;

use App\Enums\RepairMethodType;
use App\Enums\RepairOrderStatus;
use App\Enums\RepairSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * V0.5.5 返修单主表
 */
class RepairOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'repair_orders';

    protected $fillable = [
        'code', 'source_type', 'source_id', 'source_code',
        'customer_id', 'project_id', 'equipment_id',
        'contact_name', 'contact_phone', 'address',
        'equipment_brand', 'equipment_model', 'serial_no',
        'fault_type', 'fault_description', 'severity',
        'received_by', 'received_at', 'expected_finish_at',
        'status', 'method_type',
        'parts_cost', 'labor_cost', 'shipping_cost', 'total_cost',
        'is_warranty', 'warranty_until',
        'remarks', 'created_by',
    ];

    protected $casts = [
        'received_at'        => 'datetime',
        'expected_finish_at' => 'datetime',
        'warranty_until'     => 'date',
        'is_warranty'        => 'boolean',
        'parts_cost'         => 'decimal:2',
        'labor_cost'         => 'decimal:2',
        'shipping_cost'      => 'decimal:2',
        'total_cost'         => 'decimal:2',
        'status'             => RepairOrderStatus::class,
        'source_type'        => RepairSourceType::class,
        'method_type'        => RepairMethodType::class,
    ];

    public function customer()   { return $this->belongsTo(Customer::class); }
    public function project()    { return $this->belongsTo(Project::class); }
    public function receiver()   { return $this->belongsTo(User::class, 'received_by'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }

    /** 来源工单 (source_type=work_order 时) */
    public function sourceWorkOrder() {
        return $this->belongsTo(WorkOrder::class, 'source_id')->where('source_type', 'work_order');
    }

    public function shipments()  { return $this->hasMany(RepairShipment::class)->orderBy('direction')->orderBy('shipped_at'); }
    public function methods()    { return $this->hasMany(RepairMethod::class); }
    public function progressLogs() { return $this->hasMany(RepairProgressLog::class)->orderBy('action_at', 'desc'); }
    public function attachments() { return $this->hasMany(RepairAttachment::class); }

    public function outboundShipment() { return $this->hasOne(RepairShipment::class)->where('direction', 'outbound'); }
    public function inboundShipment()  { return $this->hasOne(RepairShipment::class)->where('direction', 'inbound'); }
}
