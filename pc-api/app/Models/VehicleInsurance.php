<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleInsurance extends Model
{
    use HasFactory;

    protected $table = 'vehicle_insurance';
    protected $fillable = ['vehicle_id', 'insurance_company', 'policy_no', 'type', 'premium', 'start_date', 'end_date', 'status', 'notes'];

    protected $casts = ['premium' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
}
