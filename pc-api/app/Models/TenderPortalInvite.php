<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P1-7: 供应商门户一次性邀请 token
 */
class TenderPortalInvite extends Model
{
    protected $table = 'tender_portal_invites';

    protected $fillable = [
        'supplier_id', 'token', 'phone_suffix_hash',
        'ip', 'user_agent', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeValid($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}