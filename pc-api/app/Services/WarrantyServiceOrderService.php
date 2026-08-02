<?php

namespace App\Services;

use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyServiceOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WarrantyServiceOrderService
{
    /**
     * @param array<string,mixed> $filters
     * @return array{items: LengthAwarePaginator, total: int}
     */
    public function listOrders(int $page, int $perPage, array $filters = []): array
    {
        $query = WarrantyServiceOrder::query()
            ->with([
                'warranty:id,warranty_no,project_id,customer_id,end_date',
                'warranty.project:id,name,project_no',
                'warranty.customer:id,name',
                'technician:id,name,phone',
                'creator:id,name',
            ])
            ->whereNull('deleted_at');

        $this->applyFilters($query, $filters);

        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $paginator = $query->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
        ];
    }

    public function getOrder(int $id): WarrantyServiceOrder
    {
        return WarrantyServiceOrder::with([
            'warranty:id,warranty_no,project_id,customer_id,device_id,start_date,end_date',
            'warranty.project:id,name,project_no,manager_id',
            'warranty.project.manager:id,name,email',
            'warranty.customer:id,name,address',
            'warranty.device:id,device_name,serial_number,install_location',
            'technician:id,name,phone,email',
            'creator:id,name',
            'canceller:id,name',
        ])
            ->whereNull('deleted_at')
            ->findOrFail($id);
    }

    public function createOrder(array $data, int $userId): WarrantyServiceOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            if (empty($data['warranty_id']) && empty($data['customer_id'])) {
                throw new \RuntimeException('质保单、客户至少需要提供一项');
            }

            $warranty = null;
            if (!empty($data['warranty_id'])) {
                $warranty = Warranty::whereNull('deleted_at')->find($data['warranty_id']);
                if (!$warranty) {
                    throw new \RuntimeException("质保期不存在: warranty_id={$data['warranty_id']}");
                }
            }
            if (!empty($data['technician_id']) && !User::where('id', (int) $data['technician_id'])->exists()) {
                throw new \RuntimeException("技工不存在: technician_id={$data['technician_id']}");
            }

            $hasTechnician = !empty($data['technician_id']);
            $status = $hasTechnician
                ? WarrantyServiceOrder::STATUS_ASSIGNED
                : WarrantyServiceOrder::STATUS_PENDING;

            $order = WarrantyServiceOrder::create([
                'order_no'       => $this->generateOrderNo(),
                'warranty_id'    => !empty($data['warranty_id']) ? (int) $data['warranty_id'] : null,
                'customer_id'    => $warranty ? (int) $warranty->customer_id : (int) ($data['customer_id'] ?? 0),
                'device_id'      => $warranty ? $warranty->device_id : ($data['device_id'] ?? null),
                'service_type'   => $data['service_type'] ?? 'repair',
                'priority'       => $data['priority'] ?? $data['urgency'] ?? 'normal',
                'title'          => $data['title'] ?? $data['fault_description'] ?? '质保服务单',
                'description'    => $data['description'] ?? $data['fault_description'] ?? '',
                'scheduled_date' => $data['scheduled_date'] ?? $data['scheduled_at'] ?? now()->toDateString(),
                'technician_id'  => $hasTechnician ? (int) $data['technician_id'] : null,
                'status'         => $status,
                'created_by'     => $userId,
            ]);

            return $order->fresh([
                'warranty:id,warranty_no',
                'warranty.project:id,name,project_no',
                'technician:id,name',
                'creator:id,name',
            ]);
        });
    }

    public function assignTechnician(int $id, int $technicianId, int $userId): WarrantyServiceOrder
    {
        return DB::transaction(function () use ($id, $technicianId, $userId) {
            $order = WarrantyServiceOrder::whereNull('deleted_at')->findOrFail($id);

            if ($order->status !== WarrantyServiceOrder::STATUS_PENDING && $order->status !== WarrantyServiceOrder::STATUS_ASSIGNED) {
                throw new \RuntimeException("只有 pending/assigned 状态可重派, 当前: {$order->status}");
            }
            if (!User::where('id', $technicianId)->exists()) {
                throw new \RuntimeException("技工不存在: technician_id={$technicianId}");
            }

            $order->update([
                'technician_id' => $technicianId,
                'status' => WarrantyServiceOrder::STATUS_ASSIGNED,
                'updated_by' => $userId,
            ]);

            return $order->fresh(['technician:id,name,phone']);
        });
    }

    public function startOrder(int $id, int $userId): WarrantyServiceOrder
    {
        return DB::transaction(function () use ($id, $userId) {
            $order = WarrantyServiceOrder::whereNull('deleted_at')->findOrFail($id);

            if ($order->status !== WarrantyServiceOrder::STATUS_ASSIGNED) {
                throw new \RuntimeException("只有 assigned 状态可开始, 当前: {$order->status}");
            }
            if (!$order->technician_id) {
                throw new \RuntimeException('未指派技工, 无法开始服务');
            }

            $order->update([
                'status' => WarrantyServiceOrder::STATUS_IN_PROGRESS,
                'updated_by' => $userId,
            ]);

            return $order->fresh();
        });
    }

    public function completeOrder(int $id, array $data, int $userId): WarrantyServiceOrder
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $order = WarrantyServiceOrder::whereNull('deleted_at')->findOrFail($id);

            if ($order->status !== WarrantyServiceOrder::STATUS_IN_PROGRESS) {
                throw new \RuntimeException("只有 in_progress 状态可完工, 当前: {$order->status}");
            }
            if (empty($data['result_notes'])) {
                throw new \RuntimeException('完工必须填写 result_notes (处理结果)');
            }

            $order->update([
                'status' => WarrantyServiceOrder::STATUS_COMPLETED,
                'result_notes' => $data['result_notes'],
                'customer_signature' => $data['customer_signature'] ?? null,
                'fee' => isset($data['fee']) ? (float) $data['fee'] : 0,
                'parts_cost' => isset($data['parts_cost']) ? (float) $data['parts_cost'] : 0,
                'charge_type' => $data['charge_type'] ?? null,
                'project_id' => isset($data['project_id']) ? (int) $data['project_id'] : null,
                'completed_date' => !empty($data['completed_date']) ? Carbon::parse($data['completed_date']) : now()->toDateString(),
                'updated_by' => $userId,
            ]);

            return $order->fresh([
                'technician:id,name,phone',
                'warranty:id,warranty_no',
                'project:id,name,project_no',
            ]);
        });
    }

    public function cancelOrder(int $id, string $reason, int $userId): WarrantyServiceOrder
    {
        return DB::transaction(function () use ($id, $reason, $userId) {
            $order = WarrantyServiceOrder::whereNull('deleted_at')->findOrFail($id);

            if (!in_array($order->status, [
                WarrantyServiceOrder::STATUS_PENDING,
                WarrantyServiceOrder::STATUS_ASSIGNED,
            ], true)) {
                throw new \RuntimeException("只有 pending/assigned 状态可取消, 当前: {$order->status}");
            }

            $order->update([
                'status' => WarrantyServiceOrder::STATUS_CANCELLED,
                'updated_by' => $userId,
            ]);

            return $order->fresh();
        });
    }

    public function getTechnicianStats(int $technicianId): array
    {
        $base = WarrantyServiceOrder::query()
            ->whereNull('deleted_at')
            ->where('technician_id', $technicianId);

        $pending = (clone $base)->where('status', WarrantyServiceOrder::STATUS_PENDING)->count();
        $assigned = (clone $base)->where('status', WarrantyServiceOrder::STATUS_ASSIGNED)->count();
        $inProgress = (clone $base)->where('status', WarrantyServiceOrder::STATUS_IN_PROGRESS)->count();
        $completed = (clone $base)->where('status', WarrantyServiceOrder::STATUS_COMPLETED)->count();
        $cancelled = (clone $base)->where('status', WarrantyServiceOrder::STATUS_CANCELLED)->count();

        $today = now()->toDateString();
        $todayScheduled = (clone $base)
            ->whereDate('scheduled_date', $today)
            ->whereIn('status', [
                WarrantyServiceOrder::STATUS_ASSIGNED,
                WarrantyServiceOrder::STATUS_IN_PROGRESS,
            ])
            ->count();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthCompleted = (clone $base)
            ->where('status', WarrantyServiceOrder::STATUS_COMPLETED)
            ->whereDate('completed_date', '>=', $monthStart)
            ->whereDate('completed_date', '<=', $monthEnd)
            ->count();

        $monthFee = (float) (clone $base)
            ->where('status', WarrantyServiceOrder::STATUS_COMPLETED)
            ->whereDate('completed_date', '>=', $monthStart)
            ->whereDate('completed_date', '<=', $monthEnd)
            ->sum('fee');

        return [
            'technician_id' => $technicianId,
            'pending' => $pending,
            'assigned' => $assigned,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'total' => $pending + $assigned + $inProgress + $completed + $cancelled,
            'today_scheduled' => $todayScheduled,
            'month_completed' => $monthCompleted,
            'month_fee' => round($monthFee, 2),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['warranty_id'])) {
            $query->where('warranty_id', (int) $filters['warranty_id']);
        }
        if (!empty($filters['technician_id'])) {
            $query->where('technician_id', (int) $filters['technician_id']);
        }
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $query->whereIn('status', $statuses);
        }
        if (!empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }
        if (!empty($filters['priority']) || !empty($filters['urgency'])) {
            $query->where('priority', $filters['priority'] ?? $filters['urgency']);
        }
        if (!empty($filters['scheduled_from'])) {
            $query->where('scheduled_date', '>=', $filters['scheduled_from']);
        }
        if (!empty($filters['scheduled_to'])) {
            $query->where('scheduled_date', '<=', $filters['scheduled_to']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . trim((string) $filters['keyword']) . '%';
            $query->where(function (Builder $q) use ($kw) {
                $q->where('order_no', 'like', $kw)
                    ->orWhere('title', 'like', $kw)
                    ->orWhere('description', 'like', $kw)
                    ->orWhere('result_notes', 'like', $kw);
            });
        }
    }

    private function generateOrderNo(): string
    {
        $today = now()->format('Ymd');
        $prefix = "WS-{$today}-";
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $count = (int) WarrantyServiceOrder::withTrashed()
                ->where('order_no', 'like', $prefix . '%')
                ->count();
            $seq = $count + 1 + $i;
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $exists = WarrantyServiceOrder::withTrashed()
                ->where('order_no', $candidate)
                ->exists();
            if (!$exists) {
                return $candidate;
            }
        }

        return $prefix . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
}
