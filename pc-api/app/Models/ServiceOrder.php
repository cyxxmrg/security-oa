<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ServiceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'customer_id', 'project_id', 'customer_device_id',
        'fault_description', 'fault_photos', 'urgency', 'service_type', 'status',
        'assigned_to', 'assigned_at', 'started_at', 'completed_at', 'confirmed_at',
        'rating', 'review', 'created_by', 'sla_hours',
    ];

    protected $casts = [
        'fault_photos' => 'array', 'urgency' => \App\Enums\Urgency::class,
        'status' => \App\Enums\ServiceOrderStatus::class,
        'assigned_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->order_no)) {
                $count = ServiceOrder::whereDate('created_at', today())->count() + 1;
                $order->order_no = 'SO-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function device(): BelongsTo { return $this->belongsTo(CustomerDevice::class, 'customer_device_id'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function logs(): HasMany { return $this->hasMany(ServiceOrderLog::class); }
    public function parts(): HasMany { return $this->hasMany(ServiceOrderPart::class); }
    public function approvals(): MorphMany { return $this->morphMany(ApprovalRecord::class, 'approvable'); }

    public function isOverdue(): bool
    {
        if ($this->status === \App\Enums\ServiceOrderStatus::PENDING && $this->created_at) {
            return $this->created_at->diffInHours(now()) > $this->sla_hours;
        }
        return false;
    }
}
