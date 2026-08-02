<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    use HasFactory;

    protected $fillable = ['expense_claim_id', 'item_date', 'description', 'amount', 'category', 'attachment'];

    protected $casts = ['item_date' => 'date', 'amount' => 'decimal:2'];

    public function expenseClaim(): BelongsTo { return $this->belongsTo(ExpenseClaim::class); }
}
