<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActualCost extends Model
{
    protected $table = 'project_actual_costs';

    protected $fillable = [
        'project_id', 'source_type', 'source_id', 'category',
        'amount', 'cost_date', 'description', 'metadata',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'cost_date' => 'date',
        'metadata'  => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
