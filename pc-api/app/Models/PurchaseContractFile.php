<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.6.2.2 合同附件 (PDF 等)
 * 存到 storage/app/public/purchase/contracts/{contract_id}/xxx.pdf
 */
class PurchaseContractFile extends Model
{
    use HasFactory;

    protected $table = 'purchase_contract_files';
    public $timestamps = false;

    protected $fillable = [
        'contract_id', 'file_path', 'file_name', 'mime', 'size',
        'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PurchaseContract::class, 'contract_id');
    }
}
