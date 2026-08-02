<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessInspection extends Model
{
    use HasFactory;

    const TYPE_SELF       = 'self';
    const TYPE_MUTUAL     = 'mutual';
    const TYPE_SUPERVISOR = 'supervisor';
    const TYPE_OWNER      = 'owner';

    const RESULT_PENDING = 'pending';
    const RESULT_PASS    = 'pass';
    const RESULT_FAIL    = 'fail';
    const RESULT_PARTIAL = 'partial';

    protected $fillable = [
        'process_instance_id', 'inspection_type', 'inspector_id', 'inspector_name',
        'inspection_date', 'result', 'score', 'checkpoint_results', 'issues',
        'suggestions', 'next_inspection_date', 'image_ids', 'remark',
    ];

    protected $casts = [
        'inspection_date'      => 'date',
        'next_inspection_date' => 'date',
        'score'                => 'decimal:2',
        'checkpoint_results'   => 'array',
        'issues'               => 'array',
        'image_ids'            => 'array',
    ];

    public function processInstance(): BelongsTo { return $this->belongsTo(ProcessInstance::class); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspector_id'); }
    public function images(): HasMany { return $this->hasMany(ProcessImage::class, 'inspection_id'); }
    public function signatures(): HasMany { return $this->hasMany(ProcessSignature::class, 'inspection_id'); }
}
