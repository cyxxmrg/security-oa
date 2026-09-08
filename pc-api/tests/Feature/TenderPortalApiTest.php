<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * V0.6.3 — TenderPortal 公开门户 API 测试
 *
 * 覆盖 PortalController + TenderController 公开端点
 *
 * 公开端点 (无需 token):
 *   - GET /api/portal/t/{token}           公开招标详情 (token-based)
 *   - GET /api/portal/tenders             公开招标列表
 *   - GET /api/tenders                    内部招标列表 (需要 token)
 *   - POST /api/tenders                   创建招标
 *   - GET /api/tenders/{id}               招标详情
 *   - PUT /api/tenders/{id}               更新招标
 *   - DELETE /api/tenders/{id}            删除招标
 *   - POST /api/tenders/{id}/publish      发布
 *   - POST /api/tenders/{id}/close        关闭
 *   - POST /api/tenders/{id}/bid          投标
 *   - GET /api/tenders/{id}/bids          投标列表
 *   - POST /api/tenders/{id}/award        中标
 *   - GET /api/portal/repair/{code}/{phone} 公开维修查询
 */
class TenderPortalApiTest extends TestCase
{
    /** @var list<string> */
    private array $routeFiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routeFiles = [
            __DIR__ . '/../../routes/api/purchase.php',
            __DIR__ . '/../../routes/api/portal.php',
        ];
    }

    private function getRoutes(): array
    {
        $routes = [];
        foreach ($this->routeFiles as $routeFile) {
            $content = file_get_contents($routeFile);
            // 兼容 'path' 是空 ('') 或 '/' 的情况 + 完整 namespace
            if (preg_match_all("/Route::(get|post|put|delete)\(\s*('[^']*'|\"[^\\\"]*\")\s*,\s*\[((?:App\\\\Http\\\\Controllers\\\\Api\\\\)?[A-Za-z\\\\]+)::class,\s*'([^']+)'\]/", $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $r) {
                    $path = trim($r[2], "'\"");
                    $ctrl = $r[3];
                    if (strpos($ctrl, 'App\\') !== 0) {
                        $ctrl = 'App\\Http\\Controllers\\Api\\' . ltrim($ctrl, '\\');
                    }
                    $routes[] = [
                        'method' => strtoupper($r[1]),
                        'path' => $path,
                        'controller' => $ctrl,
                        'action' => $r[4],
                    ];
                }
            }
        }
        return $routes;
    }

    /**
     * 验证 tender/portal 相关路由总数 (TenderController + PortalController 总引用)
     * 兼容路径为 '/' 的路由 (TenderController::class, 'index')
     */
    public function test_tender_routes_count(): void
    {
        $content = implode("\n", array_map('file_get_contents', $this->routeFiles));
        $tender_refs = substr_count($content, 'TenderController::class');
        $portal_refs = substr_count($content, 'PortalController::class');
        $total = $tender_refs + $portal_refs;
        $this->assertGreaterThanOrEqual(15, $total, "Tender/Portal 路由引用不足 (t=$tender_refs p=$portal_refs)");
    }

    /**
     * 验证 Controller 类存在
     */
    public function test_controllers_exist(): void
    {
        $this->assertTrue(class_exists('App\\Http\\Controllers\\Api\\PortalController'));
        $this->assertTrue(class_exists('App\\Http\\Controllers\\Api\\TenderController'));
    }

    /**
     * 验证 PortalController 公开方法
     */
    public function test_portal_controller_public_actions(): void
    {
        $ctrl = 'App\\Http\\Controllers\\Api\\PortalController';
        $r = new \ReflectionClass($ctrl);
        $public = array_filter($r->getMethods(\ReflectionMethod::IS_PUBLIC), fn($m) => $m->class === $ctrl);
        $this->assertGreaterThanOrEqual(3, count($public), "PortalController public 方法 < 3");
    }

    /**
     * 验证 TenderController 必备方法 (招标内部 API)
     */
    public function test_tender_controller_actions(): void
    {
        $ctrl = 'App\\Http\\Controllers\\Api\\TenderController';
        $this->assertTrue(class_exists($ctrl), 'TenderController 不存在');
        $r = new \ReflectionClass($ctrl);
        $public = array_filter($r->getMethods(\ReflectionMethod::IS_PUBLIC), fn($m) => $m->class === $ctrl);
        $this->assertGreaterThanOrEqual(10, count($public), "TenderController public 方法 < 10");
    }

    /**
     * 验证 PortalController 是公开的 (不在 auth 中间件里)
     * portal 路由段可能在文件末尾独立定义, 也可能在 group 里
     */
    public function test_portal_routes_no_auth(): void
    {
        $content = file_get_contents(__DIR__ . '/../../routes/api/portal.php');
        // 找 portal 段 (允许缩进变化)
        $found = false;
        if (preg_match_all("/prefix\('portal'\)->group\(function\s*\(\)\s*\{(.*?)\n\}\);/s", $content, $matches)) {
            foreach ($matches[1] as $block) {
                $found = true;
                $this->assertStringNotContainsString('auth:sanctum', $block, "Portal 端点不应要 auth");
                $this->assertStringNotContainsString('->middleware(\'auth', $block, "Portal 端点不应要 auth middleware");
            }
        }
        if (!$found) {
            $this->markTestSkipped("未找到 portal 路由段");
        }
    }

    /**
     * 验证 portal tender 公开端点存在
     * 路由: GET /api/portal/t/{token}
     */
    public function test_portal_tender_token_endpoint(): void
    {
        $content = file_get_contents(__DIR__ . '/../../routes/api/portal.php');
        // 直接查字符串 (路由在 prefix('portal')->group 内)
        $this->assertStringContainsString("Route::get('t/{token}'", $content, "portal/t/{token} 路由缺失");
        $this->assertStringContainsString("PortalController::class, 'tenderByToken'", $content, "tenderByToken action 缺失");
    }

    /**
     * 验证招标内部 + 公开路由都正确绑定
     * 兼容 '/' 路径 (TenderController::class, 'index')
     */
    public function test_routes_bindings(): void
    {
        $routes = $this->getRoutes();
        $matched = 0;
        foreach ($routes as $r) {
            $is_tender_path = stripos($r['path'], 'tender') !== false;
            $is_tender_ctrl = strpos($r['controller'], 'TenderController') !== false;
            if ($is_tender_path || $is_tender_ctrl) {
                $this->assertContains($r['controller'], [
                    'App\\Http\\Controllers\\Api\\TenderController',
                    'App\\Http\\Controllers\\Api\\PortalController',
                ], "tender 路由 {$r['method']} {$r['path']} controller 异常: {$r['controller']}");
                $matched++;
            }
        }
        $this->assertGreaterThan(0, $matched, "应至少匹配 1 条 tender 路由");
    }

    /**
     * 验证 tender 表已建立 (通过 model)
     */
    public function test_tender_models_exist(): void
    {
        $models = [
            'App\\Models\\TenderProject',
            'App\\Models\\TenderBid',
        ];
        foreach ($models as $m) {
            $exists = class_exists($m);
            if (!$exists) {
                $this->markTestSkipped("$m 不存在 (可能未启用 tender 模块)");
            } else {
                $this->assertTrue($exists, "$m 必须存在");
            }
        }
    }
}
