<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessInstance extends Model
{
    use HasFactory;

    const STATUS_PENDING    = 'pending';
    const STATUS_IN_PROGRESS= 'in_progress';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_ACCEPTED   = 'accepted';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_BLOCKED    = 'blocked';

    protected $fillable = [
        'project_id', 'template_id', 'parent_id', 'code', 'name', 'sequence',
        'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date',
        'planned_duration_days', 'actual_duration_days',
        'status', 'progress', 'foreman_id', 'workers', 'location', 'description',
        'accepted_at', 'accepted_by',
    ];

    protected $casts = [
        'planned_start_date'    => 'date',
        'planned_end_date'      => 'date',
        'actual_start_date'     => 'date',
        'actual_end_date'       => 'date',
        'planned_duration_days' => 'integer',
        'actual_duration_days'  => 'integer',
        'sequence'              => 'integer',
        'progress'              => 'integer',
        'workers'               => 'array',
        'accepted_at'           => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function template(): BelongsTo { return $this->belongsTo(ProcessTemplate::class, 'template_id'); }
    public function foreman(): BelongsTo { return $this->belongsTo(User::class, 'foreman_id'); }
    public function acceptedByUser(): BelongsTo { return $this->belongsTo(User::class, 'accepted_by'); }
    public function parent(): BelongsTo { return $this->belongsTo(ProcessInstance::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(ProcessInstance::class, 'parent_id'); }
    public function inspections(): HasMany { return $this->hasMany(ProcessInspection::class); }
    public function images(): HasMany { return $this->hasMany(ProcessImage::class); }
    public function signatures(): HasMany { return $this->hasMany(ProcessSignature::class); }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true)
            && $this->planned_end_date
            && $this->planned_end_date->lt(today());
    }
}
