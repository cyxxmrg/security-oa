<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePayment extends Model
{
    use HasFactory;

    protected $fillable = ['receivable_id', 'payable_id', 'supplier_id', 'project_id', 'account_id', 'amount', 'payment_date', 'method', 'voucher_no', 'payee', 'transfer_group_id', 'is_internal_transfer', 'operator', 'remark'];

    protected $casts = ['amount' => 'decimal:2', 'payment_date' => 'date', 'is_internal_transfer' => 'boolean'];

    public function receivable(): BelongsTo { return $this->belongsTo(Receivable::class); }
    public function payable(): BelongsTo { return $this->belongsTo(Payable::class); }
    public function account(): BelongsTo { return $this->belongsTo(FinanceAccount::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
}
