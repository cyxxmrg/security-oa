<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierContact extends Model
{
    protected $table = 'supplier_contacts';

    protected $fillable = [
        'supplier_id', 'name', 'position',
        'phone', 'tel', 'email', 'wechat',
        'is_primary', 'remark',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
