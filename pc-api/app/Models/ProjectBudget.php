<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBudget extends Model
{
    protected $table = 'project_budgets';

    protected $fillable = [
        'project_id', 'code', 'version', 'status',
        'material_budget', 'labor_budget', 'outsource_budget', 'other_budget', 'total_budget',
        'material_actual', 'labor_actual', 'outsource_actual', 'other_actual', 'total_actual',
        'approved_by', 'approved_at', 'created_by', 'remark',
    ];

    protected $casts = [
        'material_budget'  => 'decimal:2',
        'labor_budget'     => 'decimal:2',
        'outsource_budget' => 'decimal:2',
        'other_budget'     => 'decimal:2',
        'total_budget'     => 'decimal:2',
        'material_actual'  => 'decimal:2',
        'labor_actual'     => 'decimal:2',
        'outsource_actual' => 'decimal:2',
        'other_actual'     => 'decimal:2',
        'total_actual'     => 'decimal:2',
        'approved_at'      => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectBudgetItem::class, 'budget_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
