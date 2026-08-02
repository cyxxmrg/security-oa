<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.6.2.2 付款凭证 (财务付款后上传的回单/发票照片/PDF)
 * 存到 storage/app/public/purchase/vouchers/{payment_request_id}/xxx.png
 */
class PurchasePaymentVoucher extends Model
{
    use HasFactory;

    protected $table = 'purchase_payment_vouchers';
    public $timestamps = false;

    protected $fillable = [
        'payment_request_id', 'file_path', 'file_name', 'mime', 'size',
        'uploaded_by', 'uploaded_at', 'remark',
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PurchasePaymentRequest::class, 'payment_request_id');
    }
}
