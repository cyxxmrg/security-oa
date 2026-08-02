<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePlan extends Model
{
    use HasFactory, HasDataScope, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'requirement_id', 'project_id', 'title', 'total_amount', 'plan_date',
        'priority', 'status', 'submitter_id', 'submitted_at',
        'approver_id', 'approved_at', 'approve_remark', 'remark', 'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'plan_date'    => 'date',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('PP');
            }
        });
    }

    public function requirement(): BelongsTo { return $this->belongsTo(PurchaseRequirement::class, 'requirement_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitter_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function contracts(): HasMany { return $this->hasMany(PurchaseContract::class, 'plan_id'); }
}
