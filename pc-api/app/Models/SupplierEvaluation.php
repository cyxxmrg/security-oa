<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluation extends Model
{
    protected $table = 'supplier_evaluations';

    protected $fillable = [
        'supplier_id', 'project_id', 'purchase_order_id',
        'quality_score', 'delivery_score', 'service_score', 'price_score',
        'overall_score', 'pros', 'cons', 'eval_date', 'evaluator_id',
    ];

    protected $casts = [
        'quality_score'  => 'integer',
        'delivery_score' => 'integer',
        'service_score'  => 'integer',
        'price_score'    => 'integer',
        'overall_score'  => 'decimal:1',
        'eval_date'      => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
