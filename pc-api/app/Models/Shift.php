<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;
    protected $table = 'shifts';
    protected $fillable = [
        'name', 'code', 'start_time', 'end_time', 'late_threshold_minutes',
        'early_leave_threshold_minutes', 'work_hours', 'color', 'is_overnight',
        'is_active', 'is_default', 'sort_order', 'remark',
    ];
    protected $casts = [
        'is_overnight' => 'boolean', 'is_active' => 'boolean', 'is_default' => 'boolean',
        'late_threshold_minutes' => 'integer', 'early_leave_threshold_minutes' => 'integer',
        'work_hours' => 'decimal:1', 'sort_order' => 'integer',
    ];
    public function schedules(): HasMany { return $this->hasMany(Schedule::class); }
}
