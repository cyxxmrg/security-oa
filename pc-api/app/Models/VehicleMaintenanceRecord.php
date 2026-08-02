<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['vehicle_id', 'maintenance_type', 'mileage', 'cost', 'maintenance_date', 'description', 'next_maintenance_mileage', 'next_maintenance_date', 'handled_by'];

    protected $casts = ['cost' => 'decimal:2', 'maintenance_date' => 'date', 'next_maintenance_date' => 'date'];

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function handledByUser(): BelongsTo { return $this->belongsTo(User::class, 'handled_by'); }
}
