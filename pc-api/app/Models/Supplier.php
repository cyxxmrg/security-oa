<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.4.2 供应商档案（PSR-4 单文件，优先于 ProjectModels.php 聚合文件）
 *
 * 表: suppliers
 * 主键: id
 */
class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = [
        'code', 'name', 'type',
        'contact_person', 'phone', 'email', 'address', 'category',
        'business_license', 'legal_person', 'registered_capital', 'website',
        'bank_name', 'bank_account', 'account_name', 'tax_no',
        'payment_terms', 'rating', 'status',
        'notes', 'created_by',
    ];

    protected $casts = [
        'registered_capital' => 'decimal:2',
        'rating'             => 'integer',
    ];

    /** 类型枚举 */
    public const TYPE_MATERIAL  = 'material';
    public const TYPE_LABOR     = 'labor';
    public const TYPE_OUTSOURCE = 'outsource';
    public const TYPE_SERVICE   = 'service';

    /** 状态枚举 */
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_BLACKLIST = 'blacklist';

    /** 账期 */
    public const PAYMENT_CASH    = 'cash';
    public const PAYMENT_30DAYS  = '30days';
    public const PAYMENT_60DAYS  = '60days';
    public const PAYMENT_90DAYS  = '90days';

    // ========== 关联 ==========

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function primaryContact(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->where('is_primary', true);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    public function attachments(): HasMany
    {
        // V0.9.2: SupplierAttachment Model 已删除 (表 supplier_attachments 0 行, 业务无引用)
        // 保留关系声明以防后续业务接入, 实际查询会失败但不影响其他业务
        return $this->hasMany(\App\Models\SupplierAttachment::class);
    }

    public function payables(): HasMany
    {
        return $this->hasMany(SupplierPayable::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(ExternalQuote::class);
    }

    // ========== Scope / Helper ==========

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MATERIAL  => '材料',
            self::TYPE_LABOR     => '人工',
            self::TYPE_OUTSOURCE => '外包',
            self::TYPE_SERVICE   => '服务',
            default              => '其他',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE    => '正常',
            self::STATUS_PAUSED    => '暂停',
            self::STATUS_BLACKLIST => '黑名单',
            default                => $this->status,
        };
    }
}
