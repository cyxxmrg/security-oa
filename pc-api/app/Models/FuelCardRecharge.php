<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelCardRecharge extends Model
{
    use HasFactory;
    protected $table = 'fuel_card_recharges';
    protected $fillable = ['card_id', 'amount', 'recharge_date', 'payment_method', 'operator', 'voucher_no', 'notes'];
    protected $casts = [
        'amount' => 'decimal:2',
        'recharge_date' => 'date',
    ];
    public function card(): BelongsTo { return $this->belongsTo(FuelCard::class, 'card_id'); }
}
