<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['plate_no', 'brand', 'model', 'year', 'color', 'vin', 'engine_no', 'purchase_date', 'purchase_price', 'department_id', 'responsible_user_id', 'status', 'mileage', 'seats', 'fuel_type'];

    protected $casts = ['purchase_date' => 'date', 'purchase_price' => 'decimal:2'];

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function responsibleUser(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function insurances(): HasMany { return $this->hasMany(VehicleInsurance::class); }
    public function maintenanceRecords(): HasMany { return $this->hasMany(VehicleMaintenanceRecord::class); }
    public function fuelCards(): HasMany { return $this->hasMany(FuelCard::class); }
    public function usageRequests(): HasMany { return $this->hasMany(VehicleUsageRequest::class); }
}
