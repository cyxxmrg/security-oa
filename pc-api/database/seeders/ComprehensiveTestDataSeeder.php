<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * v0.3.10 综合测试数据 Seeder
 *
 * 生成符合真实业务流程的全量数据，覆盖 15 个业务模块：
 * - 工作台/考勤 (attendance_records / leave_requests / overtime_requests)
 * - 员工 (employee_profiles / certificates / employee_skills)
 * - 客户 (customers / customer_contacts / follow_up_records / customer_devices)
 * - 项目 (projects / project_members / project_contracts / contract_payment_nodes)
 * - 项目-采购 (suppliers / purchase_orders / purchase_items)
 * - 项目-施工 (construction_logs / project_materials)
 * - 项目-结算 (project_settlements)
 * - 售后 (service_orders / service_order_logs / service_order_parts)
 * - 维保 (maintenance_contracts)
 * - 报销 (expense_claims / expense_items)
 * - 审批 (approval_records)
 * - 车辆 (vehicles / vehicle_insurance / vehicle_maintenance_records / vehicle_usage_requests)
 * - 库存 (warehouses / inventory_items / stock_records / device_serial_numbers)
 * - 财务 (receivables / payables)
 * - 网盘 (disk_folders / disk_files)
 * - 知识库 (knowledge_categories / knowledge_articles)
 * - 消息 (notifications)
 *
 * 设计原则：
 * 1. 时间分布：过去 6 个月到未来 3 个月，覆盖各种状态
 * 2. 状态分布：pending / in_progress / completed / confirmed 等都有
 * 3. 关联完整性：FK 引用真实 ID（避免孤儿）
 * 4. 业务闭环：项目 → 合同 → 应收/收款 / 派工 → 工单 → 备件 → 库存扣减
 */
class ComprehensiveTestDataSeeder extends Seeder
{
    /** 用户ID缓存 */
    private $userIds = [];
    private $adminId;
    private $managerId;
    private $userId;
    private $zhaodcId;
    private $chenjingId;

    /** 部门ID缓存 */
    private $deptIds = [];
    private $deptMap = [];  // name => id

    /** 客户ID缓存 */
    private $customerIds = [];
    private $customerMap = [];  // name => id

    /** 项目ID缓存 */
    private $projectIds = [];
    private $projectMap = [];  // no => id

    /** 车辆ID缓存 */
    private $vehicleIds = [];

    /** 仓库ID缓存 */
    private $warehouseIds = [];
    private $warehouseMap = [];  // code => id

    /** 库存物品ID缓存 */
    private $inventoryIds = [];
    private $inventoryMap = [];  // code => id

    public function run(): void
    {
        $this->command->info('🚀 开始生成全流程测试数据...');

        // 0. 准备用户和部门
        $this->loadExistingData();

        // 1. 扩展员工档案 (基础用户已有)
        $this->seedEmployeeProfiles();
        $this->seedCertificates();
        $this->seedEmployeeSkills();

        // 2. 扩展客户
        $this->seedCustomers();
        $this->seedCustomerContacts();
        $this->seedFollowUpRecords();
        $this->seedCustomerDevices();

        // 3. 供应商
        $this->seedSuppliers();

        // 4. 仓库 + 库存
        $this->seedWarehouses();
        $this->seedInventoryItems();
        $this->seedStockRecords();
        $this->seedDeviceSerialNumbers();

        // 5. 车辆
        $this->seedVehicles();
        $this->seedVehicleInsurance();
        $this->seedVehicleMaintenanceRecords();
        $this->seedVehicleUsageRequests();

        // 6. 项目
        $this->seedProjects();
        $this->seedProjectMembers();
        $this->seedProjectContracts();
        $this->seedContractPaymentNodes();
        $this->seedPurchaseOrders();
        $this->seedPurchaseItems();
        $this->seedConstructionLogs();
        $this->seedProjectMaterials();
        $this->seedProjectSettlements();

        // 7. 售后
        $this->seedServiceOrders();
        $this->seedServiceOrderLogs();
        $this->seedServiceOrderParts();
        $this->seedMaintenanceContracts();

        // 8. 考勤 / 请假 / 加班
        $this->seedAttendanceRecords();
        $this->seedLeaveRequests();
        $this->seedOvertimeRequests();

        // 9. 报销 + 审批
        $this->seedExpenseClaims();
        $this->seedExpenseItems();
        $this->seedApprovalRecords();

        // 10. 财务
        $this->seedReceivables();
        $this->seedPayables();

        // 11. 网盘 + 知识库 + 消息
        $this->seedDiskFolders();
        $this->seedDiskFiles();
        $this->seedKnowledgeCategories();
        $this->seedKnowledgeArticles();
        $this->seedNotifications();

        $this->command->info('✅ 全流程测试数据生成完成！');
        $this->printSummary();
    }

    // ==================== 0. 准备 ====================

    private function loadExistingData(): void
    {
        // 加载已有用户ID（按用户名）
        $users = DB::table('users')->select('id', 'username')->get();
        foreach ($users as $u) {
            $this->userIds[$u->username] = $u->id;
        }
        $this->adminId    = $this->userIds['admin']    ?? 1;
        $this->managerId  = $this->userIds['manager']  ?? 2;
        $this->userId     = $this->userIds['user']     ?? 3;
        $this->zhaodcId   = $this->userIds['zhaodc']   ?? 4;
        $this->chenjingId = $this->userIds['chenjing'] ?? 5;

        // 加载已有部门ID
        $depts = DB::table('departments')->select('id', 'name')->get();
        foreach ($depts as $d) {
            $this->deptIds[] = $d->id;
            $this->deptMap[$d->name] = $d->id;
        }

        // 加载已有客户ID
        $custs = DB::table('customers')->select('id', 'name')->get();
        foreach ($custs as $c) {
            $this->customerIds[] = $c->id;
            $this->customerMap[$c->name] = $c->id;
        }

        $this->command->info("  ✓ 已加载 " . count($this->userIds) . " 个用户, " . count($this->deptMap) . " 个部门, " . count($this->customerMap) . " 个客户");
    }

    // ==================== 1. 员工档案 ====================

    private function seedEmployeeProfiles(): void
    {
        $profiles = [
            // username, employee_no, hire_date, base_salary
            ['admin',    'EMP-0001', '2020-03-15', 25000.00, '总经办'],
            ['manager',  'EMP-0002', '2020-05-20', 18000.00, '技术部'],
            ['user',     'EMP-0003', '2021-02-10', 9000.00,  '销售部'],
            ['zhaodc',   'EMP-0004', '2021-06-01', 15000.00, '技术部'],
            ['chenjing', 'EMP-0005', '2021-08-15', 12000.00, '财务部'],
        ];

        foreach ($profiles as [$username, $empNo, $hireDate, $salary, $deptName]) {
            $uid = $this->userIds[$username] ?? null;
            if (!$uid) continue;

            DB::table('employee_profiles')->insertOrIgnore([
                'user_id'             => $uid,
                'employee_no'         => $empNo,
                'hire_date'           => $hireDate,
                'contract_type'       => 'open',
                'contract_start'      => $hireDate,
                'contract_end'        => null,
                'base_salary'         => $salary,
                'salary_allowance'    => 0,
                'emergency_contact'   => '家属',
                'emergency_phone'     => '13900000000',
                'bank_name'           => '招商银行',
                'bank_account'        => '622588' . str_pad((string)$uid, 12, '0', STR_PAD_LEFT),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
        $this->command->info('  ✓ employee_profiles: 5 个员工档案');
    }

    private function seedCertificates(): void
    {
        $certs = [
            // user_id, name, no, issue_date, expire_date
            [$this->zhaodcId,   '安全工程师证书', 'SEC-2018-088', '2018-09-15', '2026-09-15'],
            [$this->managerId,  '一级建造师',     'BUILDER-001',  '2019-03-20', '2027-03-20'],
            [$this->zhaodcId,   '电工证',         'ELEC-2020-77', '2020-08-10', '2026-08-10'],
            [$this->userId,     '弱电工程师',     'WEAK-2021-33', '2021-05-12', '2027-05-12'],
            [$this->adminId,    '高级管理师',     'MGMT-2015-09', '2015-11-20', '2025-11-20'],  // 即将过期
        ];

        // 缓存 user_id => employee_profile_id
        $profileMap = DB::table('employee_profiles')->pluck('id', 'user_id');

        foreach ($certs as [$uid, $name, $no, $issue, $expire]) {
            $pid = $profileMap[$uid] ?? null;
            if (!$pid) continue;
            DB::table('certificates')->insertOrIgnore([
                'employee_profile_id' => $pid,
                'certificate_name'    => $name,
                'certificate_no'      => $no,
                'issue_date'          => $issue,
                'expire_date'         => $expire,
                'issuer'              => '人力资源和社会保障部',
                'status'              => strtotime($expire) > time() ? 'valid' : 'expired',
                'remind_days'         => 30,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
        $this->command->info('  ✓ certificates: 5 个证书（含 1 个即将过期）');
    }

    private function seedEmployeeSkills(): void
    {
        $skills = DB::table('skill_tags')->pluck('id', 'name');
        if ($skills->isEmpty()) return;

        $profileMap = DB::table('employee_profiles')->pluck('id', 'user_id');

        $map = [
            $this->zhaodcId   => ['监控安装', '网络配置', '报警系统'],
            $this->managerId  => ['监控安装', '门禁调试', '云平台部署', 'CAD设计'],
            $this->userId     => ['门禁调试', '综合布线', '弱电施工'],
            $this->adminId    => ['云平台部署'],
            $this->chenjingId => [],
        ];
        $proficiency = ['beginner', 'intermediate', 'advanced', 'expert'];

        $count = 0;
        foreach ($map as $uid => $names) {
            $pid = $profileMap[$uid] ?? null;
            if (!$pid) continue;
            foreach ($names as $n) {
                $sid = $skills[$n] ?? null;
                if (!$sid) continue;
                DB::table('employee_skills')->insertOrIgnore([
                    'employee_profile_id' => $pid,
                    'skill_tag_id'        => $sid,
                    'proficiency'         => $proficiency[array_rand($proficiency)],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ employee_skills: $count 条员工技能关联");
    }

    // ==================== 2. 客户与设备 ====================

    private function seedCustomers(): void
    {
        $newCustomers = [
            ['name' => '华润万家', 'category' => 'vip', 'industry' => '商业', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区科苑南路88号', 'tags' => ['商场', '连锁'], 'source' => '转介绍'],
            ['name' => '前海自贸区', 'category' => 'vip', 'industry' => '园区', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区前海路100号', 'tags' => ['园区', '政府'], 'source' => '招标'],
            ['name' => '比亚迪工厂', 'category' => 'normal', 'industry' => '工厂', 'city' => '深圳', 'district' => '坪山区', 'address' => '坪山区比亚迪路300号', 'tags' => ['工厂', '大客户'], 'source' => '官网'],
            ['name' => '深圳湾体育中心', 'category' => 'normal', 'industry' => '体育', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区滨海大道3001号', 'tags' => ['体育', '公共'], 'source' => '招标'],
            ['name' => '星河世纪写字楼', 'category' => 'potential', 'industry' => '写字楼', 'city' => '深圳', 'district' => '福田区', 'address' => '福田区中心商务区88号', 'tags' => ['写字楼'], 'source' => '陌拜'],
        ];

        foreach ($newCustomers as $c) {
            $tags = $c['tags'];
            unset($c['tags']);
            $row = array_merge($c, [
                'province'         => '广东',
                'credit_code'      => '91440300MA5' . str_pad((string)DB::table('customers')->count(), 8, 'X', STR_PAD_LEFT),
                'longitude'        => 113.9 + (mt_rand(0, 100) / 1000),
                'latitude'         => 22.5  + (mt_rand(0, 100) / 1000),
                'tags'             => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'status'           => 'active',
                'assigned_user_id' => $this->managerId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            DB::table('customers')->insertOrIgnore($row);
        }
        // 重新加载客户列表
        $this->customerIds = DB::table('customers')->pluck('id')->all();
        $this->customerMap = DB::table('customers')->pluck('id', 'name')->all();
        $this->command->info('  ✓ customers: 扩展 5 个客户 (共 ' . count($this->customerMap) . ')');
    }

    private function seedCustomerContacts(): void
    {
        $contactNames = ['张总', '李经理', '王主管', '陈工', '刘主任', '黄老师', '周厂长'];
        $count = 0;
        foreach (array_slice($this->customerIds, 0, 8) as $cid) {
            for ($i = 0; $i < mt_rand(1, 3); $i++) {
                DB::table('customer_contacts')->insertOrIgnore([
                    'customer_id'   => $cid,
                    'name'          => $contactNames[array_rand($contactNames)],
                    'position'      => ['总经理', '项目经理', '采购主管', '行政总监'][array_rand([0,1,2,3])],
                    'phone'         => '138' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'email'         => 'contact' . $count . '@customer.com',
                    'is_primary'    => $i === 0,
                    'notes'         => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ customer_contacts: $count 条联系人");
    }

    private function seedFollowUpRecords(): void
    {
        $types = ['visit', 'phone', 'wechat', 'email'];
        $contents = [
            '初次拜访，了解客户安防需求',
            '跟进方案报价，客户表示满意',
            '现场勘查，确定摄像头点位',
            '商务谈判，敲定合同细节',
            '施工进度汇报',
            '售后回访，询问使用体验',
        ];
        $count = 0;
        foreach (array_slice($this->customerIds, 0, 8) as $cid) {
            for ($i = 0; $i < mt_rand(2, 5); $i++) {
                $days = mt_rand(1, 180);
                DB::table('follow_up_records')->insertOrIgnore([
                    'customer_id'         => $cid,
                    'user_id'             => $this->managerId,
                    'type'                => $types[array_rand($types)],
                    'content'             => $contents[array_rand($contents)],
                    'next_follow_up_date' => Carbon::now()->subDays(-$days - mt_rand(7, 30)),
                    'next_follow_up_note' => '确认技术方案和报价',
                    'attachments'         => null,
                    'created_at'          => Carbon::now()->subDays($days),
                    'updated_at'          => Carbon::now()->subDays($days),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ follow_up_records: $count 条跟进记录");
    }

    private function seedCustomerDevices(): void
    {
        $deviceTypes = ['camera', 'access', 'alarm', 'intercom'];
        $brands = ['海康威视', '大华', '宇视', '华为', '天地伟业'];
        $locations = ['大门入口', '停车场', '电梯口', '走廊', '机房', '周界', '财务室', '档案室'];
        $count = 0;
        foreach (array_slice($this->customerIds, 0, 8) as $cid) {
            $n = mt_rand(3, 8);
            for ($i = 0; $i < $n; $i++) {
                $type = $deviceTypes[array_rand($deviceTypes)];
                $brand = $brands[array_rand($brands)];
                $installDate = Carbon::now()->subDays(mt_rand(30, 700))->format('Y-m-d');
                // serial_number 全部 ASCII（PG 严格 UTF-8）
                $serial = strtoupper(substr($type, 0, 3)) . '-' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
                DB::table('customer_devices')->insertOrIgnore([
                    'customer_id'      => $cid,
                    'project_id'       => null,
                    'device_name'      => $brand . ' ' . $type . '-' . str_pad((string)($i+1), 3, '0', STR_PAD_LEFT),
                    'device_type'      => $type,
                    'brand'            => $brand,
                    'model'            => 'M' . mt_rand(1000, 9999),
                    'serial_number'    => $serial,
                    'install_location' => $locations[array_rand($locations)],
                    'install_date'     => $installDate,
                    'warranty_end'     => Carbon::parse($installDate)->addYears(2)->format('Y-m-d'),
                    'status'           => ['normal', 'normal', 'normal', 'fault'][array_rand([0,1,2,3])],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ customer_devices: $count 个客户设备");
    }

    // ==================== 3. 供应商 ====================

    private function seedSuppliers(): void
    {
        $suppliers = [
            ['name' => '海康威视深圳分公司', 'contact_person' => '钱总', 'phone' => '13800001111', 'category' => '监控设备', 'rating' => 5],
            ['name' => '大华技术股份',       'contact_person' => '孙经理', 'phone' => '13800002222', 'category' => '监控设备', 'rating' => 5],
            ['name' => '同鑫五金建材',       'contact_person' => '吴老板', 'phone' => '13800003333', 'category' => '五金材料', 'rating' => 4],
            ['name' => '深圳线缆',           'contact_person' => '郑总', 'phone' => '13800004444', 'category' => '线缆',     'rating' => 4],
            ['name' => '宇视科技',           'contact_person' => '冯工', 'phone' => '13800005555', 'category' => '监控设备', 'rating' => 5],
        ];
        foreach ($suppliers as $s) {
            $row = array_merge($s, [
                'email'      => 'supplier' . mt_rand(100, 999) . '@example.com',
                'address'    => '深圳市南山区科技园',
                'status'     => 'active',
                'notes'      => '长期合作',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('suppliers')->insertOrIgnore($row);
        }
        $this->command->info('  ✓ suppliers: 5 个供应商');
    }

    // ==================== 4. 仓库库存 ====================

    private function seedWarehouses(): void
    {
        $warehouses = [
            ['name' => '南山主仓', 'code' => 'WH-NS-01', 'type' => 'main', 'address' => '南山区科技园南路10号', 'manager_id' => $this->zhaodcId],
            ['name' => '宝安分仓', 'code' => 'WH-BA-01', 'type' => 'branch', 'address' => '宝安区西乡街道100号', 'manager_id' => $this->zhaodcId],
            ['name' => '福田器材仓', 'code' => 'WH-FT-01', 'type' => 'branch', 'address' => '福田区华强北路1号', 'manager_id' => $this->userId],
        ];
        foreach ($warehouses as $w) {
            $row = array_merge($w, [
                'status'     => 'active',
                'description'=> null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('warehouses')->insertOrIgnore($row);
        }
        $this->warehouseIds = DB::table('warehouses')->pluck('id')->all();
        $this->warehouseMap = DB::table('warehouses')->pluck('id', 'code')->all();
        $this->command->info('  ✓ warehouses: 3 个仓库');
    }

    private function seedInventoryItems(): void
    {
        $items = [
            // code, name, category, spec, unit, safety, current, cost, sell
            ['INV-CAM-01', '海康400万半球', '摄像头', 'DS-2CD1343', '台', 20, 45, 280, 350],
            ['INV-CAM-02', '大华400万枪机', '摄像头', 'DH-IPC-HFW2431T', '台', 20, 38, 290, 380],
            ['INV-CAM-03', '海康800万球机', '摄像头', 'DS-2DC7423IW', '台', 5, 8, 1850, 2200],
            ['INV-NVR-01', '海康32路NVR', '录像机', 'DS-7932N-K4', '台', 5, 10, 1200, 1500],
            ['INV-SW-01', 'POE交换机24口', '交换机', 'TL-SG1024PE', '台', 8, 15, 480, 620],
            ['INV-CAB-01', '超五类网线', '线缆', 'UTP-305M', '箱', 30, 80, 320, 420],
            ['INV-CAB-02', '电源线RVV2*1.0', '线缆', 'RVV-100M', '卷', 20, 50, 180, 250],
            ['INV-ACC-01', '门禁一体机', '门禁', 'ZK-MF160', '台', 5, 12, 380, 520],
            ['INV-ALA-01', '红外探测器', '报警', 'HB-100', '个', 30, 60, 65, 95],
            ['INV-MNT-01', '监控立杆3米', '辅材', 'IRON-3M', '根', 10, 25, 220, 290],
        ];
        foreach ($items as [$code, $name, $category, $spec, $unit, $safety, $current, $cost, $sell]) {
            $row = [
                'code'          => $code,
                'name'          => $name,
                'category'      => $category,
                'specification' => $spec,
                'unit'          => $unit,
                'safety_stock'  => $safety,
                'current_stock' => $current,
                'cost_price'    => $cost,
                'sell_price'    => $sell,
                'warehouse_id'  => $this->warehouseIds[array_rand($this->warehouseIds)],
                'location'      => 'A-' . mt_rand(1, 9) . '-0' . mt_rand(1, 5),
                'has_serial'    => ($code === 'INV-CAM-01' || $code === 'INV-CAM-02'),
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
            DB::table('inventory_items')->insertOrIgnore($row);
        }
        $this->inventoryMap = DB::table('inventory_items')->pluck('id', 'code')->all();
        $this->command->info('  ✓ inventory_items: 10 个库存物品');
    }

    private function seedStockRecords(): void
    {
        $count = 0;
        foreach (array_slice($this->inventoryMap, 0, 10) as $code => $iid) {
            for ($i = 0; $i < mt_rand(2, 4); $i++) {
                $type = ['in', 'out', 'in'][array_rand([0,1,2])];
                $qty = mt_rand(5, 20);
                $days = mt_rand(5, 200);
                DB::table('stock_records')->insertOrIgnore([
                    'record_no'        => 'STK-' . date('Ymd', strtotime("-$days days")) . '-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'inventory_item_id'=> $iid,
                    'warehouse_id'     => $this->warehouseIds[array_rand($this->warehouseIds)],
                    'type'             => $type,
                    'quantity'         => $qty,
                    'remaining_stock'  => mt_rand(10, 80),
                    'related_id'       => null,
                    'related_type'     => $type === 'in' ? 'purchase' : 'project',
                    'operator_id'      => $this->zhaodcId,
                    'remark'           => $type === 'in' ? '采购入库' : '项目领用',
                    'created_at'       => Carbon::now()->subDays($days),
                    'updated_at'       => Carbon::now()->subDays($days),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ stock_records: $count 条出入库记录");
    }

    private function seedDeviceSerialNumbers(): void
    {
        $serials = [];
        $count = 0;
        foreach (array_slice($this->inventoryMap, 0, 3) as $code => $iid) {
            for ($i = 0; $i < mt_rand(5, 10); $i++) {
                $sn = substr($code, 4, 3) . '-' . str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                if (in_array($sn, $serials)) continue;
                $serials[] = $sn;
                $status = ['in_stock', 'in_stock', 'installed', 'installed', 'in_stock'][array_rand([0,1,2,3,4])];
                DB::table('device_serial_numbers')->insertOrIgnore([
                    'inventory_item_id' => $iid,
                    'serial_number'     => $sn,
                    'status'            => $status,
                    'project_id'        => $status === 'installed' ? null : null,
                    'customer_device_id'=> null,
                    'stock_record_id'   => null,
                    'install_date'      => $status === 'installed' ? Carbon::now()->subDays(mt_rand(10, 200))->format('Y-m-d') : null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ device_serial_numbers: $count 个序列号");
    }

    // ==================== 5. 车辆 ====================

    private function seedVehicles(): void
    {
        $vehicles = [
            ['plate_no' => '粤B·A1234', 'brand' => '丰田', 'model' => '凯美瑞',   'color' => '黑色', 'seats' => 5, 'fuel_type' => 'gasoline', 'purchase_date' => '2021-03-10', 'purchase_price' => 220000, 'status' => 'available', 'responsible_user_id' => $this->adminId],
            ['plate_no' => '粤B·B5678', 'brand' => '本田', 'model' => 'CRV',      'color' => '白色', 'seats' => 5, 'fuel_type' => 'gasoline', 'purchase_date' => '2022-06-15', 'purchase_price' => 240000, 'status' => 'available', 'responsible_user_id' => $this->managerId],
            ['plate_no' => '粤B·C9012', 'brand' => '福特', 'model' => '全顺',     'color' => '银色', 'seats' => 9, 'fuel_type' => 'diesel',   'purchase_date' => '2020-08-22', 'purchase_price' => 180000, 'status' => 'available', 'responsible_user_id' => $this->zhaodcId],
            ['plate_no' => '粤B·D3456', 'brand' => '五菱', 'model' => '宏光',     'color' => '灰色', 'seats' => 7, 'fuel_type' => 'gasoline', 'purchase_date' => '2023-01-10', 'purchase_price' => 65000,  'status' => 'maintenance', 'responsible_user_id' => $this->userId],
        ];
        foreach ($vehicles as $v) {
            $row = array_merge($v, [
                'department_id' => $this->deptMap['技术部'] ?? null,
                'vin'           => 'LSV' . str_pad((string)mt_rand(0, 99999999999999), 14, '0', STR_PAD_LEFT),
                'engine_no'     => 'ENG' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            DB::table('vehicles')->insertOrIgnore($row);
        }
        $this->vehicleIds = DB::table('vehicles')->pluck('id')->all();
        $this->command->info('  ✓ vehicles: ' . count($this->vehicleIds) . ' 辆车');
    }

    private function seedVehicleInsurance(): void
    {
        $count = 0;
        foreach ($this->vehicleIds as $vid) {
            DB::table('vehicle_insurance')->insertOrIgnore([
                'vehicle_id'         => $vid,
                'insurance_company'  => '平安保险',
                'policy_no'          => 'PA' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'type'               => 'comprehensive',
                'premium'            => mt_rand(3000, 8000) + 0.00,
                'start_date'         => Carbon::now()->subDays(mt_rand(30, 200))->format('Y-m-d'),
                'end_date'           => Carbon::now()->addDays(mt_rand(60, 300))->format('Y-m-d'),
                'status'             => 'active',
                'notes'              => '全险',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ vehicle_insurance: $count 条保单");
    }

    private function seedVehicleMaintenanceRecords(): void
    {
        $count = 0;
        foreach ($this->vehicleIds as $vid) {
            for ($i = 0; $i < mt_rand(2, 4); $i++) {
                DB::table('vehicle_maintenance_records')->insertOrIgnore([
                    'vehicle_id'              => $vid,
                    'maintenance_type'        => ['routine', 'repair', 'inspection'][array_rand([0,1,2])],
                    'mileage'                 => mt_rand(10000, 80000),
                    'cost'                    => mt_rand(200, 3000) + 0.00,
                    'maintenance_date'        => Carbon::now()->subDays(mt_rand(30, 365))->format('Y-m-d'),
                    'description'             => '常规保养/维修',
                    'next_maintenance_mileage'=> mt_rand(60000, 100000),
                    'next_maintenance_date'   => Carbon::now()->addDays(mt_rand(30, 180))->format('Y-m-d'),
                    'handled_by'              => $this->userId,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ vehicle_maintenance_records: $count 条维保");
    }

    private function seedVehicleUsageRequests(): void
    {
        $purposes = ['客户现场勘查', '设备采购拉货', '项目验收', '客户回访', '会议出行', '培训出行'];
        $destinations = ['南山区科技园', '宝安区客户现场', '福田区总部', '龙岗区项目地', '光明区工地'];
        $count = 0;
        foreach ($this->vehicleIds as $vid) {
            for ($i = 0; $i < mt_rand(3, 6); $i++) {
                $days = mt_rand(-30, 30);
                $status = $days > 0 ? 'pending' : (mt_rand(0, 1) ? 'approved' : 'completed');
                DB::table('vehicle_usage_requests')->insertOrIgnore([
                    'vehicle_id'   => $vid,
                    'applicant_id' => [$this->userId, $this->managerId, $this->zhaodcId][array_rand([0,1,2])],
                    'usage_date'   => Carbon::now()->addDays($days)->format('Y-m-d'),
                    'start_time'   => '09:00:00',
                    'end_time'     => '18:00:00',
                    'passengers'   => mt_rand(1, 4),
                    'destination'  => $destinations[array_rand($destinations)],
                    'purpose'      => $purposes[array_rand($purposes)],
                    'status'       => $status,
                    'start_mileage'=> $status === 'completed' ? mt_rand(10000, 90000) : null,
                    'end_mileage'  => $status === 'completed' ? mt_rand(10000, 90000) : null,
                    'approver_id'  => $status !== 'pending' ? $this->adminId : null,
                    'created_at'   => Carbon::now()->subDays(mt_rand(0, 60)),
                    'updated_at'   => Carbon::now()->subDays(mt_rand(0, 60)),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ vehicle_usage_requests: $count 条用车申请");
    }

    // ==================== 6. 项目 ====================

    private function seedProjects(): void
    {
        $stages = ['initiation', 'inquiry', 'contract', 'purchase', 'construction', 'settlement', 'warranty'];
        $types = ['camera', 'access', 'alarm', 'integrated'];
        $priorities = ['low', 'medium', 'high'];
        $statuses = ['pending', 'in_progress', 'in_progress', 'in_progress', 'completed'];

        $projectTemplates = [
            '阳光小学视频监控升级',
            '中心医院安防系统改造',
            '科技园区周界防范工程',
            '万达工厂智能门禁',
            '龙城商场人脸识别门禁',
            '华润万家监控全覆盖',
            '前海自贸区智慧园区',
            '比亚迪工厂二期监控',
            '深圳湾体育中心安检升级',
            '星河世纪写字楼智能化',
        ];

        // projects.customer_id 在 schema 里 FK 到 users（schema bug），
        // 所以用 managerId 当 customer_id 兜底。
        $count = 0;
        foreach ($projectTemplates as $i => $name) {
            $stageIdx = min($i, 6);
            $status = $stageIdx >= 5 ? 'completed' : 'in_progress';
            $cid = $this->customerIds[$i % count($this->customerIds)] ?? $this->customerIds[0];
            // 用 managerId 满足 FK 约束（schema bug）
            $customerId = $this->managerId;
            $no = 'PRJ-2026-' . str_pad((string)($i+1), 3, '0', STR_PAD_LEFT);
            $budgetDevice = mt_rand(50000, 200000) + 0.00;
            $budgetMaterial = mt_rand(10000, 50000) + 0.00;
            $budgetLabor = mt_rand(20000, 80000) + 0.00;
            $startDate = Carbon::now()->subDays(mt_rand(30, 180));
            $endDate = (clone $startDate)->addDays(mt_rand(60, 180));
            $row = [
                'project_no'        => $no,
                'name'              => $name,
                'customer_id'       => $customerId,
                'type'              => $types[array_rand($types)],
                'stage'             => $stages[$stageIdx],
                'status'            => $status,
                'description'       => '本项目为' . $name . '，包含设计、施工、调试和培训。',
                'budget_device'     => $budgetDevice,
                'budget_material'   => $budgetMaterial,
                'budget_labor'      => $budgetLabor,
                'budget_outsource'  => 0,
                'budget_other'      => 0,
                'progress'          => $stageIdx * 15,
                'manager_id'        => $this->managerId,
                'start_date'        => $startDate->format('Y-m-d'),
                'end_date'          => $endDate->format('Y-m-d'),
                'actual_end_date'   => $status === 'completed' ? $endDate->format('Y-m-d') : null,
                'priority'          => $priorities[array_rand($priorities)],
                'created_at'        => $startDate,
                'updated_at'        => now(),
            ];
            DB::table('projects')->insertOrIgnore($row);
            $count++;
        }
        $this->projectIds = DB::table('projects')->pluck('id')->all();
        $this->projectMap = DB::table('projects')->pluck('id', 'project_no')->all();
        $this->command->info("  ✓ projects: $count 个项目（覆盖 7 个阶段）");
    }

    private function seedProjectMembers(): void
    {
        $count = 0;
        foreach ($this->projectIds as $pid) {
            // 每个项目分配 1 个经理 + 2-4 个组员
            DB::table('project_members')->insertOrIgnore([
                'project_id' => $pid,
                'user_id'    => $this->managerId,
                'role'       => 'manager',
                'join_date'  => Carbon::now()->subDays(mt_rand(30, 180))->format('Y-m-d'),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;

            $members = [$this->zhaodcId, $this->userId];
            foreach ($members as $mid) {
                DB::table('project_members')->insertOrIgnore([
                    'project_id' => $pid,
                    'user_id'    => $mid,
                    'role'       => 'worker',
                    'join_date'  => Carbon::now()->subDays(mt_rand(20, 150))->format('Y-m-d'),
                    'status'     => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ project_members: $count 条项目成员");
    }

    private function seedProjectContracts(): void
    {
        $suppliers = DB::table('suppliers')->pluck('id')->all();
        $count = 0;
        foreach (array_slice($this->projectIds, 0, 8) as $pid) {
            $amount = mt_rand(150000, 500000) + 0.00;
            $start = Carbon::now()->subDays(mt_rand(60, 180));
            DB::table('project_contracts')->insertOrIgnore([
                'project_id'      => $pid,
                'contract_no'     => 'CT-' . date('Ymd', strtotime($start)) . '-' . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
                'contract_amount' => $amount,
                'payment_method'  => 'installment',
                'contract_start'  => $start->format('Y-m-d'),
                'contract_end'    => (clone $start)->addMonths(6)->format('Y-m-d'),
                'status'          => 'signed',
                'attachment'      => null,
                'notes'           => '合同条款见附件',
                'created_at'      => $start,
                'updated_at'      => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ project_contracts: $count 个合同");
    }

    private function seedContractPaymentNodes(): void
    {
        $contracts = DB::table('project_contracts')->get();
        $count = 0;
        foreach ($contracts as $c) {
            // 3 段式付款：30% 预付 + 50% 进度 + 20% 质保
            $nodes = [
                ['name' => '合同预付款', 'pct' => 30, 'days_offset' => 0],
                ['name' => '项目进度款', 'pct' => 50, 'days_offset' => 60],
                ['name' => '质保金',     'pct' => 20, 'days_offset' => 180],
            ];
            $start = Carbon::parse($c->contract_start);
            foreach ($nodes as $n) {
                $amount = round($c->contract_amount * $n['pct'] / 100, 2);
                $planned = (clone $start)->addDays($n['days_offset']);
                $isPaid = $planned->isPast();
                DB::table('contract_payment_nodes')->insertOrIgnore([
                    'contract_id'  => $c->id,
                    'name'         => $n['name'],
                    'percentage'   => $n['pct'],
                    'amount'       => $amount,
                    'planned_date' => $planned->format('Y-m-d'),
                    'actual_date'  => $isPaid ? $planned->format('Y-m-d') : null,
                    'status'       => $isPaid ? 'paid' : 'pending',
                    'paid_amount'  => $isPaid ? $amount : 0,
                    'notes'        => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ contract_payment_nodes: $count 条付款节点");
    }

    private function seedPurchaseOrders(): void
    {
        $suppliers = DB::table('suppliers')->pluck('id')->all();
        $count = 0;
        foreach (array_slice($this->projectIds, 0, 6) as $pid) {
            $amount = mt_rand(30000, 100000) + 0.00;
            DB::table('purchase_orders')->insertOrIgnore([
                'project_id'     => $pid,
                'supplier_id'    => $suppliers[array_rand($suppliers)],
                'po_no'          => 'PO-2026-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'total_amount'   => $amount,
                'status'         => ['draft', 'approved', 'received'][array_rand([0,1,2])],
                'approved_by'    => $this->adminId,
                'notes'          => '项目设备采购',
                'created_at'     => Carbon::now()->subDays(mt_rand(10, 100)),
                'updated_at'     => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ purchase_orders: $count 个采购单");
    }

    private function seedPurchaseItems(): void
    {
        $orders = DB::table('purchase_orders')->get();
        $items = array_values($this->inventoryMap);
        $count = 0;
        foreach ($orders as $o) {
            for ($i = 0; $i < mt_rand(2, 4); $i++) {
                $iid = $items[array_rand($items)];
                $qty = mt_rand(5, 30);
                $unit = mt_rand(200, 1500) + 0.00;
                DB::table('purchase_items')->insertOrIgnore([
                    'purchase_order_id' => $o->id,
                    'item_name'         => DB::table('inventory_items')->where('id', $iid)->value('name'),
                    'specification'     => '标准',
                    'quantity'          => $qty,
                    'unit'              => '件',
                    'unit_price'        => $unit,
                    'total_price'       => $qty * $unit,
                    'received_quantity' => $o->status === 'received' ? $qty : 0,
                    'created_at'        => $o->created_at,
                    'updated_at'        => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ purchase_items: $count 条采购明细");
    }

    private function seedConstructionLogs(): void
    {
        $weathers = ['晴', '多云', '阴', '小雨', '晴'];
        $statuses = ['submitted', 'approved', 'approved', 'approved'];
        $count = 0;
        foreach (array_slice($this->projectIds, 0, 8) as $pid) {
            for ($i = 0; $i < mt_rand(3, 8); $i++) {
                $days = mt_rand(0, 100);
                DB::table('construction_logs')->insertOrIgnore([
                    'project_id'  => $pid,
                    'user_id'     => $this->zhaodcId,
                    'work_date'   => Carbon::now()->subDays($days)->format('Y-m-d'),
                    'weather'     => $weathers[array_rand($weathers)],
                    'content'     => '线路敷设、摄像头安装、设备调试',
                    'problems'    => mt_rand(0, 1) ? '部分线路需要重新走' : null,
                    'solutions'   => mt_rand(0, 1) ? '已调整方案' : null,
                    'photos'      => null,
                    'work_hours'  => mt_rand(6, 10) + 0.0,
                    'location'    => '项目现场',
                    'status'      => $statuses[array_rand($statuses)],
                    'created_at'  => Carbon::now()->subDays($days),
                    'updated_at'  => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ construction_logs: $count 条施工日志");
    }

    private function seedProjectMaterials(): void
    {
        $items = array_values($this->inventoryMap);
        $count = 0;
        foreach (array_slice($this->projectIds, 0, 8) as $pid) {
            for ($i = 0; $i < mt_rand(3, 6); $i++) {
                $iid = $items[array_rand($items)];
                $qty = mt_rand(2, 20) + 0.00;
                $unit = mt_rand(150, 1000) + 0.00;
                DB::table('project_materials')->insertOrIgnore([
                    'project_id'        => $pid,
                    'material_name'     => DB::table('inventory_items')->where('id', $iid)->value('name'),
                    'specification'     => '标准',
                    'quantity'          => $qty,
                    'unit'              => '件',
                    'unit_cost'         => $unit,
                    'total_cost'        => $qty * $unit,
                    'used_by'           => $this->zhaodcId,
                    'use_date'          => Carbon::now()->subDays(mt_rand(0, 90))->format('Y-m-d'),
                    'inventory_item_id' => $iid,
                    'created_at'        => Carbon::now()->subDays(mt_rand(0, 90)),
                    'updated_at'        => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ project_materials: $count 条领料记录");
    }

    private function seedProjectSettlements(): void
    {
        $count = 0;
        foreach (array_slice($this->projectIds, 4, 5) as $pid) {
            // 只给已完成的项目生成结算
            $project = DB::table('projects')->where('id', $pid)->first();
            if (!$project) continue;
            $income = ($project->budget_device + $project->budget_material + $project->budget_labor) * 1.3;
            $costLabor = $project->budget_labor * 0.9;
            $costMaterial = $project->budget_material * 0.95;
            $costOutsource = $project->budget_outsource;
            $costOther = $project->budget_other;
            $totalCost = $costLabor + $costMaterial + $costOutsource + $costOther;
            $profit = $income - $totalCost;
            $profitRate = $income > 0 ? round($profit / $income * 100, 2) : 0;
            DB::table('project_settlements')->insertOrIgnore([
                'project_id'      => $pid,
                'total_income'    => $income,
                'total_cost'      => $totalCost,
                'cost_labor'      => $costLabor,
                'cost_material'   => $costMaterial,
                'cost_outsource'  => $costOutsource,
                'cost_other'      => $costOther,
                'profit'          => $profit,
                'profit_rate'     => $profitRate,
                'settlement_date' => Carbon::parse($project->actual_end_date ?? $project->end_date)->format('Y-m-d'),
                'status'          => 'completed',
                'notes'           => '项目已结算',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ project_settlements: $count 个结算");
    }

    // ==================== 7. 售后 ====================

    private function seedServiceOrders(): void
    {
        $urgencies = ['normal', 'normal', 'normal', 'urgent', 'critical'];
        $types = ['warranty', 'paid', 'warranty', 'paid'];
        $statuses = ['pending', 'assigned', 'in_progress', 'completed', 'confirmed', 'completed'];
        $faults = [
            '监控摄像头离线',
            '门禁刷卡无响应',
            '录像机无法存储',
            '报警器误报',
            '网络连接中断',
            '电源故障',
            '红外夜视失效',
            '云平台登录异常',
        ];
        $count = 0;
        // service_orders.customer_id 在 schema 里 FK 到 users（schema bug）
        // 用 managerId 兜底
        foreach (array_slice($this->customerIds, 0, 8) as $cid) {
            $n = mt_rand(3, 6);
            for ($i = 0; $i < $n; $i++) {
                $days = mt_rand(1, 90);
                $status = $statuses[array_rand($statuses)];
                $urgency = $urgencies[array_rand($urgencies)];
                $type = $types[array_rand($types)];
                $assignedTo = in_array($status, ['pending']) ? null : $this->zhaodcId;
                $created = Carbon::now()->subDays($days);
                $slaHours = $urgency === 'critical' ? 4 : ($urgency === 'urgent' ? 12 : 24);
                $row = [
                    'order_no'          => 'SO-' . date('Ymd', strtotime($created)) . '-' . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
                    'customer_id'       => $this->managerId,  // schema bug 兜底
                    'project_id'        => $this->projectIds[array_rand($this->projectIds)] ?? null,
                    'customer_device_id'=> null,
                    'fault_description' => $faults[array_rand($faults)],
                    'fault_photos'      => null,
                    'urgency'           => $urgency,
                    'service_type'      => $type,
                    'status'            => $status,
                    'assigned_to'       => $assignedTo,
                    'assigned_at'       => $assignedTo ? (clone $created)->addHours(mt_rand(1, 4)) : null,
                    'started_at'        => in_array($status, ['in_progress', 'completed', 'confirmed']) ? (clone $created)->addHours(mt_rand(4, 8)) : null,
                    'completed_at'      => in_array($status, ['completed', 'confirmed']) ? (clone $created)->addHours(mt_rand(8, 24)) : null,
                    'confirmed_at'      => $status === 'confirmed' ? (clone $created)->addHours(mt_rand(24, 48)) : null,
                    'rating'            => in_array($status, ['completed', 'confirmed']) ? mt_rand(3, 5) : null,
                    'review'            => in_array($status, ['confirmed']) ? '服务及时专业' : null,
                    'created_by'        => $this->managerId,
                    'sla_hours'         => $slaHours,
                    'created_at'        => $created,
                    'updated_at'        => now(),
                ];
                DB::table('service_orders')->insertOrIgnore($row);
                $count++;
            }
        }
        $this->command->info("  ✓ service_orders: $count 个工单（覆盖 6 个状态）");
    }

    private function seedServiceOrderLogs(): void
    {
        $orders = DB::table('service_orders')->whereIn('status', ['assigned', 'in_progress', 'completed', 'confirmed'])->limit(30)->get();
        $count = 0;
        foreach ($orders as $o) {
            $actions = [
                ['action' => 'created',    'content' => '工单创建'],
                ['action' => 'assigned',   'content' => '工单已分派给 ' . DB::table('users')->where('id', $o->assigned_to)->value('name')],
                ['action' => 'started',    'content' => '维修人员已到达现场开始维修'],
                ['action' => 'completed',  'content' => '维修完成，设备已恢复正常'],
            ];
            $logEntries = $o->status === 'assigned' ? array_slice($actions, 0, 2) :
                         ($o->status === 'in_progress' ? array_slice($actions, 0, 3) : $actions);
            foreach ($logEntries as $l) {
                DB::table('service_order_logs')->insertOrIgnore([
                    'service_order_id' => $o->id,
                    'user_id'          => $o->assigned_to ?? $this->managerId,
                    'action'           => $l['action'],
                    'content'          => $l['content'],
                    'photos'           => null,
                    'location'         => '客户现场',
                    'gps_lat'          => 22.5 + mt_rand(0, 100) / 1000,
                    'gps_lng'          => 113.9 + mt_rand(0, 100) / 1000,
                    'created_at'       => $o->created_at,
                    'updated_at'       => $o->created_at,
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ service_order_logs: $count 条工单日志");
    }

    private function seedServiceOrderParts(): void
    {
        $orders = DB::table('service_orders')->whereIn('status', ['completed', 'confirmed'])->limit(20)->get();
        $items = array_values($this->inventoryMap);
        $count = 0;
        foreach ($orders as $o) {
            $n = mt_rand(1, 3);
            for ($i = 0; $i < $n; $i++) {
                $iid = $items[array_rand($items)];
                $qty = mt_rand(1, 5);
                $unit = mt_rand(50, 800) + 0.00;
                DB::table('service_order_parts')->insertOrIgnore([
                    'service_order_id' => $o->id,
                    'inventory_item_id'=> $iid,
                    'part_name'        => DB::table('inventory_items')->where('id', $iid)->value('name'),
                    'quantity'         => $qty,
                    'unit_cost'        => $unit,
                    'total_cost'       => $qty * $unit,
                    'created_at'       => $o->created_at,
                    'updated_at'       => $o->created_at,
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ service_order_parts: $count 条工单备件");
    }

    private function seedMaintenanceContracts(): void
    {
        $count = 0;
        // maintenance_contracts.customer_id 在 schema 里 FK 到 users（schema bug）
        // 用 managerId 兜底
        foreach (array_slice($this->customerIds, 0, 8) as $cid) {
            $amount = mt_rand(20000, 100000) + 0.00;
            $start = Carbon::now()->subDays(mt_rand(30, 300));
            $end = (clone $start)->addYear();
            $isExpiringSoon = $end->diffInDays(Carbon::now()) < 30;
            DB::table('maintenance_contracts')->insertOrIgnore([
                'contract_no'          => 'MC-2026-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'customer_id'          => $this->managerId,  // schema bug 兜底
                'amount'               => $amount,
                'start_date'           => $start->format('Y-m-d'),
                'end_date'             => $end->format('Y-m-d'),
                'inspection_frequency' => ['monthly', 'quarterly', 'semi_annually'][array_rand([0,1,2])],
                'scope'                => '设备巡检、故障处理、备件更换',
                'status'               => $isExpiringSoon ? 'expiring' : 'active',
                'notes'                => '维保合同',
                'created_at'           => $start,
                'updated_at'           => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ maintenance_contracts: $count 个维保合同");
    }

    // ==================== 8. 考勤 / 请假 / 加班 ====================

    private function seedAttendanceRecords(): void
    {
        $statuses = ['normal', 'normal', 'normal', 'normal', 'late', 'field_work'];
        $count = 0;
        // 过去 60 天，所有员工
        foreach ([$this->adminId, $this->managerId, $this->userId, $this->zhaodcId, $this->chenjingId] as $uid) {
            for ($d = 1; $d <= 60; $d++) {
                $date = Carbon::now()->subDays($d);
                if ($date->isWeekend()) continue;  // 周末不上班

                $status = $statuses[array_rand($statuses)];
                $clockIn = '08:' . str_pad((string)mt_rand(0, 59), 2, '0', STR_PAD_LEFT);
                $clockOut = '18:' . str_pad((string)mt_rand(0, 59), 2, '0', STR_PAD_LEFT);
                DB::table('attendance_records')->insertOrIgnore([
                    'user_id'             => $uid,
                    'date'                => $date->format('Y-m-d'),
                    'clock_in'            => $clockIn,
                    'clock_in_location'   => '公司',
                    'clock_in_lat'        => 22.5,
                    'clock_in_lng'        => 113.9,
                    'clock_out'           => $clockOut,
                    'clock_out_location'  => '公司',
                    'clock_out_lat'       => 22.5,
                    'clock_out_lng'       => 113.9,
                    'status'              => $status,
                    'work_hours'          => 8.0,
                    'overtime_hours'      => mt_rand(0, 3) + 0.0,
                    'project_id'          => $status === 'field_work' ? $this->projectIds[array_rand($this->projectIds)] : null,
                    'remark'              => null,
                    'created_at'          => $date,
                    'updated_at'          => $date,
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ attendance_records: $count 条打卡记录");
    }

    private function seedLeaveRequests(): void
    {
        $types = ['annual', 'sick', 'personal', 'marriage', 'maternity'];
        $reasons = ['家中有事', '身体不适需就医', '年假休息', '结婚事宜', '产假'];
        $statuses = ['pending', 'approved', 'approved', 'approved', 'rejected'];
        $count = 0;
        foreach ([$this->adminId, $this->managerId, $this->userId, $this->zhaodcId, $this->chenjingId] as $uid) {
            for ($i = 0; $i < mt_rand(1, 3); $i++) {
                $days = mt_rand(1, 7);
                $startDate = Carbon::now()->addDays(mt_rand(-30, 30));
                $endDate = (clone $startDate)->addDays($days - 1);
                $status = $statuses[array_rand($statuses)];
                DB::table('leave_requests')->insertOrIgnore([
                    'user_id'       => $uid,
                    'type'          => $types[array_rand($types)],
                    'start_date'    => $startDate->format('Y-m-d'),
                    'end_date'      => $endDate->format('Y-m-d'),
                    'days'          => $days + 0.0,
                    'reason'        => $reasons[array_rand($reasons)],
                    'status'        => $status,
                    'approver_id'   => $status !== 'pending' ? $this->adminId : null,
                    'approved_at'   => $status !== 'pending' ? now() : null,
                    'reject_reason' => $status === 'rejected' ? '人手不足，建议改期' : null,
                    'created_at'    => Carbon::now()->subDays(mt_rand(1, 60)),
                    'updated_at'    => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ leave_requests: $count 条请假申请");
    }

    private function seedOvertimeRequests(): void
    {
        $compensationTypes = ['pay', 'compensatory', 'pay'];
        $reasons = ['项目紧急', '客户验收', '设备调试', '紧急维修'];
        $count = 0;
        foreach ([$this->managerId, $this->userId, $this->zhaodcId] as $uid) {
            for ($i = 0; $i < mt_rand(2, 5); $i++) {
                $days = mt_rand(0, 30);
                $status = ['pending', 'approved', 'approved', 'approved'][array_rand([0,1,2,3])];
                DB::table('overtime_requests')->insertOrIgnore([
                    'user_id'              => $uid,
                    'overtime_date'        => Carbon::now()->subDays($days)->format('Y-m-d'),
                    'start_time'           => '18:00:00',
                    'end_time'             => '22:00:00',
                    'hours'                => 4.0,
                    'reason'               => $reasons[array_rand($reasons)],
                    'compensation_type'    => $compensationTypes[array_rand($compensationTypes)],
                    'status'               => $status,
                    'approver_id'          => $status !== 'pending' ? $this->adminId : null,
                    'approved_at'          => $status !== 'pending' ? now() : null,
                    'timesheet_leave_hours'=> 0,
                    'created_at'           => Carbon::now()->subDays($days + 1),
                    'updated_at'           => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ overtime_requests: $count 条加班申请");
    }

    // ==================== 9. 报销 + 审批 ====================

    private function seedExpenseClaims(): void
    {
        $categories = ['travel', 'hospitality', 'office', 'transport', 'project_cost'];
        $descriptions = ['项目出差', '客户接待', '办公采购', '项目用车', '项目采购'];
        $statuses = ['submitted', 'approved', 'paid', 'rejected'];
        $count = 0;
        foreach ([$this->managerId, $this->userId, $this->zhaodcId, $this->chenjingId] as $uid) {
            for ($i = 0; $i < mt_rand(2, 4); $i++) {
                $days = mt_rand(1, 90);
                $status = $statuses[array_rand($statuses)];
                $amount = mt_rand(500, 5000) + 0.00;
                DB::table('expense_claims')->insertOrIgnore([
                    'claim_no'      => 'EXP-2026-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'user_id'       => $uid,
                    'category'      => $categories[array_rand($categories)],
                    'total_amount'  => $amount,
                    'project_id'    => $this->projectIds[array_rand($this->projectIds)] ?? null,
                    'description'   => $descriptions[array_rand($descriptions)],
                    'status'        => $status,
                    'approver_id'   => $status !== 'submitted' ? $this->adminId : null,
                    'approved_at'   => in_array($status, ['approved', 'paid']) ? Carbon::now()->subDays($days - 1) : null,
                    'paid_at'       => $status === 'paid' ? Carbon::now()->subDays($days - 2) : null,
                    'paid_amount'   => $status === 'paid' ? $amount : null,
                    'reject_reason' => $status === 'rejected' ? '金额超预算' : null,
                    'created_at'    => Carbon::now()->subDays($days),
                    'updated_at'    => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ expense_claims: $count 条报销单");
    }

    private function seedExpenseItems(): void
    {
        $claims = DB::table('expense_claims')->get();
        $count = 0;
        foreach ($claims as $c) {
            // 拆成 1-3 个明细
            $n = mt_rand(1, 3);
            $eachAmount = round($c->total_amount / $n, 2);
            for ($i = 0; $i < $n; $i++) {
                DB::table('expense_items')->insertOrIgnore([
                    'expense_claim_id' => $c->id,
                    'item_date'        => Carbon::parse($c->created_at)->subDays(mt_rand(0, 5))->format('Y-m-d'),
                    'description'      => ['高铁票', '出租车', '餐饮', '办公用品', '设备配件'][$i % 5],
                    'amount'           => $eachAmount,
                    'category'         => $c->category,
                    'attachment'       => null,
                    'created_at'       => $c->created_at,
                    'updated_at'       => $c->created_at,
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ expense_items: $count 条报销明细");
    }

    private function seedApprovalRecords(): void
    {
        $count = 0;
        // 报销审批
        $claims = DB::table('expense_claims')->whereIn('status', ['approved', 'paid', 'rejected'])->get();
        foreach ($claims as $c) {
            DB::table('approval_records')->insertOrIgnore([
                'approvable_type' => 'App\\Models\\ExpenseClaim',
                'approvable_id'   => $c->id,
                'user_id'         => $c->approver_id,
                'action'          => $c->status === 'rejected' ? 'rejected' : 'approved',
                'comment'         => $c->status === 'rejected' ? $c->reject_reason : '同意',
                'status'          => $c->status,
                'created_at'      => $c->approved_at ?? $c->created_at,
                'updated_at'      => $c->approved_at ?? $c->created_at,
            ]);
            $count++;
        }
        // 用车审批
        $vehicles = DB::table('vehicle_usage_requests')->whereIn('status', ['approved', 'completed'])->get();
        foreach ($vehicles as $v) {
            DB::table('approval_records')->insertOrIgnore([
                'approvable_type' => 'App\\Models\\VehicleUsageRequest',
                'approvable_id'   => $v->id,
                'user_id'         => $v->approver_id,
                'action'          => 'approved',
                'comment'         => '同意派车',
                'status'          => $v->status,
                'created_at'      => $v->created_at,
                'updated_at'      => $v->created_at,
            ]);
            $count++;
        }
        $this->command->info("  ✓ approval_records: $count 条审批记录");
    }

    // ==================== 10. 财务 ====================

    private function seedReceivables(): void
    {
        $statuses = ['pending', 'pending', 'partial', 'overdue', 'paid', 'paid'];
        $count = 0;
        foreach (array_slice($this->projectIds, 0, 8) as $pid) {
            $amount = mt_rand(50000, 200000) + 0.00;
            $paid = mt_rand(0, (int)$amount);
            $remaining = $amount - $paid;
            $status = $statuses[array_rand($statuses)];
            $due = Carbon::now()->addDays(mt_rand(-60, 90));
            $isReceived = $status === 'paid';
            DB::table('receivables')->insertOrIgnore([
                'customer_id'      => $this->customerIds[array_rand($this->customerIds)],
                'project_id'       => $pid,
                'contract_id'      => null,
                'amount'           => $amount,
                'received_amount'  => $paid,
                'remaining_amount' => $remaining,
                'due_date'         => $due->format('Y-m-d'),
                'received_date'    => $isReceived ? $due->subDays(mt_rand(1, 10))->format('Y-m-d') : null,
                'overdue_days'     => $status === 'overdue' ? mt_rand(1, 30) : 0,
                'status'           => $status,
                'notes'            => '项目应收款',
                'created_at'       => Carbon::now()->subDays(mt_rand(30, 180)),
                'updated_at'       => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ receivables: $count 条应收款");
    }

    private function seedPayables(): void
    {
        $suppliers = DB::table('suppliers')->pluck('id')->all();
        $statuses = ['pending', 'partial', 'paid', 'overdue'];
        $count = 0;
        foreach (array_slice($this->projectIds, 0, 6) as $pid) {
            $amount = mt_rand(20000, 80000) + 0.00;
            $paid = mt_rand(0, (int)$amount);
            DB::table('payables')->insertOrIgnore([
                'supplier_id'      => $suppliers[array_rand($suppliers)],
                'project_id'       => $pid,
                'amount'           => $amount,
                'paid_amount'      => $paid,
                'remaining_amount' => $amount - $paid,
                'due_date'         => Carbon::now()->addDays(mt_rand(-30, 60))->format('Y-m-d'),
                'paid_date'        => $paid > 0 ? Carbon::now()->subDays(mt_rand(1, 30))->format('Y-m-d') : null,
                'payment_term'     => '30天',
                'status'           => $statuses[array_rand($statuses)],
                'notes'            => '供应商应付款',
                'created_at'       => Carbon::now()->subDays(mt_rand(20, 100)),
                'updated_at'       => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ payables: $count 条应付款");
    }

    // ==================== 11. 网盘 + 知识库 + 消息 ====================

    private function seedDiskFolders(): void
    {
        $folders = [
            ['name' => '项目资料',    'path' => '/项目资料/'],
            ['name' => '客户合同',    'path' => '/客户合同/'],
            ['name' => '技术文档',    'path' => '/技术文档/'],
            ['name' => '员工培训',    'path' => '/员工培训/'],
            ['name' => '财务凭证',    'path' => '/财务凭证/'],
        ];
        $count = 0;
        foreach ($folders as $f) {
            DB::table('disk_folders')->insertOrIgnore([
                'parent_id'  => null,
                'name'       => $f['name'],
                'path'       => $f['path'],
                'created_by' => $this->adminId,
                'project_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ disk_folders: $count 个文件夹");
    }

    private function seedDiskFiles(): void
    {
        $folders = DB::table('disk_folders')->get();
        $count = 0;
        foreach ($folders as $f) {
            for ($i = 0; $i < mt_rand(3, 8); $i++) {
                $ext = ['pdf', 'docx', 'xlsx', 'jpg', 'png'][array_rand([0,1,2,3,4])];
                DB::table('disk_files')->insertOrIgnore([
                    'folder_id'     => $f->id,
                    'name'          => "{$f->name}-{$i}.{$ext}",
                    'original_name' => "原始文件-{$i}.{$ext}",
                    'extension'     => $ext,
                    'mime_type'     => $ext === 'pdf' ? 'application/pdf' : ($ext === 'jpg' ? 'image/jpeg' : 'application/octet-stream'),
                    'size'          => mt_rand(10000, 5000000),
                    'path'          => "/disk/{$f->id}/{$i}.{$ext}",
                    'uploaded_by'   => $this->adminId,
                    'version'       => 1,
                    'description'   => null,
                    'created_at'    => Carbon::now()->subDays(mt_rand(0, 90)),
                    'updated_at'    => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ disk_files: $count 个文件");
    }

    private function seedKnowledgeCategories(): void
    {
        $cats = [
            ['name' => '产品手册',  'icon' => 'document'],
            ['name' => '安装指南',  'icon' => 'tools'],
            ['name' => '故障排查',  'icon' => 'warning'],
            ['name' => '常见问题',  'icon' => 'question'],
            ['name' => '安全规范',  'icon' => 'shield'],
        ];
        foreach ($cats as $c) {
            $row = array_merge($c, [
                'parent_id'  => null,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('knowledge_categories')->insertOrIgnore($row);
        }
        $this->command->info('  ✓ knowledge_categories: 5 个分类');
    }

    private function seedKnowledgeArticles(): void
    {
        $cats = DB::table('knowledge_categories')->pluck('id')->all();
        $articles = [
            ['title' => '海康威视摄像头快速配置指南',   'summary' => '本文介绍海康摄像头初次上电配置步骤'],
            ['title' => '门禁系统常见故障排查',         'summary' => '门禁刷卡不响应、通讯失败等问题的处理方法'],
            ['title' => 'NVR 录像机存储容量计算方法',   'summary' => '根据摄像头数量和保存天数计算所需硬盘容量'],
            ['title' => '安防施工安全规范',             'summary' => '高空作业、电气作业的安全注意事项'],
            ['title' => 'POE 供电距离限制及解决方案',   'summary' => 'POE 100米限制的解决方法和注意事项'],
            ['title' => '客户常见问题 FAQ',             'summary' => '客户最关心的10个问题及解答'],
            ['title' => '硬盘录像机网络配置',           'summary' => 'NVR 网络配置、端口映射、远程访问'],
            ['title' => '视频监控镜头选型指南',         'summary' => '不同场景的镜头焦距、光圈、像素选择'],
        ];
        $count = 0;
        foreach ($articles as $a) {
            DB::table('knowledge_articles')->insertOrIgnore([
                'category_id'  => $cats[array_rand($cats)],
                'title'        => $a['title'],
                'content'      => $a['summary'] . "\n\n详细步骤请参考相关文档。",
                'author_id'    => $this->managerId,
                'tags'         => json_encode(['安防', '教程'], JSON_UNESCAPED_UNICODE),
                'view_count'   => mt_rand(0, 500),
                'like_count'   => mt_rand(0, 50),
                'status'       => 'published',
                'published_at' => Carbon::now()->subDays(mt_rand(1, 90)),
                'summary'      => $a['summary'],
                'created_at'   => Carbon::now()->subDays(mt_rand(1, 90)),
                'updated_at'   => now(),
            ]);
            $count++;
        }
        $this->command->info("  ✓ knowledge_articles: $count 篇文章");
    }

    private function seedNotifications(): void
    {
        $types = ['approval', 'service', 'system', 'message'];
        $titles = [
            '您的报销单已审批通过',
            '新工单待处理',
            '系统维护通知',
            '项目进度更新',
        ];
        $contents = [
            '您的报销单 EXP-2026-0010 已审批通过，请关注后续打款。',
            '您有一个新的售后工单需要处理，工单号：SO-20260612-005。',
            '系统将于本周六凌晨2:00-4:00进行例行维护。',
            '项目"阳光小学视频监控升级"进度已达75%。',
        ];
        $count = 0;
        foreach ([$this->adminId, $this->managerId, $this->userId, $this->zhaodcId, $this->chenjingId] as $uid) {
            for ($i = 0; $i < mt_rand(3, 6); $i++) {
                $idx = array_rand($types);
                DB::table('notifications')->insertOrIgnore([
                    'type'            => $types[$idx],
                    'title'           => $titles[$idx],
                    'content'         => $contents[$idx],
                    'data'            => null,
                    'notifiable_id'   => $uid,
                    'notifiable_type' => 'App\\Models\\User',
                    'sender_id'       => $this->adminId,
                    'level'           => ['info', 'warning', 'success'][array_rand([0,1,2])],
                    'read_at'         => mt_rand(0, 1) ? Carbon::now()->subDays(mt_rand(0, 5)) : null,
                    'created_at'      => Carbon::now()->subDays(mt_rand(0, 7)),
                    'updated_at'      => now(),
                ]);
                $count++;
            }
        }
        $this->command->info("  ✓ notifications: $count 条通知");
    }

    // ==================== 收尾 ====================

    private function printSummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 数据统计:');
        $tables = [
            'departments', 'users', 'employee_profiles', 'certificates', 'employee_skills',
            'customers', 'customer_contacts', 'follow_up_records', 'customer_devices',
            'suppliers', 'warehouses', 'inventory_items', 'stock_records', 'device_serial_numbers',
            'vehicles', 'vehicle_insurance', 'vehicle_maintenance_records', 'vehicle_usage_requests',
            'projects', 'project_members', 'project_contracts', 'contract_payment_nodes',
            'purchase_orders', 'purchase_items', 'construction_logs', 'project_materials', 'project_settlements',
            'service_orders', 'service_order_logs', 'service_order_parts', 'maintenance_contracts',
            'attendance_records', 'leave_requests', 'overtime_requests',
            'expense_claims', 'expense_items', 'approval_records',
            'receivables', 'payables',
            'disk_folders', 'disk_files', 'knowledge_categories', 'knowledge_articles', 'notifications',
        ];
        foreach ($tables as $t) {
            $cnt = DB::table($t)->count();
            $this->command->info("  $t: $cnt");
        }
    }
}
