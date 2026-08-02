<?php

namespace App\Rules;

use App\Models\ProjectBudget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BudgetNotExceeded implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $projectId = request()->input('project_id');
        if (!$projectId) {
            return;
        }

        $budget = ProjectBudget::where('project_id', $projectId)
            ->where('status', 'approved')
            ->latest('version')
            ->first();

        if (!$budget || (float) $budget->total_budget <= 0) {
            return;
        }

        $rate = (float) $budget->total_actual / (float) $budget->total_budget;
        if ($rate >= 1.0) {
            $fail("项目预算已超额（" . round($rate * 100) . "%），需财务总监特批才能继续提交");
        }
    }
}
