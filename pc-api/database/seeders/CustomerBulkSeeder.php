<?php
/**
 * 客户补种 Seeder — 15+ 真实客户
 * 解决问题: 施工管理/项目创建 时客户下拉只有 2-5 个选项
 *
 * 特点:
 * - 真实安防行业客户场景(学校/医院/园区/工厂/政府/商业/小区)
 * - 覆盖 vip / normal / potential / lost 四档
 * - 全部用 insertOrIgnore (name 唯一, 重复跑安全)
 * - 不依赖 userMap / customerMap (可在任何 seeder 后独立跑)
 *
 * 用法: php artisan db:seed --class=CustomerBulkSeeder
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerBulkSeeder extends Seeder
{
    public function run(): void
    {
        // 找一个管理员作为 assigned_user_id (软依赖)
        $adminId = DB::table('users')->where('username', 'admin')->value('id') ?? 1;

        $customers = [
            // 5 个学校/教育
            ['name' => '宁波市镇海中学', 'category' => 'vip', 'industry' => '教育', 'province' => '浙江', 'city' => '宁波', 'district' => '镇海区', 'address' => '镇海区招宝山街道', 'source' => '转介绍', 'tags' => ['学校', '重点单位', '高考考点'], 'contact' => '王主任', 'phone' => '0574-86270001', 'credit_code' => '91330211MA28XYZK01'],
            ['name' => '北仑区明港高级中学', 'category' => 'vip', 'industry' => '教育', 'province' => '浙江', 'city' => '宁波', 'district' => '北仑区', 'address' => '北仑区新碶街道', 'source' => '老客户', 'tags' => ['学校', '寄宿制'], 'contact' => '李校长', 'phone' => '0574-86860002', 'credit_code' => '91330206MA28XYZK02'],
            ['name' => '宁波大学科学技术学院', 'category' => 'normal', 'industry' => '教育', 'province' => '浙江', 'city' => '宁波', 'district' => '慈溪市', 'address' => '慈溪市白沙街道', 'source' => '招标', 'tags' => ['高校', '实验室'], 'contact' => '陈处长', 'phone' => '0574-63910003', 'credit_code' => '91330282MA28XYZK03'],

            // 5 个医院/医疗
            ['name' => '宁波李惠利医院', 'category' => 'vip', 'industry' => '医疗', 'province' => '浙江', 'city' => '宁波', 'district' => '鄞州区', 'address' => '鄞州区兴宁路57号', 'source' => '转介绍', 'tags' => ['三甲', '重点单位', '监控密集'], 'contact' => '张主任', 'phone' => '0574-87030001', 'credit_code' => '91330212MA28XYZK11'],
            ['name' => '宁波市第一医院', 'category' => 'vip', 'industry' => '医疗', 'province' => '浙江', 'city' => '宁波', 'district' => '海曙区', 'address' => '海曙区柳汀街59号', 'source' => '老客户', 'tags' => ['三甲', '老院区改造'], 'contact' => '刘工', 'phone' => '0574-87080002', 'credit_code' => '91330203MA28XYZK12'],
            ['name' => '北仑区人民医院', 'category' => 'normal', 'industry' => '医疗', 'province' => '浙江', 'city' => '宁波', 'district' => '北仑区', 'address' => '北仑区新碶街道', 'source' => '招标', 'tags' => ['二甲'], 'contact' => '黄科', 'phone' => '0574-86780003', 'credit_code' => '91330206MA28XYZK13'],

            // 4 个园区/工厂
            ['name' => '宁波经济技术开发区', 'category' => 'vip', 'industry' => '园区', 'province' => '浙江', 'city' => '宁波', 'district' => '北仑区', 'address' => '北仑区经济技术开发区', 'source' => '政府对接', 'tags' => ['园区', '政府', '大客户'], 'contact' => '王局', 'phone' => '0574-86820001', 'credit_code' => '91330206MA28XYZK21'],
            ['name' => '宁波杭州湾吉利汽车工厂', 'category' => 'vip', 'industry' => '工厂', 'province' => '浙江', 'city' => '宁波', 'district' => '慈溪市', 'address' => '慈溪市杭州湾新区', 'source' => '官网咨询', 'tags' => ['工厂', '大客户', '长期合作'], 'contact' => '陈经理', 'phone' => '0574-63050002', 'credit_code' => '91330282MA28XYZK22'],
            ['name' => '宁波鄞州万达广场', 'category' => 'normal', 'industry' => '商业', 'province' => '浙江', 'city' => '宁波', 'district' => '鄞州区', 'address' => '鄞州区四明中路668号', 'source' => '转介绍', 'tags' => ['商场', '人流密集'], 'contact' => '林总', 'phone' => '0574-88230003', 'credit_code' => '91330212MA28XYZK23'],
            ['name' => '宁波港股份有限公司', 'category' => 'vip', 'industry' => '港口', 'province' => '浙江', 'city' => '宁波', 'district' => '北仑区', 'address' => '北仑区迎宾路', 'source' => '招标', 'tags' => ['港口', '国央企', '重要设施'], 'contact' => '吴主任', 'phone' => '0574-86990001', 'credit_code' => '91330200MA28XYZK24'],

            // 3 个小区/物业
            ['name' => '万科金色水岸小区', 'category' => 'normal', 'industry' => '物业', 'province' => '浙江', 'city' => '宁波', 'district' => '鄞州区', 'address' => '鄞州区下应街道', 'source' => '老客户', 'tags' => ['小区', '物业'], 'contact' => '物业赵', 'phone' => '0574-88250001', 'credit_code' => '91330212MA28XYZK31'],
            ['name' => '雅戈尔·长岛花园', 'category' => 'potential', 'industry' => '物业', 'province' => '浙江', 'city' => '宁波', 'district' => '海曙区', 'address' => '海曙区古林街道', 'source' => '陌拜', 'tags' => ['小区', '高端'], 'contact' => '物业钱', 'phone' => '0574-88250002', 'credit_code' => '91330203MA28XYZK32'],
            ['name' => '镇海炼化生活区', 'category' => 'normal', 'industry' => '物业', 'province' => '浙江', 'city' => '宁波', 'district' => '镇海区', 'address' => '镇海区蛟川街道', 'source' => '转介绍', 'tags' => ['小区', '国企'], 'contact' => '物业孙', 'phone' => '0574-86250003', 'credit_code' => '91330211MA28XYZK33'],

            // 3 个政府/公共
            ['name' => '宁波市公安局交警支队', 'category' => 'vip', 'industry' => '政府机构', 'province' => '浙江', 'city' => '宁波', 'district' => '鄞州区', 'address' => '鄞州区宁穿路1901号', 'source' => '招标', 'tags' => ['政府', '交警', '雪亮工程'], 'contact' => '周队', 'phone' => '0574-87090001', 'credit_code' => '11330200MA28XYZK41'],
            ['name' => '宁波市镇海区人民法院', 'category' => 'normal', 'industry' => '政府机构', 'province' => '浙江', 'city' => '宁波', 'district' => '镇海区', 'address' => '镇海区骆驼街道', 'source' => '招标', 'tags' => ['政府', '法院'], 'contact' => '冯主任', 'phone' => '0574-86270041', 'credit_code' => '11330211MA28XYZK42'],
            ['name' => '宁波栎社国际机场', 'category' => 'potential', 'industry' => '交通', 'province' => '浙江', 'city' => '宁波', 'district' => '海曙区', 'address' => '海曙区栎社街道', 'source' => '官网咨询', 'tags' => ['机场', '重点单位'], 'contact' => '蒋科', 'phone' => '0574-87420001', 'credit_code' => '91330200MA28XYZK43'],
        ];

        $now = now();
        $inserted = 0;
        $skipped = 0;
        foreach ($customers as $i => $c) {
            // 查重
            $exists = DB::table('customers')->where('name', $c['name'])->value('id');
            if ($exists) {
                $skipped++;
                continue;
            }
            DB::table('customers')->insert([
                'name' => $c['name'],
                'category' => $c['category'],
                'industry' => $c['industry'],
                'province' => $c['province'],
                'city' => $c['city'],
                'district' => $c['district'],
                'address' => $c['address'],
                'source' => $c['source'],
                'tags' => json_encode($c['tags'], JSON_UNESCAPED_UNICODE),
                'credit_code' => $c['credit_code'],
                'longitude' => 121.5 + ($i * 0.02),
                'latitude' => 29.8 + ($i * 0.01),
                'status' => 'active',
                'assigned_user_id' => $adminId,
                'description' => $c['name'] . ' - 行业:' . $c['industry'] . ', 重要性:' . $c['category'] . ', 联系人:' . $c['contact'] . ' / ' . $c['phone'],
                'pipeline_stage' => $c['category'] === 'vip' ? 'customer' : ($c['category'] === 'potential' ? 'lead' : 'opportunity'),
                'expected_amount' => rand(50, 500) * 1000,
                'created_at' => $now->copy()->subMonths(rand(1, 18))->subDays(rand(0, 28)),
                'updated_at' => $now->copy()->subDays(rand(1, 30)),
            ]);

            // 同步插主联系人 (Customer::primaryContact 关联 + 422 报错的 contact 字段都来自这里)
            DB::table('customer_contacts')->insertOrIgnore([
                'customer_id' => DB::getPdo()->lastInsertId(),
                'name' => $c['contact'],
                'phone' => $c['phone'],
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }
        echo "  ✓ 客户补种完成: 新增 $inserted 条, 跳过已存在 $skipped 条\n";
        echo "  ✓ 当前 customers 总数: " . DB::table('customers')->count() . " 条\n";
    }
}
