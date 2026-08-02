<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'overtime_date', 'start_time', 'end_time', 'hours', 'reason', 'compensation_type', 'status', 'approver_id', 'approved_at', 'timesheet_leave_hours'];

    protected $casts = ['overtime_date' => 'date', 'hours' => 'decimal:1', 'approved_at' => 'datetime', 'timesheet_leave_hours' => 'decimal:1'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
