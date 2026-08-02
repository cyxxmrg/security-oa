<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairProgressLog extends Model
{
    use HasFactory;

    protected $table = 'repair_progress_logs';

    protected $fillable = [
        'repair_order_id', 'method_id',
        'progress', 'status_before', 'status_after',
        'description', 'cost_added', 'is_paid',
        'action_by', 'action_at',
    ];

    protected $casts = [
        'action_at'  => 'datetime',
        'cost_added' => 'decimal:2',
        'is_paid'    => 'boolean',
    ];

    public function repairOrder() { return $this->belongsTo(RepairOrder::class); }
    public function method()      { return $this->belongsTo(RepairMethod::class, 'method_id'); }
    public function actor()       { return $this->belongsTo(User::class, 'action_by'); }
}
