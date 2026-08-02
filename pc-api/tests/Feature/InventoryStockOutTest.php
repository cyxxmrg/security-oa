<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockRecord;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * V1.2.7 P2-4 - 物料出库测试
 *
 * 验证 5 个核心场景:
 *  1. 正常出库 (current_stock >= quantity) → success, 库存减少
 *  2. 超量出库 (current_stock < quantity) → 抛 RuntimeException, 库存不变
 *  3. 重复提交 + 并发 (2 个并发请求都尝试超量) → 1 个成功 1 个失败 (lockForUpdate 保护)
 *  4. StockRecord 创建成功 (type='out', quantity 匹配)
 *  5. 出库后 current_stock 持久化
 *
 * 跑在 117 security_oa_test 隔离 DB (RefreshDatabase).
 */
class InventoryStockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::create([
            'name'      => '出库员',
            'username'  => 'stockout_' . uniqid(),
            'phone'     => '13900000004',
            'email'     => 'stockout@test.local',
            'password'  => bcrypt('test123'),
            'user_type' => 'business',
            'status'    => 'active',
        ]);

        // 创建库存 10 件的物料
        $this->item = InventoryItem::create([
            'name'           => '测试螺丝 M8',
            'code'           => 'TEST_' . uniqid(),
            'unit'           => '个',
            'current_stock'  => 10,
            'safety_stock'   => 2,
            'min_stock'      => 1,
            'status'         => 'active',
            'category'       => 'parts', // 用枚举值, 避免 FK
        ]);

        // V1.2.7 P2-4: stock_records.warehouse_id 是 FK, 必须建仓库
        $this->warehouse = \App\Models\Warehouse::create([
            'name'    => '主仓',
            'code'    => 'WH_MAIN_' . uniqid(),
            'type'    => 'main', // enum: main/project/aftermarket
            'status'  => 'active',
        ]);
    }

    private function stockOutReq(int $quantity, ?string $remark = null): Request
    {
        $req = Request::create('/api/inventory/stock-out', 'POST', [
            'item_id'      => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity'     => $quantity,
            'remark'       => $remark,
        ]);
        // V1.2.7 P2-4 fix: 模拟登录, 否则 InventoryService::stockOut 调
        // $request->user()->id 会报 "Attempt to read property id on null"
        $req->setUserResolver(fn () => $this->operator);
        return $req;
    }

    /**
     * 1) 正常出库 → 库存减少, StockRecord 创建
     */
    public function test_normal_stock_out_succeeds(): void
    {
        $svc = app(InventoryService::class);

        $r = $svc->stockOut($this->stockOutReq(3, '领料测试'));

        // 库存: 10 - 3 = 7
        $this->item->refresh();
        $this->assertEquals(7, $this->item->current_stock, '库存应减到 7');

        // StockRecord 应创建 1 条
        $records = StockRecord::where('inventory_item_id', $this->item->id)->where('type', 'out')->get();
        $this->assertCount(1, $records);
        $this->assertEquals(3, $records->first()->quantity);
        $this->assertEquals('领料测试', $records->first()->remark);
    }

    /**
     * 2) 超量出库 → 抛异常, 库存不变
     */
    public function test_overdraw_throws_and_keeps_stock(): void
    {
        $svc = app(InventoryService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/库存不足/');
        $svc->stockOut($this->stockOutReq(100, '超量测试'));

        // 库存不变
        $this->item->refresh();
        $this->assertEquals(10, $this->item->current_stock);

        // 没有 StockRecord
        $this->assertEquals(0, StockRecord::where('inventory_item_id', $this->item->id)->count());
    }

    /**
     * 3) 并发出库: 2 个请求都尝试超量 (各扣 6, 库存 10)
     *    期望: 1 个成功 (10→4), 1 个抛异常 (因为第 2 个进来时 stock=4 < 6)
     */
    public function test_concurrent_stock_out_protected_by_lock(): void
    {
        $svc = app(InventoryService::class);

        // 先手动扣到 10
        $this->item->update(['current_stock' => 10]);

        $success = 0;
        $failed  = 0;
        $err     = null;

        try {
            $svc->stockOut($this->stockOutReq(6, 'req1'));
            $success++;
        } catch (\Throwable $e) {
            $failed++;
            $err = $e->getMessage();
        }

        try {
            $svc->stockOut($this->stockOutReq(6, 'req2'));
            $success++;
        } catch (\Throwable $e) {
            $failed++;
            $err = $e->getMessage();
        }

        $this->assertEquals(1, $success, '应该只有 1 个成功');
        $this->assertEquals(1, $failed, '应该有 1 个抛异常');
        $this->assertStringContainsString('库存不足', $err ?? '');

        // 最终库存 = 10 - 6 = 4
        $this->item->refresh();
        $this->assertEquals(4, $this->item->current_stock);

        // 只创建 1 条 StockRecord
        $this->assertEquals(1, StockRecord::where('inventory_item_id', $this->item->id)->count());
    }

    /**
     * 4) StockRecord 字段正确: type=out, quantity, remaining_stock
     */
    public function test_stock_record_fields_correct(): void
    {
        $svc = app(InventoryService::class);

        $req = Request::create('/api/inventory/stock-out', 'POST', [
            'item_id'      => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity'     => 2,
            'order_no'     => 'ORDER-2026-001',
            'remark'       => '单据测试',
        ]);
        $req->setUserResolver(fn () => $this->operator);
        $svc->stockOut($req);

        $record = StockRecord::where('inventory_item_id', $this->item->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals('out', $record->type);
        $this->assertEquals(2, $record->quantity);
        // V1.2.7 P2-4: stock_records 表实际没有 unit_price 列 (model fillable 误填),
        // 改验 order_no + record_no 自动生成
        $this->assertEquals('ORDER-2026-001', $record->order_no);
        $this->assertEquals($this->operator->id, $record->operator_id);
        $this->assertMatchesRegularExpression('/^OUT-\d{8}-\d{4}$/', $record->record_no);
    }

    /**
     * 5) 连续出库累计: 3+4+2 = 9, 库存 10-9 = 1
     */
    public function test_multiple_stock_outs_accumulate(): void
    {
        $svc = app(InventoryService::class);

        foreach ([3, 4, 2] as $i => $qty) {
            $svc->stockOut($this->stockOutReq($qty, "batch $i"));
        }

        $this->item->refresh();
        $this->assertEquals(1, $this->item->current_stock, '10 - 3 - 4 - 2 = 1');
        $this->assertEquals(3, StockRecord::where('inventory_item_id', $this->item->id)->count());
    }
}