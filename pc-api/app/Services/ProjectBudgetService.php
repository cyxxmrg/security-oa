<?php

namespace App\Services;

use App\Events\BudgetExceeded;
use App\Events\BudgetWarning;
use App\Models\ProjectActualCost;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetItem;
use Illuminate\Support\Facades\DB;

class ProjectBudgetService
{
    /** 4 大预算/实际成本分类 */
    private const CATEGORIES = ['material', 'labor', 'outsource', 'other'];

    /**
     * 生成预算编码 BUD-YYYY-NNN（按年全局递增）
     */
    public function generateCode(): string
    {
        $year = date('Y');
        $prefix = "BUD-{$year}-";

        $latest = ProjectBudget::where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('code');

        $next = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 创建草稿预算（按 category 汇总 budget 字段）
     */
    public function createBudget(int $projectId, array $data, int $userId): ProjectBudget
    {
        return DB::transaction(function () use ($projectId, $data, $userId) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $totals = $this->sumByCategory($items);

            $budget = ProjectBudget::create([
                'project_id'       => $projectId,
                'code'             => $this->generateCode(),
                'version'          => 1,
                'status'           => 'draft',
                'material_budget'  => $totals['material']  ?? 0,
                'labor_budget'     => $totals['labor']     ?? 0,
                'outsource_budget' => $totals['outsource'] ?? 0,
                'other_budget'     => $totals['other']     ?? 0,
                'total_budget'     => array_sum($totals),
                'created_by'       => $userId,
                'remark'           => $data['remark'] ?? null,
            ]);

            $this->updateBudgetItems($budget, $items);

            return $budget->fresh('items');
        });
    }

    /**
     * 更新明细（仅 draft 可调）
     */
    public function updateBudgetItems(ProjectBudget $budget, array $items): void
    {
        if ($budget->status !== 'draft') {
            throw new \RuntimeException('只有草稿状态的预算可编辑明细');
        }

        DB::transaction(function () use ($budget, $items) {
            $budget->items()->delete();

            $sort = 0;
            foreach ($items as $row) {
                $qty   = (float) ($row['quantity']       ?? 0);
                $price = (float) ($row['unit_price']     ?? 0);
                $amt   = isset($row['planned_amount']) && $row['planned_amount'] !== ''
                    ? (float) $row['planned_amount']
                    : round($qty * $price, 2);

                ProjectBudgetItem::create([
                    'budget_id'      => $budget->id,
                    'category'       => $row['category'] ?? 'other',
                    'item_name'      => $row['item_name'] ?? '',
                    'specification'  => $row['specification'] ?? null,
                    'unit'           => $row['unit'] ?? null,
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'planned_amount' => $amt,
                    'item_id'        => $row['item_id'] ?? null,
                    'item_type'      => $row['item_type'] ?? null,
                    'remark'         => $row['remark'] ?? null,
                    'sort_order'     => $row['sort_order'] ?? $sort++,
                ]);
            }

            $totals = $this->sumByCategory($items);
            $budget->update([
                'material_budget'  => $totals['material']  ?? 0,
                'labor_budget'     => $totals['labor']     ?? 0,
                'outsource_budget' => $totals['outsource'] ?? 0,
                'other_budget'     => $totals['other']     ?? 0,
                'total_budget'     => array_sum($totals),
            ]);
        });
    }

    /**
     * 审批：把同 project 其他 approved 改为 revised
     */
    public function approveBudget(ProjectBudget $budget, int $userId): ProjectBudget
    {
        return DB::transaction(function () use ($budget, $userId) {
            if ($budget->status !== 'draft') {
                throw new \RuntimeException('只有草稿状态的预算可审批');
            }

            ProjectBudget::where('project_id', $budget->project_id)
                ->where('status', 'approved')
                ->update(['status' => 'revised']);

            $budget->update([
                'status'      => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $budget->fresh();
        });
    }

    /**
     * 修订：基于已审批预算创建新 version 草稿
     */
    public function reviseBudget(ProjectBudget $oldBudget, array $newItems, int $userId): ProjectBudget
    {
        return DB::transaction(function () use ($oldBudget, $newItems, $userId) {
            $newVersion = ((int) $oldBudget->version) + 1;

            $newBudget = ProjectBudget::create([
                'project_id'       => $oldBudget->project_id,
                'code'             => $this->generateCode(),
                'version'          => $newVersion,
                'status'           => 'draft',
                'material_budget'  => 0,
                'labor_budget'     => 0,
                'outsource_budget' => 0,
                'other_budget'     => 0,
                'total_budget'     => 0,
                'created_by'       => $userId,
            ]);

            $this->updateBudgetItems($newBudget, $newItems);

            return $newBudget->fresh('items');
        });
    }

    /**
     * 记录实际成本（用 updateOrCreate 防重），并刷新预算/告警
     */
    public function recordActualCost(
        int $projectId,
        string $sourceType,
        int $sourceId,
        string $category,
        float $amount,
        string $costDate,
        string $description = '',
        array $metadata = []
    ): void {
        ProjectActualCost::updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id'   => $sourceId,
                'category'    => $category,
            ],
            [
                'project_id'  => $projectId,
                'amount'      => $amount,
                'cost_date'   => $costDate,
                'description' => $description,
                'metadata'    => $metadata,
            ]
        );

        $this->refreshActualCosts($projectId);
        $this->checkBudgetAlert($projectId);
    }

    /**
     * 聚合 project 的 actual 字段，写回当前已审批 budget
     */
    private function refreshActualCosts(int $projectId): void
    {
        $sums = ProjectActualCost::where('project_id', $projectId)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $m = (float) ($sums['material']  ?? 0);
        $l = (float) ($sums['labor']     ?? 0);
        $o = (float) ($sums['outsource'] ?? 0);
        $t = (float) ($sums['other']     ?? 0);

        $budget = ProjectBudget::where('project_id', $projectId)
            ->where('status', 'approved')
            ->latest('version')
            ->first();

        if ($budget) {
            $budget->update([
                'material_actual'  => $m,
                'labor_actual'     => $l,
                'outsource_actual' => $o,
                'other_actual'     => $t,
                'total_actual'     => $m + $l + $o + $t,
            ]);
        }
    }

    /**
     * 告警：90% ≤ rate < 1.0 warning；rate ≥ 1.0 exceeded
     */
    private function checkBudgetAlert(int $projectId): void
    {
        $budget = ProjectBudget::where('project_id', $projectId)
            ->where('status', 'approved')
            ->latest('version')
            ->first();

        if (!$budget || (float) $budget->total_budget <= 0) {
            return;
        }

        $fields = [
            'material'  => ['material_actual',  'material_budget'],
            'labor'     => ['labor_actual',     'labor_budget'],
            'outsource' => ['outsource_actual', 'outsource_budget'],
            'other'     => ['other_actual',     'other_budget'],
        ];

        $maxRate = 0.0;
        $peakCategory = 'total';
        $peakRate = (float) $budget->total_actual / (float) $budget->total_budget;

        foreach ($fields as $cat => [$actualCol, $budgetCol]) {
            $b = (float) $budget->{$budgetCol};
            $a = (float) $budget->{$actualCol};
            if ($b <= 0) {
                continue;
            }
            $rate = $a / $b;
            if ($rate > $maxRate) {
                $maxRate = $rate;
                $peakCategory = $cat;
            }
        }

        if ($peakRate >= 1.0) {
            BudgetExceeded::dispatch($budget->fresh(), $peakCategory, $peakRate);
        } elseif ($peakRate >= 0.9) {
            BudgetWarning::dispatch($budget->fresh(), $peakCategory, $peakRate);
        }
    }

    /**
     * 项目预算对比汇总（项目详情页用）
     */
    public function getSummary(int $projectId): array
    {
        $budget = ProjectBudget::where('project_id', $projectId)
            ->where('status', 'approved')
            ->latest('version')
            ->first();

        if (!$budget) {
            return [
                'has_budget'  => false,
                'version'     => null,
                'status'      => null,
                'total'       => ['budget' => 0, 'actual' => 0, 'rate' => 0, 'remaining' => 0],
                'material'    => ['budget' => 0, 'actual' => 0, 'rate' => 0, 'remaining' => 0],
                'labor'       => ['budget' => 0, 'actual' => 0, 'rate' => 0, 'remaining' => 0],
                'outsource'   => ['budget' => 0, 'actual' => 0, 'rate' => 0, 'remaining' => 0],
                'other'       => ['budget' => 0, 'actual' => 0, 'rate' => 0, 'remaining' => 0],
            ];
        }

        $rows = [];
        foreach (self::CATEGORIES as $cat) {
            $b = (float) $budget->{$cat . '_budget'};
            $a = (float) $budget->{$cat . '_actual'};
            $rows[$cat] = [
                'budget'    => $b,
                'actual'    => $a,
                'rate'      => $b > 0 ? round($a / $b, 4) : 0,
                'remaining' => round($b - $a, 2),
            ];
        }

        $tb = (float) $budget->total_budget;
        $ta = (float) $budget->total_actual;
        $rows['total'] = [
            'budget'    => $tb,
            'actual'    => $ta,
            'rate'      => $tb > 0 ? round($ta / $tb, 4) : 0,
            'remaining' => round($tb - $ta, 2),
        ];

        return [
            'has_budget' => true,
            'version'    => (int) $budget->version,
            'status'     => $budget->status,
            'code'       => $budget->code,
        ] + $rows;
    }

    /**
     * 把 items 列表按 category 汇总金额
     */
    private function sumByCategory(array $items): array
    {
        $sums = array_fill_keys(self::CATEGORIES, 0.0);
        foreach ($items as $row) {
            $cat = $row['category'] ?? 'other';
            if (!in_array($cat, self::CATEGORIES, true)) {
                $cat = 'other';
            }
            $amt = isset($row['planned_amount']) && $row['planned_amount'] !== ''
                ? (float) $row['planned_amount']
                : ((float) ($row['quantity'] ?? 0)) * ((float) ($row['unit_price'] ?? 0));
            $sums[$cat] += $amt;
        }
        foreach ($sums as $k => $v) {
            $sums[$k] = round($v, 2);
        }
        return $sums;
    }
}
