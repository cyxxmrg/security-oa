<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceContract extends Model
{
    use HasFactory;

    protected $fillable = ['contract_no', 'customer_id', 'amount', 'start_date', 'end_date', 'inspection_frequency', 'scope', 'status', 'notes', 'contract_file', 'contract_file_name'];

    protected $casts = ['amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
