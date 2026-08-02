<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPaymentNode extends Model
{
    use HasFactory;

    protected $fillable = ['contract_id', 'name', 'percentage', 'amount', 'planned_date', 'actual_date', 'status', 'paid_amount', 'notes'];

    protected $casts = ['percentage' => 'decimal:2', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'planned_date' => 'date', 'actual_date' => 'date'];

    public function contract(): BelongsTo { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
}
