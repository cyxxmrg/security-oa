<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WarrantyDeposit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.5 质保金管理服务
 *
 * 状态机:
 *  - held              (留置中, 未释放)
 *  - partial_released  (已部分释放)
 *  - fully_released    (全额释放)
 *  - forfeited         (违约没收)
 *
 * 关键字段:
 *  - deposit_amount    = contract_amount * deposit_rate / 100
 *  - release_amount    (累计已释放金额)
 *  - forfeit_amount    (累计已没收金额)
 *  - balance           = deposit_amount - release_amount - forfeit_amount
 */
class WarrantyDepositService
{
    /**
     * 质保金列表
     */
    public function listDeposits(int $page, int $perPage, array $filters = []): array
    {
        $query = WarrantyDeposit::query()
            ->with([
                'project:id,name,project_no',
                'customer:id,name',
                'approver:id,name',
                'creator:id,name',
            ])
            ->whereNull('deleted_at');

        $this->applyFilters($query, $filters);

        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $paginator = $query->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * 创建质保金
     */
    public function createDeposit(array $data, int $userId): WarrantyDeposit
    {
        return DB::transaction(function () use ($data, $userId) {
            if (empty($data['project_id'])
                || !Project::where('id', (int) $data['project_id'])->exists()) {
                throw new \RuntimeException("项目不存在: project_id=" . ($data['project_id'] ?? 'null'));
            }

            $contractAmount = round((float) ($data['contract_amount'] ?? 0), 2);
            $depositRate    = round((float) ($data['deposit_rate'] ?? 5.0), 2);

            if ($contractAmount < 0 || $depositRate < 0) {
                throw new \RuntimeException("合同金额/质保金比例不能为负");
            }

            // 若前端传了 deposit_amount, 用之; 否则自动算
            $depositAmount = isset($data['deposit_amount']) && $data['deposit_amount'] !== null
                ? round((float) $data['deposit_amount'], 2)
                : round($contractAmount * $depositRate / 100, 2);

            $deposit = WarrantyDeposit::create([
                'project_id'      => (int) $data['project_id'],
                'customer_id'     => (int) $data['customer_id'],
                'contract_amount' => $contractAmount,
                'deposit_rate'    => $depositRate,
                'deposit_amount'  => $depositAmount,
                'hold_date'       => isset($data['hold_date'])
                    ? Carbon::parse($data['hold_date'])->toDateString()
                    : now()->toDateString(),
                'release_date'    => $data['release_date'] ?? null,
                'status'          => WarrantyDeposit::STATUS_HELD,
                'release_amount'  => 0,
                'forfeit_amount'  => 0,
                'reason'          => $data['reason'] ?? null,
                'created_by'      => $userId,
            ]);

            return $deposit->fresh([
                'project:id,name,project_no',
                'customer:id,name',
                'creator:id,name',
            ]);
        });
    }

    /**
     * 部分释放
     */
    public function partialRelease(int $id, float $amount, string $reason, int $userId): WarrantyDeposit
    {
        return DB::transaction(function () use ($id, $amount, $reason, $userId) {
            $deposit = WarrantyDeposit::whereNull('deleted_at')->findOrFail($id);

            if (!in_array($deposit->status, [
                WarrantyDeposit::STATUS_HELD,
                WarrantyDeposit::STATUS_PARTIAL_RELEASED,
            ], true)) {
                throw new \RuntimeException(
                    "只有 held/partial_released 状态可释放, 当前: {$deposit->status}"
                );
            }
            if ($amount <= 0) {
                throw new \RuntimeException("释放金额必须 > 0");
            }

            $balance = $this->calcBalance($deposit);
            if ($amount > $balance + 0.001) {
                throw new \RuntimeException("释放金额超过可用余额 (balance={$balance})");
            }

            $newReleased = round((float) $deposit->release_amount + $amount, 2);
            $newStatus   = abs($newReleased - (float) $deposit->deposit_amount) < 0.01
                ? WarrantyDeposit::STATUS_FULLY_RELEASED
                : WarrantyDeposit::STATUS_PARTIAL_RELEASED;

            $deposit->update([
                'release_amount' => $newReleased,
                'status'         => $newStatus,
                'reason'         => $reason,
                'release_date'   => $deposit->release_date ?? now()->toDateString(),
                'approved_by'    => $newStatus === WarrantyDeposit::STATUS_FULLY_RELEASED
                    ? $userId
                    : $deposit->approved_by,
                'approved_at'    => $newStatus === WarrantyDeposit::STATUS_FULLY_RELEASED
                    ? ($deposit->approved_at ?? now())
                    : $deposit->approved_at,
            ]);

            return $deposit->fresh();
        });
    }

    /**
     * 全额释放
     */
    public function fullRelease(int $id, int $userId): WarrantyDeposit
    {
        return DB::transaction(function () use ($id, $userId) {
            $deposit = WarrantyDeposit::whereNull('deleted_at')->findOrFail($id);

            if (!in_array($deposit->status, [
                WarrantyDeposit::STATUS_HELD,
                WarrantyDeposit::STATUS_PARTIAL_RELEASED,
            ], true)) {
                throw new \RuntimeException(
                    "只有 held/partial_released 状态可全额释放, 当前: {$deposit->status}"
                );
            }

            $deposit->update([
                'release_amount' => (float) $deposit->deposit_amount,
                'status'         => WarrantyDeposit::STATUS_FULLY_RELEASED,
                'release_date'   => $deposit->release_date ?? now()->toDateString(),
                'approved_by'    => $deposit->approved_by ?: $userId,
                'approved_at'    => $deposit->approved_at ?: now(),
            ]);

            return $deposit->fresh();
        });
    }

    /**
     * 违约没收
     */
    public function forfeit(int $id, float $amount, string $reason, int $userId): WarrantyDeposit
    {
        return DB::transaction(function () use ($id, $amount, $reason, $userId) {
            $deposit = WarrantyDeposit::whereNull('deleted_at')->findOrFail($id);

            if (!in_array($deposit->status, [
                WarrantyDeposit::STATUS_HELD,
                WarrantyDeposit::STATUS_PARTIAL_RELEASED,
            ], true)) {
                throw new \RuntimeException(
                    "只有 held/partial_released 状态可没收, 当前: {$deposit->status}"
                );
            }
            if ($amount <= 0) {
                throw new \RuntimeException("没收金额必须 > 0");
            }

            $balance = $this->calcBalance($deposit);
            if ($amount > $balance + 0.001) {
                throw new \RuntimeException("没收金额超过可用余额 (balance={$balance})");
            }

            $newForfeited = round((float) $deposit->forfeit_amount + $amount, 2);
            $newBalance   = round((float) $deposit->deposit_amount - (float) $deposit->release_amount - $newForfeited, 2);
            $newStatus    = $newBalance < 0.01
                ? WarrantyDeposit::STATUS_FORFEITED
                : WarrantyDeposit::STATUS_PARTIAL_RELEASED;

            $deposit->update([
                'forfeit_amount' => $newForfeited,
                'reason'         => $reason,
                'status'         => $newStatus,
                'approved_by'    => $userId,
                'approved_at'    => $deposit->approved_at ?? now(),
            ]);

            return $deposit->fresh();
        });
    }

    /**
     * 余额计算
     */
    public function calcBalance(WarrantyDeposit $deposit): float
    {
        $used = (float) $deposit->release_amount + (float) $deposit->forfeit_amount;
        return round((float) $deposit->deposit_amount - $used, 2);
    }

    /**
     * 应用过滤条件
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $query->whereIn('status', $statuses);
        }
        if (!empty($filters['hold_from'])) {
            $query->where('hold_date', '>=', $filters['hold_from']);
        }
        if (!empty($filters['hold_to'])) {
            $query->where('hold_date', '<=', $filters['hold_to']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . trim((string) $filters['keyword']) . '%';
            $query->where('reason', 'like', $kw);
        }
    }
}
