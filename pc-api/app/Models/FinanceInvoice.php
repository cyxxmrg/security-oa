<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceInvoice extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_no', 'direction', 'invoice_type', 'customer_id', 'supplier_id', 'project_id', 'receivable_id', 'contract_id', 'applicant_id', 'amount', 'tax_rate', 'tax_amount', 'total_amount', 'issue_date', 'delivery_date', 'status', 'remark'];

    protected $casts = ['amount' => 'decimal:2', 'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'issue_date' => 'date', 'delivery_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function receivable(): BelongsTo { return $this->belongsTo(Receivable::class); }
    public function contract(): BelongsTo { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function applicant(): BelongsTo { return $this->belongsTo(User::class, 'applicant_id'); }
}
