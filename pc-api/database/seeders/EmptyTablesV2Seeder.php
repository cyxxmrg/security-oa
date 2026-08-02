<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * V1.2.9u 补全 13 张空表
 *  - construction_teams (8) + construction_team_members (36)
 *  - construction_logs (50)  (incl. 10 rectification logs)
 *  - work_processes (100)
 *  - process_templates (20)
 *  - process_instances (50) + process_inspections (30)
 *  - vehicle_insurance (30)
 *  - vehicle_maintenance_records (20)
 *  - fuel_cards (6) + fuel_card_recharges (20)
 *  - finance_invoices (30)
 *  - notifications (200)
 *  - work_orders (补到 100)
 *  - repair_orders (补到 50)
 *
 * 用现存的 customers/projects/users/devices 做关联
 */
class EmptyTablesV2Seeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 取 IDs
        $customerIds = DB::table('customers')->pluck('id')->toArray();
        $projectIds  = DB::table('projects')->pluck('id')->toArray();
        $userIds     = DB::table('users')->where('user_type', 'business')->pluck('id')->toArray();
        $vehicleIds  = DB::table('vehicles')->pluck('id')->toArray();
        $deviceIds   = DB::table('customer_devices')->pluck('id')->toArray();

        if (empty($customerIds) || empty($projectIds) || empty($userIds)) {
            $this->command->error('customers/projects/users 为空，无法 seed');
            return;
        }
        $this->command->info('Starting EmptyTablesV2Seeder...');

        // ===== 1. construction_teams (8) =====
        if (DB::table('construction_teams')->count() == 0) {
            $teamTypes = ['internal', 'outsource'];
            $statuses = ['active', 'active', 'active', 'inactive', 'active'];
            $specialties = ['视频监控', '门禁系统', '防盗报警', '网络布线', '综合布线', '系统集成', '弱电工程', '智能楼宇'];
            $rows = [];
            for ($i = 1; $i <= 8; $i++) {
                $leader = $userIds[array_rand($userIds)];
                $rows[] = [
                    'project_id'  => $projectIds[array_rand($projectIds)],
                    'team_name'   => "施工{$i}组",
                    'team_type'   => $teamTypes[array_rand($teamTypes)],
                    'leader_user_id' => $leader,
                    'leader_name' => "施工队长{$i}",
                    'leader_phone' => '138' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'member_count' => random_int(3, 8),
                    'specialty'   => $specialties[array_rand($specialties)],
                    'status'      => $statuses[array_rand($statuses)],
                    'created_by'  => $leader,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
            DB::table('construction_teams')->insert($rows);
            $this->command->info("  ✓ construction_teams 8 rows");
        }

        // ===== 2. construction_team_members (36) =====
        if (DB::table('construction_team_members')->count() == 0) {
            $teamIds = DB::table('construction_teams')->pluck('id')->toArray();
            $roles = ['foreman', 'worker', 'worker', 'worker', 'electrician', 'welder', 'safety_officer'];
            $rows = [];
            for ($i = 0; $i < 36; $i++) {
                $rows[] = [
                    'team_id'   => $teamIds[array_rand($teamIds)],
                    'user_id'   => $userIds[array_rand($userIds)],
                    'name'      => '施工员' . ($i + 1),
                    'phone'     => '139' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'role'      => $roles[array_rand($roles)],
                    'id_number' => '330206' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'join_date' => $now->copy()->subDays(random_int(30, 365))->toDateString(),
                    'status'    => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('construction_team_members')->insert($rows);
            $this->command->info("  ✓ construction_team_members 36 rows");
        }

        // ===== 3. work_processes (100) =====
        if (DB::table('work_processes')->count() == 0) {
            $procNames = ['现场勘察', '方案设计', '设备采购', '线缆敷设', '设备安装', '系统调试', '客户培训', '验收交付', '维护保养', '故障排查'];
            $rows = [];
            for ($i = 0; $i < 100; $i++) {
                $rows[] = [
                    'project_id'      => $projectIds[array_rand($projectIds)],
                    'name'            => $procNames[array_rand($procNames)] . ' - ' . ($i + 1),
                    'sequence'        => ($i % 10) + 1,
                    'description'     => '标准施工工序：' . $procNames[array_rand($procNames)] . '，按图施工、确保质量。',
                    'estimated_hours' => random_int(2, 24) + 0.5,
                    'status'          => 'active',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            DB::table('work_processes')->insert($rows);
            $this->command->info("  ✓ work_processes 100 rows");
        }

        // ===== 4. construction_logs (50) - 包含 10 个整改工单 =====
        if (DB::table('construction_logs')->count() == 0) {
            $weathers = ['晴', '多云', '阴', '小雨', '阵雨', '大风'];
            $rows = [];
            for ($i = 0; $i < 50; $i++) {
                $isRect = $i < 10;
                $start = $now->copy()->subDays(30);
                $rows[] = [
                    'project_id'     => $projectIds[array_rand($projectIds)],
                    'user_id'        => $userIds[array_rand($userIds)],
                    'work_date'      => $start->copy()->addDays(random_int(0, 29))->toDateString(),
                    'weather'        => $weathers[array_rand($weathers)],
                    'weather_condition' => $weathers[array_rand($weathers)],
                    'content'        => $isRect ? '整改内容：' . ['线缆重新整理', '设备位置调整', '防水处理加固', '标识牌补全', '接地电阻复测'][array_rand([0,1,2,3,4])] : '正常施工：' . ['完成设备安装', '敷设光缆 200m', '机柜固定', '标签打印', '电源接通'][array_rand([0,1,2,3,4])],
                    'problems'       => random_int(0, 4) === 0 ? '部分线缆接头松动' : null,
                    'solutions'      => random_int(0, 4) === 0 ? '重新压接水晶头并测试' : null,
                    'work_hours'     => random_int(4, 12) + 0.5,
                    'worker_count'   => random_int(2, 6),
                    'progress_percentage' => random_int(50, 100),
                    'status'         => $isRect ? 'rectified' : 'submitted',
                    'is_rectification' => $isRect,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            DB::table('construction_logs')->insert($rows);
            $this->command->info("  ✓ construction_logs 50 rows (含 10 整改)");
        }

        // ===== 5. process_templates (20) =====
        if (DB::table('process_templates')->count() == 0) {
            $industries = ['security', 'building', 'transport', 'energy', 'industrial'];
            $templates = [
                ['视频监控', ['现场勘察', '方案设计', '摄像头安装', '线缆敷设', '系统调试', '验收交付']],
                ['门禁系统', ['方案设计', '读卡器安装', '控制器配置', '权限设置', '系统调试', '培训交付']],
                ['防盗报警', ['探测器安装', '报警主机配置', '信号传输', '联动测试', '验收交付']],
                ['网络布线', ['现场勘察', '桥架安装', '线缆敷设', '配线架端接', '测试验收']],
                ['综合弱电', ['需求分析', '系统设计', '设备采购', '施工安装', '系统集成', '验收交付']],
            ];
            $rows = [];
            $seq = 0;
            foreach ($industries as $industry) {
                foreach ($templates as [$category, $steps]) {
                    foreach ($steps as $idx => $step) {
                        $seq++;
                        // 唯一 code: industry + 序号
                        $rows[] = [
                            'industry' => $industry,
                            'category' => $category,
                            'code' => sprintf('%s-%s-%03d', $industry, substr(md5($step), 0, 6), $seq),
                            'name' => $step,
                            'description' => $step . ' - 标准工艺流程',
                            'standard_duration_days' => random_int(1, 7),
                            'standard_man_hours' => random_int(4, 40),
                            'required_qualifications' => json_encode(['电工证', '高空作业证']),
                            'safety_requirements' => '佩戴安全帽、系安全带、设置警戒区',
                            'quality_checkpoints' => json_encode(['材料规格', '施工工艺', '测试验收']),
                            'acceptance_criteria' => json_encode(['通过验收', '客户签字']),
                            'sort_order' => $idx,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
            DB::table('process_templates')->insert($rows);
            $this->command->info("  ✓ process_templates " . count($rows) . " rows");
        }

        // ===== 6. process_instances (50) =====
        if (DB::table('process_instances')->count() == 0) {
            $templateIds = DB::table('process_templates')->pluck('id')->toArray();
            $statuses = ['pending', 'in_progress', 'in_progress', 'completed', 'completed', 'accepted'];
            $rows = [];
            for ($i = 0; $i < 50; $i++) {
                $start = $now->copy()->subDays(random_int(1, 60));
                $status = $statuses[array_rand($statuses)];
                $rows[] = [
                    'project_id' => $projectIds[array_rand($projectIds)],
                    'template_id' => $templateIds[array_rand($templateIds)],
                    'code' => 'PI-' . $now->format('Ymd') . str_pad((string)($i+1), 4, '0', STR_PAD_LEFT),
                    'name' => '工序实例 #' . ($i + 1),
                    'sequence' => random_int(1, 6),
                    'planned_start_date' => $start->toDateString(),
                    'planned_end_date' => $start->copy()->addDays(random_int(1, 7))->toDateString(),
                    'actual_start_date' => $start->toDateString(),
                    'actual_end_date' => $status === 'accepted' || $status === 'completed' ? $start->copy()->addDays(random_int(1, 7))->toDateString() : null,
                    'planned_duration_days' => random_int(1, 7),
                    'actual_duration_days' => $status === 'accepted' || $status === 'completed' ? random_int(1, 7) : 0,
                    'status' => $status,
                    'progress' => $status === 'pending' ? 0 : ($status === 'in_progress' ? random_int(20, 80) : 100),
                    'foreman_id' => $userIds[array_rand($userIds)],
                    'workers' => json_encode([$userIds[array_rand($userIds)], $userIds[array_rand($userIds)]]),
                    'location' => '项目现场 #' . random_int(1, 50),
                    'description' => '按图施工，确保工艺符合标准',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('process_instances')->insert($rows);
            $this->command->info("  ✓ process_instances 50 rows");
        }

        // ===== 7. process_inspections (30) =====
        if (DB::table('process_inspections')->count() == 0) {
            $instanceIds = DB::table('process_instances')->pluck('id')->toArray();
            $types = ['self', 'mutual', 'special', 'final'];
            $results = ['pending', 'passed', 'passed', 'passed', 'failed', 'rectified'];
            $rows = [];
            for ($i = 0; $i < 30; $i++) {
                $date = $now->copy()->subDays(random_int(0, 30))->toDateString();
                $result = $results[array_rand($results)];
                $rows[] = [
                    'process_instance_id' => $instanceIds[array_rand($instanceIds)],
                    'inspection_type'     => $types[array_rand($types)],
                    'inspector_id'        => $userIds[array_rand($userIds)],
                    'inspector_name'      => '验收员' . random_int(1, 5),
                    'inspection_date'     => $date,
                    'result'              => $result,
                    'score'               => $result === 'pending' ? null : random_int(60, 100),
                    'checkpoint_results'  => json_encode(['材料规格' => '合格', '施工工艺' => '合格']),
                    'issues'              => $result === 'failed' ? json_encode(['接头松动']) : null,
                    'suggestions'         => '按规范执行',
                    'next_inspection_date' => $now->copy()->addDays(random_int(7, 30))->toDateString(),
                    'remark'              => '按标准工艺验收',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
            DB::table('process_inspections')->insert($rows);
            $this->command->info("  ✓ process_inspections 30 rows");
        }

        // ===== 8. vehicle_insurance (30) =====
        if (!empty($vehicleIds) && DB::table('vehicle_insurance')->count() == 0) {
            $companies = ['中国人保', '太平洋保险', '平安保险', '中华联合', '大地保险'];
            $types = ['commercial', 'compulsory', 'third_party'];
            $rows = [];
            for ($i = 0; $i < 30; $i++) {
                $start = $now->copy()->subDays(random_int(0, 365))->toDateString();
                $end = $now->copy()->addDays(random_int(30, 365))->toDateString();
                $rows[] = [
                    'vehicle_id' => $vehicleIds[array_rand($vehicleIds)],
                    'insurance_company' => $companies[array_rand($companies)],
                    'policy_no' => 'POL-' . strtoupper(Str::random(10)),
                    'type' => $types[array_rand($types)],
                    'premium' => random_int(1000, 8000),
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'active',
                    'notes' => '车险保单',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('vehicle_insurance')->insert($rows);
            $this->command->info("  ✓ vehicle_insurance 30 rows");
        }

        // ===== 9. vehicle_maintenance_records (20) =====
        if (!empty($vehicleIds) && DB::table('vehicle_maintenance_records')->count() == 0) {
            $types = ['routine', 'repair', 'inspection', 'tire_change'];
            $rows = [];
            for ($i = 0; $i < 20; $i++) {
                $date = $now->copy()->subDays(random_int(0, 180))->toDateString();
                $rows[] = [
                    'vehicle_id' => $vehicleIds[array_rand($vehicleIds)],
                    'maintenance_type' => $types[array_rand($types)],
                    'mileage' => random_int(10000, 80000),
                    'cost' => random_int(200, 5000),
                    'maintenance_date' => $date,
                    'description' => ['常规保养', '更换机油机滤', '刹车片更换', '轮胎更换', '电瓶检查'][array_rand([0,1,2,3,4])],
                    'next_maintenance_mileage' => random_int(80000, 120000),
                    'next_maintenance_date' => $now->copy()->addDays(random_int(30, 180))->toDateString(),
                    'handled_by' => $userIds[array_rand($userIds)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('vehicle_maintenance_records')->insert($rows);
            $this->command->info("  ✓ vehicle_maintenance_records 20 rows");
        }

        // ===== 10. fuel_cards (6) + fuel_card_recharges (20) =====
        if (DB::table('fuel_cards')->count() == 0) {
            $cardIds = [];
            for ($i = 1; $i <= 6; $i++) {
                $cardIds[] = DB::table('fuel_cards')->insertGetId([
                    'card_no' => 'CNO-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT) . strtoupper(Str::random(4)),
                    'card_name' => "工程车油卡#$i",
                    'vehicle_id' => !empty($vehicleIds) ? $vehicleIds[($i-1) % count($vehicleIds)] : null,
                    'balance' => random_int(500, 5000),
                    'status' => 'active',
                    'issue_date' => $now->copy()->subMonths(random_int(1, 12))->toDateString(),
                    'expire_date' => $now->copy()->addYears(2)->toDateString(),
                    'notes' => '中石化加油卡',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->command->info("  ✓ fuel_cards 6 rows");

            // 充值记录 20 条
            $rows = [];
            for ($i = 0; $i < 20; $i++) {
                $rows[] = [
                    'card_id' => $cardIds[array_rand($cardIds)],
                    'amount' => random_int(500, 3000),
                    'recharge_date' => $now->copy()->subDays(random_int(0, 180))->toDateString(),
                    'payment_method' => ['公司转账', '现金', '微信', '支付宝'][array_rand([0,1,2,3])],
                    'operator' => '财务' . random_int(1, 3),
                    'voucher_no' => 'V' . strtoupper(Str::random(8)),
                    'notes' => '油卡充值',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('fuel_card_recharges')->insert($rows);
            $this->command->info("  ✓ fuel_card_recharges 20 rows");
        }

        // ===== 11. finance_invoices (30) =====
        if (DB::table('finance_invoices')->count() == 0) {
            $types = ['ordinary', 'special', 'electronic'];
            $statuses = ['draft', 'issued', 'issued', 'issued', 'cancelled'];
            $rows = [];
            for ($i = 0; $i < 30; $i++) {
                $amount = random_int(1000, 50000);
                $taxRate = 13.00;
                $taxAmount = round($amount / 100 * $taxRate, 2);
                $rows[] = [
                    'invoice_no' => 'INV-' . $now->format('Ymd') . str_pad((string)($i+1), 4, '0', STR_PAD_LEFT),
                    'invoice_type' => $types[array_rand($types)],
                    'customer_id' => $customerIds[array_rand($customerIds)],
                    'project_id' => $projectIds[array_rand($projectIds)],
                    'amount' => $amount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $amount + $taxAmount,
                    'issue_date' => $now->copy()->subDays(random_int(0, 180))->toDateString(),
                    'status' => $statuses[array_rand($statuses)],
                    'remark' => '工程施工款发票',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('finance_invoices')->insert($rows);
            $this->command->info("  ✓ finance_invoices 30 rows");
        }

        // ===== 12. notifications (200) =====
        if (DB::table('notifications')->count() == 0) {
            $types = ['system', 'task', 'approval', 'project', 'finance', 'warranty', 'reminder'];
            $titles = [
                '您有一个待审批的报销单', '项目进度更新提醒', '系统维护通知', '新工单分配',
                '付款已到账', '质保到期提醒', '会议提醒', '客户回访提醒',
                '设备巡检到期', '材料库存预警', '保养到期提醒', '审批结果通知',
            ];
            $levels = ['info', 'info', 'info', 'warning', 'warning', 'success', 'urgent'];
            $rows = [];
            for ($i = 0; $i < 200; $i++) {
                $userId = $userIds[array_rand($userIds)];
                $created = $now->copy()->subDays(random_int(0, 30))->subMinutes(random_int(0, 1440));
                $isRead = random_int(0, 1) === 1;
                $rows[] = [
                    'type' => $types[array_rand($types)],
                    'title' => $titles[array_rand($titles)],
                    'content' => '点击查看详情',
                    'data' => json_encode(['url' => '/dashboard', 'id' => random_int(1, 1000)]),
                    'read_at' => $isRead ? $created->copy()->addMinutes(random_int(5, 60)) : null,
                    'notifiable_id' => $userId,
                    'notifiable_type' => 'App\\Models\\User',
                    'sender_id' => $userIds[array_rand($userIds)],
                    'level' => $levels[array_rand($levels)],
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }
            // 批量插入 PG
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }
            $this->command->info("  ✓ notifications 200 rows");
        }

        // ===== 13. work_orders (补到 100 条) =====
        $existingWo = DB::table('work_orders')->count();
        if ($existingWo < 100) {
            $need = 100 - $existingWo;
            $priorities = ['low', 'medium', 'high', 'urgent'];
            $serviceTypes = ['on_site', 'phone', 'remote'];
            $statuses = ['pending', 'assigned', 'in_progress', 'in_progress', 'resolved', 'closed'];
            $rows = [];
            for ($i = 0; $i < $need; $i++) {
                $start = $now->copy()->subDays(random_int(0, 60));
                $status = $statuses[array_rand($statuses)];
                $rows[] = [
                    'code' => 'WO-' . $start->format('Ymd') . str_pad((string)($i+1+1000), 4, '0', STR_PAD_LEFT),
                    'customer_id' => $customerIds[array_rand($customerIds)],
                    'project_id' => $projectIds[array_rand($projectIds)],
                    'contact_name' => '联系人' . random_int(1, 50),
                    'contact_phone' => '138' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'address' => '客户地址 #' . random_int(1, 100),
                    'service_type' => $serviceTypes[array_rand($serviceTypes)],
                    'priority' => $priorities[array_rand($priorities)],
                    'fault_description' => '设备故障：' . ['画面不显示', '无法远程', '系统卡顿', '报警频发', '网络中断'][array_rand([0,1,2,3,4])],
                    'equipment_brand' => ['海康', '大华', '宇视', '华为', '锐捷'][array_rand([0,1,2,3,4])],
                    'equipment_model' => 'M' . random_int(100, 999),
                    'assigned_to' => $userIds[array_rand($userIds)],
                    'scheduled_at' => $start->copy()->addDays(random_int(1, 3)),
                    'started_at' => $status !== 'pending' ? $start : null,
                    'completed_at' => ($status === 'resolved' || $status === 'closed') ? $start->copy()->addDays(random_int(1, 5)) : null,
                    'status' => $status,
                    'is_billable' => random_int(0, 1) === 1,
                    'service_fee' => random_int(200, 2000),
                    'parts_cost' => random_int(0, 1500),
                    'total_cost' => random_int(300, 3500),
                    'created_by' => $userIds[array_rand($userIds)],
                    'created_at' => $start,
                    'updated_at' => $start,
                ];
            }
            DB::table('work_orders')->insert($rows);
            $this->command->info("  ✓ work_orders +{$need} rows (补到 100)");
        }

        // ===== 14. repair_orders (补到 50 条) =====
        $existingRo = DB::table('repair_orders')->count();
        if ($existingRo < 50) {
            $need = 50 - $existingRo;
            $statuses = ['received', 'in_repair', 'in_repair', 'in_repair', 'repaired', 'closed'];
            $rows = [];
            for ($i = 0; $i < $need; $i++) {
                $start = $now->copy()->subDays(random_int(0, 60));
                $status = $statuses[array_rand($statuses)];
                $rows[] = [
                    'code' => 'RO-' . $start->format('Ymd') . str_pad((string)($i+1+1000), 4, '0', STR_PAD_LEFT),
                    'source_type' => 'customer',
                    'source_id' => $customerIds[array_rand($customerIds)],
                    'source_code' => 'CUST-' . random_int(1000, 9999),
                    'customer_id' => $customerIds[array_rand($customerIds)],
                    'project_id' => $projectIds[array_rand($projectIds)],
                    'contact_name' => '报修人' . random_int(1, 50),
                    'contact_phone' => '137' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'address' => '客户地址 #' . random_int(1, 100),
                    'fault_type' => ['硬件故障', '软件故障', '线路问题', '配置问题'][array_rand([0,1,2,3])],
                    'fault_description' => '故障描述：' . ['设备无法启动', '信号中断', '画面异常', '系统报错', '操作失灵'][array_rand([0,1,2,3,4])],
                    'severity' => ['low', 'medium', 'high', 'urgent'][array_rand([0,1,2,3])],
                    'received_by' => $userIds[array_rand($userIds)],
                    'received_at' => $start,
                    'expected_finish_at' => $start->copy()->addDays(random_int(1, 5)),
                    'status' => $status,
                    'parts_cost' => random_int(100, 3000),
                    'labor_cost' => random_int(200, 1500),
                    'created_at' => $start,
                    'updated_at' => $start,
                ];
            }
            DB::table('repair_orders')->insert($rows);
            $this->command->info("  ✓ repair_orders +{$need} rows (补到 50)");
        }

        // 汇总
        $this->command->info('');
        $this->command->info('  All counts after seeding:');
        foreach (['construction_teams','construction_team_members','work_processes',
                  'construction_logs','process_templates','process_instances','process_inspections',
                  'vehicle_insurance','vehicle_maintenance_records','fuel_cards','fuel_card_recharges',
                  'finance_invoices','notifications','work_orders','repair_orders'] as $t) {
            $cnt = DB::table($t)->count();
            $this->command->info("    {$t}: {$cnt}");
        }
    }
}
