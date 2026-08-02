<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Project;
use App\Models\User;
use App\Models\Warranty;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.5 质保期管理服务
 *
 * 关键流程:
 *  - createWarranty: 自动生成 warranty_no (WY-YYYYMMDD-XXXX) + 自动算 end_date
 *  - renewWarranty:  复制旧单为新单,旧单置 renewed + renewed_from_id 关联
 *  - scanExpiringWarranties: 找出 withinDays 内到期的 active 记录
 *  - markExpiredWarranties:  把已过期但仍 active 的批量更新为 expired
 *
 * 状态机:
 *  - 主线: active → expiring → expired
 *  - 分支: active → renewed (被新单替代)
 *           active → terminated (人工终止)
 */
class WarrantyService
{
    /**
     * 质保期列表 (分页 + 多条件过滤)
     *
     * @param  array<string,mixed>  $filters
     * @return array{items: LengthAwarePaginator, total: int}
     */
    public function listWarranties(int $page, int $perPage, array $filters = []): array
    {
        $query = Warranty::query()
            ->with([
                'project:id,name,project_no,manager_id',
                'customer:id,name',
                'device:id,device_name,serial_number',
                'creator:id,name',
                'renewedFrom:id,warranty_no,start_date,end_date',
            ])
            ->whereNull('deleted_at');

        $this->applyFilters($query, $filters);

        // 默认按 end_date 升序 (快到期的在前)
        $sortBy  = $filters['sort_by']  ?? 'end_date';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        if (!in_array($sortBy, ['created_at', 'end_date', 'start_date'], true)) {
            $sortBy = 'end_date';
        }
        $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';

        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $paginator = $query->orderBy($sortBy, $sortDir)
            ->paginate(perPage: $perPage, page: $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * 质保期详情 (含全部业务关联)
     */
    public function getWarranty(int $id): Warranty
    {
        return Warranty::with([
            'project:id,name,project_no,manager_id,customer_id',
            'project.manager:id,name,email',
            'customer:id,name',  // V0.4.7: customers 表没 phone 列, 删
            'device:id,device_name,serial_number,install_location',
            'creator:id,name',
            'updater:id,name',
            'renewedFrom:id,warranty_no,start_date,end_date,status',
            'renewals:id,warranty_no,start_date,end_date,status,created_at',
            'serviceOrders:id,order_no,status,technician_id,scheduled_date,completed_date',
            'serviceOrders.technician:id,name',
        ])
            ->whereNull('deleted_at')
            ->findOrFail($id);
    }

    /**
     * 创建质保期
     *
     * 自动:
     *  - 生成 warranty_no (WY-YYYYMMDD-XXXX, 全局唯一, 软删不计)
     *  - 计算 end_date = start_date + period_months (月)
     *  - 默认 status = active
     */
    public function createWarranty(array $data, int $userId): Warranty
    {
        return DB::transaction(function () use ($data, $userId) {
            // 0) 验证外键存在 (避免脏数据)
            $this->assertReferences($data);

            $startDate  = Carbon::parse($data['start_date'])->startOfDay();
            $periodMons = max(1, (int) ($data['period_months'] ?? 12));
            $endDate    = $startDate->copy()->addMonthsNoOverflow($periodMons)->endOfDay();

            $warranty = Warranty::create([
                'warranty_no'    => $this->generateWarrantyNo(),
                'project_id'     => (int) $data['project_id'],
                'customer_id'    => (int) $data['customer_id'],
                'device_id'      => isset($data['device_id']) ? (int) $data['device_id'] : null,
                'start_date'     => $startDate->toDateString(),
                'end_date'       => $endDate->toDateString(),
                'period_months'  => $periodMons,
                'warranty_type'  => $data['warranty_type'] ?? 'basic',
                'coverage_scope' => $data['coverage_scope'] ?? null,
                'terms'          => $data['terms'] ?? null,
                'status'         => Warranty::STATUS_ACTIVE,
                'remarks'        => $data['remarks'] ?? null,
                'created_by'     => $userId,
            ]);

            return $warranty->fresh([
                'project:id,name,project_no',
                'customer:id,name',
                'device:id,device_name,serial_number',
                'creator:id,name',
            ]);
        });
    }

    /**
     * 更新质保期 (仅允许改非状态字段; 状态走 renew/terminate)
     */
    public function updateWarranty(int $id, array $data, int $userId): Warranty
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $warranty = Warranty::whereNull('deleted_at')->findOrFail($id);

            if ($warranty->status === Warranty::STATUS_RENEWED
                || $warranty->status === Warranty::STATUS_TERMINATED
                || $warranty->status === Warranty::STATUS_EXPIRED) {
                throw new \RuntimeException(
                    "质保期状态为 {$warranty->status}, 不可直接修改"
                );
            }

            // 保护: warranty_no / status / renewed_from_id 不允许外部覆盖
            unset(
                $data['warranty_no'],
                $data['status'],
                $data['renewed_from_id'],
                $data['created_by'],
            );

            // 若更新 start_date/period_months, 重新算 end_date
            if (isset($data['start_date']) || isset($data['period_months'])) {
                $newStart = isset($data['start_date'])
                    ? Carbon::parse($data['start_date'])->startOfDay()
                    : Carbon::parse($warranty->start_date)->startOfDay();
                $newMon   = isset($data['period_months'])
                    ? (int) $data['period_months']
                    : (int) $warranty->period_months;
                $data['end_date'] = $newStart->copy()
                    ->addMonthsNoOverflow(max(1, $newMon))
                    ->endOfDay()
                    ->toDateString();
            }

            $data['updated_by'] = $userId;
            $warranty->update($data);

            return $warranty->fresh([
                'project:id,name,project_no',
                'customer:id,name',
                'device:id,device_name,serial_number',
                'creator:id,name',
                'updater:id,name',
            ]);
        });
    }

    /**
     * 续期
     *
     * 行为:
     *  1. 旧单 status=renewed, 记录 renewed_at / renewed_to_id
     *  2. 新建一条 active 记录, start_date=旧.end_date+1d, end_date=新.start_date+extendMonths
     *  3. 新单 renewed_from_id 指向旧单
     */
    public function renewWarranty(int $id, int $extendMonths, int $userId): Warranty
    {
        return DB::transaction(function () use ($id, $extendMonths, $userId) {
            $old = Warranty::whereNull('deleted_at')->findOrFail($id);

            if ($old->status !== Warranty::STATUS_ACTIVE) {
                throw new \RuntimeException(
                    "只有 active 状态的质保期可续期, 当前: {$old->status}"
                );
            }

            $extendMonths = max(1, $extendMonths);
            $newStart = Carbon::parse($old->end_date)
                ->startOfDay()
                ->addDay();
            $newEnd   = $newStart->copy()
                ->addMonthsNoOverflow($extendMonths)
                ->endOfDay();

            $new = Warranty::create([
                'warranty_no'    => $this->generateWarrantyNo(),
                'project_id'     => $old->project_id,
                'customer_id'    => $old->customer_id,
                'device_id'      => $old->device_id,
                'start_date'     => $newStart->toDateString(),
                'end_date'       => $newEnd->toDateString(),
                'period_months'  => $extendMonths,
                'warranty_type'  => $old->warranty_type,
                'coverage_scope' => $old->coverage_scope,
                'terms'          => $old->terms,
                'status'         => Warranty::STATUS_ACTIVE,
                'renewed_from_id'=> $old->id,
                'remarks'        => $old->remarks,
                'created_by'     => $userId,
            ]);

            $old->update([
                'status'        => Warranty::STATUS_RENEWED,
                'renewed_to_id' => $new->id,
                'renewed_at'    => now(),
                'updated_by'    => $userId,
            ]);

            return $new->fresh([
                'project:id,name,project_no',
                'customer:id,name',
                'device:id,device_name,serial_number',
                'creator:id,name',
                'renewedFrom:id,warranty_no,start_date,end_date',
            ]);
        });
    }

    /**
     * 终止质保期
     */
    public function terminateWarranty(int $id, string $reason, int $userId): Warranty
    {
        return DB::transaction(function () use ($id, $reason, $userId) {
            $warranty = Warranty::whereNull('deleted_at')->findOrFail($id);

            if (!in_array($warranty->status, [
                Warranty::STATUS_ACTIVE,
                Warranty::STATUS_EXPIRING,
            ], true)) {
                throw new \RuntimeException(
                    "只有 active/expiring 状态可终止, 当前: {$warranty->status}"
                );
            }

            $warranty->update([
                'status'           => Warranty::STATUS_TERMINATED,
                'terminated_at'    => now(),
                'terminated_reason'=> $reason,
                'updated_by'       => $userId,
            ]);

            return $warranty->fresh();
        });
    }

    /**
     * 扫描即将到期的质保期 (withinDays 内)
     *
     * @return array<int> warranty id 列表
     */
    public function scanExpiringWarranties(int $withinDays = 30): array
    {
        $today    = now()->startOfDay()->toDateString();
        $deadline = now()->startOfDay()
            ->addDays(max(1, $withinDays))
            ->toDateString();

        return Warranty::query()
            ->whereNull('deleted_at')
            ->whereIn('status', [Warranty::STATUS_ACTIVE, Warranty::STATUS_EXPIRING])
            ->where('end_date', '>=', $today)
            ->where('end_date', '<=', $deadline)
            ->orderBy('end_date')
            ->pluck('id')
            ->all();
    }

    /**
     * 批量把已过期但仍 active/expiring 的更新为 expired
     *
     * 供 Console 调度调用
     *
     * @return int 更新的行数
     */
    public function markExpiredWarranties(): int
    {
        $today = now()->startOfDay()->toDateString();

        return Warranty::query()
            ->whereNull('deleted_at')
            ->whereIn('status', [Warranty::STATUS_ACTIVE, Warranty::STATUS_EXPIRING])
            ->where('end_date', '<', $today)
            ->update([
                'status'     => Warranty::STATUS_EXPIRED,
                'expired_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * 应用过滤条件
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }
        if (!empty($filters['device_id'])) {
            $query->where('device_id', (int) $filters['device_id']);
        }
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $query->whereIn('status', $statuses);
        }
        if (!empty($filters['warranty_type'])) {
            $query->where('warranty_type', $filters['warranty_type']);
        }
        if (!empty($filters['expired_within_days'])) {
            $days = max(1, (int) $filters['expired_within_days']);
            $deadline = now()->startOfDay()->addDays($days)->toDateString();
            $query->where('end_date', '>=', now()->startOfDay()->toDateString())
                ->where('end_date', '<=', $deadline);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . trim((string) $filters['keyword']) . '%';
            $query->where(function (Builder $q) use ($kw) {
                $q->where('warranty_no', 'like', $kw)
                    ->orWhere('remarks', 'like', $kw);
            });
        }
    }

    /**
     * 验证外键 (软删项目/客户/设备允许)
     */
    private function assertReferences(array $data): void
    {
        if (!empty($data['project_id'])
            && !Project::where('id', (int) $data['project_id'])->exists()) {
            throw new \RuntimeException("项目不存在: project_id={$data['project_id']}");
        }
        if (!empty($data['customer_id'])
            && !Customer::where('id', (int) $data['customer_id'])->exists()) {
            throw new \RuntimeException("客户不存在: customer_id={$data['customer_id']}");
        }
        if (!empty($data['device_id'])
            && !CustomerDevice::where('id', (int) $data['device_id'])->exists()) {
            throw new \RuntimeException("设备不存在: device_id={$data['device_id']}");
        }
    }

    /**
     * 生成 warranty_no: WY-YYYYMMDD-XXXX
     * - 不与已存在(含软删)冲突
     * - 同日递增 4 位序号
     */
    private function generateWarrantyNo(): string
    {
        $today  = now()->format('Ymd');
        $prefix = "WY-{$today}-";
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $count = (int) Warranty::withTrashed()
                ->where('warranty_no', 'like', $prefix . '%')
                ->count();
            $seq = $count + 1 + $i;
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $exists = Warranty::withTrashed()
                ->where('warranty_no', $candidate)
                ->exists();
            if (!$exists) {
                return $candidate;
            }
        }

        // 极端兜底: 加 random 后缀
        return $prefix . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
}
