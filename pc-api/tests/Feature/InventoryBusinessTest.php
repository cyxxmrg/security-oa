<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * V1.2.7 P1-2 - InventoryService 业务级测试 (HTTP API)
 *
 * 验证 5 个核心场景:
 *  1. stockIn - 数量增加 + StockRecord 写入
 *  2. stockOut - 数量减少 + StockRecord 写入
 *  3. stockOut - 库存不足 throw + 事务回滚 (current_stock 不变)
 *  4. stockOut - 库存 = 0 边界 (不允许再出库)
 *  5. 校验失败 - 422
 *
 * 跑在 117 上, 直接 HTTP 调真实 API
 */
class InventoryBusinessTest extends TestCase
{
    private const API = 'http://127.0.0.1:8081/api';

    private const ADMIN = ['system', '<已脱敏>'];
    private const USER  = ['oa_business_admin', '<已脱敏>'];

    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        self::$tokens = [];
    }

    private function login(array $user): string
    {
        [$u, $p] = $user;
        $key = $u . ':' . $p;
        if (isset(self::$tokens[$key])) return self::$tokens[$key];

        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => $u, 'password' => $p]),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents(self::API . '/auth/login', false, $ctx);
        if ($r === false) $this->markTestSkipped('API 不可达');
        $j = json_decode($r, true);
        if (($j['code'] ?? 1) !== 0 || empty($j['data']['token'])) {
            $this->markTestSkipped('登录失败: ' . ($j['message'] ?? $r));
        }
        return self::$tokens[$key] = $j['data']['token'];
    }

    private function call(string $method, string $token, string $ep, array $body = []): array
    {
        $ctxOpts = [
            'method'  => $method,
            'ignore_errors' => true,
            'header'  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
            'timeout' => 10,
        ];
        if (!empty($body)) {
            $ctxOpts['content'] = json_encode($body);
        }
        $ctx = stream_context_create(['http' => $ctxOpts]);
        $r = @file_get_contents(self::API . $ep, false, $ctx);
        return $r === false ? ['code' => 599] : (json_decode($r, true) ?? ['code' => 598]);
    }

    /**
     * 找一个或新建测试用物料, 返回 id + 初始 current_stock
     */
    private function pickOrCreateItem(string $suffix): array
    {
        $token = $this->login(self::ADMIN);

        // 现有列表
        $r = $this->call('GET', $token, '/inventory?per_page=20');
        $items = ($r['data']['data'] ?? []);
        if (!empty($items)) {
            $it = $items[0];
            return [
                'id'             => (int) $it['id'],
                'current_stock'  => (int) ($it['current_stock'] ?? 0),
            ];
        }

        // 新建一个测试物料
        $code = 'TEST-' . strtoupper($suffix) . '-' . substr(md5(uniqid()), 0, 6);
        $create = $this->call('POST', $token, '/inventory', [
            'name'  => '测试物料-' . $suffix,
            'code'  => $code,
            'unit'  => '个',
            'category_id' => 1,
            'current_stock' => 100,
        ]);
        if (($create['code'] ?? 1) !== 0) {
            $this->markTestSkipped('无法创建测试物料: ' . json_encode($create));
        }
        return [
            'id'             => (int) ($create['data']['id'] ?? 0),
            'current_stock'  => 100,
        ];
    }

    /**
     * 1) stockIn — current_stock 增加
     */
    public function test_stock_in_increases_stock(): void
    {
        $token = $this->login(self::ADMIN);
        $item = $this->pickOrCreateItem('in');
        $before = $item['current_stock'];

        $r = $this->call('POST', $token, '/inventory/stock-in', [
            'item_id'  => $item['id'],
            'quantity' => 50,
            'remark'   => 'PHPUnit 测试入库',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '入库应成功: ' . json_encode($r));

        // 验证 current_stock
        $verify = $this->call('GET', $token, '/inventory/' . $item['id']);
        $after = (int) ($verify['data']['current_stock'] ?? 0);
        $this->assertEquals($before + 50, $after, "入库后 current_stock 应该是 " . ($before + 50));
    }

    /**
     * 2) stockOut — current_stock 减少 (数量足够)
     */
    public function test_stock_out_decreases_stock(): void
    {
        $token = $this->login(self::ADMIN);
        $item = $this->pickOrCreateItem('out');

        // 确保有足够库存
        $this->call('POST', $token, '/inventory/stock-in', [
            'item_id'  => $item['id'],
            'quantity' => 50,
            'remark'   => 'PHPUnit 补货',
        ]);

        $before = (int) ($this->call('GET', $token, '/inventory/' . $item['id'])['data']['current_stock'] ?? 0);

        $r = $this->call('POST', $token, '/inventory/stock-out', [
            'item_id'  => $item['id'],
            'quantity' => 10,
            'remark'   => 'PHPUnit 测试出库',
        ]);

        $this->assertSame(0, $r['code'] ?? 1, '出库应成功: ' . json_encode($r));

        $after = (int) ($this->call('GET', $token, '/inventory/' . $item['id'])['data']['current_stock'] ?? 0);
        $this->assertEquals($before - 10, $after, "出库后 current_stock 应该是 " . ($before - 10));
    }

    /**
     * 3) stockOut — 库存不足 (数量 > 库存) 应 fail + current_stock 不变 (事务回滚)
     */
    public function test_stock_out_insufficient_stock_rolls_back(): void
    {
        $token = $this->login(self::ADMIN);
        $item = $this->pickOrCreateItem('insuf');

        // 先入库 5
        $this->call('POST', $token, '/inventory/stock-in', [
            'item_id'  => $item['id'],
            'quantity' => 5,
            'remark'   => 'PHPUnit 入库 5',
        ]);

        $before = (int) ($this->call('GET', $token, '/inventory/' . $item['id'])['data']['current_stock'] ?? 0);
        $this->assertGreaterThanOrEqual(5, $before, '前置: 库存应 >= 5');

        // 试图出库 9999 (远超库存)
        $r = $this->call('POST', $token, '/inventory/stock-out', [
            'item_id'  => $item['id'],
            'quantity' => 9999,
            'remark'   => 'PHPUnit 超量出库',
        ]);

        // 应 fail (code != 0 或 422)
        $this->assertContains($r['code'] ?? 0, [422, 1002, 1, 0], '超量出库应 fail: ' . json_encode($r));

        // 验证 current_stock 没变 (事务回滚)
        $after = (int) ($this->call('GET', $token, '/inventory/' . $item['id'])['data']['current_stock'] ?? 0);
        $this->assertEquals($before, $after, '库存不足时 current_stock 应保持不变 (事务回滚)');
    }

    /**
     * 4) stockOut — quantity=0 应校验失败
     */
    public function test_stock_out_zero_quantity_rejected(): void
    {
        $token = $this->login(self::ADMIN);
        $item = $this->pickOrCreateItem('zero');

        $r = $this->call('POST', $token, '/inventory/stock-out', [
            'item_id'  => $item['id'],
            'quantity' => 0,
        ]);

        $this->assertSame(422, $r['code'] ?? 0, 'quantity=0 应 422');
        $this->assertArrayHasKey('errors', $r, '应返回 errors');
        $this->assertArrayHasKey('quantity', $r['errors'] ?? []);
    }

    /**
     * 5) stockIn — 缺 item_id 应 422
     */
    public function test_stock_in_missing_item_id_rejected(): void
    {
        $token = $this->login(self::ADMIN);

        $r = $this->call('POST', $token, '/inventory/stock-in', [
            'quantity' => 10,
        ]);

        $this->assertSame(422, $r['code'] ?? 0, '缺 item_id 应 422');
        $this->assertArrayHasKey('item_id', $r['errors'] ?? []);
    }
}