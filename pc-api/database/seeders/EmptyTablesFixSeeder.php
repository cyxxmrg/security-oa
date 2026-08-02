<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 补全 6 张空表的演示数据
 *  - warranties (30)
 *  - warranty_deposits (20)
 *  - warranty_service_orders (30)
 *  - project_pool (20)
 *  - project_contracts (100)
 *  - contract_payment_nodes (~300)
 *
 * 用现存的 customers/projects/opportunities/users 做关联，不破坏已有数据
 */
class EmptyTablesFixSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 取可用 IDs
        $customerIds = DB::table('customers')->pluck('id')->toArray();
        $projectIds  = DB::table('projects')->pluck('id')->toArray();
        $userIds     = DB::table('users')->where('user_type', 'business')->pluck('id')->toArray();
        $opIds       = DB::table('opportunities')->pluck('id')->toArray();

        if (empty($customerIds) || empty($projectIds) || empty($userIds)) {
            $this->command->error('customers/projects/users 为空，无法 seed');
            return;
        }

        // ===== 1. project_contracts (100 条) =====
        if (DB::table('project_contracts')->count() == 0) {
            $rows = [];
            for ($i = 1; $i <= 100; $i++) {
                $projectId = $projectIds[($i - 1) % count($projectIds)];
                $start = now()->subDays(rand(30, 365));
                $rows[] = [
                    'project_id'      => $projectId,
                    'contract_no'     => 'PC' . now()->format('ymd') . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                    'contract_amount' => rand(50000, 800000) + 0.00,
                    'payment_method'  => collect(['lump_sum', 'installment', 'milestone'])->random(),
                    'contract_start'  => $start->toDateString(),
                    'contract_end'    => $start->copy()->addMonths(rand(3, 18))->toDateString(),
                    'status'          => collect(['draft', 'active', 'active', 'active', 'expired', 'terminated'])->random(),
                    'signed_at'       => $start->copy()->addDays(rand(1, 30)),
                    'notes'           => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            // chunk insert 避免 query 过大
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('project_contracts')->insert($chunk);
            }
            $this->command->info('project_contracts: inserted 100');
        }

        // ===== 2. contract_payment_nodes (~3 per contract = ~300) =====
        if (DB::table('contract_payment_nodes')->count() == 0) {
            $contracts = DB::table('project_contracts')->select('id', 'contract_amount', 'contract_start')->get();
            $rows = [];
            foreach ($contracts as $c) {
                $n = rand(2, 4);
                $totalAmount = (float)$c->contract_amount;
                $baseDate = \Carbon\Carbon::parse($c->contract_start);
                for ($j = 1; $j <= $n; $j++) {
                    $isLast = $j === $n;
                    $pct = $isLast ? 100 - array_sum(array_column(array_slice($rows, -($j - 1)), 'percentage')) : rand(20, 40);
                    if ($isLast && $pct < 5) $pct = 10;
                    $amount = round($totalAmount * $pct / 100, 2);
                    $statuses = ['pending', 'paid', 'paid', 'paid', 'pending', 'pending'];
                    $status = $statuses[array_rand($statuses)];
                    $rows[] = [
                        'contract_id'  => $c->id,
                        'name'         => collect(['首款', '进度款', '验收款', '质保金', '尾款', '预付款'])->random(),
                        'percentage'   => $pct,
                        'amount'       => $amount,
                        'planned_date' => $baseDate->copy()->addDays($j * 30)->toDateString(),
                        'actual_date'  => $status === 'paid' ? $baseDate->copy()->addDays($j * 30 + rand(-5, 10))->toDateString() : null,
                        'status'       => $status,
                        'paid_amount'  => $status === 'paid' ? $amount : 0,
                        'notes'        => null,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('contract_payment_nodes')->insert($chunk);
            }
            $this->command->info('contract_payment_nodes: inserted ' . count($rows));
        }

        // ===== 3. project_pool (20 条) =====
        if (DB::table('project_pool')->count() == 0) {
            $rows = [];
            for ($i = 1; $i <= 20; $i++) {
                $rows[] = [
                    'pool_no'         => 'PL' . now()->format('ymd') . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                    'opportunity_id'  => !empty($opIds) ? $opIds[array_rand($opIds)] : null,
                    'name'            => collect([
                        '弱电改造工程', '安防升级项目', '监控系统集成',
                        '门禁系统安装', '网络布线工程', '机房建设',
                        '智能楼宇改造', '周界防范工程', '视频监控扩容',
                    ])->random() . '-' . $i,
                    'customer_id'     => $customerIds[array_rand($customerIds)],
                    'contract_amount' => rand(80000, 600000) + 0.00,
                    'signed_at'       => now()->subDays(rand(1, 60))->toDateString(),
                    'status'          => collect(['pending', 'pending', 'pending', 'active', 'archived'])->random(),
                    'related_project_id' => null,
                    'notes'           => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            DB::table('project_pool')->insert($rows);
            $this->command->info('project_pool: inserted 20');
        }

        // ===== 4. warranties (30 条) =====
        if (DB::table('warranties')->count() == 0) {
            $rows = [];
            for ($i = 1; $i <= 30; $i++) {
                $start = now()->subDays(rand(60, 700));
                $months = collect([6, 12, 12, 24, 24, 36])->random();
                $end = $start->copy()->addMonths($months);
                $status = $end->isPast() ? 'expired' : (rand(1, 10) > 8 ? 'terminated' : 'active');
                $projectId = $projectIds[array_rand($projectIds)];
                // project 对应 customer: 反查
                $customerId = DB::table('projects')->where('id', $projectId)->value('customer_id') ?: $customerIds[array_rand($customerIds)];

                $rows[] = [
                    'uuid'           => (string)Str::uuid(),
                    'project_id'     => $projectId,
                    'customer_id'    => $customerId,
                    'device_id'      => null,
                    'warranty_no'    => 'WT' . now()->format('ymd') . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                    'warranty_type'  => collect(['basic', 'extended', 'premium'])->random(),
                    'start_date'     => $start->toDateString(),
                    'end_date'       => $end->toDateString(),
                    'period_months'  => $months,
                    'status'         => $status,
                    'amount'         => rand(0, 5000) + 0.00,
                    'terms'          => '质保范围：硬件故障 + 软件调试',
                    'notes'          => null,
                    'renewed_from_id' => null,
                    'created_by'     => $userIds[array_rand($userIds)],
                    'updated_by'     => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                    'deleted_at'     => null,
                ];
            }
            foreach (array_chunk($rows, 15) as $chunk) {
                DB::table('warranties')->insert($chunk);
            }
            $this->command->info('warranties: inserted 30');
        }

        // ===== 5. warranty_deposits (20 条) =====
        if (DB::table('warranty_deposits')->count() == 0) {
            $rows = [];
            for ($i = 1; $i <= 20; $i++) {
                $projectId = $projectIds[array_rand($projectIds)];
                $customerId = DB::table('projects')->where('id', $projectId)->value('customer_id') ?: $customerIds[array_rand($customerIds)];
                $contractAmount = rand(80000, 800000) + 0.00;
                $depositRate = collect([3, 5, 5, 5, 8, 10])->random();
                $depositAmount = round($contractAmount * $depositRate / 100, 2);
                $holdDate = now()->subDays(rand(30, 400));
                $status = collect(['held', 'held', 'held', 'partial_released', 'full_released', 'forfeited'])->random();

                $rows[] = [
                    'project_id'      => $projectId,
                    'customer_id'     => $customerId,
                    'contract_amount' => $contractAmount,
                    'deposit_rate'    => $depositRate,
                    'deposit_amount'  => $depositAmount,
                    'hold_date'       => $holdDate->toDateString(),
                    'release_date'    => in_array($status, ['full_released', 'partial_released']) ? $holdDate->copy()->addMonths(rand(6, 24))->toDateString() : null,
                    'status'          => $status,
                    'release_amount'  => $status === 'full_released' ? $depositAmount : ($status === 'partial_released' ? round($depositAmount / 2, 2) : 0),
                    'forfeit_amount'  => $status === 'forfeited' ? $depositAmount : 0,
                    'reason'          => $status === 'forfeited' ? '客户违约' : null,
                    'approved_by'     => $userIds[array_rand($userIds)],
                    'approved_at'     => $now,
                    'created_by'      => $userIds[array_rand($userIds)],
                    'created_at'      => $now,
                    'updated_at'      => $now,
                    'deleted_at'      => null,
                ];
            }
            DB::table('warranty_deposits')->insert($rows);
            $this->command->info('warranty_deposits: inserted 20');
        }

        // ===== 6. warranty_service_orders (30 条) =====
        if (DB::table('warranty_service_orders')->count() == 0) {
            $warrantyIds = DB::table('warranties')->pluck('id', 'project_id')->toArray();
            $rows = [];
            for ($i = 1; $i <= 30; $i++) {
                $projectId = array_rand($warrantyIds);
                $warrantyId = $warrantyIds[$projectId];
                $customerId = DB::table('projects')->where('id', $projectId)->value('customer_id') ?: $customerIds[array_rand($customerIds)];
                $scheduledDate = now()->subDays(rand(1, 180));
                $status = collect(['pending', 'assigned', 'in_progress', 'completed', 'completed', 'completed', 'cancelled'])->random();

                $rows[] = [
                    'warranty_id'   => $warrantyId,
                    'customer_id'   => $customerId,
                    'device_id'     => null,
                    'project_id'    => $projectId,
                    'order_no'      => 'WSO' . now()->format('ymd') . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                    'service_type'  => collect(['inspect', 'repair', 'clean', 'calibrate', 'replace'])->random(),
                    'priority'      => collect(['low', 'normal', 'normal', 'high', 'urgent'])->random(),
                    'title'         => collect([
                        '摄像头故障维修', '门禁系统调试', '硬盘录像机检查',
                        '网络设备巡检', '红外对焦调整', '监控电源更换',
                        '硬盘更换与数据迁移', '镜头清洁', '网络线路检修',
                    ])->random() . ' #' . $i,
                    'description'   => '客户报修，工程师上门服务',
                    'scheduled_date' => $scheduledDate->toDateString(),
                    'completed_date' => $status === 'completed' ? $scheduledDate->copy()->addDays(rand(0, 3))->toDateString() : null,
                    'technician_id' => $userIds[array_rand($userIds)],
                    'fee'           => rand(0, 500) + 0.00,
                    'parts_cost'    => rand(0, 300) + 0.00,
                    'status'        => $status,
                    'charge_type'   => collect(['warranty', 'paid', 'free'])->random(),
                    'result_notes'  => $status === 'completed' ? '已修复，客户确认' : null,
                    'customer_signature' => null,
                    'created_by'    => $userIds[array_rand($userIds)],
                    'completed_by'  => $status === 'completed' ? $userIds[array_rand($userIds)] : null,
                    'created_at'    => $scheduledDate,
                    'updated_at'    => $now,
                    'deleted_at'    => null,
                ];
            }
            foreach (array_chunk($rows, 15) as $chunk) {
                DB::table('warranty_service_orders')->insert($chunk);
            }
            $this->command->info('warranty_service_orders: inserted 30');
        }

        // 同步 sequence
        $this->syncSequences();
        $this->command->info('All empty tables filled successfully!');
    }

    private function syncSequences(): void
    {
        $tables = ['warranties', 'warranty_deposits', 'warranty_service_orders', 'project_pool', 'project_contracts', 'contract_payment_nodes'];
        foreach ($tables as $t) {
            $seq = $t . '_id_seq';
            $max = DB::table($t)->max('id') ?? 0;
            if ($max > 0) {
                DB::statement("SELECT setval('{$seq}', $max)");
            }
        }
    }
}