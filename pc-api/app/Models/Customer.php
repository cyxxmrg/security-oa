<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'credit_code', 'industry', 'category',
        'province', 'city', 'district', 'address',
        'longitude', 'latitude', 'tags', 'source', 'status',
        'assigned_user_id', 'description',
    ];

    protected $casts = ['tags' => 'array', 'longitude' => 'decimal:7', 'latitude' => 'decimal:7'];

    public function contacts(): HasMany { return $this->hasMany(CustomerContact::class); }
    public function primaryContact() {
        return $this->hasOne(CustomerContact::class)
            ->where('is_primary', true)
            ->whereRaw('is_primary IS NOT FALSE');  // V1.2.10 兼容 PG boolean 绑定
    }
    public function devices(): HasMany { return $this->hasMany(CustomerDevice::class); }
    public function followUps(): HasMany { return $this->hasMany(FollowUpRecord::class); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
    public function serviceOrders(): HasMany { return $this->hasMany(ServiceOrder::class); }
    public function receivables(): HasMany { return $this->hasMany(Receivable::class); }
    public function maintenanceContracts(): HasMany { return $this->hasMany(MaintenanceContract::class); }
    public function invoiceInfos(): HasMany { return $this->hasMany(CustomerInvoiceInfo::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class); }
    public function warranties(): HasMany { return $this->hasMany(Warranty::class); }
    public function serviceOrderCount(): int { return $this->serviceOrders()->count(); }
    public function activeProjectsCount(): int { return $this->projects()->whereIn('status', ['in_progress', 'execution'])->count(); }

    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeOfCategory($query, string $cat) { return $query->where('category', $cat); }
}
