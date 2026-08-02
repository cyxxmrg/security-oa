<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OpportunityStageFile extends Model
{
    protected $table = 'opportunity_stage_files';

    protected $fillable = [
        'opportunity_id', 'stage', 'original_name', 'stored_path',
        'mime_type', 'file_size', 'notes', 'uploaded_by',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDisk(): string
    {
        return 'opportunity-files';
    }

    /** 文件在磁盘上的完整 URL */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->getDisk())->url($this->stored_path);
    }

    /** 是否存在于磁盘 */
    public function fileExists(): bool
    {
        return Storage::disk($this->getDisk())->exists($this->stored_path);
    }

    /** 可读文件大小 */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}