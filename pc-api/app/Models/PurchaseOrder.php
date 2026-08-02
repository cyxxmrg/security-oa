<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory, HasDataScope, GeneratesUniqueCode;

    protected $fillable = ['project_id', 'supplier_id', 'po_no', 'total_amount', 'status', 'approved_by', 'approved_at', 'notes', 'code', 'tender_id', 'title', 'created_by', 'plan_id', 'source_requirement_id', 'path', 'quote_id', 'contract_id'];

    protected $casts = ['total_amount' => 'decimal:2', 'approved_at' => 'datetime'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class); }
    public function plan(): BelongsTo { return $this->belongsTo(PurchasePlan::class, 'plan_id'); }
    public function contract(): BelongsTo { return $this->belongsTo(PurchaseContract::class, 'contract_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(PurchaseRequirement::class, 'source_requirement_id'); }
    public function tender(): BelongsTo { return $this->belongsTo(TenderProject::class, 'tender_id'); }
    public function payables(): HasMany { return $this->hasMany(Payable::class, 'po_id'); }

    protected static function booted()
    {
        static::creating(function ($po) {
            if (empty($po->po_no)) {
                $po->po_no = self::uniqueCode('PO');
            }
            if (empty($po->code)) {
                $po->code = self::uniqueCode('POC');
            }
        });
    }
}
