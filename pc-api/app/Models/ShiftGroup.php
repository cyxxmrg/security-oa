<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftGroup extends Model
{
    use HasFactory;
    protected $table = 'shift_groups';
    protected $fillable = ['name', 'code', 'leader_id', 'color', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function leader(): BelongsTo { return $this->belongsTo(User::class, 'leader_id'); }
    public function members(): HasMany { return $this->hasMany(ShiftGroupMember::class, 'group_id'); }
}
