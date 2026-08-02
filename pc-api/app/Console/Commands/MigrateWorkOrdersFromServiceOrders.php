<?php

namespace App\Console\Commands;

use App\Models\ServiceOrder;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V0.5.6 B1 — 把老 service_orders 表中的维修工单迁移到 work_orders
 *
 * 用法:
 *   php artisan migrate:work-orders               # 实际迁移
 *   php artisan migrate:work-orders --dry-run     # 仅统计
 *   php artisan migrate:work-orders --revert      # 回滚 (按 migrated_to_work_order_id 反向)
 */
class MigrateWorkOrdersFromServiceOrders extends Command
{
    protected $signature = 'migrate:work-orders
        {--dry-run : 仅统计可迁移数量, 不实际执行}
        {--revert  : 回滚上次的迁移 (按 service_orders.migrated_to_work_order_id)}';

    protected $description = 'V0.5.6 — 从 service_orders 迁移到 work_orders (利旧+重新设计)';

    public function handle(): int
    {
        if ($this->option('revert')) {
            return $this->revert();
        }

        $this->info('开始迁移 service_orders → work_orders');
        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] 不会实际写入');
        }

        $query = ServiceOrder::query();
        if (DB::getSchemaBuilder()->hasColumn('service_orders', 'migrated_to_work_order_id')) {
            $query->whereNull('migrated_to_work_order_id');
        }
        $rows = $query->get();

        $this->line("找到 {$rows->count()} 条未迁移的 service_orders");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $so) {
            if ($this->option('dry-run')) {
                $this->line("  [DRY] WO 将创建: SO #{$so->id} ({$so->order_no}) → 客户 {$so->customer_id}");
                $created++;
                continue;
            }

            try {
                DB::transaction(function () use ($so, &$created) {
                    $year = date('Y');
                    $seq = (int) WorkOrder::where('code', 'like', "WO{$year}-%")
                        ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'WO[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
                        ->value('seq') + 1 + $created;
                    $code = sprintf('WO%s-%03d', $year, $seq);

                    $wo = WorkOrder::create([
                        'code'              => $code,
                        'customer_id'       => $so->customer_id,
                        'project_id'        => $so->project_id,
                        'equipment_id'      => $so->customer_device_id,
                        'contact_name'      => null, // 老表无此字段
                        'contact_phone'     => null,
                        'address'           => null,
                        'service_type'      => $this->mapServiceType($so->service_type),
                        'priority'          => $this->mapPriority($so->urgency),
                        'fault_description' => $so->fault_description ?? '从 service_order 迁移',
                        'equipment_brand'   => null,
                        'equipment_model'   => null,
                        'serial_no'         => null,
                        'status'            => $this->mapStatus($so->status),
                        'assigned_to'       => $so->assigned_to,
                        'started_at'        => $so->started_at,
                        'completed_at'      => $so->completed_at,
                        'result_notes'      => $so->review,
                        'created_by'        => $so->created_by,
                        'created_at'        => $so->created_at,
                        'updated_at'        => $so->updated_at,
                        'is_billable'       => false,
                        'migrated_from_service_order_id' => $so->id,
                    ]);

                    DB::table('service_orders')
                        ->where('id', $so->id)
                        ->update([
                            'migrated_to_work_order_id' => $wo->id,
                            'migrated_at' => now(),
                        ]);

                    $created++;
                    $this->line("  ✓ SO #{$so->id} → {$wo->code}");
                });
            } catch (\Throwable $e) {
                \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
                $errors++;
                $this->error("  ✗ SO #{$so->id} 失败: " . $e->getMessage());
            }
        }

        $this->info("完成: 创建 {$created} / 跳过 {$skipped} / 失败 {$errors}");
        return $errors > 0 ? 1 : 0;
    }

    private function revert(): int
    {
        $this->warn('回滚迁移: 删除 work_orders 中 migrated_from_service_order_id 不为空的记录');

        $wos = WorkOrder::whereNotNull('migrated_from_service_order_id')->get();
        $count = 0;
        foreach ($wos as $wo) {
            $wo->delete();
            DB::table('service_orders')
                ->where('id', $wo->migrated_from_service_order_id)
                ->update(['migrated_to_work_order_id' => null, 'migrated_at' => null]);
            $count++;
        }
        $this->info("回滚 {$count} 条 work_orders");
        return 0;
    }

    private function mapServiceType(?string $v): string
    {
        return match ($v) {
            'on_site', '上门' => 'on_site',
            'remote'          => 'remote',
            default           => 'in_store',
        };
    }

    private function mapPriority($urgency): string
    {
        if (is_object($urgency) && property_exists($urgency, 'value')) $urgency = $urgency->value;
        return match ($urgency) {
            'low'      => 'low',
            'urgent', 'critical' => 'urgent',
            'high'     => 'high',
            default    => 'medium',
        };
    }

    private function mapStatus($status): string
    {
        if (is_object($status) && property_exists($status, 'value')) $status = $status->value;
        return match ($status) {
            'pending'   => 'pending',
            'assigned'  => 'assigned',
            'in_progress', 'processing' => 'in_progress',
            'completed', 'confirmed'    => 'resolved',
            'cancelled' => 'cancelled',
            default     => 'pending',
        };
    }
}
