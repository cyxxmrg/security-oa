<?php
/**
 * V1.2.3 152 服务器半年测试数据 Seeder (匹配 152 实际 schema)
 * 时间: 2025-12-27 ~ 2026-06-27
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HalfYearTestDataSeeder extends Seeder
{
    private $startDate;
    private $endDate;

    // 缓存池
    private $userIds = [];
    private $deptIds = [];
    private $positionIds = [];
    private $skillIds = [];
    private $employeeProfileIds = [];
    private $customerIds = [];
    private $projectIds = [];
    private $supplierIds = [];
    private $productIds = [];
    private $vehicleIds = [];
    private $fuelCardIds = [];
    private $warehouseIds = [];
    private $categoryIds = [];
    private array $customerIds = [];
    private $opportunityIds = [];
    private $processTemplateIds = [];
    private $maintenanceContractIds = [];

    public function run(): void
    {
        $this->startDate = Carbon::parse('2025-12-27');
        $this->endDate   = Carbon::parse('2026-06-27');
        $this->command->info("=== 半年数据生成开始 ({$this->startDate->toDateString()} ~ {$this->endDate->toDateString()}) ===");

        DB::transaction(function () {
            $this->seedDepartments();
            $this->seedPositions();
            $this->seedSkills();
            $this->seedBusinessUsers();
            $this->seedEmployeeProfiles();
            $this->seedEmployeeSkills();
            $this->seedCustomers();
            $this->seedCustomerContacts();
            $this->seedCustomerDevices();
            $this->seedCustomerInvoiceInfos();
            $this->seedSuppliers();
            $this->seedWarehouses();
            $this->seedInventoryCategories();
            $this->seedInventoryItems();
            $this->seedSalesProducts();
            $this->seedOpportunities();
            $this->seedQuotations();
            $this->seedProjects();
            $this->seedProjectMembers();
            $this->seedProjectBudgets();
            $this->seedProjectMaterials();
            $this->seedProjectContracts();
            $this->seedProjectSettlements();
            $this->seedPurchaseRequirements();
            $this->seedPurchasePlans();
            $this->seedPurchaseContracts();
            $this->seedPurchaseOrders();
            $this->seedPurchaseShipments();
            $this->seedPurchasePayments();
            $this->seedStockRecords();
            $this->seedServiceOrders();
            $this->seedServiceOrderLogs();
            $this->seedWorkOrders();
            $this->seedRepairOrders();
            $this->seedCustomerReceivables();
            $this->seedCustomerReceipts();
            $this->seedPayables();
            $this->seedReceivables();
            $this->seedFinancePayments();
            $this->seedFinanceInvoices();
            $this->seedSupplierPayables();
            $this->seedSupplierPayments();
            $this->seedAttendance();
            $this->seedLeaveRequests();
            $this->seedOvertimeRequests();
            $this->seedExpenseClaims();
            $this->seedVehicles();
            $this->seedFuelCards();
            $this->seedVehicleUsageRequests();
            $this->seedFuelCardRecharges();
            $this->seedVehicleMaintenance();
            $this->seedVehicleInsurance();
            $this->seedMaintenanceContracts();
            $this->seedWarranties();
            $this->seedWarrantyDeposits();
            $this->seedWarrantyServiceOrders();
            $this->seedProcessTemplates();
            $this->seedProcessInstances();
            $this->seedProcessInspections();
            $this->seedRectifications();
            $this->seedApprovalTemplates();
            $this->seedApprovalInstances();
            $this->seedTenderProjects();
            $this->seedTenderBids();
            $this->seedExternalQuoteRequests();
            $this->seedExternalQuotes();
            $this->seedExternalConstructionWorks();
            $this->seedSalesFollowUps();
            $this->seedDiskFolders();
            $this->seedDiskFiles();
            $this->seedNotifications();
            $this->seedSystemLogs();
        });

        $this->command->info("=== 半年数据生成完成 ===");
    }

    private function randDate($from = null, $to = null): string
    {
        $from = $from instanceof Carbon ? $from->timestamp : (is_string($from) ? Carbon::parse($from)->timestamp : $this->startDate->timestamp);
        $to   = $to instanceof Carbon ? $to->timestamp : (is_string($to) ? Carbon::parse($to)->timestamp : $this->endDate->timestamp);
        return Carbon::createFromTimestamp(rand($from, $to))->format('Y-m-d H:i:s');
    }

    private function pickRandom(array $arr) { return empty($arr) ? null : $arr[array_rand($arr)]; }
    private function pickMany(array $arr, int $n): array
    {
        if (empty($arr)) return [];
        shuffle($arr);
        return array_slice($arr, min($n, count($arr)));
    }
    private function randomAmount(float $min, float $max): float
    {
        return round(rand((int)($min * 100), (int)($max * 100)) / 100, 2);
    }
    private function batchInsert(string $table, array $data, int $chunk = 200): void
    {
        if (empty($data)) return;
        foreach (array_chunk($data, $chunk) as $batch) {
            DB::table($table)->insert($batch);
        }
    }

    // ============== 1. 部门岗位技能 ==============
    private function seedDepartments(): void
    {
        $depts = [
            ['name' => '总经理办公室', 'parent_id' => null, 'manager_id' => null, 'sort_order' => 1, 'status' => 'active'],
            ['name' => '工程部',       'parent_id' => null, 'manager_id' => null, 'sort_order' => 2, 'status' => 'active'],
            ['name' => '工程一部',     'parent_id' => null, 'manager_id' => null, 'sort_order' => 3, 'status' => 'active'],
            ['name' => '工程二部',     'parent_id' => null, 'manager_id' => null, 'sort_order' => 4, 'status' => 'active'],
            ['name' => '工程三部',     'parent_id' => null, 'manager_id' => null, 'sort_order' => 5, 'status' => 'active'],
            ['name' => '销售部',       'parent_id' => null, 'manager_id' => null, 'sort_order' => 6, 'status' => 'active'],
            ['name' => '华东销售',     'parent_id' => null, 'manager_id' => null, 'sort_order' => 7, 'status' => 'active'],
            ['name' => '华南销售',     'parent_id' => null, 'manager_id' => null, 'sort_order' => 8, 'status' => 'active'],
            ['name' => '财务部',       'parent_id' => null, 'manager_id' => null, 'sort_order' => 9, 'status' => 'active'],
            ['name' => '采购部',       'parent_id' => null, 'manager_id' => null, 'sort_order' => 10, 'status' => 'active'],
            ['name' => '行政人事部',   'parent_id' => null, 'manager_id' => null, 'sort_order' => 11, 'status' => 'active'],
            ['name' => '技术研发部',   'parent_id' => null, 'manager_id' => null, 'sort_order' => 12, 'status' => 'active'],
        ];
        $this->batchInsert('departments', $depts);
        $this->deptIds = DB::table('departments')->pluck('id')->toArray();
        $this->command->info("  ✓ departments: " . count($depts));
    }

    private function seedPositions(): void
    {
        $positions = [
            ['name' => '总经理',     'department_id' => $this->deptIds[0], 'level' => 'L10', 'status' => 'active', 'sort_order' => 1],
            ['name' => '工程总监',   'department_id' => $this->deptIds[1], 'level' => 'L9',  'status' => 'active', 'sort_order' => 2],
            ['name' => '工程经理',   'department_id' => $this->deptIds[2], 'level' => 'L7',  'status' => 'active', 'sort_order' => 3],
            ['name' => '高级工程师', 'department_id' => $this->deptIds[2], 'level' => 'L5',  'status' => 'active', 'sort_order' => 4],
            ['name' => '工程师',     'department_id' => $this->deptIds[3], 'level' => 'L4',  'status' => 'active', 'sort_order' => 5],
            ['name' => '技术员',     'department_id' => $this->deptIds[4], 'level' => 'L3',  'status' => 'active', 'sort_order' => 6],
            ['name' => '销售总监',   'department_id' => $this->deptIds[5], 'level' => 'L9',  'status' => 'active', 'sort_order' => 7],
            ['name' => '销售经理',   'department_id' => $this->deptIds[6], 'level' => 'L7',  'status' => 'active', 'sort_order' => 8],
            ['name' => '销售员',     'department_id' => $this->deptIds[7], 'level' => 'L4',  'status' => 'active', 'sort_order' => 9],
            ['name' => '财务经理',   'department_id' => $this->deptIds[8], 'level' => 'L7',  'status' => 'active', 'sort_order' => 10],
            ['name' => '会计',       'department_id' => $this->deptIds[8], 'level' => 'L5',  'status' => 'active', 'sort_order' => 11],
            ['name' => '出纳',       'department_id' => $this->deptIds[8], 'level' => 'L4',  'status' => 'active', 'sort_order' => 12],
            ['name' => '采购经理',   'department_id' => $this->deptIds[9], 'level' => 'L7',  'status' => 'active', 'sort_order' => 13],
            ['name' => '采购员',     'department_id' => $this->deptIds[9], 'level' => 'L4',  'status' => 'active', 'sort_order' => 14],
            ['name' => 'HR 经理',    'department_id' => $this->deptIds[10], 'level' => 'L7', 'status' => 'active', 'sort_order' => 15],
            ['name' => '技术总监',   'department_id' => $this->deptIds[11], 'level' => 'L9', 'status' => 'active', 'sort_order' => 16],
            ['name' => '研发工程师', 'department_id' => $this->deptIds[11], 'level' => 'L5', 'status' => 'active', 'sort_order' => 17],
        ];
        $this->batchInsert('positions', $positions);
        $this->positionIds = DB::table('positions')->pluck('id')->toArray();
        $this->command->info("  ✓ positions: " . count($positions));
    }

    private function seedSkills(): void
    {
        $skills = [
            ['name' => '海康威视摄像头',     'color' => '#409EFF', 'category' => '产品', 'sort_order' => 1],
            ['name' => '大华摄像头',         'color' => '#67C23A', 'category' => '产品', 'sort_order' => 2],
            ['name' => '宇视摄像头',         'color' => '#E6A23C', 'category' => '产品', 'sort_order' => 3],
            ['name' => '门禁系统',           'color' => '#F56C6C', 'category' => '系统', 'sort_order' => 4],
            ['name' => '人脸识别',           'color' => '#909399', 'category' => '系统', 'sort_order' => 5],
            ['name' => '车牌识别',           'color' => '#9C27B0', 'category' => '系统', 'sort_order' => 6],
            ['name' => '网络交换机配置',     'color' => '#00BCD4', 'category' => '网络', 'sort_order' => 7],
            ['name' => '光纤熔接',           'color' => '#FF9800', 'category' => '网络', 'sort_order' => 8],
            ['name' => '弱电布线',           'color' => '#795548', 'category' => '施工', 'sort_order' => 9],
            ['name' => '高空作业',           'color' => '#E91E63', 'category' => '施工', 'sort_order' => 10],
            ['name' => '电焊',               'color' => '#673AB7', 'category' => '施工', 'sort_order' => 11],
            ['name' => 'PLC 编程',           'color' => '#3F51B5', 'category' => '技术', 'sort_order' => 12],
            ['name' => '系统集成',           'color' => '#009688', 'category' => '技术', 'sort_order' => 13],
            ['name' => '售后维修',           'color' => '#FFC107', 'category' => '服务', 'sort_order' => 14],
            ['name' => '现场勘测',           'color' => '#4CAF50', 'category' => '服务', 'sort_order' => 15],
        ];
        $this->batchInsert('skill_tags', $skills);
        $this->skillIds = DB::table('skill_tags')->pluck('id')->toArray();
        $this->command->info("  ✓ skill_tags: " . count($skills));
    }

    private function seedBusinessUsers(): void
    {
        $this->userIds = DB::table('users')->where('is_system', false)->pluck('id')->toArray();
        $surnames = ['张','王','李','赵','陈','刘','杨','黄','周','吴'];
        $givenNames = ['伟','芳','娜','敏','静','丽','强','磊','军','洋','勇','艳','杰','娟','涛','明','超'];
        $deptNameToId = DB::table('departments')->pluck('id', 'name')->toArray();
        $posNameToId = DB::table('positions')->pluck('id', 'name')->toArray();
        $newUsers = [];
        $i = 0;
        $deptPosPairs = [
            ['工程一部', ['工程师', '高级工程师']],
            ['工程二部', ['工程师', '技术员']],
            ['工程三部', ['工程师', '技术员']],
            ['销售部',   ['销售员', '销售经理']],
            ['财务部',   ['会计', '出纳']],
            ['采购部',   ['采购员']],
            ['行政人事部', ['HR 经理']],
        ];
        foreach ($deptPosPairs as [$deptName, $posList]) {
            $deptId = $deptNameToId[$deptName] ?? null;
            if (!$deptId) continue;
            foreach ($posList as $posName) {
                $posId = $posNameToId[$posName] ?? null;
                for ($j = 0; $j < 2; $j++) {
                    $name = $this->pickRandom($surnames) . $this->pickRandom($givenNames);
                    $username = 'staff' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
                    $newUsers[] = [
                        'name'     => $name,
                        'username' => $username,
                        'email'    => $username . '@oa.local',
                        'phone'    => '138' . str_pad((string)rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                        'password' => Hash::make(\App\Support\TemporaryPassword::generate()),
                        'must_change_password' => true,
                        'department_id' => $deptId,
                        'position_id'   => $posId,
                        'gender'   => rand(0, 1) ? 'male' : 'female',
                        'status'   => 'active',
                        'is_system' => false,
                        'user_type' => 'business',
                        'type'      => 'staff',
                        'created_at' => $this->randDate(Carbon::parse('2025-08-01'), Carbon::parse('2025-12-26')),
                        'updated_at' => now(),
                    ];
                    $i++;
                }
            }
        }
        $this->batchInsert('users', $newUsers);
        $this->userIds = DB::table('users')->where('is_system', false)->pluck('id')->toArray();
        $this->command->info("  ✓ users (new): " . count($newUsers));
    }

    private function seedEmployeeProfiles(): void
    {
        $data = [];
        $i = 0;
        foreach ($this->userIds as $uid) {
            $u = DB::table('users')->where('id', $uid)->first();
            $data[] = [
                'user_id' => $uid,
                'employee_no' => 'E' . str_pad((string)++$i, 6, '0', STR_PAD_LEFT),
                'hire_date' => Carbon::parse('2018-01-01')->addDays(rand(0, 2500))->toDateString(),
                'contract_type' => ['open','3years','5years'][array_rand(['open','3years','5years'])],
                'contract_start' => Carbon::parse('2018-01-01')->addDays(rand(0, 2500))->toDateString(),
                'base_salary' => rand(5000, 25000),
                'salary_allowance' => rand(500, 5000),
                'emergency_contact' => '家属' . rand(1000, 9999),
                'emergency_phone' => '139' . str_pad((string)rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'bank_name' => ['招商银行','建设银行','工商银行','中国银行'][array_rand(['招商银行','建设银行','工商银行','中国银行'])],
                'bank_account' => (string)rand(6222020000000000, 6222029999999999),
                'notes' => '员工档案',
                'created_at' => $u->created_at ?? now(),
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('employee_profiles', $data);
        $this->employeeProfileIds = DB::table('employee_profiles')->pluck('id')->toArray();
        $this->command->info("  ✓ employee_profiles: " . count($data));
    }

    private function seedEmployeeSkills(): void
    {
        $data = [];
        foreach ($this->employeeProfileIds as $epid) {
            $n = rand(2, 4);
            $skills = $this->pickMany($this->skillIds, $n);
            foreach ($skills as $sid) {
                $data[] = [
                    'employee_profile_id' => $epid,
                    'skill_tag_id' => $sid,
                    'proficiency' => ['beginner','intermediate','advanced','expert'][array_rand(['beginner','intermediate','advanced','expert'])],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('employee_skills', $data);
        $this->command->info("  ✓ employee_skills: " . count($data));
    }

    private function seedCustomers(): void
    {
        $provinces = ['北京','上海','广东','江苏','浙江','山东','四川','湖北','福建','河南','河北','湖南','安徽','江西','陕西','辽宁'];
        $cities = [
            '北京' => ['北京'], '上海' => ['上海'],
            '广东' => ['深圳','广州','东莞','佛山','珠海'],
            '江苏' => ['南京','苏州','无锡','常州','南通'],
            '浙江' => ['杭州','宁波','温州','嘉兴','绍兴'],
            '山东' => ['济南','青岛','烟台','潍坊'],
            '四川' => ['成都','绵阳','德阳'],
            '湖北' => ['武汉','宜昌','襄阳'],
            '福建' => ['福州','厦门','泉州'],
            '河南' => ['郑州','洛阳','开封'],
            '河北' => ['石家庄','唐山','保定'],
            '湖南' => ['长沙','株洲','湘潭'],
            '安徽' => ['合肥','芜湖','蚌埠'],
            '江西' => ['南昌','九江','赣州'],
            '陕西' => ['西安','咸阳','宝鸡'],
            '辽宁' => ['沈阳','大连','鞍山'],
        ];
        $industries = ['制造业','商业地产','政府机关','教育','医疗','金融','物流','零售','酒店','餐饮','交通','能源','化工','建筑','互联网'];
        $sources = ['老客户介绍','网络搜索','展会','电话营销','陌拜','渠道代理','投标中标','战略合作'];
        $stages = ['lead','contacted','qualified','proposal','negotiation','won','lost'];
        $prefix = ['华','盛','金','银','东方','南方','长城','中','国','华润','万科','碧桂园','保利','万达','海康','大华','华为','中兴','阿里','腾讯','京东','美的','格力','海尔','比亚迪','吉利','三一','徐工','浪潮','用友','金蝶'];
        $suffix = ['科技','实业','集团','股份','有限公司','分公司','控股','投资','贸易','电子','信息','工程','建设','物业','管理','服务'];
        $customerTypes = ['normal','vip','strategic','partner'];
        $districts = ['朝阳区','浦东新区','南山区','天河区','西湖区','武侯区','鼓楼区','玄武区','市中区','青羊区'];
        $customers = [];
        for ($i = 0; $i < 50; $i++) {
            $province = $this->pickRandom($provinces);
            $city = $this->pickRandom($cities[$province] ?? ['未知']);
            $created = $this->randDate();
            $stage = $this->pickRandom($stages);
            $customers[] = [
                'name' => $this->pickRandom($prefix) . $this->pickRandom($suffix) . ($i + 1) . '号',
                'credit_code' => '91' . str_pad((string)rand(100000000000000, 999999999999999), 14, '0', STR_PAD_LEFT),
                'industry' => $this->pickRandom($industries),
                'category' => $this->pickRandom($customerTypes),
                'province' => $province,
                'city' => $city,
                'district' => $this->pickRandom($districts),
                'address' => $province . $city . 'XX 路 ' . rand(1, 999) . ' 号',
                'longitude' => 116.0 + (rand(-2000, 2000) / 100),
                'latitude'  => 39.0 + (rand(-2000, 2000) / 100),
                'tags' => json_encode([$this->pickRandom(['重点客户','潜在大客户','已合作','待跟进','已报价','需拜访','需回访','已签约','需续约','需维护'])]),
                'source' => $this->pickRandom($sources),
                'status' => 'active',
                'assigned_user_id' => $this->pickRandom($this->userIds),
                'description' => '这是 ' . $city . ' 的客户，需要定期跟进。',
                'pipeline_stage' => $stage,
                'expected_amount' => $this->randomAmount(50000, 5000000),
                'expected_close_date' => Carbon::parse($created)->addDays(rand(30, 180))->toDateString(),
                'last_activity_at' => $this->randDate($created, $this->endDate),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('customers', $customers);
        $this->customerIds = DB::table('customers')->pluck('id')->toArray();
        $this->command->info("  ✓ customers: " . count($customers));
    }

    private function seedCustomerContacts(): void
    {
        $data = [];
        $titles = ['总经理','副总经理','技术总监','采购经理','工程经理','项目经理','技术员','行政经理','财务经理','IT 主管'];
        $surnames = ['张','王','李','赵','陈','刘'];
        $givenNames = ['伟','芳','娜','敏','静','丽','强','磊','军','洋'];
        foreach ($this->customerIds as $cid) {
            $n = rand(1, 3);
            for ($i = 0; $i < $n; $i++) {
                $data[] = [
                    'customer_id' => $cid,
                    'name' => $this->pickRandom($surnames) . $this->pickRandom($givenNames),
                    'position' => $this->pickRandom($titles),
                    'phone' => '1' . rand(3, 9) . str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                    'email' => Str::lower(rand(1, 99999)) . '@' . ['163.com','qq.com','gmail.com'][array_rand(['163.com','qq.com','gmail.com'])],
                    'is_primary' => $i === 0,
                    'wechat' => 'wx_' . Str::random(8),
                    'notes' => $i === 0 ? '主要联系人' : '',
                    'created_at' => $this->randDate(),
                    'updated_at' => now(),
                ];
            }
        }
        $this->batchInsert('customer_contacts', $data);
        $this->command->info("  ✓ customer_contacts: " . count($data));
    }

    private function seedCustomerDevices(): void
    {
        $data = [];
        $brands = ['海康威视','大华','宇视','天地伟业','东方网力','苏州科达','华为','H3C','锐捷','TP-LINK','小米','萤石','乐橙'];
        $types = ['camera','nvr','access_controller','face_terminal','plate_recognizer','switch','fiber_converter','alarm_host'];
        $locations = ['1楼大厅','2楼办公室','停车场','机房','仓库','大门口','侧门','周界','办公区','生产区'];
        foreach ($this->customerIds as $cid) {
            $n = rand(2, 5);
            for ($i = 0; $i < $n; $i++) {
                $data[] = [
                    'customer_id' => $cid,
                    'project_id' => null,
                    'device_name' => $this->pickRandom($brands) . '设备' . ($i + 1),
                    'device_type' => $this->pickRandom($types),
                    'brand' => $this->pickRandom($brands),
                    'model' => strtoupper(Str::random(2)) . '-' . rand(100, 9999),
                    'serial_number' => strtoupper(Str::random(12)),
                    'install_location' => $this->pickRandom($locations),
                    'install_date' => $this->randDate(),
                    'warranty_end' => Carbon::parse($this->randDate())->addYear()->toDateString(),
                    'status' => ['normal','maintenance','broken','decommissioned'][array_rand(['normal','maintenance','broken','decommissioned'])],
                    'notes' => '',
                    'created_at' => $this->randDate(),
                    'updated_at' => now(),
                ];
            }
        }
        $this->batchInsert('customer_devices', $data);
        $this->command->info("  ✓ customer_devices: " . count($data));
    }

    private function seedCustomerInvoiceInfos(): void
    {
        $data = [];
        foreach (array_slice($this->customerIds, 0, 30) as $cid) {
            $data[] = [
                'customer_id' => $cid,
                'invoice_type' => ['general','special'][array_rand(['general','special'])],
                'company_name' => '某科技' . $cid . '号',
                'tax_no' => '91' . str_pad((string)rand(100000000000000, 999999999999999), 14, '0', STR_PAD_LEFT),
                'register_address' => '某市某区某路',
                'register_phone' => '1' . rand(3, 9) . str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'bank_name' => ['招商银行','建设银行','工商银行'][array_rand(['招商银行','建设银行','工商银行'])],
                'bank_account' => (string)rand(6222020000000000, 6222029999999999),
                'is_default' => true,
                'remark' => '',
                'created_at' => $this->randDate(),
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('customer_invoice_infos', $data);
        $this->command->info("  ✓ customer_invoice_infos: " . count($data));
    }

    private function seedSuppliers(): void
    {
        $data = [];
        $prefixes = ['深圳','上海','北京','广州','杭州','成都','苏州'];
        $types = ['海康','大华','宇视','华为','H3C','锐捷','TP','小米','萤石'];
        $suffixes = ['总代理','有限公司','一级代理','金牌代理','指定供应商'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $data[] = [
                'name' => $this->pickRandom($prefixes) . $this->pickRandom($types) . $this->pickRandom($suffixes),
                'contact_person' => ['张经理','李总','王主管','陈总'][array_rand(['张经理','李总','王主管','陈总'])],
                'phone' => '1' . rand(3, 9) . str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'email' => 'sales' . $i . '@supplier.local',
                'address' => '某市某区某路 ' . rand(1, 100) . ' 号',
                'category' => ['设备','线材','施工','服务','系统集成'][array_rand(['设备','线材','施工','服务','系统集成'])],
                'rating' => rand(60, 100),
                'status' => 'active',
                'notes' => '主要供应' . $this->pickRandom(['摄像头','交换机','线材','门禁设备','对讲设备']),
                'code' => 'SUP' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'type' => ['material','labor','outsource','service'][array_rand(['material','labor','outsource','service'])],
                'business_license' => '91' . str_pad((string)rand(100000000000000, 999999999999999), 14, '0', STR_PAD_LEFT),
                'legal_person' => '某' . ['张','王','李','赵'][array_rand(['张','王','李','赵'])],
                'registered_capital' => rand(100, 5000) * 10000,
                'website' => 'https://www.supplier' . $i . '.com',
                'bank_name' => ['招商银行','建设银行','工商银行'][array_rand(['招商银行','建设银行','工商银行'])],
                'bank_account' => (string)rand(6222020000000000, 6222029999999999),
                'account_name' => '某公司',
                'tax_no' => '91' . str_pad((string)rand(100000000000000, 999999999999999), 14, '0', STR_PAD_LEFT),
                'payment_terms' => ['30days','60days','90days'][array_rand(['30days','60days','90days'])],
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('suppliers', $data);
        $this->supplierIds = DB::table('suppliers')->pluck('id')->toArray();
        $this->command->info("  ✓ suppliers: " . count($data));
    }

    private function seedWarehouses(): void
    {
        $data = [
            ['name' => '深圳总部仓库', 'code' => 'WH-SZ-01', 'type' => 'main', 'address' => '深圳市南山区', 'manager_id' => $this->pickRandom($this->userIds), 'status' => 'active'],
            ['name' => '北京中转仓',   'code' => 'WH-BJ-01', 'type' => 'transit', 'address' => '北京市朝阳区', 'manager_id' => $this->pickRandom($this->userIds), 'status' => 'active'],
            ['name' => '上海中转仓',   'code' => 'WH-SH-01', 'type' => 'transit', 'address' => '上海市浦东新区', 'manager_id' => $this->pickRandom($this->userIds), 'status' => 'active'],
            ['name' => '广州仓库',     'code' => 'WH-GZ-01', 'type' => 'branch', 'address' => '广州市天河区', 'manager_id' => $this->pickRandom($this->userIds), 'status' => 'active'],
        ];
        $this->batchInsert('warehouses', $data);
        $this->warehouseIds = DB::table('warehouses')->pluck('id')->toArray();
        $this->command->info("  ✓ warehouses: " . count($data));
    }

    private function seedInventoryCategories(): void
    {
        $data = [
            ['parent_id' => null, 'name' => '摄像头',   'code' => 'CAM', 'sort_order' => 1],
            ['parent_id' => null, 'name' => '录像机',   'code' => 'NVR', 'sort_order' => 2],
            ['parent_id' => null, 'name' => '门禁',     'code' => 'ACS', 'sort_order' => 3],
            ['parent_id' => null, 'name' => '网络设备', 'code' => 'NET', 'sort_order' => 4],
            ['parent_id' => null, 'name' => '线材',     'code' => 'WIR', 'sort_order' => 5],
            ['parent_id' => null, 'name' => '辅材',     'code' => 'ACC', 'sort_order' => 6],
        ];
        $this->batchInsert('inventory_categories', $data);
        $this->categoryIds = DB::table('inventory_categories')->pluck('id')->toArray();
        $this->command->info("  ✓ inventory_categories: " . count($data));
    }

    private function seedInventoryItems(): void
    {
        $data = [];
        $items = [
            ['海康威视 200万半球网络摄像头', 250, '台', 'CAM'],
            ['海康威视 400万枪机摄像头', 480, '台', 'CAM'],
            ['海康威视 800万 4K 摄像头', 950, '台', 'CAM'],
            ['大华 200万枪机摄像头', 230, '台', 'CAM'],
            ['大华 400万摄像头', 420, '台', 'CAM'],
            ['宇视 200万摄像头', 220, '台', 'CAM'],
            ['海康威视 8路录像机', 850, '台', 'NVR'],
            ['海康威视 16路录像机', 1450, '台', 'NVR'],
            ['海康威视 32路录像机', 2800, '台', 'NVR'],
            ['大华 16路录像机', 1380, '台', 'NVR'],
            ['海康 4TB 监控专用硬盘', 580, '块', 'NVR'],
            ['海康 8TB 监控专用硬盘', 1180, '块', 'NVR'],
            ['中控智慧门禁控制器', 480, '台', 'ACS'],
            ['海康人脸识别门禁一体机', 1850, '台', 'ACS'],
            ['海康车牌识别摄像头', 2400, '台', 'ACS'],
            ['H3C 8口千兆交换机', 280, '台', 'NET'],
            ['H3C 16口千兆交换机', 480, '台', 'NET'],
            ['H3C 24口 PoE 交换机', 1850, '台', 'NET'],
            ['华为 24口 PoE 交换机', 2400, '台', 'NET'],
            ['TP-LINK 5口百兆交换机', 60, '台', 'NET'],
            ['TP-LINK 8口千兆交换机', 180, '台', 'NET'],
            ['海康光纤收发器', 95, '对', 'NET'],
            ['超五类网线 305米/箱', 380, '箱', 'WIR'],
            ['六类网线 305米/箱', 580, '箱', 'WIR'],
            ['光纤 12芯室外单模', 6, '米', 'WIR'],
            ['电源线 RVV 2*1.0 100米/卷', 280, '卷', 'WIR'],
            ['电源线 RVV 2*1.5 100米/卷', 380, '卷', 'WIR'],
            ['电源适配器 12V 2A', 28, '个', 'ACC'],
            ['BNC 接头', 2, '个', 'ACC'],
            ['水晶头 RJ45', 1, '个', 'ACC'],
            ['PVC 线槽 20mm', 4, '米', 'ACC'],
            ['PVC 线槽 40mm', 8, '米', 'ACC'],
            ['机柜 6U 壁挂', 280, '个', 'ACC'],
            ['机柜 12U 落地', 680, '个', 'ACC'],
            ['机柜 22U 落地', 1280, '个', 'ACC'],
            ['UPS 1KVA 山特', 1280, '台', 'ACC'],
            ['光模块 千兆单模', 95, '个', 'NET'],
            ['光纤跳线 LC-LC 3米', 18, '根', 'WIR'],
            ['尾纤 SC 单模 1米', 6, '根', 'WIR'],
            ['防水接头 PG11', 3, '个', 'ACC'],
        ];
        $i = 0;
        foreach ($items as [$name, $price, $unit, $catCode]) {
            $catId = DB::table('inventory_categories')->where('code', $catCode)->value('id');
            $created = $this->randDate();
            $data[] = [
                'name' => $name,
                'code' => 'INV' . str_pad((string)(++$i), 5, '0', STR_PAD_LEFT),
                'category' => $catCode,
                'specification' => '标准规格',
                'unit' => $unit,
                'safety_stock' => 10,
                'current_stock' => rand(0, 200),
                'cost_price' => $price * 0.7,
                'sell_price' => $price,
                'warehouse_id' => $this->pickRandom($this->warehouseIds),
                'location' => 'A-' . rand(1, 20),
                'has_serial' => rand(0, 1) ? true : false,
                'status' => 'active',
                'category_id' => $catId,
                'min_stock' => 5,
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('inventory_items', $data);
        $this->productIds = DB::table('inventory_items')->pluck('id')->toArray();
        $this->command->info("  ✓ inventory_items: " . count($data));
    }

    private function seedSalesProducts(): void
    {
        $data = [];
        $packages = [
            ['标准监控套餐 (8路)', 8800],
            ['标准监控套餐 (16路)', 15800],
            ['高清监控套餐 (16路 4百万)', 22800],
            ['4K超清监控套餐 (32路)', 45800],
            ['门禁系统标准包 (4门)', 6800],
            ['门禁系统增强包 (8门带人脸)', 18800],
            ['人脸识别考勤系统 (10人)', 14800],
            ['车牌识别停车场系统', 28000],
            ['智能楼宇对讲系统', 36000],
            ['综合安防集成包', 88000],
        ];
        $i = 0;
        foreach ($packages as [$name, $price]) {
            $data[] = [
                'code' => 'PKG' . str_pad((string)(++$i), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'category_id' => null,
                'unit' => '套',
                'spec' => $name,
                'sale_price' => $price,
                'cost_price' => $price * 0.6,
                'description' => $name . '，含安装调试一年质保',
                'status' => 'active',
                'created_at' => $this->randDate(),
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('sales_products', $data);
        $this->command->info("  ✓ sales_products: " . count($data));
    }

    private function seedOpportunities(): void
    {
        $data = [];
        $stages = ['requirement','solution','negotiation','contracting','won','lost'];
        $titles = ['监控系统升级改造','门禁系统新建','综合安防集成','车牌识别停车场','人脸识别门禁','远程监控项目','周界防范升级','智能楼宇改造'];
        for ($i = 0; $i < 40; $i++) {
            $created = $this->randDate();
            $data[] = [
                'opportunity_no' => 'OP' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'customer_id' => $this->pickRandom($this->customerIds),
                'customer_name' => '客户' . ($i + 1) . '号',
                'name' => $this->pickRandom($titles) . '-' . ($i + 1),
                'stage' => $this->pickRandom($stages),
                'estimated_amount' => $this->randomAmount(20000, 1000000),
                'probability' => rand(20, 90),
                'expected_sign_date' => Carbon::parse($created)->addDays(rand(30, 180))->toDateString(),
                'sales_id' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('opportunities', $data);
        $this->opportunityIds = DB::table('opportunities')->pluck('id')->toArray();
        $this->command->info("  ✓ opportunities: " . count($data));
    }

    private function seedQuotations(): void
    {
        $data = [];
        $statuses = ['draft','sent','accepted','rejected','expired'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $subtotal = $this->randomAmount(20000, 800000);
            $tax = round($subtotal * 0.13 / 1.13, 2);
            $total = $subtotal;
            $data[] = [
                'quotation_no' => 'Q' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'opportunity_id' => $this->pickRandom($this->opportunityIds),
                'version' => 1,
                'status' => $this->pickRandom($statuses),
                'subtotal' => $subtotal,
                'discount_rate' => 0,
                'discount_amount' => 0,
                'tax_rate' => 13,
                'tax_amount' => $tax,
                'total' => $total,
                'valid_until' => Carbon::parse($created)->addDays(30)->toDateString(),
                'notes' => '含设备、安装、调试、1年质保',
                'creator_id' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('quotations', $data);
        $this->command->info("  ✓ quotations: " . count($data));
    }

    private function seedProjects(): void
    {
        $data = [];
        $stages = ['initiation','inquiry','contract','purchase','construction','settlement','warranty'];
        $statuses = ['pending','in_progress','completed','on_hold','cancelled'];
        $types = ['camera','access_control','parking','alarm','integration'];
        $priorities = ['low','medium','high','urgent'];
        $prefix = ['智能安防','监控改造','门禁系统','综合安防','智慧园区','智慧楼宇','智慧工厂','智慧社区'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $customer = DB::table('customers')->where('id', $this->pickRandom($this->customerIds))->first();
            $data[] = [
                'project_no' => 'P' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $customer->name . '-' . $this->pickRandom($prefix) . '项目',
                'customer_id' => $customer->id,
                'type' => $this->pickRandom($types),
                'stage' => $this->pickRandom($stages),
                'status' => $this->pickRandom($statuses),
                'description' => '该项目位于' . $customer->city . '，共' . rand(20, 200) . '点位',
                'budget_device' => $this->randomAmount(50000, 800000),
                'budget_material' => $this->randomAmount(10000, 100000),
                'budget_labor' => $this->randomAmount(20000, 200000),
                'budget_outsource' => $this->randomAmount(0, 100000),
                'budget_other' => $this->randomAmount(0, 50000),
                'progress' => rand(0, 100),
                'manager_id' => $this->pickRandom($this->userIds),
                'start_date' => Carbon::parse($created)->addDays(rand(15, 45))->toDateString(),
                'end_date' => Carbon::parse($created)->addDays(rand(60, 180))->toDateString(),
                'actual_end_date' => rand(0, 1) ? Carbon::parse($created)->addDays(rand(60, 200))->toDateString() : null,
                'priority' => $this->pickRandom($priorities),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('projects', $data);
        $this->projectIds = DB::table('projects')->pluck('id')->toArray();
        $this->command->info("  ✓ projects: " . count($data));
    }

    private function seedProjectMembers(): void
    {
        $data = [];
        $roles = ['manager','engineer','electrician','installer','quality','safety','worker'];
        foreach ($this->projectIds as $pid) {
            $n = rand(3, 8);
            $members = $this->pickMany($this->userIds, $n);
            foreach ($members as $mid) {
                $data[] = [
                    'project_id' => $pid,
                    'user_id' => $mid,
                    'role' => $this->pickRandom($roles),
                    'join_date' => $this->randDate(),
                    'leave_date' => rand(0, 5) === 0 ? $this->randDate() : null,
                    'status' => 'active',
                    'created_at' => $this->randDate(),
                    'updated_at' => now(),
                ];
            }
        }
        $this->batchInsert('project_members', $data);
        $this->command->info("  ✓ project_members: " . count($data));
    }

    private function seedProjectBudgets(): void
    {
        $data = [];
        $statuses = ['draft','approved','rejected','archived'];
        foreach ($this->projectIds as $pid) {
            $created = $this->randDate();
            $data[] = [
                'project_id' => $pid,
                'code' => 'B' . $pid . '-v1',
                'version' => 1,
                'status' => $this->pickRandom($statuses),
                'material_budget' => $this->randomAmount(10000, 100000),
                'labor_budget' => $this->randomAmount(20000, 200000),
                'outsource_budget' => $this->randomAmount(0, 100000),
                'other_budget' => $this->randomAmount(0, 50000),
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('project_budgets', $data);
        $this->command->info("  ✓ project_budgets: " . count($data));
    }

    private function seedProjectMaterials(): void
    {
        $data = [];
        $units = ['台','米','个','箱','对','根','块','卷'];
        foreach ($this->projectIds as $pid) {
            $n = rand(5, 20);
            for ($i = 0; $i < $n; $i++) {
                $unit = $this->pickRandom($units);
                $qty = rand(1, 50);
                $cost = $this->randomAmount(20, 2000);
                $data[] = [
                    'project_id' => $pid,
                    'material_name' => $this->pickRandom(['摄像头','网线','交换机','电源','机柜','水晶头','线槽']),
                    'specification' => '标准规格',
                    'quantity' => $qty,
                    'unit' => $unit,
                    'unit_cost' => $cost,
                    'total_cost' => $qty * $cost,
                    'used_by' => $this->pickRandom($this->userIds),
                    'use_date' => $this->randDate(),
                    'inventory_item_id' => $this->pickRandom($this->productIds),
                    'notes' => '',
                    'created_at' => $this->randDate(),
                    'updated_at' => now(),
                ];
            }
        }
        $this->batchInsert('project_materials', $data);
        $this->command->info("  ✓ project_materials: " . count($data));
    }

    private function seedProjectContracts(): void
    {
        $data = [];
        $statuses = ['draft','signed','executing','completed','terminated'];
        $methods = ['installment','one_time','progress'];
        foreach ($this->projectIds as $pid) {
            $created = $this->randDate();
            $start = Carbon::parse($created);
            $end = $start->copy()->addDays(rand(60, 180));
            $data[] = [
                'project_id' => $pid,
                'contract_no' => 'C' . date('Ymd', strtotime($created)) . str_pad((string)$pid, 4, '0', STR_PAD_LEFT),
                'contract_amount' => $this->randomAmount(100000, 1500000),
                'payment_method' => $this->pickRandom($methods),
                'contract_start' => $start->toDateString(),
                'contract_end' => $end->toDateString(),
                'status' => $this->pickRandom($statuses),
                'attachment' => null,
                'signed_at' => $created,
                'notes' => '总包合同，含税',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('project_contracts', $data);
        $this->command->info("  ✓ project_contracts: " . count($data));
    }

    private function seedProjectSettlements(): void
    {
        $data = [];
        foreach ($this->projectIds as $pid) {
            if (rand(0, 2) > 0) {
                $created = $this->randDate();
                $income = $this->randomAmount(100000, 1500000);
                $cost = $income * 0.7;
                $data[] = [
                    'project_id' => $pid,
                    'total_income' => $income,
                    'total_cost' => $cost,
                    'cost_labor' => $cost * 0.4,
                    'cost_material' => $cost * 0.5,
                    'cost_outsource' => $cost * 0.05,
                    'cost_other' => $cost * 0.05,
                    'profit' => $income - $cost,
                    'profit_rate' => round(($income - $cost) / $income * 100, 2),
                    'settlement_date' => Carbon::parse($created)->toDateString(),
                    'status' => 'completed',
                    'created_at' => $created,
                    'updated_at' => $this->randDate($created, $this->endDate),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('project_settlements', $data);
        $this->command->info("  ✓ project_settlements: " . count($data));
    }

    private function seedPurchaseRequirements(): void
    {
        $data = [];
        $priorities = ['low','medium','high','urgent'];
        $statuses = ['pending','approved','rejected','converted','completed'];
        $units = ['台','米','个','箱','对','根','块','卷','件'];
        $materials = ['海康摄像头','大华摄像头','网线','交换机','门禁控制器','电源适配器','机柜','水晶头','线槽','硬盘','光模块'];
        for ($i = 0; $i < 50; $i++) {
            $created = $this->randDate();
            $data[] = [
                'code' => 'PR' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'project_id' => $this->pickRandom($this->projectIds),
                'material' => $this->pickRandom($materials),
                'spec' => '标准规格',
                'quantity' => rand(1, 100),
                'unit' => $this->pickRandom($units),
                'need_date' => Carbon::parse($created)->addDays(rand(7, 30))->toDateString(),
                'priority' => $this->pickRandom($priorities),
                'status' => $this->pickRandom($statuses),
                'creator' => $this->pickRandom($this->userIds) ? '某' . ['张','李','王'][array_rand(['张','李','王'])] : null,
                'remark' => '项目需要采购此物料',
                'review_remark' => rand(0, 1) ? '审核通过' : null,
                'reviewed_by' => rand(0, 1) ? $this->pickRandom($this->userIds) : null,
                'reviewed_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
                'name' => '采购需求-' . ($i + 1),
            ];
        }
        $this->batchInsert('purchase_requirements', $data);
        $this->command->info("  ✓ purchase_requirements: " . count($data));
    }

    private function seedPurchasePlans(): void
    {
        $data = [];
        $statuses = ['draft','submitted','approved','rejected','completed','cancelled'];
        $priorities = ['low','medium','high','urgent'];
        for ($i = 0; $i < 40; $i++) {
            $created = $this->randDate();
            $data[] = [
                'code' => 'PP' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'requirement_id' => $this->pickRandom(DB::table('purchase_requirements')->pluck('id')->toArray() ?: [null]),
                'project_id' => $this->pickRandom($this->projectIds),
                'title' => '采购计划-' . ($i + 1),
                'total_amount' => $this->randomAmount(50000, 800000),
                'plan_date' => Carbon::parse($created)->addDays(rand(7, 30))->toDateString(),
                'priority' => $this->pickRandom($priorities),
                'status' => $this->pickRandom($statuses),
                'submitter_id' => $this->pickRandom($this->userIds),
                'submitted_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'approver_id' => $this->pickRandom($this->userIds),
                'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'approve_remark' => rand(0, 1) ? '审核通过' : null,
                'remark' => '本期采购计划',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('purchase_plans', $data);
        $this->command->info("  ✓ purchase_plans: " . count($data));
    }

    private function seedPurchaseContracts(): void
    {
        $data = [];
        $statuses = ['draft','signed','delivering','completed','cancelled'];
        $paymentTerms = ['cash','30days','60days','90days'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $start = Carbon::parse($created);
            $amount = $this->randomAmount(30000, 600000);
            $data[] = [
                'code' => 'PC' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'plan_id' => $this->pickRandom(DB::table('purchase_plans')->pluck('id')->toArray() ?: [null]),
                'project_id' => $this->pickRandom($this->projectIds),
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'title' => '采购合同-' . ($i + 1),
                'total_amount' => $amount,
                'signed_at' => $start->toDateString(),
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(rand(30, 180))->toDateString(),
                'payment_terms' => $this->pickRandom($paymentTerms),
                'delivery_address' => '某市某区',
                'status' => $this->pickRandom($statuses),
                'signer' => '某' . ['张','李','王'][array_rand(['张','李','王'])],
                'signer_id' => $this->pickRandom($this->userIds),
                'remark' => '采购合同',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
                'purchase_order_id' => null,
                'payment_plan' => json_encode(['首付款30%', '验收后70%']),
            ];
        }
        $this->batchInsert('purchase_contracts', $data);
        $this->command->info("  ✓ purchase_contracts: " . count($data));
    }

    private function seedPurchaseOrders(): void
    {
        $data = [];
        $statuses = ['draft','approved','shipped','received','cancelled'];
        for ($i = 0; $i < 50; $i++) {
            $created = $this->randDate();
            $amount = $this->randomAmount(1000, 100000);
            $data[] = [
                'project_id' => $this->pickRandom($this->projectIds),
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'po_no' => 'PO' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'total_amount' => $amount,
                'status' => $this->pickRandom($statuses),
                'approved_by' => $this->pickRandom($this->userIds),
                'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'notes' => '采购订单',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
                'code' => 'PO-CODE-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'title' => '采购单-' . ($i + 1),
                'plan_id' => $this->pickRandom(DB::table('purchase_plans')->pluck('id')->toArray() ?: [null]),
            ];
        }
        $this->batchInsert('purchase_orders', $data);
        $this->command->info("  ✓ purchase_orders: " . count($data));
    }

    private function seedPurchaseShipments(): void
    {
        $data = [];
        $statuses = ['shipped','in_transit','delivered','exception'];
        $carriers = ['顺丰','德邦','京东物流','中通','圆通'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $data[] = [
                'code' => 'PS' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'contract_id' => $this->pickRandom(DB::table('purchase_contracts')->pluck('id')->toArray() ?: [null]),
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'shipped_at' => Carbon::parse($created)->toDateString(),
                'expected_arrival_at' => Carbon::parse($created)->addDays(rand(2, 7))->toDateString(),
                'arrived_at' => rand(0, 1) ? Carbon::parse($created)->addDays(rand(2, 7))->toDateString() : null,
                'carrier' => $this->pickRandom($carriers),
                'tracking_no' => strtoupper(Str::random(12)),
                'status' => $this->pickRandom($statuses),
                'consignee' => '某' . ['张','李','王'][array_rand(['张','李','王'])],
                'remark' => '运输中',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
                'stock_record_id' => null,
            ];
        }
        $this->batchInsert('purchase_shipments', $data);
        $this->command->info("  ✓ purchase_shipments: " . count($data));
    }

    private function seedPurchasePayments(): void
    {
        $data = [];
        $statuses = ['pending','success','failed','cancelled'];
        $methods = ['cash','transfer','acceptance','alipay','wechat'];
        for ($i = 0; $i < 40; $i++) {
            $created = $this->randDate();
            $data[] = [
                'code' => 'PAY' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'payment_request_id' => null,
                'contract_id' => $this->pickRandom(DB::table('purchase_contracts')->pluck('id')->toArray() ?: [null]),
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'amount' => $this->randomAmount(10000, 200000),
                'payment_method' => $this->pickRandom($methods),
                'paid_at' => Carbon::parse($created)->toDateString(),
                'voucher_no' => 'V' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'operator' => '某出纳',
                'operator_id' => $this->pickRandom($this->userIds),
                'status' => $this->pickRandom($statuses),
                'remark' => '支付货款',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('purchase_payments', $data);
        $this->command->info("  ✓ purchase_payments: " . count($data));
    }

    private function seedStockRecords(): void
    {
        $data = [];
        $types = ['in','out','adjust'];
        $reasons = ['采购入库','销售出库','项目领用','盘点调整','退货入库','调拨','报损'];
        for ($i = 0; $i < 500; $i++) {
            $created = $this->randDate();
            $type = $this->pickRandom($types);
            $qty = rand(1, 50);
            $data[] = [
                'record_no' => 'SR' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                'inventory_item_id' => $this->pickRandom($this->productIds),
                'warehouse_id' => $this->pickRandom($this->warehouseIds),
                'type' => $type,
                'quantity' => $qty,
                'remaining_stock' => rand(0, 200),
                'related_id' => null,
                'related_type' => null,
                'operator_id' => $this->pickRandom($this->userIds),
                'remark' => $this->pickRandom($reasons),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
                'party_type' => 'supplier',
                'party_id' => $this->pickRandom($this->supplierIds),
                'settle_id' => null,
                'project_id' => $this->pickRandom($this->projectIds),
                'out_method' => $type === 'out' ? 'pickup' : null,
                'logistics_company' => null,
                'logistics_no' => null,
                'parent_request_id' => null,
                'batch_no' => 'B' . str_pad((string)rand(1, 1000), 4, '0', STR_PAD_LEFT),
                'is_partial' => false,
            ];
        }
        $this->batchInsert('stock_records', $data, 100);
        $this->command->info("  ✓ stock_records: " . count($data));
    }

    private function seedServiceOrders(): void
    {
        $data = [];
        $urgencies = ['low','normal','urgent','critical'];
        $types = ['warranty','paid','contract','installation'];
        $statuses = ['pending','assigned','in_progress','completed','confirmed','cancelled'];
        $faults = ['摄像头无图像','录像机无法录像','门禁刷卡无效','人脸识别不通过','网络不通','图像模糊','红外失效','存储满','软件崩溃','电源故障','线缆老化'];
        for ($i = 0; $i < 200; $i++) {
            $created = $this->randDate();
            $status = $this->pickRandom($statuses);
            $data[] = [
                'order_no' => 'SO' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                'customer_id' => $this->pickRandom($this->customerIds),
                'project_id' => rand(0, 3) === 0 ? $this->pickRandom($this->projectIds) : null,
                'customer_device_id' => null,
                'fault_description' => $this->pickRandom($faults) . '，需要现场处理',
                'fault_photos' => json_encode([]),
                'urgency' => $this->pickRandom($urgencies),
                'service_type' => $this->pickRandom($types),
                'status' => $status,
                'assigned_to' => $this->pickRandom($this->userIds),
                'assigned_at' => $status !== 'pending' ? $this->randDate($created, Carbon::parse($created)->addDays(2)) : null,
                'started_at' => in_array($status, ['in_progress','completed','confirmed']) ? $this->randDate($created, Carbon::parse($created)->addDays(3)) : null,
                'completed_at' => in_array($status, ['completed','confirmed']) ? $this->randDate($created, Carbon::parse($created)->addDays(5)) : null,
                'confirmed_at' => $status === 'confirmed' ? $this->randDate($created, Carbon::parse($created)->addDays(7)) : null,
                'rating' => $status === 'confirmed' ? rand(3, 5) : null,
                'review' => $status === 'confirmed' && rand(0, 2) ? '处理及时' : null,
                'created_by' => $this->pickRandom($this->userIds),
                'sla_hours' => [12, 24, 48, 72][array_rand([12, 24, 48, 72])],
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('service_orders', $data, 100);
        $this->command->info("  ✓ service_orders: " . count($data));
    }

    private function seedServiceOrderLogs(): void
    {
        $data = [];
        $sos = DB::table('service_orders')->limit(100)->pluck('id')->toArray();
        $actions = ['创建工单','指派工程师','开始处理','更换配件','完成维修','客户确认','关闭工单'];
        foreach ($sos as $soId) {
            $n = rand(2, 5);
            for ($i = 0; $i < $n; $i++) {
                $data[] = [
                    'service_order_id' => $soId,
                    'user_id' => $this->pickRandom($this->userIds),
                    'action' => $this->pickRandom($actions),
                    'content' => '操作记录',
                    'photos' => json_encode([]),
                    'location' => '某市某区',
                    'gps_lat' => 22.5 + (rand(-1000, 1000) / 1000),
                    'gps_lng' => 113.9 + (rand(-1000, 1000) / 1000),
                    'created_at' => $this->randDate(),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('service_order_logs', $data);
        $this->command->info("  ✓ service_order_logs: " . count($data));
    }

    private function seedWorkOrders(): void
    {
        $data = [];
        $statuses = ['pending','assigned','in_progress','completed','confirmed'];
        $priorities = ['low','medium','high','urgent'];
        $serviceTypes = ['on_site','remote','shop'];
        for ($i = 0; $i < 60; $i++) {
            $created = $this->randDate();
            $status = $this->pickRandom($statuses);
            $data[] = [
                'code' => 'WO' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'customer_id' => $this->pickRandom($this->customerIds),
                'project_id' => rand(0, 2) === 0 ? $this->pickRandom($this->projectIds) : null,
                'contact_name' => '某工',
                'contact_phone' => '1' . rand(3, 9) . str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'address' => '某市某区某路 ' . rand(1, 999) . ' 号',
                'service_type' => $this->pickRandom($serviceTypes),
                'priority' => $this->pickRandom($priorities),
                'fault_description' => '需要现场' . $this->pickRandom(['检修','安装','调试','维护']) . '设备',
                'equipment_brand' => ['海康','大华','宇视'][array_rand(['海康','大华','宇视'])],
                'equipment_model' => strtoupper(Str::random(2)) . '-' . rand(100, 9999),
                'serial_no' => strtoupper(Str::random(12)),
                'assigned_to' => $this->pickRandom($this->userIds),
                'scheduled_at' => $this->randDate(),
                'started_at' => in_array($status, ['in_progress','completed','confirmed']) ? $this->randDate() : null,
                'completed_at' => in_array($status, ['completed','confirmed']) ? $this->randDate() : null,
                'status' => $status,
                'is_billable' => rand(0, 1) ? true : false,
                'service_fee' => $this->randomAmount(200, 2000),
                'parts_cost' => $this->randomAmount(0, 5000),
                'total_cost' => $this->randomAmount(200, 7000),
                'result_notes' => '已处理',
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('work_orders', $data);
        $this->command->info("  ✓ work_orders: " . count($data));
    }

    private function seedRepairOrders(): void
    {
        $data = [];
        $statuses = ['received','in_progress','completed','shipped','delivered','cancelled'];
        $severities = ['low','medium','high','critical'];
        $sourceTypes = ['customer','service_order','work_order','other'];
        for ($i = 0; $i < 40; $i++) {
            $created = $this->randDate();
            $data[] = [
                'code' => 'RO' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'source_type' => $this->pickRandom($sourceTypes),
                'source_id' => null,
                'source_code' => null,
                'customer_id' => $this->pickRandom($this->customerIds),
                'project_id' => $this->pickRandom($this->projectIds),
                'equipment_id' => null,
                'contact_name' => '某' . ['张','李','王'][array_rand(['张','李','王'])],
                'contact_phone' => '1' . rand(3, 9) . str_pad((string)rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'address' => '某市某区',
                'equipment_brand' => ['海康','大华','宇视'][array_rand(['海康','大华','宇视'])],
                'equipment_model' => strtoupper(Str::random(2)) . '-' . rand(100, 9999),
                'serial_no' => strtoupper(Str::random(12)),
                'fault_type' => $this->pickRandom(['主板','电源','外壳','线路','其他']),
                'fault_description' => $this->pickRandom(['主板损坏','电源故障','外壳破损','线路老化','无法开机','图像异常']),
                'severity' => $this->pickRandom($severities),
                'received_by' => $this->pickRandom($this->userIds),
                'received_at' => $created,
                'expected_finish_at' => Carbon::parse($created)->addDays(rand(3, 14)),
                'status' => $this->pickRandom($statuses),
                'method_type' => $this->pickRandom(['in_shop','on_site','shipped_back']),
                'parts_cost' => $this->randomAmount(0, 1000),
                'labor_cost' => $this->randomAmount(100, 2000),
                'shipping_cost' => $this->randomAmount(0, 200),
                'total_cost' => $this->randomAmount(100, 3000),
                'is_warranty' => rand(0, 1) ? true : false,
                'warranty_until' => Carbon::parse($created)->addMonths(rand(3, 12))->toDateString(),
                'remarks' => '维修单',
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('repair_orders', $data);
        $this->command->info("  ✓ repair_orders: " . count($data));
    }

    private function seedCustomerReceivables(): void
    {
        $data = [];
        $statuses = ['pending','partial','paid','overdue'];
        for ($i = 0; $i < 80; $i++) {
            $created = $this->randDate();
            $amount = $this->randomAmount(20000, 500000);
            $paid = rand(0, 100) / 100 * $amount;
            $data[] = [
                'customer_id' => $this->pickRandom($this->customerIds),
                'project_id' => $this->pickRandom($this->projectIds),
                'source_type' => 'manual',
                'source_id' => null,
                'ref_no' => 'CR' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'receivable_type' => 'contract',
                'amount' => $amount,
                'received_amount' => $paid,
                'due_date' => Carbon::parse($created)->addDays(rand(30, 90))->toDateString(),
                'status' => $this->pickRandom($statuses),
                'note' => '应收款',
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('customer_receivables', $data);
        $this->command->info("  ✓ customer_receivables: " . count($data));
    }

    private function seedCustomerReceipts(): void
    {
        $data = [];
        $methods = ['cash','bank','alipay','wechat','other'];
        for ($i = 0; $i < 50; $i++) {
            $created = $this->randDate();
            $data[] = [
                'customer_id' => $this->pickRandom($this->customerIds),
                'amount' => $this->randomAmount(10000, 200000),
                'receipt_date' => Carbon::parse($created)->toDateString(),
                'method' => $this->pickRandom($methods),
                'voucher_no' => 'V' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'bank_account' => (string)rand(6222020000000000, 6222029999999999),
                'operator' => '某出纳',
                'remark' => '客户付款',
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('customer_receipts', $data);
        $this->command->info("  ✓ customer_receipts: " . count($data));
    }

    private function seedPayables(): void
    {
        $data = [];
        $statuses = ['pending','partial','paid','overdue'];
        for ($i = 0; $i < 60; $i++) {
            $created = $this->randDate();
            $amount = $this->randomAmount(5000, 200000);
            $paid = rand(0, 100) / 100 * $amount;
            $data[] = [
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'project_id' => $this->pickRandom($this->projectIds),
                'amount' => $amount,
                'paid_amount' => $paid,
                'remaining_amount' => $amount - $paid,
                'due_date' => Carbon::parse($created)->addDays(rand(30, 90))->toDateString(),
                'paid_date' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'payment_term' => '30days',
                'status' => $this->pickRandom($statuses),
                'notes' => '应付供应商',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('payables', $data);
        $this->command->info("  ✓ payables: " . count($data));
    }

    private function seedReceivables(): void
    {
        $data = [];
        $statuses = ['pending','partial','paid','overdue'];
        for ($i = 0; $i < 60; $i++) {
            $created = $this->randDate();
            $amount = $this->randomAmount(10000, 300000);
            $paid = rand(0, 100) / 100 * $amount;
            $data[] = [
                'customer_id' => $this->pickRandom($this->customerIds),
                'project_id' => $this->pickRandom($this->projectIds),
                'contract_id' => null,
                'amount' => $amount,
                'received_amount' => $paid,
                'remaining_amount' => $amount - $paid,
                'due_date' => Carbon::parse($created)->addDays(rand(30, 90))->toDateString(),
                'received_date' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'overdue_days' => rand(0, 30),
                'status' => $this->pickRandom($statuses),
                'notes' => '应收客户',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('receivables', $data);
        $this->command->info("  ✓ receivables: " . count($data));
    }

    private function seedFinancePayments(): void
    {
        $data = [];
        $methods = ['cash','bank','alipay','wechat','other'];
        $categories = ['办公费','差旅费','业务招待','设备采购','工程款','工资','水电费','租金','通讯费','其他'];
        for ($i = 0; $i < 100; $i++) {
            $created = $this->randDate();
            $data[] = [
                'receivable_id' => null,
                'payable_id' => null,
                'account_id' => null,
                'amount' => $this->randomAmount(500, 50000),
                'payment_date' => Carbon::parse($created)->toDateString(),
                'method' => $this->pickRandom($methods),
                'voucher_no' => 'V' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'operator' => '某出纳',
                'remark' => $this->pickRandom($categories) . '支出',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('finance_payments', $data);
        $this->command->info("  ✓ finance_payments: " . count($data));
    }

    private function seedFinanceInvoices(): void
    {
        $data = [];
        $types = ['ordinary','special','electronic'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $amount = $this->randomAmount(10000, 200000);
            $data[] = [
                'invoice_no' => 'INV' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'invoice_type' => $this->pickRandom($types),
                'customer_id' => $this->pickRandom($this->customerIds),
                'project_id' => $this->pickRandom($this->projectIds),
                'receivable_id' => null,
                'amount' => $amount,
                'tax_rate' => 0.13,
                'tax_amount' => round($amount * 0.13 / 1.13, 2),
                'total_amount' => $amount,
                'issue_date' => Carbon::parse($created)->toDateString(),
                'status' => $this->pickRandom(['draft','issued','cancelled']),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('finance_invoices', $data);
        $this->command->info("  ✓ finance_invoices: " . count($data));
    }

    private function seedSupplierPayables(): void
    {
        $data = [];
        $statuses = ['pending','partial','paid'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $amount = $this->randomAmount(10000, 200000);
            $paid = rand(0, 100) / 100 * 100000;
            $data[] = [
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'project_id' => $this->pickRandom($this->projectIds),
                'source_type' => 'manual',
                'source_id' => null,
                'ref_no' => 'SP' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'amount' => $amount,
                'paid_amount' => $paid,
                'due_date' => Carbon::parse($created)->addDays(rand(30, 90))->toDateString(),
                'status' => $this->pickRandom($statuses),
                'note' => '应付供应商',
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('supplier_payables', $data);
        $this->command->info("  ✓ supplier_payables: " . count($data));
    }

    private function seedSupplierPayments(): void
    {
        $data = [];
        $methods = ['cash','bank','alipay','wechat','other'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $data[] = [
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'amount' => $this->randomAmount(10000, 150000),
                'payment_date' => Carbon::parse($created)->toDateString(),
                'method' => $this->pickRandom($methods),
                'voucher_no' => 'V' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'bank_account' => (string)rand(6222020000000000, 6222029999999999),
                'operator' => '某出纳',
                'remark' => '支付供应商',
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('supplier_payments', $data);
        $this->command->info("  ✓ supplier_payments: " . count($data));
    }

    private function seedAttendance(): void
    {
        $data = [];
        $businessUsers = DB::table('users')->where('is_system', false)->limit(6)->pluck('id')->toArray();
        if (empty($businessUsers)) $businessUsers = $this->userIds;
        $start = Carbon::parse('2025-12-27');
        for ($d = 0; $d < 180; $d++) {
            $date = $start->copy()->addDays($d);
            if (in_array($date->dayOfWeek, [0, 6])) continue;
            foreach ($businessUsers as $uid) {
                if (rand(0, 9) === 0) continue;
                $checkIn = $date->copy()->setTime(8, rand(0, 30));
                $checkOut = $date->copy()->setTime(17, rand(30, 59));
                $data[] = [
                    'user_id' => $uid,
                    'date' => $date->toDateString(),
                    'clock_in' => $checkIn->format('H:i:s'),
                    'clock_in_location' => '公司',
                    'clock_in_lat' => 22.5,
                    'clock_in_lng' => 113.9,
                    'clock_out' => $checkOut->format('H:i:s'),
                    'clock_out_location' => '公司',
                    'clock_out_lat' => 22.5,
                    'clock_out_lng' => 113.9,
                    'status' => 'present',
                    'work_hours' => $checkOut->diffInHours($checkIn),
                    'overtime_hours' => 0,
                    'project_id' => null,
                    'remark' => '',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }
        $this->batchInsert('attendance_records', $data, 500);
        $this->command->info("  ✓ attendance_records: " . count($data));
    }

    private function seedLeaveRequests(): void
    {
        $data = [];
        $types = ['annual','sick','personal','maternity','marriage','bereavement'];
        $statuses = ['pending','approved','rejected'];
        $users = DB::table('users')->where('is_system', false)->limit(10)->pluck('id')->toArray();
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $start = Carbon::parse($created);
            $days = rand(1, 5);
            $data[] = [
                'user_id' => $this->pickRandom($users),
                'type' => $this->pickRandom($types),
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays($days)->toDateString(),
                'days' => (float)$days,
                'reason' => $this->pickRandom(['家中有事','身体不适','婚假','探亲','个人事务','产假护理']),
                'status' => $this->pickRandom($statuses),
                'approver_id' => $this->pickRandom($this->userIds),
                'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'reject_reason' => null,
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('leave_requests', $data);
        $this->command->info("  ✓ leave_requests: " . count($data));
    }

    private function seedOvertimeRequests(): void
    {
        $data = [];
        $users = DB::table('users')->where('is_system', false)->limit(10)->pluck('id')->toArray();
        $compensations = ['time_off','money','none'];
        for ($i = 0; $i < 20; $i++) {
            $created = $this->randDate();
            $data[] = [
                'user_id' => $this->pickRandom($users),
                'overtime_date' => Carbon::parse($created)->toDateString(),
                'start_time' => '18:00:00',
                'end_time' => '22:00:00',
                'hours' => rand(1, 6),
                'reason' => $this->pickRandom(['项目紧急','交付压力大','客户要求','系统故障处理','日常加班']),
                'compensation_type' => $this->pickRandom($compensations),
                'status' => $this->pickRandom(['pending','approved','rejected']),
                'approver_id' => $this->pickRandom($this->userIds),
                'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'timesheet_leave_hours' => 0,
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('overtime_requests', $data);
        $this->command->info("  ✓ overtime_requests: " . count($data));
    }

    private function seedExpenseClaims(): void
    {
        $data = [];
        $categories = ['travel','meal','transport','office','accommodation','other','communication'];
        $statuses = ['draft','submitted','approving','approved','rejected','paid'];
        for ($i = 0; $i < 40; $i++) {
            $created = $this->randDate();
            $data[] = [
                'claim_no' => 'EX' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'user_id' => $this->pickRandom($this->userIds),
                'category' => $this->pickRandom($categories),
                'total_amount' => $this->randomAmount(100, 5000),
                'project_id' => $this->pickRandom($this->projectIds),
                'description' => $this->pickRandom(['出差','接待客户','办公采购','加班餐','打车','住宿']),
                'status' => $this->pickRandom($statuses),
                'approver_id' => $this->pickRandom($this->userIds),
                'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'paid_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'paid_amount' => rand(0, 1) ? $this->randomAmount(100, 5000) : null,
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('expense_claims', $data);
        $this->command->info("  ✓ expense_claims: " . count($data));
    }

    private function seedVehicles(): void
    {
        $data = [];
        $brands = ['丰田','大众','本田','日产','福特','别克','雪佛兰','比亚迪','吉利','长城','五菱','江淮','现代','起亚','奥迪','宝马','奔驰','特斯拉','蔚来','理想','小鹏'];
        $colors = ['白色','黑色','银色','灰色','红色','蓝色','香槟色'];
        $fuels = ['gasoline','diesel','electric','hybrid'];
        $deptNameToId = DB::table('departments')->pluck('id', 'name')->toArray();
        for ($i = 0; $i < 10; $i++) {
            $data[] = [
                'plate_no' => '粤B' . chr(65 + rand(0, 25)) . str_pad((string)rand(0, 99999), 5, '0', STR_PAD_LEFT),
                'brand' => $this->pickRandom($brands),
                'model' => '某车型' . ($i + 1),
                'color' => $this->pickRandom($colors),
                'purchase_date' => Carbon::parse('2020-01-01')->addDays(rand(0, 1500))->toDateString(),
                'purchase_price' => rand(10, 80) * 10000,
                'department_id' => $deptNameToId['行政人事部'] ?? null,
                'responsible_user_id' => $this->pickRandom($this->userIds),
                'status' => ['available','in_use','maintenance','retired'][array_rand(['available','in_use','maintenance','retired'])],
                'vin' => strtoupper(Str::random(17)),
                'engine_no' => strtoupper(Str::random(11)),
                'seats' => [2, 4, 5, 7, 9][array_rand([2, 4, 5, 7, 9])],
                'fuel_type' => $this->pickRandom($fuels),
                'created_at' => $this->randDate(),
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('vehicles', $data);
        $this->vehicleIds = DB::table('vehicles')->pluck('id')->toArray();
        $this->command->info("  ✓ vehicles: " . count($data));
    }

    private function seedFuelCards(): void
    {
        $data = [];
        $brands = ['中石化','中石油','BP','壳牌','道达尔','中化'];
        for ($i = 0; $i < 5; $i++) {
            $data[] = [
                'card_no' => 'FC' . str_pad((string)($i + 1), 10, '0', STR_PAD_LEFT),
                'card_name' => $this->pickRandom($brands) . '加油卡' . ($i + 1),
                'vehicle_id' => $this->pickRandom($this->vehicleIds),
                'balance' => $this->randomAmount(100, 3000),
                'status' => 'active',
                'issue_date' => Carbon::parse('2024-01-01')->addDays(rand(0, 365))->toDateString(),
                'expire_date' => Carbon::parse('2027-01-01')->toDateString(),
                'notes' => '加油卡',
                'created_at' => $this->randDate(),
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('fuel_cards', $data);
        $this->fuelCardIds = DB::table('fuel_cards')->pluck('id')->toArray();
        $this->command->info("  ✓ fuel_cards: " . count($data));
    }

    private function seedVehicleUsageRequests(): void
    {
        $data = [];
        $purposes = ['客户拜访','现场勘测','设备运输','项目巡检','会议参加','培训','其他'];
        $statuses = ['pending','approved','rejected','completed','cancelled'];
        $destinations = ['深圳','广州','东莞','惠州','中山','珠海','上海','北京'];
        for ($i = 0; $i < 50; $i++) {
            $created = $this->randDate();
            $data[] = [
                'vehicle_id' => $this->pickRandom($this->vehicleIds),
                'applicant_id' => $this->pickRandom($this->userIds),
                'usage_date' => Carbon::parse($created)->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'destination' => $this->pickRandom($destinations),
                'purpose' => $this->pickRandom($purposes),
                'passengers' => rand(1, 5),
                'self_drive' => rand(0, 1) ? true : false,
                'status' => $this->pickRandom($statuses),
                'approver_id' => $this->pickRandom($this->userIds),
                'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'actual_mileage' => rand(50, 500),
                'actual_fuel' => $this->randomAmount(10, 200),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('vehicle_usage_requests', $data);
        $this->command->info("  ✓ vehicle_usage_requests: " . count($data));
    }

    private function seedFuelCardRecharges(): void
    {
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $data[] = [
                'card_id' => $this->pickRandom($this->fuelCardIds),
                'amount' => $this->randomAmount(200, 1000),
                'recharge_date' => Carbon::parse($created)->toDateString(),
                'payment_method' => 'cash',
                'operator' => '某出纳',
                'voucher_no' => 'V' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'notes' => '加油卡充值',
                'created_at' => $created,
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('fuel_card_recharges', $data);
        $this->command->info("  ✓ fuel_card_recharges: " . count($data));
    }

    private function seedVehicleMaintenance(): void
    {
        $data = [];
        $types = ['routine','repair','inspection','tire_change','oil_change'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $data[] = [
                'vehicle_id' => $this->pickRandom($this->vehicleIds),
                'maintenance_type' => $this->pickRandom($types),
                'mileage' => rand(5000, 80000),
                'cost' => $this->randomAmount(200, 5000),
                'maintenance_date' => Carbon::parse($created)->toDateString(),
                'description' => $this->pickRandom(['常规保养','更换机油','更换轮胎','事故维修','年检','更换刹车片','更换电瓶']),
                'next_maintenance_mileage' => rand(60000, 100000),
                'next_maintenance_date' => Carbon::parse($created)->addMonths(rand(3, 12))->toDateString(),
                'handled_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('vehicle_maintenance_records', $data);
        $this->command->info("  ✓ vehicle_maintenance_records: " . count($data));
    }

    private function seedVehicleInsurance(): void
    {
        $data = [];
        $types = ['compulsory','commercial','third_party'];
        $companies = ['平安','人保','太平洋','中华联合','大地'];
        $statuses = ['active','expired','cancelled'];
        foreach ($this->vehicleIds as $vid) {
            $created = $this->randDate();
            $data[] = [
                'vehicle_id' => $vid,
                'insurance_company' => $this->pickRandom($companies),
                'policy_no' => 'INS' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'type' => $this->pickRandom($types),
                'premium' => $this->randomAmount(2000, 15000),
                'start_date' => $created,
                'end_date' => Carbon::parse($created)->addYear()->toDateString(),
                'status' => $this->pickRandom($statuses),
                'notes' => '车险',
                'created_at' => $created,
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('vehicle_insurance', $data);
        $this->command->info("  ✓ vehicle_insurance: " . count($data));
    }

    private function seedMaintenanceContracts(): void
    {
        $data = [];
        $statuses = ['active','expired','cancelled','suspended'];
        $frequencies = ['monthly','quarterly','semi_annual','annual','on_demand'];
        for ($i = 0; $i < 20; $i++) {
            $created = $this->randDate();
            $start = Carbon::parse($created);
            $end = $start->copy()->addYear();
            $data[] = [
                'contract_no' => 'MC' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'customer_id' => $this->pickRandom($this->userIds), // 152 旧 schema: customer_id 引用 users
                'amount' => $this->randomAmount(10000, 200000),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'inspection_frequency' => $this->pickRandom($frequencies),
                'scope' => '含' . $this->pickRandom(['摄像头','门禁','网络设备','对讲设备']) . '的定期巡检和故障处理',
                'status' => $this->pickRandom($statuses),
                'notes' => '含人工与材料',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('maintenance_contracts', $data);
        $this->maintenanceContractIds = DB::table('maintenance_contracts')->pluck('id')->toArray();
        $this->command->info("  ✓ maintenance_contracts: " . count($data));
    }

    private function seedWarrantyDeposits(): void
    {
        $data = [];
        $statuses = ['pending','held','released','forfeited'];
        foreach ($this->projectIds as $pid) {
            if (rand(0, 2) > 0) {
                $created = $this->randDate();
                $amount = $this->randomAmount(5000, 200000);
                $data[] = [
                    'project_id' => $pid,
                    'customer_id' => DB::table('projects')->where('id', $pid)->value('customer_id'),
                    'contract_amount' => $amount * 5,
                    'deposit_rate' => 5,
                    'deposit_amount' => $amount,
                    'hold_date' => Carbon::parse($created)->toDateString(),
                    'release_date' => Carbon::parse($created)->addYears(rand(1, 3))->toDateString(),
                    'status' => $this->pickRandom($statuses),
                    'release_amount' => 0,
                    'forfeit_amount' => 0,
                    'reason' => '质保金',
                    'approved_by' => rand(0, 1) ? $this->pickRandom($this->userIds) : null,
                    'approved_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                    'created_by' => $this->pickRandom($this->userIds),
                    'created_at' => $created,
                    'updated_at' => $this->randDate($created, $this->endDate),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('warranty_deposits', $data);
        $this->command->info("  ✓ warranty_deposits: " . count($data));
    }

    private function seedWarranties(): void
    {
        $data = [];
        $types = ['basic','extended','premium'];
        $statuses = ['active','expired','cancelled','renewed'];
        foreach ($this->projectIds as $pid) {
            if (rand(0, 2) > 0) {
                $created = $this->randDate();
                $start = Carbon::parse($created);
                $period = rand(12, 36);
                $data[] = [
                    'uuid' => Str::uuid()->toString(),
                    'project_id' => $pid,
                    'customer_id' => DB::table('projects')->where('id', $pid)->value('customer_id'),
                    'device_id' => null,
                    'warranty_no' => 'W' . date('Ymd', strtotime($created)) . str_pad((string)$pid, 4, '0', STR_PAD_LEFT),
                    'warranty_type' => $this->pickRandom($types),
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addMonths($period)->toDateString(),
                    'period_months' => $period,
                    'status' => $this->pickRandom($statuses),
                    'amount' => $this->randomAmount(1000, 50000),
                    'terms' => '标准质保条款',
                    'notes' => '',
                    'renewed_from_id' => null,
                    'created_by' => $this->pickRandom($this->userIds),
                    'updated_by' => null,
                    'created_at' => $created,
                    'updated_at' => $this->randDate($created, $this->endDate),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('warranties', $data);
        $this->command->info("  ✓ warranties: " . count($data));
    }

    private function seedWarrantyServiceOrders(): void
    {
        $data = [];
        $types = ['inspection','repair','replacement','maintenance'];
        $priorities = ['low','normal','high','urgent'];
        $statuses = ['pending','assigned','in_progress','completed','cancelled'];
        $warrantyIds = DB::table('warranties')->pluck('id')->toArray();
        if (empty($warrantyIds)) return;
        $seq = 0;
        foreach ($warrantyIds as $wid) {
            $n = rand(1, 3);
            for ($i = 0; $i < $n; $i++) {
                $seq++;
                $created = $this->randDate();
                $data[] = [
                    'warranty_id' => $wid,
                    'customer_id' => $this->pickRandom($this->customerIds),
                    'device_id' => null,
                    'order_no' => 'WSO-' . $wid . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT),
                    'service_type' => $this->pickRandom($types),
                    'priority' => $this->pickRandom($priorities),
                    'title' => '质保服务单-' . $seq,
                    'description' => $this->pickRandom(['定期巡检','故障处理','设备更换','紧急维修']),
                    'scheduled_date' => Carbon::parse($created)->addDays(rand(1, 7))->toDateString(),
                    'completed_date' => rand(0, 1) ? Carbon::parse($created)->addDays(rand(2, 14))->toDateString() : null,
                    'technician_id' => $this->pickRandom($this->userIds),
                    'fee' => $this->randomAmount(100, 3000),
                    'status' => $this->pickRandom($statuses),
                    'result_notes' => rand(0, 1) ? '已完成' : null,
                    'created_by' => $this->pickRandom($this->userIds),
                    'completed_by' => null,
                    'created_at' => $created,
                    'updated_at' => $this->randDate($created, $this->endDate),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('warranty_service_orders', $data);
        $this->command->info("  ✓ warranty_service_orders: " . count($data));
    }

    private function seedProcessTemplates(): void
    {
        $data = [];
        $industries = ['security','access','fire','electrical','low_voltage','integrated'];
        $categories = ['daily','weekly','monthly','quarterly','annual','on_demand'];
        $names = ['视频监控巡检','门禁系统巡检','综合安防月度巡检','停车场设备巡检','周界防范巡检','网络设备巡检'];
        foreach ($names as $name) {
            $data[] = [
                'industry' => $this->pickRandom($industries),
                'category' => $this->pickRandom($categories),
                'code' => 'PT' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'description' => $name . '标准流程',
                'standard_duration_days' => 1,
                'standard_man_hours' => 8,
                'is_active' => true,
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $this->randDate(),
                'updated_at' => now(),
            ];
        }
        $this->batchInsert('process_templates', $data);
        $this->processTemplateIds = DB::table('process_templates')->pluck('id')->toArray();
        $this->command->info("  ✓ process_templates: " . count($data));
    }

    private function seedProcessInstances(): void
    {
        $data = [];
        $statuses = ['pending','in_progress','completed','cancelled','paused'];
        for ($i = 0; $i < 30; $i++) {
            $created = $this->randDate();
            $start = Carbon::parse($created);
            $data[] = [
                'project_id' => $this->pickRandom($this->projectIds),
                'template_id' => $this->pickRandom($this->processTemplateIds),
                'parent_id' => null,
                'code' => 'PI' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => '巡检任务-' . ($i + 1),
                'sequence' => 1,
                'planned_start_date' => $start->toDateString(),
                'planned_end_date' => $start->copy()->addDays(7)->toDateString(),
                'actual_start_date' => $start->toDateString(),
                'actual_end_date' => rand(0, 1) ? $start->copy()->addDays(rand(3, 7))->toDateString() : null,
                'planned_duration_days' => 7,
                'actual_duration_days' => rand(3, 7),
                'status' => $this->pickRandom($statuses),
                'progress' => rand(0, 100),
                'foreman_id' => $this->pickRandom($this->userIds),
                'workers' => json_encode([]),
                'location' => '客户现场',
                'description' => '巡检描述',
                'accepted_at' => $created,
                'accepted_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('process_instances', $data);
        $this->command->info("  ✓ process_instances: " . count($data));
    }

    private function seedProcessInspections(): void
    {
        $data = [];
        $results = ['pending','pass','fail','partial','skipped'];
        $instances = DB::table('process_instances')->pluck('id')->toArray();
        $types = ['设备检查','功能测试','安全验证','性能检测','清洁卫生','线缆检查'];
        foreach ($instances as $iid) {
            for ($j = 0; $j < rand(3, 6); $j++) {
                $created = $this->randDate();
                $data[] = [
                    'process_instance_id' => $iid,
                    'inspection_type' => $this->pickRandom($types),
                    'inspector_id' => $this->pickRandom($this->userIds),
                    'inspector_name' => '某' . ['张','李','王'][array_rand(['张','李','王'])],
                    'inspection_date' => Carbon::parse($created)->toDateString(),
                    'result' => $this->pickRandom($results),
                    'score' => rand(60, 100),
                    'checkpoint_results' => json_encode([]),
                    'issues' => json_encode([]),
                    'suggestions' => '建议定期检查',
                    'next_inspection_date' => Carbon::parse($created)->addMonths(1)->toDateString(),
                    'created_at' => $created,
                    'updated_at' => $this->randDate($created, $this->endDate),
                ];
            }
        }
        if (!empty($data)) $this->batchInsert('process_inspections', $data);
        $this->command->info("  ✓ process_inspections: " . count($data));
    }

    private function seedRectifications(): void
    {
        $data = [];
        $statuses = ['pending','in_progress','completed','overdue','cancelled'];
        $severities = ['low','medium','high','critical'];
        $sources = ['inspection','customer','internal','audit','other'];
        for ($i = 0; $i < 20; $i++) {
            $created = $this->randDate();
            $data[] = [
                'project_id' => $this->pickRandom($this->projectIds),
                'commencement_order_id' => null,
                'construction_log_id' => null,
                'code' => 'RC' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'source_type' => $this->pickRandom($sources),
                'source_id' => null,
                'title' => '整改单-' . ($i + 1),
                'description' => $this->pickRandom(['摄像头角度需调整','门禁需增加','电源老化','线路整改','防水处理','机房整理']),
                'severity' => $this->pickRandom($severities),
                'responsible_id' => $this->pickRandom($this->userIds),
                'deadline' => Carbon::parse($created)->addDays(rand(7, 30))->toDateString(),
                'status' => $this->pickRandom($statuses),
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('rectifications', $data);
        $this->command->info("  ✓ rectifications: " . count($data));
    }

    private function seedApprovalTemplates(): void
    {
        $data = [];
        $types = [
            ['expense', '报销审批', '通用报销审批流程'],
            ['leave', '请假审批', '请假审批流程'],
            ['overtime', '加班审批', '加班申请流程'],
            ['purchase', '采购审批', '采购申请流程'],
            ['payment', '付款审批', '付款申请流程'],
        ];
        foreach ($types as $i => [$code, $name, $desc]) {
            $data[] = [
                'name' => $name,
                'module' => $code,
                'description' => $desc,
                'nodes' => json_encode([
                    ['name' => '申请人提交', 'type' => 'start'],
                    ['name' => '部门经理审批', 'type' => 'approve', 'role' => 'manager'],
                    ['name' => '财务审批', 'type' => 'approve', 'role' => 'finance'],
                    ['name' => '总经理审批', 'type' => 'approve', 'role' => 'admin'],
                    ['name' => '完成', 'type' => 'end'],
                ]),
                'status' => '启用',
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $this->randDate(),
                'updated_at' => $this->randDate(),
            ];
        }
        $this->batchInsert('approval_templates', $data);
        $this->command->info("  ✓ approval_templates: " . count($data));
    }

    private function seedApprovalInstances(): void
    {
        $data = [];
        $statuses = ['pending','approved','rejected'];
        $actions = ['submitted','approved','rejected','forwarded','returned'];
        for ($i = 0; $i < 100; $i++) {
            $created = $this->randDate();
            $action = $this->pickRandom($actions);
            $data[] = [
                'approvable_type' => 'App\\Models\\ExpenseClaim',
                'approvable_id' => rand(1, 40),
                'user_id' => $this->pickRandom($this->userIds),
                'action' => $action,
                'comment' => '审批意见',
                'status' => $this->pickRandom($statuses),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('approval_records', $data);
        $this->command->info("  ✓ approval_records: " . count($data));
    }

    private function seedTenderProjects(): void
    {
        $data = [];
        $statuses = ['draft','published','bidding','evaluating','awarded','cancelled'];
        $types = ['tender','rfq','auction'];
        for ($i = 0; $i < 15; $i++) {
            $created = $this->randDate();
            $data[] = [
                'code' => 'T' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => '招标项目-' . ($i + 1),
                'description' => '招标项目，需按时投标',
                'project_id' => $this->pickRandom($this->projectIds),
                'rfq_id' => null,
                'created_by' => $this->pickRandom($this->userIds),
                'type' => $this->pickRandom($types),
                'status' => $this->pickRandom($statuses),
                'required_items' => json_encode([]),
                'invited_supplier_ids' => json_encode([]),
                'publish_at' => $created,
                'deadline' => Carbon::parse($created)->addDays(rand(15, 60)),
                'open_at' => Carbon::parse($created)->addDays(rand(16, 61)),
                'public_token' => Str::uuid()->toString(),
                'score_config' => json_encode([]),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('tender_projects', $data);
        $this->command->info("  ✓ tender_projects: " . count($data));
    }

    private function seedTenderBids(): void
    {
        $data = [];
        $statuses = ['draft','submitted','won','lost','cancelled'];
        $tenderIds = DB::table('tender_projects')->pluck('id')->toArray();
        foreach ($tenderIds as $tpid) {
            $n = rand(1, 3);
            $used = [];
            for ($i = 0; $i < $n; $i++) {
                $created = $this->randDate();
                $sid = $this->pickRandom($this->supplierIds);
                // 去重 (tender_project_id, supplier_id)
                $k = $tpid . '-' . $sid;
                if (in_array($k, $used)) continue;
                $used[] = $k;
                $data[] = [
                    'tender_project_id' => $tpid,
                    'supplier_id' => $sid,
                    'code' => 'B' . $tpid . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                    'total_amount' => $this->randomAmount(200000, 5000000),
                    'lead_time_days' => rand(15, 60),
                    'technical_proposal' => '技术方案内容',
                    'remark' => '投标备注',
                    'status' => $this->pickRandom($statuses),
                    'submitted_at' => $created,
                    'submitter_user_id' => $this->pickRandom($this->userIds),
                    'created_at' => $created,
                    'updated_at' => $this->randDate($created, $this->endDate),
                ];
            }
        }
        $this->batchInsert('tender_bids', $data);
        $this->command->info("  ✓ tender_bids: " . count($data));
    }

    private function seedExternalQuoteRequests(): void
    {
        $data = [];
        $statuses = ['open','sent','received','accepted','cancelled'];
        for ($i = 0; $i < 15; $i++) {
            $created = $this->randDate();
            $data[] = [
                'project_id' => $this->pickRandom($this->projectIds),
                'code' => 'ER' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'title' => '外询请求-' . ($i + 1),
                'required_items' => json_encode([]),
                'required_files' => json_encode([]),
                'deadline' => $created,
                'status' => $this->pickRandom($statuses),
                'public_token' => Str::uuid()->toString(),
                'awarded_supplier_id' => null,
                'awarded_quote_id' => null,
                'created_by' => $this->pickRandom($this->userIds),
                'description' => '需要外部报价',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('external_quote_requests', $data);
        $this->command->info("  ✓ external_quote_requests: " . count($data));
    }

    private function seedExternalQuotes(): void
    {
        $data = [];
        $paymentTerms = ['30days','60days','90days'];
        $reqIds = DB::table('external_quote_requests')->pluck('id')->toArray();
        for ($i = 0; $i < 15; $i++) {
            $created = $this->randDate();
            $data[] = [
                'request_id' => $reqIds[$i] ?? ($i + 1),
                'supplier_id' => $this->pickRandom($this->supplierIds),
                'code' => 'EQ' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'items' => json_encode([]),
                'total_amount' => $this->randomAmount(10000, 200000),
                'valid_until' => Carbon::parse($created)->addDays(30)->toDateString(),
                'lead_time_days' => rand(7, 30),
                'payment_terms' => $this->pickRandom($paymentTerms),
                'attachments' => json_encode([]),
                'note' => '外部供应商报价',
                'submitted_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('external_quotes', $data);
        $this->command->info("  ✓ external_quotes: " . count($data));
    }

    private function seedExternalConstructionWorks(): void
    {
        $data = [];
        $statuses = ['open','bidding','evaluating','awarded','in_progress','completed','cancelled'];
        for ($i = 0; $i < 15; $i++) {
            $created = $this->randDate();
            $start = Carbon::parse($created);
            $data[] = [
                'project_id' => $this->pickRandom($this->projectIds),
                'code' => 'EW' . date('Ymd', strtotime($created)) . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                'title' => '外包工程-' . ($i + 1),
                'work_scope' => '外包工程内容描述',
                'required_skills' => json_encode(['弱电', '布线', '高空作业']),
                'estimated_budget' => $this->randomAmount(20000, 500000),
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(rand(30, 90))->toDateString(),
                'bid_deadline' => $start->copy()->addDays(rand(7, 15))->toDateString(),
                'bid_count' => rand(0, 5),
                'status' => $this->pickRandom($statuses),
                'awarded_amount' => rand(0, 1) ? $this->randomAmount(20000, 500000) : null,
                'created_by' => $this->pickRandom($this->userIds),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('external_construction_works', $data);
        $this->command->info("  ✓ external_construction_works: " . count($data));
    }

    private function seedSalesFollowUps(): void
    {
        $data = [];
        $methods = ['phone','wechat','email','visit','meeting'];
        $results = ['客户感兴趣','客户需考虑','客户已拒绝','客户要报价','客户要方案','已约见'];
        foreach ($this->customerIds as $cid) {
            $n = rand(2, 6);
            for ($i = 0; $i < $n; $i++) {
                $data[] = [
                    'customer_id' => $cid,
                    'user_id' => $this->pickRandom($this->userIds),
                    'method' => $this->pickRandom($methods),
                    'content' => '与客户沟通' . $this->pickRandom(['需求','预算','方案','技术','商务条款']),
                    'result' => $this->pickRandom($results),
                    'followed_at' => $this->randDate(),
                    'next_followup_at' => Carbon::parse($this->randDate())->addDays(rand(1, 14)),
                    'created_at' => $this->randDate(),
                    'updated_at' => $this->randDate(),
                ];
            }
        }
        $this->batchInsert('sales_follow_ups', $data);
        $this->command->info("  ✓ sales_follow_ups: " . count($data));
    }

    private function seedDiskFolders(): void
    {
        $data = [
            ['scope' => 'project_root', 'parent_id' => null, 'name' => '项目', 'is_protected' => true, 'owner_id' => 1, 'created_by' => 1, 'created_at' => $this->randDate(), 'updated_at' => now()],
            ['scope' => 'work_root', 'parent_id' => null, 'name' => '工作', 'is_protected' => true, 'owner_id' => 1, 'created_by' => 1, 'created_at' => $this->randDate(), 'updated_at' => now()],
            ['scope' => 'share_root', 'parent_id' => null, 'name' => '共享', 'is_protected' => false, 'owner_id' => 1, 'created_by' => 1, 'created_at' => $this->randDate(), 'updated_at' => now()],
        ];
        $this->batchInsert('disk_folders', $data);
        $parentIds = DB::table('disk_folders')->pluck('id')->toArray();

        $subFolders = [];
        $subNames = ['合同文档','技术方案','项目报告','客户资料','培训资料','财务凭证','行政文件'];
        foreach ($parentIds as $fid) {
            $n = rand(2, 4);
            for ($i = 0; $i < $n; $i++) {
                $subFolders[] = [
                    'scope' => 'share',
                    'parent_id' => $fid,
                    'name' => $this->pickRandom($subNames),
                    'is_protected' => false,
                    'owner_id' => $this->pickRandom($this->userIds),
                    'created_by' => $this->pickRandom($this->userIds),
                    'created_at' => $this->randDate(),
                    'updated_at' => now(),
                ];
            }
        }
        $this->batchInsert('disk_folders', $subFolders);
        $this->command->info("  ✓ disk_folders: " . (count($data) + count($subFolders)));
    }

    private function seedDiskFiles(): void
    {
        $data = [];
        $types = ['doc','pdf','xls','jpg','png','dwg','zip'];
        $folders = DB::table('disk_folders')->pluck('id')->toArray();
        for ($i = 0; $i < 100; $i++) {
            $created = $this->randDate();
            $ext = $this->pickRandom($types);
            $data[] = [
                'folder_id' => $this->pickRandom($folders),
                'name' => '文件' . ($i + 1) . '.' . $ext,
                'original_name' => '原始文件' . ($i + 1) . '.' . $ext,
                'path' => '/uploads/' . date('Y/m', strtotime($created)) . '/file_' . ($i + 1) . '.' . $ext,
                'mime_type' => 'application/' . $ext,
                'size' => rand(1024, 10485760),
                'extension' => $ext,
                'uploaded_by' => $this->pickRandom($this->userIds),
                'download_count' => rand(0, 50),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('disk_files', $data);
        $this->command->info("  ✓ disk_files: " . count($data));
    }

    private function seedNotifications(): void
    {
        $data = [];
        $types = ['approval','system','project','finance','service'];
        $levels = ['info','warning','success','error'];
        $titles = ['新审批待处理','项目状态变更','付款到期提醒','工单分配','系统通知','巡检任务','考勤提醒','合同到期提醒'];
        for ($i = 0; $i < 200; $i++) {
            $created = $this->randDate();
            $uid = $this->pickRandom($this->userIds);
            $data[] = [
                'type' => $this->pickRandom($types),
                'title' => $this->pickRandom($titles),
                'content' => '系统通知：' . $this->pickRandom(['您的报销已审批通过','新工单已分配给您','项目已通过验收','考勤异常提醒','付款已到账','巡检任务待处理']),
                'read_at' => rand(0, 1) ? $this->randDate($created, $this->endDate) : null,
                'data' => json_encode(['link' => '/admin']),
                'notifiable_id' => $uid,
                'notifiable_type' => 'App\\Models\\User',
                'sender_id' => $this->pickRandom($this->userIds),
                'level' => $this->pickRandom($levels),
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('notifications', $data);
        $this->command->info("  ✓ notifications: " . count($data));
    }

    private function seedSystemLogs(): void
    {
        $data = [];
        $actions = ['login','create','update','delete','view','export','import','approve','reject'];
        $modules = ['auth','user','role','permission','customer','project','service','purchase','finance','attendance'];
        $types = ['operation','auth','data','system','error'];
        for ($i = 0; $i < 200; $i++) {
            $created = $this->randDate();
            $data[] = [
                'user_id' => $this->pickRandom($this->userIds),
                'type' => $this->pickRandom($types),
                'module' => $this->pickRandom($modules),
                'action' => $this->pickRandom($actions),
                'description' => $this->pickRandom($actions) . ' ' . $this->pickRandom($modules),
                'ip' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'created_at' => $created,
                'updated_at' => $this->randDate($created, $this->endDate),
            ];
        }
        $this->batchInsert('system_logs', $data);
        $this->command->info("  ✓ system_logs: " . count($data));
    }
}
