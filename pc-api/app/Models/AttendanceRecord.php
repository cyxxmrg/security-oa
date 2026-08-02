<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'date', 'clock_in', 'clock_in_location', 'clock_in_lat', 'clock_in_lng',
        'clock_out', 'clock_out_location', 'clock_out_lat', 'clock_out_lng',
        'status', 'work_hours', 'overtime_hours', 'project_id', 'remark',
    ];

    protected $casts = [
        'date' => 'date', 'clock_in_lat' => 'decimal:7', 'clock_in_lng' => 'decimal:7',
        'clock_out_lat' => 'decimal:7', 'clock_out_lng' => 'decimal:7',
        'work_hours' => 'decimal:1', 'overtime_hours' => 'decimal:1',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
