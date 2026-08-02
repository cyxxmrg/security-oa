<?php

namespace App\Models;

use App\Enums\RepairMethodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairMethod extends Model
{
    use HasFactory;

    protected $table = 'repair_methods';

    protected $fillable = [
        'repair_order_id', 'method_type', 'method_category',
        'estimated_cost', 'actual_cost', 'parts_replaced', 'hours_spent',
        'vendor_id',
        'payment_method', 'payment_status', 'paid_at', 'invoice_no',
        'remarks', 'created_by',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost'    => 'decimal:2',
        'hours_spent'    => 'decimal:2',
        'paid_at'        => 'datetime',
        'parts_replaced' => 'array',
        'method_type'    => RepairMethodType::class,
    ];

    public function repairOrder() { return $this->belongsTo(RepairOrder::class); }
    public function progressLogs() { return $this->hasMany(RepairProgressLog::class, 'method_id'); }
}
