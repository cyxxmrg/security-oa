<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderAttachment extends Model
{
    protected $table = 'tender_attachments';
    protected $fillable = [
        'tender_project_id', 'tender_bid_id',
        'uploaded_by_user_id', 'uploaded_by_supplier_id',
        'file_name', 'file_path', 'mime_type', 'file_size',
        'category', 'visibility',
    ];

    public function project(): BelongsTo { return $this->belongsTo(TenderProject::class, 'tender_project_id'); }
    public function bid(): BelongsTo { return $this->belongsTo(TenderBid::class, 'tender_bid_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
    public function uploadedBySupplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'uploaded_by_supplier_id'); }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
