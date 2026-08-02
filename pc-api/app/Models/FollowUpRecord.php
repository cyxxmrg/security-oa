<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpRecord extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'user_id', 'type', 'content', 'next_follow_up_date', 'next_follow_up_note', 'attachments'];

    protected $casts = ['attachments' => 'array', 'next_follow_up_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
