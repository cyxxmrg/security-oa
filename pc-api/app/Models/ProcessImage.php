<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessImage extends Model
{
    use HasFactory;

    const CATEGORY_BEFORE     = 'before';
    const CATEGORY_DURING     = 'during';
    const CATEGORY_AFTER      = 'after';
    const CATEGORY_ISSUE      = 'issue';
    const CATEGORY_ACCEPTANCE = 'acceptance';

    protected $fillable = [
        'process_instance_id', 'inspection_id', 'category', 'file_type',
        'file_name', 'file_path', 'file_size', 'mime_type',
        'width', 'height', 'duration', 'thumbnail_path',
        'taken_at', 'taken_by', 'location', 'geo', 'description', 'tags',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width'     => 'integer',
        'height'    => 'integer',
        'duration'  => 'integer',
        'taken_at'  => 'datetime',
        'geo'       => 'array',
        'tags'      => 'array',
    ];

    public function processInstance(): BelongsTo { return $this->belongsTo(ProcessInstance::class); }
    public function inspection(): BelongsTo { return $this->belongsTo(ProcessInspection::class, 'inspection_id'); }
    public function takenByUser(): BelongsTo { return $this->belongsTo(User::class, 'taken_by'); }
}
