<?php

namespace App\Models;

use App\Models\FinanceAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 质保金操作记录
 *
 * @property int $id
 * @property int $deposit_id
 * @property string $operation_type
 * @property float $amount
 * @property string|null $before_status
 * @property string|null $after_status
 * @property int|null $bank_account_id
 * @property string|null $beneficiary
 * @property string|null $reason
 * @property int|null $operator_id
 * @property string $created_at
 */
class WarrantyDepositLog extends Model
{
    public const UPDATED_AT = null;
    public const CREATED_AT = 'created_at';

    protected $fillable = [
        'deposit_id', 'operation_type', 'amount',
        'before_status', 'after_status',
        'bank_account_id', 'beneficiary', 'reason',
        'operator_id',
    ];

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(WarrantyDeposit::class, 'deposit_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'bank_account_id');
    }
}
