<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftGroupMember extends Model
{
    use HasFactory;
    protected $table = 'shift_group_members';
    protected $fillable = ['group_id', 'user_id', 'joined_at'];
    protected $casts = ['joined_at' => 'date'];
    public function group(): BelongsTo { return $this->belongsTo(ShiftGroup::class, 'group_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
