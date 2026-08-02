<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetItem extends Model
{
    protected $table = 'project_budget_items';

    protected $fillable = [
        'budget_id', 'category', 'item_name', 'specification', 'unit',
        'quantity', 'unit_price', 'planned_amount', 'item_id', 'item_type',
        'remark', 'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'planned_amount' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class, 'budget_id');
    }
}
