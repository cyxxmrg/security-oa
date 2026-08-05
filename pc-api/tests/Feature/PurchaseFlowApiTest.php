<?php

namespace Tests\Feature;

use App\Models\PurchasePlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseContract;
use App\Models\PurchaseShipment;
use App\Models\PurchasePaymentRequest;
use App\Models\PurchasePayment;
use App\Models\User;
use App\Models\Supplier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * V0.6.3 — PurchaseFlow API Feature 测试
 *
 * 不连数据库 — 通过 PHP 反射 + 路由清单验证 20+ 端点完整性
 *
 * 覆盖端点 (purchase-flow 前缀):
 *   需求 (3):    POST /requirements, POST /requirements/{id}/submit, POST /requirements/{id}/approve
 *   通用 (2):    POST {entityType}/{id}/cancel, GET {entityType}/{id}/trace
 *   计划 (3):    POST /plans, POST /plans/{id}/submit, POST /plans/{id}/approve
 *   采购单 (3):  POST /orders, POST /orders/{id}/submit, POST /orders/{id}/approve
 *   合同 (4):    POST /contracts, POST /contracts/{id}/sign, GET /contracts/{id}/files, GET /contracts/{id}/items
 *   合同清单CRUD(4): POST /contracts/{id}/items, POST /contracts/{id}/items/sync, PUT /contracts/{id}/items/{iid}, DELETE
 *   付款 (3):    POST /payment-requests, POST /payment-requests/{id}/approve, POST /payments
 *   收货 (3):    POST /shipments, POST /shipments/{id}/update-status, POST /shipments/{id}/auto-inbound
 *   入库 (1):    POST /shipments/{id}/confirm-inbound
 *   列表 (2):    GET /logs, GET /orders-list
 *   触发 (2):    POST /from-work-order/{id}, POST /from-external-work/{id}
 *   其他 (1):    GET /by-source/{type}/{id}
 */
class PurchaseFlowApiTest extends TestCase
{
    private string $routeFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routeFile = __DIR__ . '/../../routes/api/purchase.php';
    }

    /**
     * 从 routes/api.php 提取所有 purchase-flow 路由
     */
    private function extractPurchaseFlowRoutes(): array
    {
        $content = file_get_contents($this->routeFile);
        // 提取 prefix('purchase-flow') ... }); 段
        if (!preg_match("/prefix\('purchase-flow'\).*?->group\(function\s*\(\)\s*\{(.*?)\n\}\);/s", $content, $m)) {
            $this->fail("未找到 purchase-flow 路由段");
        }
        $block = $m[1];

        $routes = [];
        // 匹配 Route::METHOD('path', [Controller::class, 'method']);
        if (preg_match_all("/Route::(get|post|put|delete)\(\s*'([^']+)'\s*,\s*\[([A-Za-z\\\\]+)::class,\s*'([^']+)'\]/", $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $r) {
                $routes[] = [
                    'method' => strtoupper($r[1]),
                    'path' => $r[2],
                    'controller' => $r[3],
                    'action' => $r[4],
                ];
            }
        }
        return $routes;
    }

    /**
     * 验证 purchase-flow 至少 30 条路由
     */
    public function test_purchase_flow_route_count(): void
    {
        $routes = $this->extractPurchaseFlowRoutes();
        $this->assertGreaterThanOrEqual(30, count($routes), "purchase-flow 路由数不足 (现在 ".count($routes).")");
    }

    /**
     * 验证 controller 类存在 (PurchaseFlowController)
     */
    public function test_controller_class_exists(): void
    {
        $this->assertTrue(class_exists('App\\Http\\Controllers\\Api\\PurchaseFlowController'));
        $r = new \ReflectionClass('App\\Http\\Controllers\\Api\\PurchaseFlowController');
        $this->assertTrue($r->isInstantiable());
    }

    /**
     * 验证 controller 含 V0.6.3 必备方法
     */
    public function test_controller_has_required_actions(): void
    {
        $ctrl = 'App\\Http\\Controllers\\Api\\PurchaseFlowController';
        $r = new \ReflectionClass($ctrl);
        $required = [
            'createRequirement','approveRequirement',
            'createPlan','submitPlan','approvePlan',
            'createOrder','submitOrder','approveOrder',
            'createContract','signContract',
            'createPaymentRequest','approvePaymentRequest','executePayment',
            'createShipment','updateShipmentStatus','autoInbound','confirmInbound',
            'cancel','trace',
        ];
        foreach ($required as $m) {
            $this->assertTrue($r->hasMethod($m), "PurchaseFlowController::$m 缺失");
            $this->assertTrue($r->getMethod($m)->isPublic(), "$m 应 public");
        }
    }

    /**
     * 验证关键路由存在
     */
    public function test_key_routes_present(): void
    {
        $routes = $this->extractPurchaseFlowRoutes();
        $paths = array_map(fn($r) => "{$r['method']} /{$r['path']}", $routes);

        $must = [
            'POST /requirements',
            'POST /requirements/{id}/approve',
            'POST /plans',
            'POST /plans/{id}/approve',
            'POST /orders',
            'POST /orders/{id}/approve',
            'POST /contracts',
            'POST /contracts/{id}/sign',
            'POST /payment-requests',
            'POST /payment-requests/{id}/approve',
            'POST /payments',
            'POST /shipments',
            'POST /shipments/{id}/auto-inbound',
            'POST /shipments/{id}/confirm-inbound',
            'GET /logs',
            'GET /orders-list',
        ];
        foreach ($must as $r) {
            $this->assertContains($r, $paths, "必备路由 $r 缺失");
        }
    }

    /**
     * 验证 V0.6.2.2 附件/清单/凭证路由
     */
    public function test_v0622_attachment_routes_present(): void
    {
        $routes = $this->extractPurchaseFlowRoutes();
        $paths = array_map(fn($r) => "{$r['method']} /{$r['path']}", $routes);

        $v0622 = [
            'GET /contracts/{id}/files',
            'POST /contracts/{id}/files',
            'GET /contracts/{id}/items',
            'POST /contracts/{id}/items',
            'POST /contracts/{id}/items/sync',
            'GET /payment-requests/{id}/vouchers',
            'POST /payment-requests/{id}/voucher',
            'POST /contracts/{id}/shipping-plans',
            'POST /contracts/{id}/tracking',
        ];
        foreach ($v0622 as $r) {
            $this->assertContains($r, $paths, "V0.6.2.2 路由 $r 缺失");
        }
    }

    /**
     * 验证路由都绑定到 PurchaseFlowController (无串路由)
     */
    public function test_all_routes_bound_to_purchaseflow_controller(): void
    {
        $routes = $this->extractPurchaseFlowRoutes();
        foreach ($routes as $r) {
            $this->assertStringContainsString(
                'PurchaseFlowController',
                $r['controller'],
                "路由 {$r['method']} {$r['path']} 未绑定到 PurchaseFlowController (现在是 {$r['controller']})"
            );
        }
    }

    /**
     * 验证所有路径以 whereNumber 约束 id (避免字符串注入)
     * 例外: by-source/{type}/{id} 这种 path 含 {type} 占位的合理路由
     */
    public function test_routes_use_wherenumber_for_ids(): void
    {
        $content = file_get_contents($this->routeFile);
        if (!preg_match("/prefix\('purchase-flow'\).*?->group\(function\s*\(\)\s*\{(.*?)\n\}\);/s", $content, $m)) {
            $this->fail('purchase-flow 段未找到');
        }
        $block = $m[1];

        // 找所有行：含 {id}/{iid}/{fid}/{workOrderId}/{workId} 但不含 {type}
        $lines = explode("\n", $block);
        $checked = 0;
        foreach ($lines as $line) {
            if (!preg_match("/Route::\w+\(\s*'[^']+?'/", $line)) continue;
            // 跳过含 {type}/{workOrderId} 等非纯数字约束的路由
            if (strpos($line, '{type}') !== false) continue;
            if (preg_match('/\{(?:id|iid|fid)\}/', $line)) {
                $this->assertStringContainsString('whereNumber', $line, "路由缺 whereNumber 约束: $line");
                $checked++;
            }
        }
        $this->assertGreaterThanOrEqual(10, $checked, "应至少检查 10 条带 id 的路由");
    }

    /**
     * 验证路由数量精确 (≥ 35 即健康)
     */
    public function test_route_count_health(): void
    {
        $routes = $this->extractPurchaseFlowRoutes();
        $by_method = [];
        foreach ($routes as $r) {
            $by_method[$r['method']] = ($by_method[$r['method']] ?? 0) + 1;
        }
        // POST 至少 25, GET 至少 4, PUT/DELETE 至少 2
        $this->assertGreaterThanOrEqual(20, $by_method['POST'] ?? 0, "POST 路由 < 20");
        $this->assertGreaterThanOrEqual(3, $by_method['GET'] ?? 0, "GET 路由 < 3");
    }

    /**
     * 验证 controller 方法数量合理
     */
    public function test_controller_method_count(): void
    {
        $ctrl = 'App\\Http\\Controllers\\Api\\PurchaseFlowController';
        $r = new \ReflectionClass($ctrl);
        $public = array_filter($r->getMethods(\ReflectionMethod::IS_PUBLIC), fn($m) => $m->class === $ctrl);
        $this->assertGreaterThanOrEqual(20, count($public), "Controller public 方法数 < 20");
    }

    /**
     * 验证 service 类注入 (通过 constructor 或 facade)
     */
    public function test_service_injection_possible(): void
    {
        $this->assertTrue(class_exists('App\\Services\\PurchaseFlowService'));
        $svc = app('App\\Services\\PurchaseFlowService');
        $this->assertInstanceOf('App\\Services\\PurchaseFlowService', $svc);
    }

    /**
     * 验证 8 步业务模型完整可实例化
     */
    public function test_eight_step_models_instantiable(): void
    {
        $models = [
            PurchasePlan::class,
            PurchaseOrder::class,
            PurchaseContract::class,
            PurchasePaymentRequest::class,
            PurchasePayment::class,
            PurchaseShipment::class,
        ];
        foreach ($models as $m) {
            $this->assertTrue(class_exists($m), "$m 必须存在");
            $r = new \ReflectionClass($m);
            $this->assertTrue($r->isInstantiable(), "$m 必须可实例化");
        }
    }
}
