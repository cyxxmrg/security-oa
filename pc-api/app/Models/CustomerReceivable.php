<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Concerns\HasDataScope;

class CustomerReceivable extends Model
{
    use HasDataScope;
    protected $table = 'customer_receivables';

    protected $fillable = [
        'customer_id', 'project_id',
        'source_type', 'source_id', 'ref_no',
        'receivable_type',
        'amount', 'received_amount', 'due_date',
        'status', 'note', 'created_by',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'received_amount' => 'decimal:2',
        'balance'         => 'decimal:2',  // PG 生成列
        'due_date'        => 'date',
    ];

    public const TYPE_CONTRACT   = 'contract';
    public const TYPE_PROGRESS   = 'progress';
    public const TYPE_RETENTION  = 'retention';
    public const TYPE_WARRANTY   = 'warranty';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID    = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->receivable_type) {
            self::TYPE_CONTRACT  => '合同款',
            self::TYPE_PROGRESS  => '进度款',
            self::TYPE_RETENTION => '保留金',
            self::TYPE_WARRANTY  => '质保金',
            default              => $this->receivable_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待收',
            self::STATUS_PARTIAL => '部分收',
            self::STATUS_PAID    => '已结清',
            self::STATUS_OVERDUE => '逾期',
            default              => $this->status,
        };
    }
}
