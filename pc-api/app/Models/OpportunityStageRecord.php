<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityStageRecord extends Model
{
    use HasFactory;
    protected $table = 'opportunity_stage_records';
    protected $fillable = [
        'opportunity_id', 'stage', 'data', 'note', 'entered_at', 'entered_by',
        'next_assignee_id', 'next_assignee_name', 'next_due_at',
    ];
    protected $casts = [
        'data' => 'array',
        'entered_at' => 'datetime',
        'next_due_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
    public function nextAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'next_assignee_id');
    }
}