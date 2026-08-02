<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiskFile extends Model
{
    use HasFactory;

    protected $fillable = ['folder_id', 'name', 'original_name', 'extension', 'mime_type', 'size', 'path', 'uploaded_by', 'version', 'description', 'is_starred'];

    protected $casts = ['size' => 'integer', 'version' => 'integer', 'is_starred' => 'boolean'];

    public function folder(): BelongsTo { return $this->belongsTo(DiskFolder::class); }
    public function uploadedByUser(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
