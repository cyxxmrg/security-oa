<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'start_date', 'end_date', 'days', 'reason', 'status', 'approver_id', 'approved_at', 'reject_reason'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'days' => 'decimal:1', 'approved_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function approvals(): MorphMany { return $this->morphMany(ApprovalRecord::class, 'approvable'); }
}
