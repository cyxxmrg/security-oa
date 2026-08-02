<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceipt extends Model
{
    protected $table = 'customer_receipts';

    protected $fillable = [
        'customer_id', 'project_id', 'amount', 'receipt_date',
        'method', 'voucher_no', 'allocations',
        'bank_account', 'account_id', 'operator', 'remark', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'allocations'  => 'array',
        'receipt_date' => 'date',
    ];

    public const METHOD_CASH   = 'cash';
    public const METHOD_BANK   = 'bank';
    public const METHOD_ALIPAY = 'alipay';
    public const METHOD_WECHAT = 'wechat';
    public const METHOD_CHECK   = 'check';
    public const METHOD_OTHER  = 'other';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
