<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'project_id', 'device_name', 'device_type', 'brand', 'model',
        'serial_number', 'install_location', 'install_date', 'warranty_end', 'status', 'notes',
    ];

    protected $casts = ['install_date' => 'date', 'warranty_end' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
