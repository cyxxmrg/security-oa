<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesFollowUp extends Model
{
    use HasFactory;
    protected $table = 'sales_follow_ups';
    protected $fillable = ['target_type', 'target_id', 'contact_method', 'content', 'result', 'next_action', 'next_action_at', 'user_id'];
    protected $casts = ['next_action_at' => 'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attachments(): HasMany { return $this->hasMany(SalesFollowUpAttachment::class, 'follow_up_id'); }
}
