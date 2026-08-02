<?php

namespace App\Models;

use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory, HasDataScope;

    protected $fillable = [
        'project_no', 'name', 'customer_id', 'type', 'stage', 'status', 'description',
        'budget_device', 'budget_material', 'budget_labor', 'budget_outsource', 'budget_other',
        'progress', 'manager_id', 'start_date', 'end_date', 'actual_end_date', 'priority',
    ];

    protected $casts = [
        'budget_device' => 'decimal:2', 'budget_material' => 'decimal:2',
        'budget_labor' => 'decimal:2', 'budget_outsource' => 'decimal:2', 'budget_other' => 'decimal:2',
        'start_date' => 'date', 'end_date' => 'date', 'actual_end_date' => 'date',
        'stage' => \App\Enums\ProjectStage::class, 'status' => 'string',
    ];

    // V1.2.12k: 合同金额 accessor 默认 append 到 JSON
    protected $appends = ['contract_amount', 'sales_contract_amount', 'purchase_contract_amount'];

    protected static function booted()
    {
        static::creating(function ($project) {
            if (empty($project->project_no)) {
                $count = Project::whereDate('created_at', today())->count() + 1;
                $project->project_no = 'PRJ-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function members(): BelongsToMany { return $this->belongsToMany(User::class, 'project_members')->withPivot('role', 'status')->withTimestamps(); }
    public function contract(): HasMany { return $this->hasMany(ProjectContract::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
    public function constructionLogs(): HasMany { return $this->hasMany(ConstructionLog::class); }
    public function materials(): HasMany { return $this->hasMany(ProjectMaterial::class); }
    public function settlement(): HasMany { return $this->hasMany(ProjectSettlement::class); }
    public function serviceOrders(): HasMany { return $this->hasMany(ServiceOrder::class); }
    public function devices(): HasMany { return $this->hasMany(CustomerDevice::class, 'project_id'); }
    public function budgets(): HasMany { return $this->hasMany(ProjectBudget::class); }
    public function budget(): HasOne { return $this->hasOne(ProjectBudget::class)->latestOfMany('id'); }
    public function actualCosts(): HasMany { return $this->hasMany(ProjectActualCost::class); }
    public function receivables(): HasMany { return $this->hasMany(Receivable::class); }
    public function warranties(): HasMany { return $this->hasMany(Warranty::class); }
    public function rectifications(): HasMany { return $this->hasMany(Rectification::class); }
    public function processInstances(): HasMany { return $this->hasMany(WorkProcess::class, 'project_id'); }
    public function commencementOrder(): HasOne { return $this->hasOne(ProjectCommencementOrder::class)->latestOfMany('id'); }
    public function settlements(): HasMany { return $this->hasMany(ProjectSettlement::class); }
    public function followUps(): HasMany { return $this->hasMany(SalesFollowUp::class, 'target_id')->where('target_type', 'project'); }

    public function getTotalBudgetAttribute(): float
    {
        return (float) ($this->budget_device + $this->budget_material + $this->budget_labor + $this->budget_outsource + $this->budget_other);
    }

    public function getTotalActualCostAttribute(): float
    {
        return (float) $this->actualCosts()->sum('amount');
    }

    /** 项目合同总额 (来自 project_contracts 实时求和) */
    public function getContractAmountAttribute(): float
    {
        return (float) $this->contract()->sum('contract_amount');
    }

    /** 销售合同总额 (type=sales) */
    public function getSalesContractAmountAttribute(): float
    {
        return (float) $this->contract()->where('type', 'sales')->sum('contract_amount');
    }

    /** 采购合同总额 (type=purchase) */
    public function getPurchaseContractAmountAttribute(): float
    {
        return (float) $this->contract()->where('type', 'purchase')->sum('contract_amount');
    }
}
