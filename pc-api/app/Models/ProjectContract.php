<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectContract extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'customer_id', 'quotation_id', 'contract_no', 'contract_amount', 'payment_method', 'contract_start', 'contract_end', 'status', 'attachment', 'signed_at', 'notes'];

    protected $casts = ['contract_amount' => 'decimal:2', 'contract_start' => 'date', 'contract_end' => 'date', 'signed_at' => 'datetime'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function paymentNodes(): HasMany { return $this->hasMany(ContractPaymentNode::class, 'contract_id'); }
}
