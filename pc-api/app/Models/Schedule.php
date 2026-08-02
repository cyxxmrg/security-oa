<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;
    protected $table = 'schedules';
    protected $fillable = ['user_id', 'group_id', 'shift_id', 'date', 'status', 'note', 'created_by'];
    protected $casts = ['date' => 'date'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function group(): BelongsTo { return $this->belongsTo(ShiftGroup::class, 'group_id'); }
    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
