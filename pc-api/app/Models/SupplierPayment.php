<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    protected $table = 'supplier_payments';

    protected $fillable = [
        'supplier_id', 'amount', 'payment_date',
        'method', 'voucher_no', 'allocations',
        'bank_account', 'operator', 'remark', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'allocations'  => 'array',
        'payment_date' => 'date',
    ];

    public const METHOD_CASH   = 'cash';
    public const METHOD_BANK   = 'bank';
    public const METHOD_ALIPAY = 'alipay';
    public const METHOD_WECHAT = 'wechat';
    public const METHOD_OTHER  = 'other';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
