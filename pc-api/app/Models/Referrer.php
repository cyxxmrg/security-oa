<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referrer extends Model
{
    use HasFactory;
    protected $table = 'referrers';
    protected $fillable = ['name', 'phone', 'customer_id', 'bank_account', 'bank_name', 'commission_rate', 'total_commission', 'notes', 'owner_id'];
    protected $casts = ['commission_rate' => 'decimal:2', 'total_commission' => 'decimal:2'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
