<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseApproval extends Model
{
    use HasFactory, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'target_type', 'target_id', 'title', 'applicant_id', 'applicant',
        'applied_at', 'status', 'approver_id', 'approved_at', 'approve_remark',
        'reason', 'amount',
    ];

    protected $casts = [
        'applied_at'  => 'datetime',
        'approved_at' => 'datetime',
        'amount'      => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('PA');
            }
        });
    }

    public function applicant(): BelongsTo { return $this->belongsTo(User::class, 'applicant_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
