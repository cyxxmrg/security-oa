<?php

namespace App\Models;

use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receivable extends Model
{
    use HasFactory, HasDataScope;

    protected $fillable = ['customer_id', 'project_id', 'contract_id', 'amount', 'received_amount', 'remaining_amount', 'due_date', 'received_date', 'overdue_days', 'status', 'notes', 'source'];

    protected $casts = ['amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'due_date' => 'date', 'received_date' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
