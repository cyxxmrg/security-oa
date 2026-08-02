<?php

namespace Tests\Unit;

use App\Models\PurchasePlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseContract;
use App\Models\PurchasePaymentRequest;
use App\Models\PurchasePayment;
use App\Models\PurchaseShipment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseFlowService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

/**
 * V0.6.3 — PurchaseFlowService 单元测试
 *
 * 覆盖 10 个核心方法:
 *   1. createPlan
 *   2. approvePlan
 *   3. createOrder
 *   4. signContract
 *   5. createPaymentRequest
 *   6. approvePaymentRequest
 *   7. executePayment
 *   8. createShipment
 *   9. autoInbound
 *  10. confirmInbound
 *
 * 不连数据库 — 仅反射 + 签名 + 状态常量验证
 *   (完整功能由 Feature 测试覆盖)
 */
class PurchaseFlowServiceTest extends TestCase
{
    private string $svc = PurchaseFlowService::class;

    /**
     * 验证类存在 + 是 service 类
     */
    public function test_service_class_exists(): void
    {
        $this->assertTrue(class_exists($this->svc));
        $r = new \ReflectionClass($this->svc);
        $this->assertTrue($r->isInstantiable());
    }

    /**
     * 验证常量定义完整
     */
    public function test_status_constants_defined(): void
    {
        $r = new \ReflectionClass($this->svc);
        $constants = $r->getConstants();

        $required = [
            // entity
            'ENTITY_REQUIREMENT','ENTITY_PLAN','ENTITY_ORDER','ENTITY_CONTRACT','ENTITY_PAYMENT_REQ','ENTITY_PAYMENT','ENTITY_SHIPMENT',
            // plan
            'STATUS_PLAN_DRAFT','STATUS_PLAN_SUBMITTED','STATUS_PLAN_APPROVED','STATUS_PLAN_FULFILLED','STATUS_PLAN_REJECTED',
            // order
            'STATUS_ORDER_DRAFT','STATUS_ORDER_PENDING','STATUS_ORDER_APPROVED','STATUS_ORDER_FULFILLED','STATUS_ORDER_REJECTED','STATUS_ORDER_CANCELLED',
            // contract
            'STATUS_CONTRACT_DRAFT','STATUS_CONTRACT_SIGNING','STATUS_CONTRACT_SIGNED','STATUS_CONTRACT_EFFECTIVE','STATUS_CONTRACT_CANCELLED',
            // payment request
            'STATUS_PAYREQ_PENDING','STATUS_PAYREQ_APPROVED','STATUS_PAYREQ_PAID','STATUS_PAYREQ_REJECTED',
            // payment
            'STATUS_PAY_PROCESSING','STATUS_PAY_COMPLETED','STATUS_PAY_FAILED',
            // shipment
            'STATUS_SHIP_PENDING','STATUS_SHIP_SHIPPED','STATUS_SHIP_IN_TRANSIT','STATUS_SHIP_ARRIVED','STATUS_SHIP_RECEIVED','STATUS_SHIP_INSPECTED','STATUS_SHIP_INBOUNDED',
        ];

        foreach ($required as $c) {
            $this->assertArrayHasKey($c, $constants, "常量 $c 未定义");
        }
    }

    /**
     * 验证 10 个核心方法签名
     *
     * 注意: 服务层方法名:
     *   createOrder -> planToOrder (从 plan 派生)
     *   autoInbound -> autoCreateInbound
     */
    public function test_method_signatures(): void
    {
        $r = new \ReflectionClass($this->svc);

        $expectations = [
            'createPlan' => ['array','NULL','array'],
            'approvePlan' => ['int','NULL','string'],
            'planToOrder' => ['int','array','string','NULL'],
            'signContract' => ['int','NULL'],
            'createPaymentRequest' => ['int','array','NULL'],
            'approvePaymentRequest' => ['int','NULL','string'],
            'executePayment' => ['int','array','NULL'],
            'createShipment' => ['int','array','NULL'],
            'autoCreateInbound' => ['int','NULL'],
            'confirmInbound' => ['int','NULL'],
        ];

        foreach ($expectations as $method => $paramTypes) {
            $this->assertTrue($r->hasMethod($method), "方法 $method 缺失");
            $m = $r->getMethod($method);
            $this->assertTrue($m->isPublic(), "$method 应是 public");
            $params = $m->getParameters();
            $this->assertGreaterThanOrEqual(count($paramTypes), count($params), "$method 参数数量不足");

            foreach ($paramTypes as $i => $type) {
                $this->assertTrue($params[$i]->hasType(), "$method 第 ".($i+1)." 个参数缺类型");
                $got = (string) $params[$i]->getType();
                if ($type === 'array') {
                    $this->assertStringContainsString('array', strtolower($got), "$method 第 ".($i+1)." 个参数应为 array");
                } elseif ($type === 'int') {
                    $this->assertStringContainsString('int', strtolower($got), "$method 第 ".($i+1)." 个参数应为 int");
                } elseif ($type === 'NULL') {
                    $this->assertTrue($params[$i]->allowsNull(), "$method 第 ".($i+1)." 个参数应允许 null");
                }
            }
        }
    }

    /**
     * 验证返回类型正确
     */
    public function test_return_types(): void
    {
        $r = new \ReflectionClass($this->svc);
        $expectations = [
            'createPlan' => PurchasePlan::class,
            'approvePlan' => PurchasePlan::class,
            'planToOrder' => PurchaseOrder::class,
            'signContract' => PurchaseContract::class,
            'createPaymentRequest' => PurchasePaymentRequest::class,
            'approvePaymentRequest' => PurchasePaymentRequest::class,
            'executePayment' => PurchasePayment::class,
            'createShipment' => PurchaseShipment::class,
            'confirmInbound' => PurchaseShipment::class,
        ];
        foreach ($expectations as $method => $model) {
            $m = $r->getMethod($method);
            $rt = $m->getReturnType();
            $this->assertNotNull($rt, "$method 应有返回类型声明");
            $this->assertEquals($model, $rt->getName(), "$method 返回类型应是 $model");
        }
    }

    /**
     * 验证常量状态流转合理 (确保 status 推进方向)
     */
    public function test_status_progression_logic(): void
    {
        $c = (new \ReflectionClass($this->svc))->getConstants();

        // Plan: draft → submitted → approved → fulfilled
        $this->assertEquals('draft', $c['STATUS_PLAN_DRAFT']);
        $this->assertEquals('submitted', $c['STATUS_PLAN_SUBMITTED']);
        $this->assertEquals('approved', $c['STATUS_PLAN_APPROVED']);

        // Contract: draft → signing → signed → effective
        $this->assertEquals('draft', $c['STATUS_CONTRACT_DRAFT']);
        $this->assertEquals('signing', $c['STATUS_CONTRACT_SIGNING']);
        $this->assertEquals('signed', $c['STATUS_CONTRACT_SIGNED']);
        $this->assertEquals('effective', $c['STATUS_CONTRACT_EFFECTIVE']);

        // Shipment: pending → shipped → in_transit → arrived → received → inspected → inbounded
        $this->assertEquals('pending', $c['STATUS_SHIP_PENDING']);
        $this->assertEquals('arrived', $c['STATUS_SHIP_ARRIVED']);
        $this->assertEquals('inbounded', $c['STATUS_SHIP_INBOUNDED']);

        // Payment: pending → approved → paid
        $this->assertEquals('pending', $c['STATUS_PAYREQ_PENDING']);
        $this->assertEquals('paid', $c['STATUS_PAYREQ_PAID']);
    }

    /**
     * 验证 service 依赖的 model 类都存在
     */
    public function test_dependent_models_exist(): void
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
        }
    }

    /**
     * 验证 service 用到必要的 namespace
     */
    public function test_service_namespace_correct(): void
    {
        $r = new \ReflectionClass($this->svc);
        $this->assertEquals('App\\Services', $r->getNamespaceName());
    }
}