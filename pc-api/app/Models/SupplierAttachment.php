<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAttachment extends Model
{
    protected $table = 'supplier_attachments';

    protected $fillable = [
        'supplier_id', 'type', 'name', 'file_path', 'file_size',
        'mime_type', 'expire_date', 'uploaded_by',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'file_size' => 'integer',
        'expire_date' => 'date',
        'uploaded_by' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
