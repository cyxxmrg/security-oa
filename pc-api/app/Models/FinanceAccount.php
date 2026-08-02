<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceAccount extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'balance', 'bank_name', 'account_no', 'currency', 'status', 'remark'];

    protected $casts = ['balance' => 'decimal:2'];

    public function payments(): HasMany { return $this->hasMany(FinancePayment::class, 'account_id'); }
}
