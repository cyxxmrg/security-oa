<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleUsageRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id', 'applicant_id', 'usage_date', 'start_time', 'end_time',
        'destination', 'purpose', 'passengers', 'self_drive', 'status',
        'approver_id', 'approved_at', 'actual_mileage', 'actual_fuel', 'start_mileage', 'end_mileage',
    ];

    protected $casts = ['usage_date' => 'date', 'approved_at' => 'datetime', 'passengers' => 'integer', 'self_drive' => 'boolean', 'actual_mileage' => 'integer', 'actual_fuel' => 'decimal:2'];

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function applicant(): BelongsTo { return $this->belongsTo(User::class, 'applicant_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
