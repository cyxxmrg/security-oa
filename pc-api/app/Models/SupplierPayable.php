<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Concerns\HasDataScope;

class SupplierPayable extends Model
{
    use HasDataScope;

    protected $table = 'supplier_payables';

    protected $fillable = [
        'supplier_id', 'project_id',
        'source_type', 'source_id', 'ref_no',
        'amount', 'paid_amount', 'due_date',
        'status', 'note', 'created_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance'     => 'decimal:2',  // PG 生成列
        'due_date'    => 'date',
    ];

    public const SOURCE_QUOTE   = 'quote';
    public const SOURCE_CONTRACT = 'contract';
    public const SOURCE_MANUAL  = 'manual';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID    = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待付',
            self::STATUS_PARTIAL => '部分付',
            self::STATUS_PAID    => '已结清',
            self::STATUS_OVERDUE => '逾期',
            default              => $this->status,
        };
    }
}
