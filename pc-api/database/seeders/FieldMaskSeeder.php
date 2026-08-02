<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * V0.5.2 - 把 V0.5.1 静态 $protected 搬进 field_masks 表
 *
 * 跑法: php artisan db:seed --class=FieldMaskSeeder
 */
class FieldMaskSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            'finance' => [
                'allowed_roles' => 'admin,finance',
                'fields' => [
                    'amount'           => '金额',
                    'received_amount'  => '已收金额',
                    'paid_amount'      => '已付金额',
                    'remaining_amount' => '剩余金额',
                    'total'            => '总额',
                    'total_amount'     => '总金额',
                    'price'            => '单价',
                    'cost'             => '成本',
                    'balance'          => '余额',
                ],
            ],
            'projects' => [
                'allowed_roles' => 'admin,finance',
                'fields' => [
                    'budget'           => '项目预算',
                    'contract_amount'  => '合同金额',
                    'actual_cost'      => '实际成本',
                    'revenue'          => '项目收入',
                ],
            ],
            'sales' => [
                'allowed_roles' => 'admin,finance',
                'fields' => [
                    'amount'    => '合同金额',
                    'commission' => '佣金',
                ],
            ],
            'employee' => [
                'allowed_roles' => 'admin',
                'fields' => [
                    'salary'       => '薪资',
                    'bank_account' => '银行账号',
                    'id_card'      => '身份证号',
                ],
            ],
        ];

        $now = now();
        $rows = [];
        foreach ($presets as $endpoint => $cfg) {
            foreach ($cfg['fields'] as $field => $desc) {
                $rows[] = [
                    'endpoint'      => $endpoint,
                    'field'         => $field,
                    'allowed_roles' => $cfg['allowed_roles'],
                    'description'   => $desc,
                    'enabled'       => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        // upsert by (endpoint, field)
        DB::table('field_masks')->upsert(
            $rows,
            ['endpoint', 'field'],
            ['allowed_roles', 'description', 'enabled', 'updated_at']
        );

        // 清缓存
        \App\Support\FieldMask::flushCache();
    }
}
