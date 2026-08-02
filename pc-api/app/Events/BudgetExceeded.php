<?php

namespace App\Events;

use App\Models\ProjectBudget;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BudgetExceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProjectBudget $budget,
        public string $category,
        public float $rate,
    ) {}
}
