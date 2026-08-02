<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStageLog extends Model
{
    protected $table = 'project_stage_logs';
    protected $fillable = ['project_id', 'stage_key', 'action', 'note', 'entered_by'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function enteredBy(): BelongsTo { return $this->belongsTo(User::class, 'entered_by'); }
}