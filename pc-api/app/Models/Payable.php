<?php

namespace App\Models;

use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    use HasFactory, HasDataScope;

    protected $fillable = ['supplier_id', 'project_id', 'amount', 'paid_amount', 'remaining_amount', 'due_date', 'paid_date', 'payment_term', 'status', 'notes', 'ref_no', 'po_id', 'tender_id', 'description', 'source'];

    protected $casts = ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'due_date' => 'date', 'paid_date' => 'date'];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function payments(): HasMany { return $this->hasMany(FinancePayment::class, 'payable_id'); }
}
