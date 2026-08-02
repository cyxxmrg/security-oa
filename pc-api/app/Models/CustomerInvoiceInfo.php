<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'invoice_type', 'company_name', 'tax_no',
        'register_address', 'register_phone', 'bank_name', 'bank_account',
        'is_default', 'remark',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public static function invoiceTypeLabel(string $t): string
    {
        return match ($t) {
            'special'    => '增值税专用发票',
            'electronic' => '电子发票',
            'default'      => '增值税普通发票',
        };
    }
}
