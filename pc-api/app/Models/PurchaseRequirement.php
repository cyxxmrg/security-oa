<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequirement extends Model
{
    use HasFactory, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'project_id', 'inventory_item_id', 'material', 'spec', 'quantity', 'unit',
        'need_date', 'priority', 'status', 'creator', 'remark',
        'review_remark', 'reviewed_by', 'reviewed_at',
        'source_type', 'source_id', 'name', 'budget', 'spec_text',
        'merged_at', 'merged_plan_id',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'need_date'   => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('REQ');
            }
        });
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function plans(): HasMany { return $this->hasMany(PurchasePlan::class, 'requirement_id'); }
}
