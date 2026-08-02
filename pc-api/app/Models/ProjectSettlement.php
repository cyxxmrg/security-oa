<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSettlement extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'total_income', 'total_cost', 'cost_labor', 'cost_material', 'cost_outsource', 'cost_other', 'profit', 'profit_rate', 'settlement_date', 'status', 'notes'];

    protected $casts = ['total_income' => 'decimal:2', 'total_cost' => 'decimal:2', 'cost_labor' => 'decimal:2', 'cost_material' => 'decimal:2', 'cost_outsource' => 'decimal:2', 'cost_other' => 'decimal:2', 'profit' => 'decimal:2', 'profit_rate' => 'decimal:2', 'settlement_date' => 'date'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function contract(): BelongsTo { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
}
