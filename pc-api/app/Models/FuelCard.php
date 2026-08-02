<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelCard extends Model
{
    use HasFactory;
    protected $table = 'fuel_cards';
    protected $fillable = ['card_no', 'card_name', 'vehicle_id', 'balance', 'status', 'issue_date', 'expire_date', 'notes'];
    protected $casts = [
        'balance' => 'decimal:2',
        'issue_date' => 'date',
        'expire_date' => 'date',
    ];
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function recharges(): HasMany { return $this->hasMany(FuelCardRecharge::class, 'card_id'); }
}
