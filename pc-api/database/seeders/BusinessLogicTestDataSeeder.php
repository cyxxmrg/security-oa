<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * v0.3.14 业务逻辑驱动的测试数据
 *
 * 设计原则：模拟一家安防工程公司半年的真实业务运营
 *
 * 业务时间线:
 *  6个月前: 销售签合同 → 项目立项 → 客户入库
 *  5个月前: 询比价 → 采购订单
 *  4个月前: 设备到货 → 库存入库
 *  3个月前: 现场施工 → 施工日志 → 项目材料领用
 *  2个月前: 项目进度款 → 应收账款
 *  1个月前: 部分工单完工 → 客户确认
 *  现在: 1 个项目已结算、2 个质保期、3 个在建、2 个刚立项
 *
 * 数据规模: 5 客户、10 项目、25 工单、5 车辆、3 仓库、20 库存物品
 */
class BusinessLogicTestDataSeeder extends Seeder
{
    private $now;
    private $userIds = [];        // username => id
    private $userIdList = [];     // [1, 2, 3, ...]
    private $deptIds = [];
    private $customerMap = [];    // name => id
    private $customerIds = [];
    private $projectMap = [];     // no => id
    private $projectIds = [];
    private $supplierMap = [];    // name => id
    private $supplierIds = [];
    private $warehouseMap = [];   // code => id
    private $inventoryMap = [];   // code => id
    private $inventoryIds = [];
    private $vehicleMap = [];     // plate_no => id
    private $deviceMap = [];      // serial => id

    public function run(): void
    {
        $this->now = Carbon::now();
        $this->command->info('🌱 开始生成业务逻辑驱动的测试数据...');
        $this->command->info('   业务时间线：6个月前立项 → 1个月前完工 → 现在 5 客户/10 项目/25 工单/5 车辆/3 仓库');

        $this->loadExistingData();
        $this->seedCustomers();
        $this->seedSuppliers();
        $this->seedWarehouses();
        $this->seedInventoryItems();
        $this->seedVehicles();
        $this->seedProjects();
        $this->seedServiceOrders();
        $this->seedKnowledgeAndDisk();
        try { $this->seedAttendanceExpense(); } catch (\Throwable $e) { $this->command->error('  ! seedAttendanceExpense failed: ' . $e->getMessage()); }
        $this->printSummary();

        $this->command->info('✅ 业务逻辑驱动的测试数据生成完成');
    }

    private function loadExistingData(): void
    {
        $users = DB::table('users')->select('id', 'username', 'name')->get();
        foreach ($users as $u) {
            $this->userIds[$u->username] = $u->id;
            $this->userIdList[] = $u->id;
        }
        $depts = DB::table('departments')->select('id', 'name')->get();
        foreach ($depts as $d) $this->deptIds[] = $d->id;
        $this->command->info("  ✓ 已加载 " . count($this->userIds) . " 个用户 / " . count($this->deptIds) . " 个部门");
    }

    /**
     * 客户：5 个真实场景客户
     */
    private function seedCustomers(): void
    {
        $customers = [
            ['name' => '华润万家商业集团', 'category' => 'vip', 'industry' => '商业连锁', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区科苑南路88号', 'source' => '转介绍', 'tags' => ['商场', '连锁', '大客户']],
            ['name' => '前海自贸区管委会', 'category' => 'vip', 'industry' => '政府机构', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区前海路100号', 'source' => '招标', 'tags' => ['园区', '政府', '重点']],
            ['name' => '比亚迪汽车工业', 'category' => 'normal', 'industry' => '汽车制造', 'city' => '深圳', 'district' => '坪山区', 'address' => '坪山区比亚迪路300号', 'source' => '官网咨询', 'tags' => ['工厂', '大客户']],
            ['name' => '深圳湾体育中心', 'category' => 'normal', 'industry' => '体育场馆', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区滨海大道3001号', 'source' => '公开招标', 'tags' => ['体育', '公共']],
            ['name' => '星河世纪物业', 'category' => 'potential', 'industry' => '商业地产', 'city' => '深圳', 'district' => '福田区', 'address' => '福田区中心商务区88号', 'source' => '陌拜', 'tags' => ['写字楼', '物业']],
        ];

        foreach ($customers as $i => $c) {
            $existing = DB::table('customers')->where('name', $c['name'])->value('id');
            if ($existing) {
                $this->customerMap[$c['name']] = $existing;
                continue;
            }
            $tags = $c['tags'];
            unset($c['tags']);
            $row = array_merge($c, [
                'province' => '广东',
                'credit_code' => '91440300MA5' . str_pad((string)($i + 1), 8, 'X', STR_PAD_LEFT),
                'longitude' => 113.9 + ($i * 0.05),
                'latitude' => 22.5 + ($i * 0.03),
                'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'assigned_user_id' => $this->userIds['manager'] ?? 2,
                'description' => $c['name'] . '是公司重要客户，已建立长期合作关系。',
                'created_at' => $this->now->copy()->subMonths(6)->subDays(20 + $i * 5),
                'updated_at' => $this->now->copy()->subDays(15),
            ]);
            $id = DB::table('customers')->insertGetId($row);
            $this->customerMap[$c['name']] = $id;
        }
        $this->customerIds = array_values($this->customerMap);

        // 客户联系人
        foreach ($this->customerMap as $cname => $cid) {
            $existingCnt = DB::table('customer_contacts')->where('customer_id', $cid)->count();
            if ($existingCnt > 0) continue;
            $contacts = [
                ['name' => '王经理', 'position' => '采购经理', 'phone' => '138' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT), 'is_primary' => true],
                ['name' => '李主管', 'position' => '技术主管', 'phone' => '139' . str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT), 'is_primary' => false],
            ];
            foreach ($contacts as $c) {
                DB::table('customer_contacts')->insertOrIgnore([
                    'customer_id' => $cid,
                    'name' => $c['name'],
                    'position' => $c['position'],
                    'phone' => $c['phone'],
                    'email' => $c['name'] . '@' . preg_replace('/[^\w]/', '', $cname) . '.com',
                    'is_primary' => $c['is_primary'],
                    'created_at' => $this->now->copy()->subMonths(6),
                    'updated_at' => $this->now->copy()->subDays(10),
                ]);
            }

            // 客户设备 (5-8 个) — 用 device_type/install_location/warranty_end
            $devices = [
                ['name' => '海康威视 DS-2CD2T47 400万枪机', 'device_type' => 'camera', 'model' => 'DS-2CD2T47G2', 'quantity' => mt_rand(8, 24)],
                ['name' => '大华 DH-IPC-HFW2231T 200万枪机', 'device_type' => 'camera', 'model' => 'DH-IPC-HFW2231T', 'quantity' => mt_rand(4, 12)],
                ['name' => '海康 DS-K1T341M 门禁一体机', 'device_type' => 'access', 'model' => 'DS-K1T341M', 'quantity' => mt_rand(2, 6)],
                ['name' => '海康 DS-2FA1202 球型摄像机', 'device_type' => 'ptz', 'model' => 'DS-2FA1202', 'quantity' => mt_rand(1, 4)],
                ['name' => '霍尼韦尔 VISTA-128 报警主机', 'device_type' => 'alarm', 'model' => 'VISTA-128BPT', 'quantity' => mt_rand(1, 2)],
            ];
            foreach ($devices as $d) {
                for ($q = 0; $q < $d['quantity']; $q++) {
                    $serial = $d['model'] . '-' . $cid . str_pad((string)($q + 1), 4, '0', STR_PAD_LEFT);
                    $devRow = [
                        'customer_id' => $cid,
                        'project_id' => null,
                        'device_name' => $d['name'],
                        'device_type' => $d['device_type'],
                        'brand' => explode(' ', $d['name'])[0],
                        'model' => $d['model'],
                        'serial_number' => $serial,
                        'install_location' => '楼栋' . mt_rand(1, 5) . '层',
                        'install_date' => $this->now->copy()->subMonths(mt_rand(2, 8))->format('Y-m-d'),
                        'warranty_end' => $this->now->copy()->addMonths(mt_rand(6, 24))->format('Y-m-d'),
                        'status' => 'normal',
                        'notes' => null,
                        'created_at' => $this->now->copy()->subMonths(mt_rand(2, 8)),
                        'updated_at' => $this->now->copy()->subDays(5),
                    ];
                    try {
                        $devId = DB::table('customer_devices')->insertGetId($devRow);
                        $this->deviceMap[$serial] = $devId;
                    } catch (\Throwable $e) { /* ignore dup */ }
                }
            }

            // 跟进记录
            $followups = [
                ['type' => 'phone', 'content' => '初次接洽，介绍产品方案', 'note' => '客户有兴趣'],
                ['type' => 'visit', 'content' => '技术交流，现场勘察', 'note' => '现场已勘察'],
                ['type' => 'visit', 'content' => '商务报价，方案调整', 'note' => '报价已提交'],
                ['type' => 'meeting', 'content' => '项目签约，准备开工', 'note' => '合同已签订'],
            ];
            foreach ($followups as $fi => $f) {
                $fdate = $this->now->copy()->subMonths(5)->addDays($fi * 30);
                DB::table('follow_up_records')->insertOrIgnore([
                    'customer_id' => $cid,
                    'user_id' => $this->userIds['manager'] ?? 2,
                    'type' => $f['type'],
                    'content' => $f['content'] . ' - ' . $f['note'],
                    'next_follow_up_date' => $fdate->copy()->addDays(30),
                    'next_follow_up_note' => '持续跟进客户需求',
                    'attachments' => null,
                    'created_at' => $fdate,
                    'updated_at' => $this->now->copy()->subDays(2),
                ]);
            }
        }
        $this->command->info("  ✓ 客户: " . count($this->customerMap) . " 个 | 联系人 " . DB::table('customer_contacts')->count() . " | 设备 " . DB::table('customer_devices')->count() . " | 跟进 " . DB::table('follow_up_records')->count());
    }

    /**
     * 供应商：5 个
     */
    private function seedSuppliers(): void
    {
        $suppliers = [
            ['name' => '海康威视深圳分公司', 'category' => '设备', 'contact_person' => '张经理', 'phone' => '13800138001', 'rating' => 5, 'address' => '深圳市南山区高新南一道海康大厦'],
            ['name' => '大华股份深圳办事处', 'category' => '设备', 'contact_person' => '李经理', 'phone' => '13800138002', 'rating' => 5, 'address' => '深圳市南山区科技园大华科技'],
            ['name' => '深圳线缆批发城', 'category' => '材料', 'contact_person' => '王老板', 'phone' => '13800138003', 'rating' => 4, 'address' => '深圳市福田区华强北'],
            ['name' => '霍尼韦尔安防代理', 'category' => '设备', 'contact_person' => '赵经理', 'phone' => '13800138004', 'rating' => 4, 'address' => '深圳市福田区华强北路'],
            ['name' => '立林科技门禁专卖', 'category' => '设备', 'contact_person' => '陈经理', 'phone' => '13800138005', 'rating' => 4, 'address' => '深圳市南山区科技园'],
        ];
        foreach ($suppliers as $s) {
            $existing = DB::table('suppliers')->where('name', $s['name'])->value('id');
            if ($existing) {
                $this->supplierMap[$s['name']] = $existing;
                continue;
            }
            $row = $s;
            $row['email'] = $s['contact_person'] . '@' . preg_replace('/[^\w]/', '', $s['name']) . '.com';
            $row['status'] = 'active';
            $row['notes'] = $s['category'] . '供应商，长期合作';
            $row['created_at'] = $this->now->copy()->subMonths(8);
            $row['updated_at'] = $this->now->copy()->subDays(10);
            $id = DB::table('suppliers')->insertGetId($row);
            $this->supplierMap[$s['name']] = $id;
        }
        $this->supplierIds = array_values($this->supplierMap);
        $this->command->info("  ✓ 供应商: " . count($this->supplierMap));
    }

    /**
     * 仓库：3 个 (主仓 + 前置仓 + 现场仓)
     */
    private function seedWarehouses(): void
    {
        $warehouses = [
            ['code' => 'WH-SZ-01', 'name' => '深圳南山大仓', 'address' => '深圳市南山区科技园', 'type' => 'main', 'manager_id' => $this->userIds['manager'] ?? 2, 'description' => '公司主仓库'],
            ['code' => 'WH-SZ-02', 'name' => '深圳福田前置仓', 'address' => '深圳市福田区华强北', 'type' => 'transit', 'manager_id' => $this->userIds['user'] ?? 3, 'description' => '临时周转仓'],
            ['code' => 'WH-PROJ-01', 'name' => '比亚迪工厂现场仓', 'address' => '深圳市坪山区比亚迪厂区', 'type' => 'site', 'manager_id' => $this->userIds['zhaodc'] ?? 4, 'description' => '项目现场仓'],
        ];
        foreach ($warehouses as $w) {
            $existing = DB::table('warehouses')->where('code', $w['code'])->value('id');
            if ($existing) {
                $this->warehouseMap[$w['code']] = $existing;
                continue;
            }
            $row = $w;
            $row['status'] = 'active';
            $row['created_at'] = $this->now->copy()->subMonths(8);
            $row['updated_at'] = $this->now->copy()->subDays(5);
            $id = DB::table('warehouses')->insertGetId($row);
            $this->warehouseMap[$w['code']] = $id;
        }
        $this->command->info("  ✓ 仓库: " . count($this->warehouseMap));
    }

    /**
     * 库存物品：20 种真实安防设备
     */
    private function seedInventoryItems(): void
    {
        $items = [
            ['code' => 'CAM-IPC-4MP-IR', 'name' => '400万红外网络摄像头', 'category' => '监控设备', 'unit' => '台', 'specification' => '4MP, 红外30m, POE', 'cost_price' => 380, 'sell_price' => 480, 'stock' => [50, 30, 10]],
            ['code' => 'CAM-IPC-8MP-AI', 'name' => '800万AI人形识别摄像头', 'category' => '监控设备', 'unit' => '台', 'specification' => '8MP, AI识别, POE', 'cost_price' => 700, 'sell_price' => 880, 'stock' => [20, 10, 5]],
            ['code' => 'CAM-PTZ-4MP', 'name' => '400万球型云台摄像机', 'category' => '监控设备', 'unit' => '台', 'specification' => '4MP, 25倍光学变焦', 'cost_price' => 2500, 'sell_price' => 3200, 'stock' => [8, 4, 2]],
            ['code' => 'ACC-CTRL-FACE', 'name' => '人脸识别门禁一体机', 'category' => '门禁设备', 'unit' => '台', 'specification' => '双摄, 5万人脸库', 'cost_price' => 1400, 'sell_price' => 1800, 'stock' => [15, 8, 4]],
            ['code' => 'ACC-CARD-RD', 'name' => 'IC卡读卡器', 'category' => '门禁设备', 'unit' => '台', 'specification' => 'Wiegand 26/34', 'cost_price' => 90, 'sell_price' => 120, 'stock' => [40, 20, 10]],
            ['code' => 'ALA-HOST-8', 'name' => '8路报警主机', 'category' => '报警设备', 'unit' => '台', 'specification' => '8防区, 网络上报', 'cost_price' => 520, 'sell_price' => 680, 'stock' => [12, 6, 3]],
            ['code' => 'NVR-32CH', 'name' => '32路网络录像机', 'category' => '存储设备', 'unit' => '台', 'specification' => '32路, 8盘位', 'cost_price' => 2200, 'sell_price' => 2800, 'stock' => [6, 3, 2]],
            ['code' => 'HDD-4TB', 'name' => '4TB监控专用硬盘', 'category' => '存储设备', 'unit' => '块', 'specification' => '4TB, 5900RPM', 'cost_price' => 450, 'sell_price' => 580, 'stock' => [20, 10, 5]],
            ['code' => 'SW-POE-24', 'name' => '24口POE交换机', 'category' => '网络设备', 'unit' => '台', 'specification' => '24口千兆POE', 'cost_price' => 1300, 'sell_price' => 1680, 'stock' => [10, 5, 3]],
            ['code' => 'SW-FIBER-8', 'name' => '8口光纤交换机', 'category' => '网络设备', 'unit' => '台', 'specification' => '8口千兆SFP', 'cost_price' => 1700, 'sell_price' => 2200, 'stock' => [4, 2, 1]],
            ['code' => 'CBL-UTP-CAT6', 'name' => '六类网线', 'category' => '线材', 'unit' => '箱', 'specification' => '305米/箱, CAT6', 'cost_price' => 560, 'sell_price' => 720, 'stock' => [30, 15, 8]],
            ['code' => 'CBL-FIBER-SM', 'name' => '单模光纤', 'category' => '线材', 'unit' => '米', 'specification' => '9/125, 室外', 'cost_price' => 2.50, 'sell_price' => 3.50, 'stock' => [5000, 2000, 1000]],
            ['code' => 'CBL-POWER-RVV', 'name' => '电源线 RVV 2x1.5', 'category' => '线材', 'unit' => '米', 'specification' => '2x1.5平方, 100米/卷', 'cost_price' => 6.50, 'sell_price' => 8.50, 'stock' => [2000, 1000, 500]],
            ['code' => 'PIPE-PVC-25', 'name' => 'PVC线管 25mm', 'category' => '辅材', 'unit' => '米', 'specification' => '25mm, 阻燃', 'cost_price' => 3.20, 'sell_price' => 4.20, 'stock' => [3000, 1500, 500]],
            ['code' => 'CAM-DOME-2MP', 'name' => '200万半球摄像机', 'category' => '监控设备', 'unit' => '台', 'specification' => '2MP, 室内吸顶', 'cost_price' => 220, 'sell_price' => 280, 'stock' => [30, 15, 8]],
            ['code' => 'ACC-FG-LOCK', 'name' => '磁力锁 280kg', 'category' => '门禁设备', 'unit' => '把', 'specification' => '280kg吸力, 常开', 'cost_price' => 240, 'sell_price' => 320, 'stock' => [20, 10, 5]],
            ['code' => 'ACC-BTN-EXIT', 'name' => '出门按钮', 'category' => '门禁设备', 'unit' => '个', 'specification' => '不锈钢, 嵌入式', 'cost_price' => 18, 'sell_price' => 28, 'stock' => [40, 20, 10]],
            ['code' => 'ACC-POWER-12V', 'name' => '门禁电源 12V 5A', 'category' => '门禁设备', 'unit' => '台', 'specification' => '12V 5A, 备用电池', 'cost_price' => 130, 'sell_price' => 180, 'stock' => [25, 12, 6]],
            ['code' => 'CAM-PTZ-EX-2MP', 'name' => '防爆云台摄像机', 'category' => '监控设备', 'unit' => '台', 'specification' => '2MP, 防爆等级ExdII CT6', 'cost_price' => 7000, 'sell_price' => 8800, 'stock' => [4, 2, 1]],
            ['code' => 'NVR-16CH-AI', 'name' => '16路AI智能录像机', 'category' => '存储设备', 'unit' => '台', 'specification' => '16路, AI人形检测', 'cost_price' => 2500, 'sell_price' => 3200, 'stock' => [5, 3, 2]],
        ];
        foreach ($items as $idx => $it) {
            $existing = DB::table('inventory_items')->where('code', $it['code'])->value('id');
            if ($existing) {
                $this->inventoryMap[$it['code']] = $existing;
                $this->inventoryIds[] = $existing;
                continue;
            }
            $row = $it;
            unset($row['stock']);
            $row['safety_stock'] = 5;
            $row['current_stock'] = array_sum($it['stock']);
            $row['warehouse_id'] = $this->warehouseMap['WH-SZ-01'] ?? null;
            $row['location'] = 'A-' . str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT);
            $row['has_serial'] = true;
            $row['status'] = 'active';
            $row['created_at'] = $this->now->copy()->subMonths(6)->subDays($idx);
            $row['updated_at'] = $this->now->copy()->subDays(2);
            $id = DB::table('inventory_items')->insertGetId($row);
            $this->inventoryMap[$it['code']] = $id;
            $this->inventoryIds[] = $id;

            // 库存记录 (每个仓库一条)
            foreach (['WH-SZ-01', 'WH-SZ-02', 'WH-PROJ-01'] as $wi => $wcode) {
                $wh = $this->warehouseMap[$wcode] ?? null;
                if (!$wh) continue;
                $qty = $it['stock'][$wi] ?? 0;
                if ($qty > 0) {
                    DB::table('stock_records')->insertOrIgnore([
                        'record_no' => 'STK-' . date('Ymd', strtotime('-3 months')) . '-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                        'inventory_item_id' => $id,
                        'warehouse_id' => $wh,
                        'type' => 'in',
                        'quantity' => $qty,
                        'remaining_stock' => $qty,
                        'related_id' => null,
                        'related_type' => 'purchase',
                        'operator_id' => $this->userIds['admin'] ?? 1,
                        'remark' => '初始入库',
                        'created_at' => $this->now->copy()->subMonths(mt_rand(1, 5)),
                        'updated_at' => $this->now->copy()->subDays(2),
                    ]);
                }
            }

            // 设备序列号 (前 5 个)
            for ($s = 1; $s <= 5; $s++) {
                $serial = $it['code'] . '-SN' . str_pad((string)$s, 4, '0', STR_PAD_LEFT);
                DB::table('device_serial_numbers')->insertOrIgnore([
                    'inventory_item_id' => $id,
                    'serial_number' => $serial,
                    'status' => $s <= 3 ? 'in_stock' : ($s == 4 ? 'in_use' : 'maintenance'),
                    'project_id' => null,
                    'customer_device_id' => null,
                    'stock_record_id' => null,
                    'install_date' => null,
                    'notes' => '生产序列号',
                    'created_at' => $this->now->copy()->subMonths(4),
                    'updated_at' => $this->now->copy()->subDays(2),
                ]);
            }
        }
        $this->command->info("  ✓ 库存物品: " . count($this->inventoryMap) . " 种 | 库存记录: " . DB::table('stock_records')->count() . " | 序列号: " . DB::table('device_serial_numbers')->count());
    }

    /**
     * 车辆：5 辆
     */
    private function seedVehicles(): void
    {
        $vehicles = [
            ['plate_no' => '粤B-A1234', 'brand' => '丰田凯美瑞', 'model' => '2.5G', 'color' => '黑色', 'seats' => 5, 'fuel_type' => 'gasoline', 'purchase_date' => '2023-03-15', 'purchase_price' => 220000],
            ['plate_no' => '粤B-B5678', 'brand' => '别克GL8', 'model' => 'ES陆尊', 'color' => '白色', 'seats' => 7, 'fuel_type' => 'gasoline', 'purchase_date' => '2022-08-10', 'purchase_price' => 320000],
            ['plate_no' => '粤B-C9012', 'brand' => '福特全顺', 'model' => '短轴中顶', 'color' => '银色', 'seats' => 6, 'fuel_type' => 'diesel', 'purchase_date' => '2023-06-20', 'purchase_price' => 180000],
            ['plate_no' => '粤B-D3456', 'brand' => '江铃顺达', 'model' => '双排座', 'color' => '白色', 'seats' => 5, 'fuel_type' => 'diesel', 'purchase_date' => '2024-01-08', 'purchase_price' => 95000],
            ['plate_no' => '粤B-E7890', 'brand' => '比亚迪汉', 'model' => 'EV 605km', 'color' => '红色', 'seats' => 5, 'fuel_type' => 'electric', 'purchase_date' => '2024-05-15', 'purchase_price' => 220000],
        ];
        foreach ($vehicles as $v) {
            $existing = DB::table('vehicles')->where('plate_no', $v['plate_no'])->value('id');
            if ($existing) {
                $this->vehicleMap[$v['plate_no']] = $existing;
                continue;
            }
            $row = $v;
            $row['status'] = 'available';
            $row['responsible_user_id'] = $this->userIds[array_rand($this->userIds)];
            $row['vin'] = 'LSV' . str_pad((string)mt_rand(10000000000000000, 99999999999999999), 17, '0', STR_PAD_LEFT);
            $row['engine_no'] = str_pad((string)mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $row['created_at'] = $v['purchase_date'];
            $row['updated_at'] = $this->now->copy()->subDays(3);
            $id = DB::table('vehicles')->insertGetId($row);
            $this->vehicleMap[$v['plate_no']] = $id;

            // 维护记录
            for ($m = 0; $m < mt_rand(2, 3); $m++) {
                $mileage = mt_rand(10000, 60000) - $m * 8000;
                DB::table('vehicle_maintenance_records')->insertOrIgnore([
                    'vehicle_id' => $id,
                    'maintenance_type' => ['routine', 'repair', 'inspection'][$m % 3],
                    'mileage' => $mileage,
                    'cost' => mt_rand(300, 2000) + 0.00,
                    'maintenance_date' => $this->now->copy()->subMonths(mt_rand(1, 6))->format('Y-m-d'),
                    'description' => ['常规保养，更换机油机滤', '刹车片更换', '年检'][$m % 3],
                    'next_maintenance_mileage' => $mileage + 5000,
                    'handled_by' => $this->userIds['admin'] ?? 1,
                    'created_at' => $this->now->copy()->subMonths(mt_rand(1, 6)),
                    'updated_at' => $this->now->copy()->subDays(15),
                ]);
            }

            // 用车记录
            for ($u = 0; $u < mt_rand(5, 8); $u++) {
                $date = $this->now->copy()->subDays(mt_rand(1, 90));
                $startTime = $date->copy()->setTime(mt_rand(8, 16), mt_rand(0, 59), 0);
                $endTime = $startTime->copy()->addHours(mt_rand(2, 8));
                $status = ['pending', 'approved', 'in_progress', 'completed'][mt_rand(0, 3)];
                DB::table('vehicle_usage_requests')->insertOrIgnore([
                    'vehicle_id' => $id,
                    'applicant_id' => $this->userIdList[array_rand($this->userIdList)],
                    'usage_date' => $date->format('Y-m-d'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'destination' => ['南山区科苑南路', '福田区华强北', '坪山区比亚迪路', '龙岗区中心城'][$u % 4],
                    'purpose' => ['客户现场勘察', '设备运输', '会议出行', '项目验收'][$u % 4],
                    'passengers' => mt_rand(1, 4),
                    'self_drive' => true,
                    'status' => $status,
                    'approver_id' => $this->userIds['admin'] ?? 1,
                    'approved_at' => $status !== 'pending' ? $date : null,
                    'actual_mileage' => $status === 'completed' ? mt_rand(20, 200) : null,
                    'start_mileage' => 10000 + $u * 1000,
                    'end_mileage' => $status === 'completed' ? 10000 + $u * 1000 + mt_rand(20, 200) : null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        }
        $this->command->info("  ✓ 车辆: " . count($this->vehicleMap) . " | 维护: " . DB::table('vehicle_maintenance_records')->count() . " | 用车: " . DB::table('vehicle_usage_requests')->count());
    }

    /**
     * 项目：10 个，分布 7 个阶段
     */
    private function seedProjects(): void
    {
        $projects = [
            ['name' => '华润万家南山店监控升级',     'customer' => '华润万家商业集团',  'type' => 'camera',   'stage' => 'warranty',     'progress' => 100, 'priority' => 'high',   'status' => 'completed', 'months_ago' => 7, 'duration' => 4, 'budget' => 280000],
            ['name' => '华润万家宝安店门禁改造',     'customer' => '华润万家商业集团',  'type' => 'access',   'stage' => 'warranty',     'progress' => 100, 'priority' => 'medium', 'status' => 'completed', 'months_ago' => 6, 'duration' => 3, 'budget' => 120000],
            ['name' => '前海自贸区智慧园区一期',     'customer' => '前海自贸区管委会',  'type' => 'integrated','stage' => 'settlement',   'progress' => 95,  'priority' => 'high',   'status' => 'in_progress','months_ago' => 6, 'duration' => 5, 'budget' => 580000],
            ['name' => '比亚迪坪山工厂周界防范',     'customer' => '比亚迪汽车工业',    'type' => 'camera',   'stage' => 'construction', 'progress' => 75,  'priority' => 'high',   'status' => 'in_progress','months_ago' => 4, 'duration' => 5, 'budget' => 420000],
            ['name' => '深圳湾体育中心安检升级',     'customer' => '深圳湾体育中心',    'type' => 'access',   'stage' => 'construction', 'progress' => 60,  'priority' => 'medium', 'status' => 'in_progress','months_ago' => 3, 'duration' => 4, 'budget' => 320000],
            ['name' => '星河世纪写字楼智能化',       'customer' => '星河世纪物业',      'type' => 'integrated','stage' => 'construction', 'progress' => 45,  'priority' => 'medium', 'status' => 'in_progress','months_ago' => 2, 'duration' => 5, 'budget' => 380000],
            ['name' => '比亚迪工厂二期监控',         'customer' => '比亚迪汽车工业',    'type' => 'camera',   'stage' => 'purchase',  'progress' => 20,  'priority' => 'low',    'status' => 'in_progress','months_ago' => 1, 'duration' => 4, 'budget' => 260000],
            ['name' => '前海自贸区二期扩建',         'customer' => '前海自贸区管委会',  'type' => 'alarm',    'stage' => 'contract',     'progress' => 10,  'priority' => 'high',   'status' => 'in_progress','months_ago' => 1, 'duration' => 6, 'budget' => 720000],
            ['name' => '星河福田总部弱电升级',       'customer' => '星河世纪物业',      'type' => 'integrated','stage' => 'inquiry',       'progress' => 5,   'priority' => 'medium', 'status' => 'pending',   'months_ago' => 0, 'duration' => 4, 'budget' => 180000],
            ['name' => '华润万家龙岗店新建监控',     'customer' => '华润万家商业集团',  'type' => 'camera',   'stage' => 'initiation',   'progress' => 0,   'priority' => 'low',    'status' => 'pending',   'months_ago' => 0, 'duration' => 4, 'budget' => 350000],
        ];

        foreach ($projects as $idx => $p) {
            $cid = $this->customerMap[$p['customer']] ?? null;
            if (!$cid) continue;
            $existing = DB::table('projects')->where('name', $p['name'])->value('id');
            if ($existing) {
                $this->projectIds[] = $existing;
                $projectNo = 'PRJ-2026-' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT);
                $this->projectMap[$projectNo] = $existing;
                continue;
            }
            $startDate = $this->now->copy()->subMonths($p['months_ago'])->subDays(mt_rand(1, 20));
            $endDate = (clone $startDate)->addMonths($p['duration']);
            $budget = $p['budget'];
            $budgetDevice = $budget * 0.55;
            $budgetMaterial = $budget * 0.15;
            $budgetLabor = $budget * 0.20;
            $budgetOutsource = $budget * 0.05;
            $budgetOther = $budget * 0.05;

            $projectNo = 'PRJ-2026-' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT);
            $row = [
                'project_no' => $projectNo,
                'name' => $p['name'],
                'customer_id' => $cid,
                'type' => $p['type'],
                'stage' => $p['stage'],
                'status' => $p['status'],
                'description' => $p['name'] . '，本项目为' . $p['name'] . '工程，包含设计、设备采购、安装施工、调试培训和质保服务。客户为' . $p['customer'] . '，合同金额约 ' . number_format($budget / 10000, 1) . ' 万元。',
                'budget_device' => $budgetDevice,
                'budget_material' => $budgetMaterial,
                'budget_labor' => $budgetLabor,
                'budget_outsource' => $budgetOutsource,
                'budget_other' => $budgetOther,
                'progress' => $p['progress'],
                'manager_id' => $this->userIds['manager'] ?? 2,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'actual_end_date' => in_array($p['stage'], ['settlement', 'warranty']) ? (clone $endDate)->subDays(mt_rand(5, 20))->format('Y-m-d') : null,
                'priority' => $p['priority'],
                'created_at' => $startDate,
                'updated_at' => $this->now->copy()->subDays(1),
            ];
            $pid = DB::table('projects')->insertGetId($row);
            $this->projectIds[] = $pid;
            $this->projectMap[$projectNo] = $pid;

            // 项目团队
            for ($m = 0; $m < mt_rand(2, 3); $m++) {
                $staffId = $this->userIdList[array_rand($this->userIdList)];
                DB::table('project_members')->insertOrIgnore([
                    'project_id' => $pid,
                    'user_id' => $staffId,
                    'role' => ['manager', 'engineer', 'worker'][$m],
                    'join_date' => $startDate->format('Y-m-d'),
                    'leave_date' => null,
                    'status' => 'active',
                    'created_at' => $startDate,
                    'updated_at' => $this->now->copy()->subDays(1),
                ]);
            }

            // 合同 (initiation/inquiry 阶段没有)
            if (!in_array($p['stage'], ['initiation', 'inquiry'])) {
                $contractAmount = $budget * (1 + mt_rand(-5, 5) / 100);
                $contractStart = (clone $startDate)->addDays(mt_rand(10, 30));
                $cid2 = DB::table('project_contracts')->insertGetId([
                    'project_id' => $pid,
                    'contract_no' => 'CT-' . date('Ymd', strtotime($contractStart)) . '-' . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
                    'contract_amount' => $contractAmount,
                    'payment_method' => 'installment',
                    'contract_start' => $contractStart->format('Y-m-d'),
                    'contract_end' => (clone $contractStart)->addMonths(6)->format('Y-m-d'),
                    'status' => 'signed',
                    'signed_at' => $contractStart,
                    'notes' => '合同条款按附件执行',
                    'created_at' => $contractStart,
                    'updated_at' => $this->now->copy()->subDays(3),
                ]);

                // 3 段付款
                $nodes = [
                    ['name' => '合同预付款', 'pct' => 30, 'days_offset' => 7],
                    ['name' => '项目进度款', 'pct' => 50, 'days_offset' => 90],
                    ['name' => '质保金',     'pct' => 20, 'days_offset' => 180],
                ];
                foreach ($nodes as $n) {
                    $planned = (clone $contractStart)->addDays($n['days_offset']);
                    $isPaid = $planned->isPast() && $p['progress'] >= 20;
                    DB::table('contract_payment_nodes')->insertOrIgnore([
                        'contract_id' => $cid2,
                        'name' => $n['name'],
                        'percentage' => $n['pct'],
                        'amount' => round($contractAmount * $n['pct'] / 100, 2),
                        'planned_date' => $planned->format('Y-m-d'),
                        'actual_date' => $isPaid ? $planned->format('Y-m-d') : null,
                        'status' => $isPaid ? 'paid' : 'pending',
                        'paid_amount' => $isPaid ? round($contractAmount * $n['pct'] / 100, 2) : 0,
                        'created_at' => $contractStart,
                        'updated_at' => $this->now->copy()->subDays(2),
                    ]);
                }

                // 应收账款
                DB::table('receivables')->insertOrIgnore([
                    'project_id' => $pid,
                    'contract_id' => $cid2,
                    'customer_id' => $cid,
                    'amount' => $contractAmount,
                    'received_amount' => $p['stage'] === 'warranty' ? $contractAmount : 0,
                    'due_date' => $endDate->format('Y-m-d'),
                    'status' => $p['status'] === 'completed' ? 'paid' : 'partial',
                    'notes' => $p['name'] . ' 应收账款',
                    'created_at' => $contractStart,
                    'updated_at' => $this->now->copy()->subDays(1),
                ]);

                // 采购订单 (purchase/construction/settlement/warranty 阶段)
                if (in_array($p['stage'], ['purchase', 'construction', 'settlement', 'warranty'])) {
                    $supplierId = $this->supplierIds[array_rand($this->supplierIds)];
                    $poDate = (clone $contractStart)->addDays(15);
                    $poId = DB::table('purchase_orders')->insertGetId([
                        'project_id' => $pid,
                        'supplier_id' => $supplierId,
                        'po_no' => 'PO-' . date('Ymd', strtotime($poDate)) . '-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                        'total_amount' => 0,  // 后算
                        'status' => 'received',
                        'approved_by' => $this->userIds['admin'] ?? 1,
                        'notes' => '项目主要设备采购',
                        'created_at' => $poDate,
                        'updated_at' => (clone $poDate)->addDays(10),
                    ]);

                    // 采购明细 (3-5 个物品)
                    $poItemIds = (array) array_rand($this->inventoryMap, mt_rand(3, 5));
                    $total = 0;
                    foreach ($poItemIds as $itemKey) {
                        $itemId = is_int($itemKey) ? $this->inventoryIds[$itemKey] : ($this->inventoryMap[$itemKey] ?? null);
                        if (!$itemId) continue;
                        $item = DB::table('inventory_items')->where('id', $itemId)->first();
                        if (!$item) continue;
                        $qty = mt_rand(5, 20);
                        $lineTotal = $qty * $item->cost_price;
                        $total += $lineTotal;
                        DB::table('purchase_items')->insertOrIgnore([
                            'purchase_order_id' => $poId,
                            'item_name' => $item->name,
                            'specification' => $item->specification,
                            'quantity' => $qty,
                            'unit' => $item->unit,
                            'unit_price' => $item->cost_price,
                            'total_price' => $lineTotal,
                            'received_quantity' => $qty,
                            'notes' => null,
                            'created_at' => $poDate,
                            'updated_at' => (clone $poDate)->addDays(7),
                        ]);
                    }
                    DB::table('purchase_orders')->where('id', $poId)->update(['total_amount' => $total]);
                }
            }

            // 施工日志 (construction 阶段及之后)
            if (in_array($p['stage'], ['construction', 'settlement', 'warranty'])) {
                $logCount = match ($p['stage']) {
                    'construction' => mt_rand(8, 15),
                    'settlement' => 20,
                    'warranty' => 25,
                    default => 0,
                };
                for ($d = 0; $d < $logCount; $d++) {
                    $workDate = (clone $startDate)->addDays(mt_rand(15, 100));
                    if ($workDate->isFuture()) continue;
                    DB::table('construction_logs')->insertOrIgnore([
                        'project_id' => $pid,
                        'user_id' => $this->userIdList[array_rand($this->userIdList)],
                        'work_date' => $workDate->format('Y-m-d'),
                        'weather' => ['晴', '多云', '阴', '小雨'][$d % 4],
                        'content' => ['摄像头安装', '线缆敷设', '机柜组装', '设备调试', '网络配置', '客户培训'][$d % 6] . '，当日完成 ' . mt_rand(50, 100) . '%',
                        'problems' => $d % 5 === 0 ? '部分线槽需要重新开挖' : null,
                        'solutions' => $d % 5 === 0 ? '已与甲方协调调整' : null,
                        'work_hours' => mt_rand(6, 10) + 0.0,
                        'location' => '楼栋' . mt_rand(1, 5) . '层',
                        'status' => 'submitted',
                        'created_at' => $workDate,
                        'updated_at' => $workDate,
                    ]);
                }

                // 项目材料领用
                $matCount = mt_rand(3, 5);
                for ($m = 0; $m < $matCount; $m++) {
                    $itemId = $this->inventoryIds[array_rand($this->inventoryIds)];
                    $item = DB::table('inventory_items')->where('id', $itemId)->first();
                    if (!$item) continue;
                    $qty = mt_rand(2, 15);
                    $matDate = (clone $startDate)->addDays(mt_rand(30, 90));
                    if ($matDate->isFuture()) continue;
                    DB::table('project_materials')->insertOrIgnore([
                        'project_id' => $pid,
                        'material_name' => $item->name,
                        'specification' => $item->specification,
                        'quantity' => $qty,
                        'unit' => $item->unit,
                        'unit_cost' => $item->cost_price,
                        'total_cost' => $qty * $item->cost_price,
                        'used_by' => $this->userIdList[array_rand($this->userIdList)],
                        'use_date' => $matDate->format('Y-m-d'),
                        'inventory_item_id' => $itemId,
                        'notes' => '项目' . $p['name'] . ' 领用',
                        'created_at' => $matDate,
                        'updated_at' => $matDate,
                    ]);
                }

                // 项目结算
                if (in_array($p['stage'], ['settlement', 'warranty'])) {
                    $totalIncome = $budget;
                    $totalCost = $budgetDevice * 0.85 + $budgetMaterial + $budgetLabor * 0.7;
                    $profit = $totalIncome - $totalCost;
                    DB::table('project_settlements')->insertOrIgnore([
                        'project_id' => $pid,
                        'total_income' => $totalIncome,
                        'total_cost' => $totalCost,
                        'cost_labor' => $budgetLabor * 0.7,
                        'cost_material' => $budgetMaterial,
                        'cost_outsource' => $budgetOutsource * 0.6,
                        'cost_other' => $budgetOther * 0.5,
                        'profit' => $profit,
                        'profit_rate' => round($profit / $totalIncome * 100, 2),
                        'settlement_date' => (clone $endDate)->subDays(mt_rand(10, 30))->format('Y-m-d'),
                        'status' => 'settled',
                        'notes' => '项目已结算，最终毛利率 ' . round($profit / $totalIncome * 100, 1) . '%',
                        'created_at' => $endDate,
                        'updated_at' => $endDate,
                    ]);
                }
            }
        }

        // 维保合同 (5 个)
        for ($i = 0; $i < 5; $i++) {
            $pid = $this->projectIds[$i] ?? null;
            if (!$pid) continue;
            $p = DB::table('projects')->where('id', $pid)->first();
            if (!$p) continue;
            DB::table('maintenance_contracts')->insertOrIgnore([
                'contract_no' => 'MC-' . date('Ymd') . '-' . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
                'customer_id' => $p->customer_id,
                'amount' => mt_rand(10000, 50000) + 0.00,
                'start_date' => $p->end_date ?: $this->now->copy()->subMonths(1)->format('Y-m-d'),
                'end_date' => $p->end_date ? (new \DateTime($p->end_date))->modify('+1 year')->format('Y-m-d') : $this->now->copy()->addYear()->format('Y-m-d'),
                'inspection_frequency' => 'quarterly',
                'scope' => '监控系统全包：摄像头/门禁/报警日常巡检 + 故障应急 + 远程技术支持',
                'status' => 'active',
                'notes' => '客户' . ($this->customerMap ? array_search($p->customer_id, $this->customerMap) : '') . '的设备维保合同',
                'created_at' => $p->end_date ?: $this->now->copy(),
                'updated_at' => $this->now->copy()->subDays(5),
            ]);
        }

        $this->command->info("  ✓ 项目: " . count($this->projectIds) . " 个 | 合同 " . DB::table('project_contracts')->count() . " | 付款节点 " . DB::table('contract_payment_nodes')->count() . " | 采购 " . DB::table('purchase_orders')->count() . " | 施工日志 " . DB::table('construction_logs')->count());
    }

    /**
     * 售后工单：25 个
     */
    private function seedServiceOrders(): void
    {
        $faults = [
            '监控摄像头无图像', '门禁刷卡无响应', '录像机硬盘故障', '网络断线',
            '夜视红外灯失效', '云台转动异常', '门禁主板故障', '电源适配器损坏',
            '客户端软件崩溃', '报警主机离线', '硬盘录像机死机', '交换机端口异常',
        ];
        $devSerials = array_keys($this->deviceMap);
        $count = 0;
        foreach (array_slice($this->customerMap, 0, 5, true) as $cname => $cid) {
            for ($i = 0; $i < mt_rand(3, 5); $i++) {
                $days = mt_rand(1, 150);
                $created = $this->now->copy()->subDays($days);
                $urgency = ['normal', 'normal', 'normal', 'urgent', 'critical'][mt_rand(0, 4)];
                $serviceType = ['warranty', 'warranty', 'paid'][$i % 3];
                if ($days > 120) $status = 'confirmed';
                elseif ($days > 60) $status = 'completed';
                elseif ($days > 30) $status = 'in_progress';
                elseif ($days > 7) $status = 'assigned';
                else $status = 'pending';

                $pid = $this->projectIds[array_rand($this->projectIds)] ?? null;
                $serial = !empty($devSerials) ? $devSerials[array_rand($devSerials)] : null;
                $devId = $serial ? ($this->deviceMap[$serial] ?? null) : null;
                $slaHours = ['critical' => 4, 'urgent' => 12, 'normal' => 24][$urgency];

                $row = [
                    'order_no' => 'SO-' . date('Ymd', strtotime($created)) . '-' . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
                    'customer_id' => $cid,
                    'project_id' => $pid,
                    'customer_device_id' => $devId,
                    'fault_description' => $faults[array_rand($faults)] . '，请尽快安排工程师上门',
                    'fault_photos' => null,
                    'urgency' => $urgency,
                    'service_type' => $serviceType,
                    'status' => $status,
                    'assigned_to' => in_array($status, ['pending']) ? null : ($this->userIds['zhaodc'] ?? 4),
                    'assigned_at' => !in_array($status, ['pending']) ? (clone $created)->addHours(mt_rand(1, 4)) : null,
                    'started_at' => in_array($status, ['in_progress', 'completed', 'confirmed']) ? (clone $created)->addHours(mt_rand(4, 8)) : null,
                    'completed_at' => in_array($status, ['completed', 'confirmed']) ? (clone $created)->addHours(mt_rand(8, 24)) : null,
                    'confirmed_at' => $status === 'confirmed' ? (clone $created)->addHours(mt_rand(24, 48)) : null,
                    'rating' => in_array($status, ['completed', 'confirmed']) ? mt_rand(3, 5) : null,
                    'review' => $status === 'confirmed' ? '服务及时专业，工程师态度好' : null,
                    'created_by' => $this->userIds['manager'] ?? 2,
                    'sla_hours' => $slaHours,
                    'created_at' => $created,
                    'updated_at' => $this->now->copy()->subDays(1),
                ];
                $soId = DB::table('service_orders')->insertGetId($row);
                $count++;

                // 跟进日志
                $logCount = match ($status) {
                    'pending' => 1, 'assigned' => 2, 'in_progress' => 3, 'completed' => 4, 'confirmed' => 5, default => 1,
                };
                $logActions = ['已派单，等待工程师联系', '工程师已到现场，正在排查', '已找到故障原因，开始处理', '维修完成，设备已恢复正常', '客户已确认服务完成，评价 5 星'];
                for ($l = 0; $l < $logCount; $l++) {
                    $logTime = (clone $created)->addHours(mt_rand(1, 6) * ($l + 1));
                    if ($logTime->isFuture()) break;
                    DB::table('service_order_logs')->insertOrIgnore([
                        'service_order_id' => $soId,
                        'user_id' => $this->userIdList[array_rand($this->userIdList)],
                        'action' => $logActions[$l] ?? '处理中',
                        'content' => '工单处理进度更新：' . ($logActions[$l] ?? '处理中'),
                        'photos' => null,
                        'location' => '客户现场',
                        'gps_lat' => 22.5 + mt_rand(0, 50) / 100,
                        'gps_lng' => 113.9 + mt_rand(0, 50) / 100,
                        'created_at' => $logTime,
                        'updated_at' => $logTime,
                    ]);
                }

                // 已完成/已确认工单使用备件
                if (in_array($status, ['completed', 'confirmed']) && !empty($this->inventoryMap)) {
                    $partItems = (array) array_rand($this->inventoryMap, mt_rand(1, 3));
                    foreach ($partItems as $itemKey) {
                        $itemId = is_int($itemKey) ? $this->inventoryIds[$itemKey] : ($this->inventoryMap[$itemKey] ?? null);
                        if (!$itemId) continue;
                        $item = DB::table('inventory_items')->where('id', $itemId)->first();
                        if (!$item) continue;
                        $qty = mt_rand(1, 3);
                        DB::table('service_order_parts')->insertOrIgnore([
                            'service_order_id' => $soId,
                            'inventory_item_id' => $itemId,
                            'part_name' => $item->name,
                            'quantity' => $qty,
                            'unit_cost' => $item->cost_price,
                            'total_cost' => $qty * $item->cost_price,
                            'created_at' => $logTime ?? $created,
                            'updated_at' => $logTime ?? $created,
                        ]);
                    }
                }
            }
        }
        $this->command->info("  ✓ 售后工单: $count 个 | 工单日志: " . DB::table('service_order_logs')->count() . " | 备件: " . DB::table('service_order_parts')->count() . " | 维保: " . DB::table('maintenance_contracts')->count());
    }

    /**
     * 知识库 + 网盘 + 消息
     */
    private function seedKnowledgeAndDisk(): void
    {
        // 知识库分类
        $categories = [
            ['name' => '产品手册', 'slug' => 'product', 'sort_order' => 1],
            ['name' => '技术文档', 'slug' => 'tech', 'sort_order' => 2],
            ['name' => '施工规范', 'slug' => 'construction', 'sort_order' => 3],
            ['name' => '常见问题', 'slug' => 'faq', 'sort_order' => 4],
            ['name' => '培训资料', 'slug' => 'training', 'sort_order' => 5],
        ];
        $catMap = [];
        foreach ($categories as $c) {
            $existing = DB::table('knowledge_categories')->where('slug', $c['slug'])->value('id');
            if ($existing) {
                $catMap[$c['slug']] = $existing;
                continue;
            }
            $row = $c;
            $row['description'] = $c['name'];
            $row['created_at'] = $this->now->copy()->subMonths(6);
            $row['updated_at'] = $this->now->copy()->subDays(2);
            $catMap[$c['slug']] = DB::table('knowledge_categories')->insertGetId($row);
        }

        // 知识库文章
        $articles = [
            ['title' => '海康威视 400万摄像头 快速配置指南', 'category' => 'product', 'view_count' => 1250, 'is_featured' => true],
            ['title' => '人脸识别门禁系统施工规范 v2.0',     'category' => 'construction', 'view_count' => 856, 'is_featured' => true],
            ['title' => '网络录像机硬盘故障排查手册',         'category' => 'tech', 'view_count' => 642],
            ['title' => '监控摄像头常见故障 FAQ',             'category' => 'faq', 'view_count' => 920],
            ['title' => '弱电工程项目竣工验收流程',           'category' => 'construction', 'view_count' => 458],
            ['title' => '客户使用培训 - 监控系统日常维护',     'category' => 'training', 'view_count' => 367],
            ['title' => '海康威视 iVMS-8700 平台使用手册',     'category' => 'product', 'view_count' => 534],
            ['title' => 'POE 交换机选型指南',                  'category' => 'tech', 'view_count' => 421],
            ['title' => '门禁系统权限配置最佳实践',           'category' => 'tech', 'view_count' => 312],
            ['title' => '安防工程报价规范 (2026 版)',         'category' => 'construction', 'view_count' => 723, 'is_featured' => true],
        ];
        foreach ($articles as $i => $a) {
            $existing = DB::table('knowledge_articles')->where('title', $a['title'])->value('id');
            if ($existing) continue;
            $row = $a;
            $row['category_id'] = $catMap[$a['category']];
            unset($row['category']);
            $row['slug'] = 'art-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $row['summary'] = '本文介绍' . $a['title'] . '的详细内容。';
            $row['content'] = '<h2>概述</h2><p>本指南详细介绍' . $a['title'] . '的实操步骤。</p><h2>步骤</h2><ol><li>准备工作</li><li>实施步骤</li><li>验收标准</li></ol><h2>常见问题</h2><p>如有疑问请联系技术支持。</p>';
            $row['author_id'] = $this->userIds['manager'] ?? 2;
            $row['status'] = 'published';
            $row['published_at'] = $this->now->copy()->subMonths(mt_rand(1, 6));
            $row['created_at'] = $this->now->copy()->subMonths(mt_rand(1, 6));
            $row['updated_at'] = $this->now->copy()->subDays(mt_rand(1, 30));
            DB::table('knowledge_articles')->insertOrIgnore($row);
        }

        // 网盘文件夹 (3 个一级 + 3 个子文件夹)
        $folders = [
            ['name' => '项目资料', 'parent_id' => null, 'path' => '/项目资料', 'created_by' => $this->userIds['admin'] ?? 1, 'is_system' => false],
            ['name' => '产品资料', 'parent_id' => null, 'path' => '/产品资料', 'created_by' => $this->userIds['manager'] ?? 2, 'is_system' => false],
            ['name' => '公司制度', 'parent_id' => null, 'path' => '/公司制度', 'created_by' => $this->userIds['admin'] ?? 1, 'is_system' => false],
        ];
        $parentFolderIds = [];
        foreach ($folders as $f) {
            $existing = DB::table('disk_folders')->where('name', $f['name'])->where('parent_id', null)->value('id');
            if ($existing) {
                $parentFolderIds[$f['name']] = $existing;
                continue;
            }
            $row = $f;
            $row['created_at'] = $this->now->copy()->subMonths(mt_rand(1, 6));
            $row['updated_at'] = $this->now->copy()->subDays(5);
            $id = DB::table('disk_folders')->insertGetId($row);
            $parentFolderIds[$f['name']] = $id;
        }
        // 子文件夹
        $subFolders = ['华润万家南山店', '前海自贸区一期', '比亚迪坪山工厂'];
        foreach ($subFolders as $sf) {
            $existing = DB::table('disk_folders')->where('name', $sf)->value('id');
            if ($existing) continue;
            $parentId = $parentFolderIds['项目资料'];
            $row = [
                'name' => $sf,
                'parent_id' => $parentId,
                'path' => '/项目资料/' . $sf,
                'created_by' => $this->userIds['manager'] ?? 2,
                'is_system' => false,
                'created_at' => $this->now->copy()->subMonths(3),
                'updated_at' => $this->now->copy()->subDays(3),
            ];
            DB::table('disk_folders')->insertOrIgnore($row);
        }

        // 网盘文件
        $files = [
            ['folder' => '华润万家南山店', 'name' => '施工图_v2.dwg', 'size' => 2048576, 'ext' => 'dwg'],
            ['folder' => '华润万家南山店', 'name' => '设备清单.xlsx', 'size' => 102400, 'ext' => 'xlsx'],
            ['folder' => '华润万家南山店', 'name' => '验收报告.pdf', 'size' => 524288, 'ext' => 'pdf'],
            ['folder' => '前海自贸区一期', 'name' => '设计方案.docx', 'size' => 256000, 'ext' => 'docx'],
            ['folder' => '前海自贸区一期', 'name' => '施工进度表.xlsx', 'size' => 51200, 'ext' => 'xlsx'],
            ['folder' => '比亚迪坪山工厂', 'name' => '现场照片合集.zip', 'size' => 10485760, 'ext' => 'zip'],
            ['folder' => '产品资料', 'name' => '海康2026产品手册.pdf', 'size' => 8388608, 'ext' => 'pdf'],
            ['folder' => '产品资料', 'name' => '大华产品选型指南.pdf', 'size' => 4194304, 'ext' => 'pdf'],
            ['folder' => '公司制度', 'name' => '员工手册2026版.pdf', 'size' => 1048576, 'ext' => 'pdf'],
            ['folder' => '公司制度', 'name' => '考勤管理制度.docx', 'size' => 204800, 'ext' => 'docx'],
        ];
        foreach ($files as $f) {
            $folder = DB::table('disk_folders')->where('name', $f['folder'])->first();
            if (!$folder) continue;
            $existing = DB::table('disk_files')->where('name', $f['name'])->value('id');
            if ($existing) continue;
            DB::table('disk_files')->insertOrIgnore([
                'folder_id' => $folder->id,
                'name' => $f['name'],
                'original_name' => $f['name'],
                'path' => $folder->path . '/' . $f['name'],
                'size' => $f['size'],
                'extension' => $f['ext'],
                'mime_type' => 'application/' . $f['ext'],
                'uploaded_by' => $this->userIdList[array_rand($this->userIdList)],
                'created_at' => $this->now->copy()->subMonths(mt_rand(1, 5)),
                'updated_at' => $this->now->copy()->subDays(mt_rand(1, 30)),
            ]);
        }

        // 消息
        $notifTemplates = ['您有一个新的项目待处理', '报销审批已通过', '工单已派工给赵大成', '系统将于今晚 22:00 进行维护', '客户来电：合同尾款催收', '新员工入职：需要分配账号', '本月考勤报表已生成', '车辆年检到期提醒', '库存物品低于安全库存', '项目进度更新：华润万家南山店'];
        $notifTypes = ['system', 'task', 'approval', 'alert'];
        for ($i = 0; $i < 20; $i++) {
            $uid = $this->userIdList[array_rand($this->userIdList)];
            $type = $notifTypes[array_rand($notifTypes)];
            $tpl = $notifTemplates[array_rand($notifTemplates)];
            $created = $this->now->copy()->subDays(mt_rand(0, 30));
            DB::table('notifications')->insertOrIgnore([
                'type' => $type,
                'title' => $tpl,
                'content' => $tpl . '，请及时处理。',
                'notifiable_id' => $uid,
                'notifiable_type' => 'user',
                'sender_id' => $this->userIds['admin'] ?? 1,
                'level' => $type,
                'data' => null,
                'read_at' => mt_rand(0, 1) === 1 ? $created : null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }

        $this->command->info("  ✓ 知识库: " . DB::table('knowledge_articles')->count() . " 篇 | 网盘: " . DB::table('disk_folders')->count() . " 文件夹 " . DB::table('disk_files')->count() . " 文件 | 消息: " . DB::table('notifications')->count());
    }

    /**
     * 考勤 + 报销 + 审批
     */
    private function seedAttendanceExpense(): void
    {
        // 考勤 (每个员工 22 个工作日)
        foreach ($this->userIdList as $uid) {
            for ($d = 0; $d < 30; $d++) {
                $date = $this->now->copy()->subDays($d);
                if ($date->isWeekend()) continue;
                $clockIn = $date->copy()->setTime(8, mt_rand(45, 60), 0);
                $clockOut = $date->copy()->setTime(17, mt_rand(30, 60), 0);
                DB::table('attendance_records')->insertOrIgnore([
                    'user_id' => $uid,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => $clockIn->format('H:i:s'),
                    'clock_out' => $clockOut->format('H:i:s'),
                    'clock_in_location' => '公司',
                    'clock_out_location' => '客户现场',
                    'clock_in_lat' => 22.5431,
                    'clock_in_lng' => 113.9335,
                    'clock_out_lat' => 22.55 + mt_rand(0, 30) / 100,
                    'clock_out_lng' => 113.93 + mt_rand(0, 30) / 100,
                    'status' => $clockIn->format('H:i') > '09:00' ? 'late' : 'normal',
                    'work_hours' => round($clockOut->diffInMinutes($clockIn) / 60, 2),
                    'overtime_hours' => 0,
                    'project_id' => null,
                    'remark' => null,
                    'created_at' => $clockOut,
                    'updated_at' => $clockOut,
                ]);
            }
        }

        // 请假
        $leaveTypes = ['事假', '病假', '年假', '调休'];
        for ($i = 0; $i < 5; $i++) {
            $uid = $this->userIdList[array_rand($this->userIdList)];
            $type = $leaveTypes[$i % 4];
            $start = $this->now->copy()->subDays(mt_rand(20, 100));
            $days = mt_rand(1, 3);
            $status = $i < 3 ? 'approved' : 'pending';
            DB::table('leave_requests')->insertOrIgnore([
                'user_id' => $uid,
                'type' => $type,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => (clone $start)->addDays($days)->format('Y-m-d'),
                'days' => $days,
                'reason' => ['家中有事需要处理', '感冒发烧需要休息', '已预约的年假', '调休处理私事'][$i % 4],
                'status' => $status,
                'approver_id' => $this->userIds['admin'] ?? 1,
                'approved_at' => $status === 'approved' ? (clone $start)->subDays(2) : null,
                'reject_reason' => null,
                'created_at' => (clone $start)->subDays(5),
                'updated_at' => $this->now->copy()->subDays(2),
            ]);
        }

        // 加班
        for ($i = 0; $i < 5; $i++) {
            $uid = $this->userIdList[array_rand($this->userIdList)];
            $date = $this->now->copy()->subDays(mt_rand(1, 60));
            $startTime = $date->copy()->setTime(18, 0, 0);
            $endTime = $date->copy()->setTime(22, 0, 0);
            $hours = $endTime->diffInHours($startTime);
            $status = $i < 3 ? 'approved' : 'pending';
            DB::table('overtime_requests')->insertOrIgnore([
                'user_id' => $uid,
                'overtime_date' => $date->format('Y-m-d'),
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $endTime->format('H:i:s'),
                'hours' => $hours,
                'reason' => ['项目紧急交付', '客户验收准备', '设备紧急维修', '工程调试加班'][$i % 4],
                'compensation_type' => 'pay',
                'status' => $status,
                'approver_id' => $this->userIds['admin'] ?? 1,
                'approved_at' => $i < 3 ? (clone $date)->subDay() : null,
                'timesheet_leave_hours' => 0,
                'created_at' => (clone $date)->subDays(3),
                'updated_at' => $this->now->copy()->subDays(2),
            ]);
        }

        // 报销
        $expenseTypes = ['差旅费', '招待费', '办公费', '通讯费', '培训费'];
        for ($i = 0; $i < 10; $i++) {
            $uid = $this->userIdList[array_rand($this->userIdList)];
            $projectId = $this->projectIds[array_rand($this->projectIds)] ?? null;
            $type = $expenseTypes[$i % 5];
            $amount = mt_rand(500, 5000) + 0.00;
            $status = $i < 5 ? 'approved' : ($i < 8 ? 'pending' : 'rejected');
            $expenseDate = $this->now->copy()->subDays(mt_rand(5, 90));
            $ecId = DB::table('expense_claims')->insertGetId([
                'claim_no' => 'EX-' . date('Ymd', strtotime($expenseDate)) . '-' . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
                'user_id' => $uid,
                'project_id' => $projectId,
                'category' => $type,
                'total_amount' => $amount,
                'description' => $type . ' - ' . ['深圳出差', '客户接待', '办公用品采购', '客户拜访', '参加培训'][$i % 5],
                'status' => $status,
                'approver_id' => $this->userIds['admin'] ?? 1,
                'approved_at' => $status === 'approved' ? (clone $expenseDate)->addDays(mt_rand(1, 3)) : null,
                'paid_at' => $status === 'approved' ? (clone $expenseDate)->addDays(5) : null,
                'paid_amount' => $status === 'approved' ? $amount : 0,
                'reject_reason' => $status === 'rejected' ? '发票不齐全' : null,
                'created_at' => $expenseDate,
                'updated_at' => $this->now->copy()->subDays(1),
            ]);
            // 报销明细
            $itemCount = mt_rand(2, 3);
            for ($j = 0; $j < $itemCount; $j++) {
                DB::table('expense_items')->insertOrIgnore([
                    'expense_claim_id' => $ecId,
                    'item_date' => $expenseDate->format('Y-m-d'),
                    'description' => ['高铁票', '出租车', '酒店住宿', '餐费', '办公用品', '培训费'][$j % 6],
                    'amount' => round($amount / $itemCount, 2),
                    'category' => ['transport', 'transport', 'accommodation', 'meal', 'office', 'training'][$j % 6],
                    'created_at' => $expenseDate,
                    'updated_at' => $expenseDate,
                ]);
            }
        }

        // 应付账款
        $purchaseOrders = DB::table('purchase_orders')->get();
        foreach (array_slice($purchaseOrders->all(), 0, 8) as $po) {
            DB::table('payables')->insertOrIgnore([
                'project_id' => $po->project_id,
                'supplier_id' => $po->supplier_id,
                'amount' => $po->total_amount,
                'paid_amount' => mt_rand(0, 1) ? $po->total_amount : 0,
                'due_date' => $this->now->copy()->subDays(mt_rand(-30, 60))->format('Y-m-d'),
                'status' => mt_rand(0, 1) ? 'paid' : 'pending',
                'notes' => '采购订单 ' . $po->po_no . ' 应付账款',
                'created_at' => $po->created_at,
                'updated_at' => $this->now->copy()->subDays(2),
            ]);
        }

        // 审批记录
        $approvalTemplates = ['请假审批', '报销审批', '采购审批', '合同审批', '用车审批', '加班审批'];
        for ($i = 0; $i < 15; $i++) {
            DB::table('approval_records')->insertOrIgnore([
                'user_id' => $this->userIdList[array_rand($this->userIdList)],
                'approvable_type' => 'order',
                'approvable_id' => mt_rand(1, 30),
                'action' => $approvalTemplates[$i % 6],
                'comment' => '申请内容详情：' . ['深圳客户现场勘察', '采购海康摄像头一批', '签订前海项目合同', '比亚迪项目用车'][$i % 4],
                'status' => ['approved', 'pending', 'rejected'][$i % 3],
                'created_at' => $this->now->copy()->subDays(mt_rand(5, 60)),
                'updated_at' => $this->now->copy()->subDays(1),
            ]);
        }

        $this->command->info("  ✓ 考勤: " . DB::table('attendance_records')->count() . " | 请假: " . DB::table('leave_requests')->count() . " | 加班: " . DB::table('overtime_requests')->count() . " | 报销: " . DB::table('expense_claims')->count() . " | 应付: " . DB::table('payables')->count() . " | 审批: " . DB::table('approval_records')->count());
    }

    private function printSummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 数据汇总：');
        $tables = [
            'users' => '用户', 'customers' => '客户', 'customer_contacts' => '客户联系人',
            'customer_devices' => '客户设备', 'suppliers' => '供应商', 'warehouses' => '仓库',
            'inventory_items' => '库存物品', 'stock_records' => '库存记录', 'projects' => '项目',
            'project_contracts' => '项目合同', 'contract_payment_nodes' => '付款节点',
            'purchase_orders' => '采购订单', 'construction_logs' => '施工日志',
            'project_materials' => '项目材料', 'project_settlements' => '项目结算',
            'service_orders' => '工单', 'maintenance_contracts' => '维保合同',
            'vehicles' => '车辆', 'vehicle_maintenance_records' => '车辆维护',
            'attendance_records' => '考勤记录', 'expense_claims' => '报销',
            'leave_requests' => '请假', 'overtime_requests' => '加班',
            'receivables' => '应收', 'payables' => '应付', 'approval_records' => '审批',
            'knowledge_articles' => '知识库', 'disk_files' => '网盘文件', 'notifications' => '消息',
        ];
        foreach ($tables as $tbl => $label) {
            try {
                $cnt = DB::table($tbl)->count();
                $this->command->info("  - {$label}: {$cnt}");
            } catch (\Throwable $e) { /* ignore */ }
        }
    }
}
