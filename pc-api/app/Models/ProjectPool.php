<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPool extends Model
{
    use HasFactory;
    protected $table = 'project_pool';
    protected $fillable = ['pool_no', 'opportunity_id', 'name', 'customer_id', 'contract_amount', 'signed_at', 'status', 'related_project_id', 'notes'];
    protected $casts = ['contract_amount' => 'decimal:2', 'signed_at' => 'date'];

    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class, 'related_project_id'); }
}
