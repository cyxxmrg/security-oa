<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'name', 'position', 'phone', 'email', 'is_primary', 'wechat', 'notes'];

    protected $casts = ['is_primary' => 'boolean'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
