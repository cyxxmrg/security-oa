<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderLog extends Model
{
    use HasFactory;

    protected $fillable = ['service_order_id', 'user_id', 'action', 'content', 'photos', 'location', 'gps_lat', 'gps_lng'];

    protected $casts = ['photos' => 'array', 'gps_lat' => 'decimal:7', 'gps_lng' => 'decimal:7'];

    public function serviceOrder(): BelongsTo { return $this->belongsTo(ServiceOrder::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
