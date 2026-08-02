<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Concerns\HasDataScope;
use Carbon\Carbon;

/**
 * V0.4.5 质保期管理 - 质保单
 *
 * 表: warranties
 * 主键: id
 *
 * 业务:
 *  - 一份 warranty 绑定 1 个 project + 1 个 customer + 0..1 个 device
 *  - 续约时新建一份 warranty, renewed_from_id 指向旧单
 *  - 临近过期判定: isExpiring($days=30) / daysUntilExpiry()
 */
class Warranty extends Model
{
    use SoftDeletes, HasDataScope;

    protected $table = 'warranties';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid', 'project_id', 'customer_id', 'device_id',
        'warranty_no', 'warranty_type',
        'start_date', 'end_date', 'period_months',
        'status', 'amount', 'terms', 'notes',
        'renewed_from_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'amount'           => 'decimal:2',
        'period_months'    => 'integer',
        'renewed_from_id'  => 'integer',
    ];

    /** 状态 */
    public const STATUS_ACTIVE     = 'active';      // 在保
    public const STATUS_EXPIRING   = 'expiring';    // 即将过期
    public const STATUS_EXPIRED    = 'expired';     // 已过期
    public const STATUS_RENEWED    = 'renewed';     // 已续约
    public const STATUS_TERMINATED = 'terminated';  // 已终止

    /** 类型 */
    public const TYPE_BASIC    = 'basic';     // 基础质保
    public const TYPE_EXTENDED = 'extended';  // 延保

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(CustomerDevice::class, 'device_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(WarrantyServiceOrder::class, 'warranty_id');
    }

    public function deposit(): HasOne
    {
        return $this->hasOne(WarrantyDeposit::class, 'project_id', 'project_id');
    }

    // ========== Scope ==========

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiring($query)
    {
        return $query->where('status', self::STATUS_EXPIRING);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('warranty_type', $type);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // ========== Helper ==========

    /**
     * 距过期是否不足 N 天（默认 30 天），且状态仍为 active。
     */
    public function isExpiring(int $days = 30): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        $daysLeft = $this->daysUntilExpiry();
        return $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= $days;
    }

    /**
     * 距离 end_date 还有多少天（已过期返回负数）。
     * 返 null = end_date 为空。
     */
    public function daysUntilExpiry(): ?int
    {
        if (empty($this->end_date)) {
            return null;
        }
        return (int) Carbon::now()->startOfDay()->diffInDays(
            Carbon::parse($this->end_date)->startOfDay(),
            false  // 允许负数
        );
    }

    public function isExpired(): bool
    {
        $d = $this->daysUntilExpiry();
        return $d !== null && $d < 0;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE     => '在保',
            self::STATUS_EXPIRING   => '即将过期',
            self::STATUS_EXPIRED    => '已过期',
            self::STATUS_RENEWED    => '已续约',
            self::STATUS_TERMINATED => '已终止',
            default                 => (string) $this->status,
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->warranty_type) {
            self::TYPE_BASIC    => '基础质保',
            self::TYPE_EXTENDED => '延保',
            default             => (string) $this->warranty_type,
        };
    }
}
