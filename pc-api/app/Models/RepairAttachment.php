<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairAttachment extends Model
{
    use HasFactory;

    protected $table = 'repair_attachments';

    protected $fillable = [
        'repair_order_id', 'file_path', 'file_name', 'file_type',
        'category', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function repairOrder() { return $this->belongsTo(RepairOrder::class); }
    public function uploader()    { return $this->belongsTo(User::class, 'uploaded_by'); }
}
