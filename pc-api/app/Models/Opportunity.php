<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use HasFactory;
    protected $table = 'opportunities';
    protected $fillable = [
        'opp_no', 'name', 'customer_id', 'lead_id', 'referrer_id', 'type', 'estimated_amount', 'expected_sign_date',
        'stage', 'probability', 'sales_id', 'presale_id', 'competitor', 'lost_reason',
        'project_id', 'pool_id', 'last_contact_at', 'next_action', 'next_action_at', 'notes'
    ];
    protected $casts = ['estimated_amount' => 'decimal:2', 'expected_sign_date' => 'date', 'last_contact_at' => 'datetime', 'next_action_at' => 'date'];

    protected static function booted()
    {
        static::creating(function ($opp) {
            if (empty($opp->opp_no)) {
                $opp->opp_no = 'OPP-' . date('YmdHis') . substr((string) microtime(true), -4) . random_int(100, 999);
            }
        });
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function referrer(): BelongsTo { return $this->belongsTo(Referrer::class, 'referrer_id'); }
    public function sales(): BelongsTo { return $this->belongsTo(User::class, 'sales_id'); }
    public function presale(): BelongsTo { return $this->belongsTo(User::class, 'presale_id'); }
    public function quotations(): HasMany { return $this->hasMany(Quotation::class); }
    public function followUps(): HasMany { return $this->hasMany(SalesFollowUp::class, 'target_id')->where('target_type', 'opp'); }
}
