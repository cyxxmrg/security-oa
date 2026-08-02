<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExpenseClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_no', 'user_id', 'category', 'total_amount', 'project_id',
        'description', 'status', 'approver_id', 'approved_at', 'paid_at', 'paid_amount', 'reject_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2',
        'approved_at' => 'datetime', 'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($claim) {
            if (empty($claim->claim_no)) {
                $count = ExpenseClaim::whereDate('created_at', today())->count() + 1;
                $claim->claim_no = 'EXP-' . date('Ymd') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function items(): HasMany { return $this->hasMany(ExpenseItem::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function approvals(): MorphMany { return $this->morphMany(ApprovalRecord::class, 'approvable'); }
}
