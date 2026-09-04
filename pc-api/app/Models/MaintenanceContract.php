<?php

namespace App\Models;

use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceContract extends Model
{
    use HasFactory, HasDataScope;

    protected $fillable = ['contract_no', 'customer_id', 'amount', 'start_date', 'end_date', 'inspection_frequency', 'scope', 'status', 'notes', 'contract_file', 'contract_file_name'];

    protected $casts = ['amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function plans(): HasMany { return $this->hasMany(InspectionPlan::class, 'contract_id'); }
}
