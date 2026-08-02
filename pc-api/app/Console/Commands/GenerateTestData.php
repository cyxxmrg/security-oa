<?php
// 在152服务器上创建生成测试数据的Laravel命令
// 保存为 /var/www/oa-api/app/Console/Commands/GenerateTestData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class GenerateTestData extends Command
{
    protected $signature = 'app:generate-test-data';
    protected $description = '生成至少半年的测试数据 for 所有模块';

    public function handle()
    {
        $faker = Faker::create('zh_CN');

        $this->info('开始生成测试数据...');

        // 1. 生成员工数据
        $this->generateEmployees($faker);

        // 2. 生成客户数据
        $this->generateCustomers($faker);

        // 3. 生成项目数据（含7阶段）
        $this->generateProjects($faker);

        // 4. 生成考勤数据
        $this->generateAttendances($faker);

        // 5. 生成报销数据
        $this->generateExpenses($faker);

        // 6. 生成车辆管理数据
        $this->generateVehicles($faker);

        // 7. 生成库存管理数据
        $this->generateInventory($faker);

        // 8. 生成财务管理数据
        $this->generateFinance($faker);

        // 9. 生成网盘数据
        $this->generateDisk($faker);

        // 10. 生成知识库数据
        $this->generateKnowledge($faker);

        // 11. 生成消息数据
        $this->generateMessages($faker);

        $this->info('✅ 测试数据生成完成！');
    }

    private function generateEmployees($faker)
    {
        $this->info('生成员工数据...');
        // 实现代码...
    }

    private function generateCustomers($faker)
    {
        $this->info('生成客户数据...');
        // 实现代码...
    }

    private function generateProjects($faker)
    {
        $this->info('生成项目数据...');
        // 实现代码...
    }

    private function generateAttendances($faker)
    {
        $this->info('生成考勤数据...');
        // 实现代码...
    }

    private function generateExpenses($faker)
    {
        $this->info('生成报销数据...');
        // 实现代码...
    }

    private function generateVehicles($faker)
    {
        $this->info('生成车辆管理数据...');
        // 实现代码...
    }

    private function generateInventory($faker)
    {
        $this->info('生成库存管理数据...');
        // 实现代码...
    }

    private function generateFinance($faker)
    {
        $this->info('生成财务管理数据...');
        // 实现代码...
    }

    private function generateDisk($faker)
    {
        $this->info('生成网盘数据...');
        // 实现代码...
    }

    private function generateKnowledge($faker)
    {
        $this->info('生成知识库数据...');
        // 实现代码...
    }

    private function generateMessages($faker)
    {
        $this->info('生成消息数据...');
        // 实现代码...
    }
}
