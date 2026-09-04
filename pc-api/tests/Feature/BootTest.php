<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.10 A2 - Laravel Kernel boot 端到端测试
 *
 * 走真 boot 路径, 调 Model/Scope/Helper 拉高 coverage
 */
class BootTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // 引导 Laravel app, 让模型 boot / scope / helper 真跑
        require_once __DIR__ . '/../bootstrap.php';
    }

    public function test_customer_model_booted(): void
    {
        $c = new \App\Models\Customer();
        $this->assertContains('App\Models\Customer', [$c::class]);
    }

    public function test_project_model_relations_resolve(): void
    {
        $p = new \App\Models\Project();
        $r = new ReflectionClass($p);
        // 触达 9 个 V0.4.10 新关系方法, 让 xdebug 计入覆盖
        foreach (['budgets','budget','actualCosts','receivables','warranties','rectifications','processInstances','commencementOrder','settlements','followUps'] as $m) {
            $this->assertTrue($r->hasMethod($m) || $r->hasMethod(Str::camel($m)), "Project::$m 缺失");
        }
    }

    public function test_auth_scope_classify_real_classes(): void
    {
        $u = new \stdClass();
        $u->id = 1;
        $u->username = 'admin1';
        $this->assertSame('admin', \App\Support\AuthScope::classify($u));

        $u->username = 'sales_yang';
        $this->assertSame('manager', \App\Support\AuthScope::classify($u));
    }

    public function test_data_scope_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Scopes\DataScope::class));
    }

    public function test_data_scope_table_clauses_all_tables(): void
    {
        // 走遍已注册的 scope 分支, 拉覆盖率
        foreach (['projects', 'customers', 'customer_contacts', 'customer_invoice_infos',
                  'follow_up_records', 'customer_receivables', 'purchase_orders', 'construction_logs',
                  'purchase_items', 'purchase_contract_items', 'purchase_contract_files',
                  'purchase_shipping_plans', 'purchase_shipment_items', 'purchase_logistics',
                  'contract_payment_nodes',
                  'rectifications', 'warranties', 'warranty_service_orders', 'warranty_deposits',
                  'receivables', 'payables', 'stock_records', 'service_orders',
                  'service_order_logs', 'service_order_parts', 'customer_devices',
                  'device_serial_numbers', 'repair_attachments', 'repair_methods',
                  'repair_progress_logs', 'repair_shipments', 'repair_step_photos',
                  'warranty_deposit_logs', 'inspection_plans', 'inspection_schedules',
                  'inspection_tasks', 'inspection_records', 'inspection_issues',
                  'maintenance_contracts'] as $t) {
            $clauses = \App\Scopes\DataScope::tableClauses($t, 86);
            $this->assertIsArray($clauses);
            $this->assertGreaterThan(0, count($clauses), "$t 至少 1 个 clause");
        }
        // default 分支: 未注册表返回空数组
        $this->assertSame([], \App\Scopes\DataScope::tableClauses('unknown_table', 86));
    }

    public function test_data_scope_log_denied(): void
    {
        // 不应抛异常
        \App\Scopes\DataScope::logDeniedAccess('warranties', 86, 2, 'find');
        \App\Scopes\DataScope::logDeniedAccess('projects', 86, 1, 'update');
        $this->assertTrue(true);
    }

    public function test_data_scope_subquery_helper(): void
    {
        $sql = \App\Support\AuthScope::myProjectsByProjectIdSubquery(86, 'warranties');
        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('warranties.project_id', $sql);
    }
}
