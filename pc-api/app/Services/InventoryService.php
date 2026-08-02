<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryCategory;
use App\Models\StockRecord;
use App\Models\Tool;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 库存业务服务 — V1.2.7d 拆自 InventoryController
 *
 * 业务域：
 *   - InventoryItem   物料 CRUD + 库存
 *   - StockRecord     出入库流水
 *   - 批量操作        删除 / 更新 / 导入
 *   - 分类 / 仓库     树 + 字典
 */
class InventoryService
{
    // ============================================================
    // === 物料 InventoryItem ===
    // ============================================================

    public function paginateItems(Request $request)
    {
        $query = InventoryItem::with('warehouse');
        if ($request->filled('category'))     $query->where('category', $request->category);
        if ($request->filled('category_id')) {
            // 递归收集所有子类 ID, 支持树形分类筛选
            $allIds = $this->collectCategoryDescendantIds((int)$request->category_id);
            $query->whereIn('category_id', $allIds);
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%")
                  ->orWhere('specification', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->filled('low_stock'))    $query->whereColumn('current_stock', '<=', 'safety_stock');

        $list = $query->with(['warehouse:id,name,code', 'categoryRef:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        $list->getCollection()->transform(function ($it) {
            $it->is_low_stock = $it->current_stock <= $it->safety_stock;
            return $it;
        });
        return $list;
    }

    /**
     * 递归收集分类及其所有子分类的 ID
     */
    private function collectCategoryDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = InventoryCategory::where('parent_id', $categoryId)->pluck('id')->all();
        foreach ($children as $cid) {
            $ids = array_merge($ids, $this->collectCategoryDescendantIds($cid));
        }
        return $ids;
    }

    public function showItem(InventoryItem $item): InventoryItem
    {
        $item->load('warehouse', 'stockRecords.operator:id,name', 'serialNumbers', 'categoryRef');
        $item->is_low_stock = $item->current_stock <= $item->safety_stock;
        return $item;
    }

    public function createItem(Request $request): InventoryItem
    {
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'code'         => 'required|string|max:64|unique:inventory_items,code',
            'category'     => 'nullable|string|max:50',
            'category_id'  => 'nullable|integer',
            'specification'=> 'nullable|string|max:255',
            'unit'         => 'required|string|max:20',
            'safety_stock' => 'nullable|integer',
            'current_stock'=> 'nullable|integer',
            'cost_price'   => 'nullable|numeric',
            'sell_price'   => 'nullable|numeric',
            'warehouse_id' => 'nullable|integer',
            'location'     => 'nullable|string|max:100',
            'has_serial'   => 'nullable|boolean',
            'status'       => 'nullable|string',
        ]);
        $data['status'] = $data['status'] ?? 'active';
        // V1.2.10: category DB 列 NOT NULL, 默认为 'general'
        $data['category'] = $data['category'] ?? 'general';

        // 同步 category 字符串
        if (!empty($data['category_id']) && empty($data['category'])) {
            $cat = InventoryCategory::find($data['category_id']);
            if ($cat) $data['category'] = $cat->name;
        }

        return InventoryItem::create($data);
    }

    public function updateItem(Request $request, InventoryItem $item): InventoryItem
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:200',
            'category'     => 'nullable|string|max:50',
            'category_id'  => 'nullable|integer',
            'specification'=> 'nullable|string|max:255',
            'unit'         => 'sometimes|string|max:20',
            'safety_stock' => 'nullable|integer',
            'current_stock'=> 'nullable|integer',
            'cost_price'   => 'nullable|numeric',
            'sell_price'   => 'nullable|numeric',
            'warehouse_id' => 'nullable|integer',
            'location'     => 'nullable|string|max:100',
            'has_serial'   => 'nullable|boolean',
            'status'       => 'nullable|string',
        ]);
        if (!empty($data['category_id']) && empty($data['category'])) {
            $cat = InventoryCategory::find($data['category_id']);
            if ($cat) $data['category'] = $cat->name;
        }
        $item->update($data);
        return $item->fresh();
    }

    public function destroyItem(Request $request, InventoryItem $item): void
    {
        $item->delete();
    }

    public function batchDelete(Request $request): int
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        return InventoryItem::whereIn('id', $data['ids'])->delete();
    }

    public function batchUpdate(Request $request): int
    {
        $data = $request->validate([
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer',
            'updates' => 'required|array',
        ]);
        return InventoryItem::whereIn('id', $data['ids'])->update($data['updates']);
    }

    public function lowStock(Request $request)
    {
        return InventoryItem::with('warehouse:id,name,code')
            ->whereColumn('current_stock', '<=', 'safety_stock')
            ->orderBy('current_stock', 'asc')
            ->limit($request->limit ?? 50)
            ->get();
    }

    public function stats(Request $request): array
    {
        return [
            'total_items'   => InventoryItem::count(),
            'total_stock'   => (int) InventoryItem::sum('current_stock'),
            'total_value'   => (float) InventoryItem::selectRaw('SUM(current_stock * cost_price) as v')->value('v'),
            'low_stock_cnt' => InventoryItem::whereColumn('current_stock', '<=', 'safety_stock')->count(),
            'out_of_stock'  => InventoryItem::where('current_stock', '<=', 0)->count(),
            'category_cnt'  => InventoryCategory::count(),
            'warehouse_cnt' => Warehouse::count(),
        ];
    }

    public function treeWithCounts(Request $request)
    {
        $cats = InventoryCategory::with('children')->orderBy('sort_order')->get();
        $items = InventoryItem::whereNotNull('category_id');
        if ($request->filled('warehouse_id')) {
            $items->where('warehouse_id', $request->warehouse_id);
        }
        $counts = (clone $items)->selectRaw('category_id, count(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id');
        $lowCounts = (clone $items)->selectRaw('category_id, count(*) as cnt')
            ->whereColumn('current_stock', '<=', 'safety_stock')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id');

        return $cats->map(function ($c) use ($counts, $lowCounts) {
            $sum = $counts[$c->id] ?? 0;
            $lowSum = $lowCounts[$c->id] ?? 0;
            foreach ($c->children as $child) {
                $sum += $counts[$child->id] ?? 0;
                $lowSum += $lowCounts[$child->id] ?? 0;
            }
            return [
                'id'    => $c->id,
                'name'  => $c->name,
                'count' => $sum,
                'low_stock_count' => $lowSum,
                'children' => $c->children->map(fn($ch) => [
                    'id'    => $ch->id,
                    'name'  => $ch->name,
                    'count' => (int)($counts[$ch->id] ?? 0),
                    'low_stock_count' => (int)($lowCounts[$ch->id] ?? 0),
                ]),
            ];
        });
    }

    public function itemsByCategory(Request $request)
    {
        $query = InventoryItem::query();
        if ($request->filled('category_id')) {
            $rootId = (int) $request->category_id;
            // 复用同文件 line 64 已有的 collectCategoryDescendantIds() 方法
            $allIds = $this->collectCategoryDescendantIds($rootId);
            $query->whereIn('category_id', $allIds);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        return $query->select(['id', 'name', 'code', 'category_id', 'specification', 'unit', 'current_stock', 'min_stock', 'cost_price', 'warehouse_id']) // V1.2.10 fix: 移除不存在的 is_active 列
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    public function warnings(Request $request)
    {
        return [
            'low_stock' => $this->lowStock($request),
            'out_of_stock' => InventoryItem::where('current_stock', '<=', 0)->get(),
        ];
    }

    // ============================================================
    // === 出入库 StockRecord ===
    // ============================================================

    public function stockIn(Request $request): array
    {
        // V1.2.14p: 支持单条 (item_id) 和批量 (items[]) 两种形态
        // 批量模式: 一次提交的多物料共享同一个 record_no
        $rules = [
            'warehouse_id'  => 'nullable|integer|exists:warehouses,id',
            'payment_method'=> 'nullable|in:cash,credit',
            'account_id'    => 'nullable|integer|exists:finance_accounts,id',
            'party_type'    => 'nullable|in:supplier,customer',
            'party_id'      => 'nullable|integer',
            'settle_id'     => 'nullable|integer',
            'project_id'    => 'nullable|integer|exists:projects,id',
            'batch_no'      => 'nullable|string|max:100',
            'remark'        => 'nullable|string',
            'type'          => 'nullable|in:in,return',  // V1.2.14p: 退料入库用 'return'
        ];
        $hasItems = $request->has('items') && is_array($request->items);
        if ($hasItems) {
            $rules['items']                = 'required|array|min:1';
            $rules['items.*.item_id']      = 'required|integer|exists:inventory_items,id';
            $rules['items.*.quantity']     = 'required|integer|min:1';
            $rules['items.*.unit_cost']    = 'nullable|numeric|min:0';
            $rules['items.*.total_amount'] = 'nullable|numeric|min:0';
        } else {
            $rules['item_id']      = 'required|integer|exists:inventory_items,id';
            $rules['quantity']     = 'required|integer|min:1';
            $rules['unit_cost']    = 'nullable|numeric|min:0';
            $rules['total_amount'] = 'nullable|numeric|min:0';
        }
        $data = $request->validate($rules);

        $itemsPayload = $hasItems
            ? $data['items']
            : [[
                'item_id'      => $data['item_id'],
                'quantity'     => $data['quantity'],
                'unit_cost'    => $data['unit_cost']    ?? null,
                'total_amount' => $data['total_amount'] ?? null,
            ]];

        return DB::transaction(function () use ($itemsPayload, $data, $request) {
            // 一次提交的所有物料共用一个 record_no
            $recordNo = $this->nextRecordNo('IN');
            $records  = [];
            $lastItem = null;
            foreach ($itemsPayload as $it) {
                $item = InventoryItem::lockForUpdate()->findOrFail($it['item_id']);
                $item->increment('current_stock', $it['quantity']);
                // V1.2.14p: 入库时同步更新物料的仓库
                if ($item->warehouse_id === null && !empty($data['warehouse_id'])) {
                    $item->warehouse_id = (int) $data['warehouse_id'];
                    $item->save();
                }
                $records[] = StockRecord::create([
                    'record_no'         => $recordNo,
                    'inventory_item_id' => $item->id,
                    'warehouse_id'      => $data['warehouse_id'] ?? null,
                    'type'              => $data['type'] ?? 'in',
                    'quantity'          => $it['quantity'],
                    'unit_cost'         => $it['unit_cost']    ?? null,
                    'total_amount'      => $it['total_amount'] ?? null,
                    'payment_method'    => $data['payment_method'] ?? null,
                    'account_id'        => $data['account_id']     ?? null,
                    'party_type'        => $data['party_type']     ?? null,
                    'party_id'          => $data['party_id']       ?? null,
                    'settle_id'         => $data['settle_id']      ?? null,
                    'project_id'        => $data['project_id']     ?? null,
                    'remaining_stock'   => $item->current_stock,
                    'batch_no'          => $data['batch_no']  ?? null,
                    'remark'            => $data['remark']    ?? null,
                    'operator_id'       => $request->user()->id,
                ]);
                $lastItem = $item->fresh();
            }

            // V1.2.14p: 入库单入库后联动财务
            //   - 现金付款 (cash + account_id): 创建 FinancePayment + 扣减资金账户余额
            //   - 应付款   (credit + party_id): 创建 Payable + 关联 FinancePayment
            $totalAmount = array_sum(array_column($itemsPayload, 'total_amount'));
            $paymentMethod = $data['payment_method'] ?? null;
            $accountId     = $data['account_id'] ?? null;
            $partyType     = $data['party_type'] ?? null;
            $partyId       = $data['party_id'] ?? null;
            $projectId     = $data['project_id'] ?? null;
            $payableId     = null;
            $financePaymentId = null;

            // 1) 应付款 → 创建 Payable (供应商)
            if ($paymentMethod === 'credit' && $partyType === 'supplier' && $partyId && $totalAmount > 0) {
                $payable = \App\Models\Payable::create([
                    'supplier_id'       => $partyId,
                    'project_id'        => $projectId,
                    'amount'            => $totalAmount,
                    'paid_amount'       => 0,
                    'remaining_amount'  => $totalAmount,
                    'due_date'          => now()->addDays(30)->toDateString(),
                    'status'            => 'pending',
                    'ref_no'            => $recordNo,
                    'description'       => '入库单: ' . $recordNo . ($data['remark'] ? ' - ' . $data['remark'] : ''),
                    'payment_term'      => 30,
                ]);
                $payableId = $payable->id;
            }

            // 2) 现金付款 → 创建 FinancePayment + 扣减账户余额
            if ($paymentMethod === 'cash' && $accountId && $totalAmount > 0) {
                $account = \App\Models\FinanceAccount::lockForUpdate()->find($accountId);
                if ($account) {
                    $fp = \App\Models\FinancePayment::create([
                        'account_id'   => $accountId,
                        'payable_id'   => $payableId,
                        'amount'       => $totalAmount,
                        'payment_date' => now()->toDateString(),
                        'method'       => '现金',
                        'operator'     => $request->user()->name ?? '',
                        'remark'       => '入库单: ' . $recordNo . ($data['remark'] ? ' - ' . $data['remark'] : ''),
                    ]);
                    $financePaymentId = $fp->id;
                    $account->decrement('balance', $totalAmount);
                }
            } elseif ($paymentMethod === 'credit' && $payableId && $accountId && $totalAmount > 0) {
                // 应付款也支持立即部分付款 (用同一账户), 记录 FinancePayment
                $account = \App\Models\FinanceAccount::lockForUpdate()->find($accountId);
                if ($account) {
                    \App\Models\FinancePayment::create([
                        'account_id'   => $accountId,
                        'payable_id'   => $payableId,
                        'amount'       => $totalAmount,
                        'payment_date' => now()->toDateString(),
                        'method'       => '现金',
                        'operator'     => $request->user()->name ?? '',
                        'remark'       => '入库单: ' . $recordNo . ' 即时付款',
                    ]);
                    $account->decrement('balance', $totalAmount);
                }
            }

            return [
                'record_no'         => $recordNo,
                'item_count'        => count($records),
                'item'              => $lastItem,
                'record'            => $records[0],
                'records'           => $records,
                'payable_id'        => $payableId,
                'finance_payment_id'=> $financePaymentId,
            ];
        });
    }

    public function stockOut(Request $request): array
    {
        // V1.2.14p: 支持单条 (item_id) 和批量 (items[]) 两种形态; 出库是进账 (现金收款/应收款)
        $rules = [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'order_no'     => 'nullable|string|max:100',
            'logistics_company' => 'nullable|string|max:100',
            'logistics_no' => 'nullable|string|max:100',
            'payment_method' => 'nullable|in:cash,receivable',
            'account_id'   => 'nullable|integer|exists:finance_accounts,id',
            'party_type'   => 'nullable|in:customer',
            'party_id'     => 'nullable|integer',
            'settle_id'    => 'nullable|integer',
            'project_id'   => 'nullable|integer|exists:projects,id',
            'batch_no'     => 'nullable|string|max:100',
            'type'         => 'nullable|in:out,sale,scrap',
            'remark'       => 'nullable|string',
        ];
        $hasItems = $request->has('items') && is_array($request->items);
        if ($hasItems) {
            $rules['items']                = 'required|array|min:1';
            $rules['items.*.item_id']      = 'required|integer|exists:inventory_items,id';
            $rules['items.*.quantity']     = 'required|integer|min:1';
            $rules['items.*.unit_price']   = 'nullable|numeric|min:0';
            $rules['items.*.total_amount'] = 'nullable|numeric|min:0';
        } else {
            $rules['item_id']      = 'required|integer|exists:inventory_items,id';
            $rules['quantity']     = 'required|integer|min:1';
            $rules['unit_price']   = 'nullable|numeric|min:0';
            $rules['total_amount'] = 'nullable|numeric|min:0';
        }
        $data = $request->validate($rules);

        $itemsPayload = $hasItems
            ? $data['items']
            : [[
                'item_id'      => $data['item_id'],
                'quantity'     => $data['quantity'],
                'unit_price'   => $data['unit_price']   ?? null,
                'total_amount' => $data['total_amount'] ?? null,
            ]];

        return DB::transaction(function () use ($itemsPayload, $data, $request) {
            // V1.2.14p: 一次出库的多物料共享 record_no
            $recordNo = $this->nextRecordNo('OUT');
            $records  = [];
            $lastItem = null;
            foreach ($itemsPayload as $it) {
                $item = InventoryItem::lockForUpdate()->findOrFail($it['item_id']);
                if ($item->current_stock < $it['quantity']) {
                    throw new RuntimeException("库存不足: 「{$item->name}」当前 {$item->current_stock}, 需要 {$it['quantity']}");
                }
                $item->decrement('current_stock', $it['quantity']);
                // V1.2.14p: 出库时同步更新物料仓库
                if ($item->warehouse_id === null && !empty($data['warehouse_id'])) {
                    $item->warehouse_id = (int) $data['warehouse_id'];
                    $item->save();
                }
                $records[] = StockRecord::create([
                    'record_no'         => $recordNo,
                    'inventory_item_id' => $item->id,
                    'warehouse_id'      => $data['warehouse_id'],
                    'type'              => $data['type'] ?? 'out',
                    'quantity'          => $it['quantity'],
                    'unit_cost'         => $it['unit_price']   ?? null,   // V1.2.14p: 字段统一
                    'total_amount'      => $it['total_amount'] ?? null,
                    'remaining_stock'   => $item->current_stock,
                    'order_no'          => $data['order_no']         ?? null,
                    'logistics_company' => $data['logistics_company']?? null,
                    'logistics_no'      => $data['logistics_no']     ?? null,
                    'party_type'        => $data['party_type']       ?? 'customer',
                    'party_id'          => $data['party_id']         ?? null,
                    'settle_id'         => $data['settle_id']        ?? null,
                    'project_id'        => $data['project_id']       ?? null,
                    'payment_method'    => $data['payment_method']   ?? null,
                    'account_id'        => $data['account_id']       ?? null,
                    'batch_no'          => $data['batch_no']         ?? null,
                    'remark'            => $data['remark']           ?? null,
                    'operator_id'       => $request->user()->id,
                ]);
                $lastItem = $item->fresh();
            }

            // V1.2.14p: 出库联动财务 (进账)
            //   - 现金收款 (cash + account_id): 账户余额 + 增加
            //   - 应收款   (receivable + customer): 创建 Receivable
            $totalAmount = array_sum(array_column($itemsPayload, 'total_amount'));
            $paymentMethod = $data['payment_method'] ?? null;
            $accountId     = $data['account_id'] ?? null;
            $partyId       = $data['party_id'] ?? null;
            $projectId     = $data['project_id'] ?? null;
            $receivableId  = null;
            $financePaymentId = null;

            // 1) 应收款 → 创建 Receivable (客户)
            if ($paymentMethod === 'receivable' && $partyId && $totalAmount > 0) {
                $recv = \App\Models\Receivable::create([
                    'customer_id'       => $partyId,
                    'project_id'        => $projectId,
                    'amount'            => $totalAmount,
                    'received_amount'   => 0,
                    'remaining_amount'  => $totalAmount,
                    'due_date'          => now()->addDays(30)->toDateString(),
                    'status'            => 'pending',
                    'notes'             => '出库单: ' . $recordNo . ($data['remark'] ? ' - ' . $data['remark'] : ''),
                ]);
                $receivableId = $recv->id;
            }

            // 2) 现金收款 → 账户余额 + 增加 + FinancePayment
            if ($paymentMethod === 'cash' && $accountId && $totalAmount > 0) {
                $account = \App\Models\FinanceAccount::lockForUpdate()->find($accountId);
                if ($account) {
                    $fp = \App\Models\FinancePayment::create([
                        'account_id'    => $accountId,
                        'receivable_id' => $receivableId,
                        'amount'        => $totalAmount,
                        'payment_date'  => now()->toDateString(),
                        'method'        => '现金',
                        'operator'      => $request->user()->name ?? '',
                        'remark'        => '出库单: ' . $recordNo . ($data['remark'] ? ' - ' . $data['remark'] : ''),
                    ]);
                    $financePaymentId = $fp->id;
                    $account->increment('balance', $totalAmount);
                }
            } elseif ($paymentMethod === 'receivable' && $receivableId && $accountId && $totalAmount > 0) {
                // 应收款 + 立即收款 (用同一账户), 记录 FinancePayment
                $account = \App\Models\FinanceAccount::lockForUpdate()->find($accountId);
                if ($account) {
                    \App\Models\FinancePayment::create([
                        'account_id'    => $accountId,
                        'receivable_id' => $receivableId,
                        'amount'        => $totalAmount,
                        'payment_date'  => now()->toDateString(),
                        'method'        => '现金',
                        'operator'      => $request->user()->name ?? '',
                        'remark'        => '出库单: ' . $recordNo . ' 即时收款',
                    ]);
                    $account->increment('balance', $totalAmount);
                }
            }

            return [
                'record_no'         => $recordNo,
                'item_count'        => count($records),
                'item'              => $lastItem,
                'record'            => $records[0],
                'records'           => $records,
                'receivable_id'     => $receivableId,
                'finance_payment_id'=> $financePaymentId,
            ];
        });
    }

    /**
     * V1.2.14p: 按 record_no 聚合, 同一单的多个物料合并为一行返回 (整单视图)
     * 返回结构: record_no / type / created_at / operator / warehouse / project / party /
     *           payment_method / item_count / total_quantity / total_amount / remark
     */
    public function paginateStockRecords(Request $request)
    {
        $base = DB::table('stock_records')
            ->select([
                'record_no',
                'type',
                DB::raw('MAX(created_at) AS created_at'),
                DB::raw('MAX(operator_id) AS operator_id'),
                DB::raw('MAX(warehouse_id) AS warehouse_id'),
                // V1.2.16: 调拨单用 source_warehouse_id / target_warehouse_id 区分两端
                DB::raw('MAX(source_warehouse_id) AS source_warehouse_id'),
                DB::raw('MAX(target_warehouse_id) AS target_warehouse_id'),
                // V1.2.16 fix: PG 没有 max(boolean), 用 CASE WHEN 转 int
                DB::raw('MAX(CASE WHEN is_transfer THEN 1 ELSE 0 END) AS is_transfer'),
                DB::raw('MAX(project_id) AS project_id'),
                DB::raw('MAX(party_id) AS party_id'),
                DB::raw('MAX(party_type) AS party_type'),
                DB::raw('MAX(payment_method) AS payment_method'),
                DB::raw('MAX(account_id) AS account_id'),
                DB::raw('MAX(remark) AS remark'),
                DB::raw('COUNT(DISTINCT inventory_item_id) AS item_count'),
                // V1.2.16 fix: 调拨单每个物料写 2 条记录 (源/目标), SUM(quantity) 双倍, 调拨单要除以 2
                DB::raw('SUM(quantity) / (1 + MAX(CASE WHEN is_transfer THEN 1 ELSE 0 END)) AS total_quantity'),
                DB::raw('SUM(total_amount) AS total_amount'),
            ])
            ->groupBy('record_no', 'type')
            ->orderByDesc('created_at');

        if ($request->filled('item_id')) $base->where('inventory_item_id', $request->item_id);
        if ($request->filled('type')) {
            // V1.2.16: 兼容历史调拨数据 (type='out'/'in' 但 is_transfer=true) 和新数据 (type='transfer')
            if ($request->type === 'transfer') {
                $base->where(function ($q) {
                    $q->where('type', 'transfer')->orWhere('is_transfer', true);
                });
            } else {
                $base->where('type', $request->type)->where('is_transfer', false);
            }
        }
        if ($request->filled('project_id')) $base->where('project_id', $request->project_id);
        if ($request->filled('direction'))  $base->where('type', $request->direction === 'out' ? 'out' : 'in');

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = (int)($request->per_page ?? 20);
        $total   = (clone $base)->get()->count();
        $rows    = $base->forPage($page, $perPage)->get();

        // 加载关联
        $operatorIds = $rows->pluck('operator_id')->filter()->unique()->values()->all();
        $warehouseIds = $rows->pluck('warehouse_id')->filter()->unique()->values()->all();
        // V1.2.16: 调拨单的源/目标仓库也要加载
        $srcIds = $rows->pluck('source_warehouse_id')->filter()->unique()->values()->all();
        $tgtIds = $rows->pluck('target_warehouse_id')->filter()->unique()->values()->all();
        $allWhIds = array_unique(array_merge($warehouseIds, $srcIds, $tgtIds));
        $projectIds  = $rows->pluck('project_id')->filter()->unique()->values()->all();
        $partyIds    = $rows->pluck('party_id')->filter()->unique()->values()->all();

        $operators  = $operatorIds ? DB::table('users')->whereIn('id', $operatorIds)->select('id','name')->get()->keyBy('id') : collect();
        $warehouses = $allWhIds ? DB::table('warehouses')->whereIn('id', $allWhIds)->select('id','name')->get()->keyBy('id') : collect();
        $projects   = $projectIds ? DB::table('projects')->whereIn('id', $projectIds)->select('id','name')->get()->keyBy('id') : collect();
        // V1.2.14p fix: 用 array 累积, merge collection 会丢失 keyBy 状态, 必须自己 dict
        $partyMap = [];
        foreach ($rows->pluck('party_type')->filter()->unique() as $pt) {
            $tbl = $pt === 'supplier' ? 'suppliers' : ($pt === 'customer' ? 'customers' : null);
            if (!$tbl) continue;
            $ids = $rows->where('party_type', $pt)->pluck('party_id')->filter()->unique()->values()->all();
            if ($ids) {
                foreach (DB::table($tbl)->whereIn('id', $ids)->select('id','name')->get() as $p) {
                    $partyMap[$pt.'_'.$p->id] = $p->name;
                }
            }
        }

        $data = $rows->map(function ($r) use ($operators, $warehouses, $projects, $partyMap) {
            $pt = $r->party_type;
            $partyName = ($pt && $r->party_id) ? ($partyMap[$pt.'_'.$r->party_id] ?? null) : null;
            $result = [
                'record_no'      => $r->record_no,
                'type'           => $r->type,
                'is_transfer'    => (bool)$r->is_transfer,
                'created_at'     => $r->created_at,
                'operator'       => $r->operator_id ? ['id' => (int)$r->operator_id, 'name' => optional($operators->get($r->operator_id))->name] : null,
                'warehouse'      => $r->warehouse_id ? ['id' => (int)$r->warehouse_id, 'name' => optional($warehouses->get($r->warehouse_id))->name] : null,
                'project'        => $r->project_id ? ['id' => (int)$r->project_id, 'name' => optional($projects->get($r->project_id))->name] : null,
                'party'          => $r->party_id ? ['id' => (int)$r->party_id, 'name' => $partyName, 'type' => $pt] : null,
                'payment_method' => $r->payment_method,
                'item_count'     => (int)$r->item_count,
                'total_quantity' => (int)$r->total_quantity,
                'total_amount'   => (string)$r->total_amount,
                'remark'         => $r->remark,
            ];
            // V1.2.16: 调拨单附加源/目标仓库字段
            if ($r->is_transfer) {
                $result['source_warehouse'] = $r->source_warehouse_id ? ['id' => (int)$r->source_warehouse_id, 'name' => optional($warehouses->get($r->source_warehouse_id))->name] : null;
                $result['target_warehouse'] = $r->target_warehouse_id ? ['id' => (int)$r->target_warehouse_id, 'name' => optional($warehouses->get($r->target_warehouse_id))->name] : null;
            }
            return $result;
        });

        return [
            'data'         => $data,
            'total'        => $total,
            'current_page' => $page,
            'per_page'     => $perPage,
            'last_page'    => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    /**
     * V1.3.6: 库存流水记录 — 原始明细流水分页 (不聚合)
     *
     * 供「出入库」流水记录页逐条展示 (每行=一次物料变动, 带完整物料信息)。
     * 排除工具领退流水 (tool_checkout/tool_return), 工具流水在「工具使用单」页展示。
     */
    public function paginateRawStockRecords(Request $request)
    {
        $query = StockRecord::with([
                'inventoryItem:id,code,name,specification,unit',
                'operator:id,name',
                'warehouse:id,name',
            ])
            ->whereNotIn('type', ['tool_checkout', 'tool_return']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('record_no', 'like', "%{$kw}%")
                  ->orWhereHas('inventoryItem', fn ($x) => $x->where('name', 'like', "%{$kw}%")->orWhere('code', 'like', "%{$kw}%"))
                  ->orWhereHas('operator', fn ($x) => $x->where('name', 'like', "%{$kw}%"));
            });
        }
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);

        return $query->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 15));
    }

    /**
     * V1.2.14p: 单据详情 (整单 + 物料明细)
     */
    public function stockRecordDetail(string $recordNo): ?array
    {
        $first = StockRecord::where('record_no', $recordNo)->orderBy('id')->first();
        if (!$first) return null;

        $rows = StockRecord::with(['inventoryItem:id,name,code,specification,unit'])
            ->where('record_no', $recordNo)
            ->orderBy('id')
            ->get();

        $operator  = $first->operator_id  ? DB::table('users')->where('id', $first->operator_id)->select('id','name')->first() : null;
        $warehouse = $first->warehouse_id ? DB::table('warehouses')->where('id', $first->warehouse_id)->select('id','name')->first() : null;
        // V1.2.16: 调拨单也要返回源/目标仓库
        $sourceWh  = $first->source_warehouse_id ? DB::table('warehouses')->where('id', $first->source_warehouse_id)->select('id','name')->first() : null;
        $targetWh  = $first->target_warehouse_id ? DB::table('warehouses')->where('id', $first->target_warehouse_id)->select('id','name')->first() : null;
        $project   = $first->project_id   ? DB::table('projects')->where('id', $first->project_id)->select('id','name')->first() : null;
        $party     = null;
        if ($first->party_id) {
            $tbl = $first->party_type === 'supplier' ? 'suppliers' : ($first->party_type === 'customer' ? 'customers' : null);
            if ($tbl) $party = DB::table($tbl)->where('id', $first->party_id)->select('id','name')->first();
        }

        $items = $rows->map(function ($r) {
            return [
                'id'               => $r->id,
                'inventory_item_id'=> $r->inventory_item_id,
                'quantity'         => $r->quantity,
                'unit_cost'        => (string)$r->unit_cost,
                'total_amount'     => (string)$r->total_amount,
                'remaining_stock'  => $r->remaining_stock,
                'batch_no'         => $r->batch_no,
                'inventoryItem'    => $r->inventoryItem ? [
                    'id'           => $r->inventoryItem->id,
                    'name'         => $r->inventoryItem->name,
                    'code'         => $r->inventoryItem->code,
                    'specification'=> $r->inventoryItem->specification,
                    'unit'         => $r->inventoryItem->unit,
                ] : null,
            ];
        });

        // V1.2.16 fix: 调拨单的同一物料在源/目标仓各写一条记录 (共 2 条),
        // 详情页要按 inventory_item_id 合并成 1 行, 避免用户看到「选 1 个变 2 个」
        if ($first->is_transfer) {
            $grouped = [];
            foreach ($items as $r) {
                $iid = $r['inventory_item_id'];
                if (!isset($grouped[$iid])) {
                    $grouped[$iid] = $r;
                } else {
                    // 合并: 数量只取第一条 (源/目标数量一致), 但保留第一条 ID
                    $grouped[$iid]['id'] = $r['id'];
                }
            }
            $items = collect(array_values($grouped));
        }

        return [
            'record_no'      => $first->record_no,
            'type'           => $first->type,
            'is_transfer'    => (bool)$first->is_transfer,
            'created_at'     => $first->created_at,
            'operator'       => $operator  ? ['id' => $operator->id,  'name' => $operator->name]  : null,
            'warehouse'      => $warehouse ? ['id' => $warehouse->id, 'name' => $warehouse->name] : null,
            'source_warehouse' => $sourceWh ? ['id' => $sourceWh->id, 'name' => $sourceWh->name] : null,
            'target_warehouse' => $targetWh ? ['id' => $targetWh->id, 'name' => $targetWh->name] : null,
            'project'        => $project   ? ['id' => $project->id,   'name' => $project->name]   : null,
            'party'          => $party     ? ['id' => $party->id,     'name' => $party->name]     : null,
            'payment_method' => $first->payment_method,
            // V1.2.16 fix: item_count/total_quantity 也按合并后的物料数算, 不算 raw rows
            'item_count'     => $items->count(),
            'total_quantity' => (int)$items->sum('quantity'),
            // V1.2.16 fix: total_amount 字段已 cast 成 string, sum 不能直接加, 用 array_sum + floatval
            'total_amount'   => (string)array_sum(array_map(fn($r) => (float)$r['total_amount'], $items->all())),
            'remark'         => $first->remark,
            'items'          => $items,
        ];
    }

    public function warehouses()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        // 附加每个仓库的物料数和库存总额
        $itemCounts = InventoryItem::selectRaw('warehouse_id, count(*) as cnt, sum(current_stock) as total_stock')
            ->whereNotNull('warehouse_id')
            ->groupBy('warehouse_id')
            ->get()->keyBy('warehouse_id');
        $totalValues = InventoryItem::selectRaw('warehouse_id, sum(current_stock * cost_price) as total_value')
            ->whereNotNull('warehouse_id')
            ->groupBy('warehouse_id')
            ->get()->keyBy('warehouse_id');

        return $warehouses->map(function ($w) use ($itemCounts, $totalValues) {
            $stats = $itemCounts->get($w->id);
            $val = $totalValues->get($w->id);
            $w->item_count = $stats ? (int) $stats->cnt : 0;
            $w->total_stock_qty = $stats ? (int) $stats->total_stock : 0;
            $w->total_value = $val ? (float) $val->total_value : 0;
            return $w;
        });
    }

    // ============================================================
    // === 仓库 CRUD (V1.2.14p) ===
    // ============================================================

    public function storeWarehouse(Request $request): Warehouse
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'required|string|max:50|unique:warehouses,code',
            'type'        => 'nullable|in:main,project,aftermarket',
            'address'     => 'nullable|string|max:255',
            'manager_id'  => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:active,inactive',
        ]);
        return Warehouse::create($request->only(['name','code','type','address','manager_id','description','status']));
    }

    public function updateWarehouse(Request $request, int $id): Warehouse
    {
        $warehouse = Warehouse::findOrFail($id);
        $request->validate([
            'name'        => 'nullable|string|max:100',
            'code'        => 'nullable|string|max:50|unique:warehouses,code,'.$id,
            'type'        => 'nullable|in:main,project,aftermarket',
            'address'     => 'nullable|string|max:255',
            'manager_id'  => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:active,inactive',
        ]);
        $warehouse->update($request->only(['name','code','type','address','manager_id','description','status']));
        return $warehouse->fresh();
    }

    public function destroyWarehouse(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);
        // 检查是否有物料关联
        if (InventoryItem::where('warehouse_id', $id)->exists()) {
            throw new RuntimeException('该仓库下仍有物料，无法删除');
        }
        if (StockRecord::where('warehouse_id', $id)->exists()) {
            throw new RuntimeException('该仓库仍有出入库记录，无法删除');
        }
        $warehouse->delete();
    }

    // ============================================================
    // === 仓库调拨 (V1.2.14p) ===
    // ============================================================

    public function stockTransfer(Request $request): array
    {
        $request->validate([
            'source_warehouse_id' => 'required|integer|exists:warehouses,id|different:target_warehouse_id',
            'target_warehouse_id' => 'required|integer|exists:warehouses,id',
            'items'               => 'required|array|min:1',
            'items.*.item_id'     => 'required|integer|exists:inventory_items,id',
            'items.*.quantity'    => 'required|integer|min:1',
            'remark'              => 'nullable|string|max:500',
        ]);

        $data = $request->all();
        $sourceId = (int) $data['source_warehouse_id'];
        $targetId = (int) $data['target_warehouse_id'];
        $items = $data['items'];
        $remark = $data['remark'] ?? '';
        $operatorId = auth()->id();

        // 查询仓库名称用于备注
        $sourceName = Warehouse::find($sourceId)?->name ?? "源仓#{$sourceId}";
        $targetName = Warehouse::find($targetId)?->name ?? "目标仓#{$targetId}";

        $recordNo = $this->nextRecordNo('TR');
        $created = [];

        DB::transaction(function () use ($sourceId, $targetId, $items, $remark, $operatorId, $recordNo, $sourceName, $targetName, &$created) {
            foreach ($items as $item) {
                $itemId = (int) $item['item_id'];
                $qty = (int) $item['quantity'];

                // 锁定库存并扣减源仓
                $inv = InventoryItem::where('id', $itemId)->lockForUpdate()->firstOrFail();
                if ($inv->current_stock < $qty) {
                    throw new RuntimeException("物料「{$inv->name}」库存不足（当前: {$inv->current_stock}, 需: {$qty}）");
                }
                $inv->decrement('current_stock', $qty);

                // V1.2.16: 调拨单统一 type='transfer', 用 is_transfer + source/target 仓库区分方向
                // 创建源仓出库记录
                StockRecord::create([
                    'inventory_item_id'  => $itemId,
                    'warehouse_id'       => $sourceId,
                    'source_warehouse_id'=> $sourceId,
                    'target_warehouse_id'=> $targetId,
                    'is_transfer'        => true,
                    'type'               => 'transfer',
                    'quantity'           => $qty,
                    'remaining_stock'    => max(0, $inv->current_stock - $qty),
                    'operator_id'        => $operatorId,
                    'remark'             => "调拨至「{$targetName}」" . ($remark ? " - {$remark}" : ''),
                    'record_no'          => $recordNo,
                ]);

                // 创建目标仓库入库记录
                StockRecord::create([
                    'inventory_item_id'  => $itemId,
                    'warehouse_id'       => $targetId,
                    'source_warehouse_id'=> $sourceId,
                    'target_warehouse_id'=> $targetId,
                    'is_transfer'        => true,
                    'type'               => 'transfer',
                    'quantity'           => $qty,
                    'remaining_stock'    => $inv->current_stock,
                    'operator_id'        => $operatorId,
                    'remark'             => "从「{$sourceName}」调拨" . ($remark ? " - {$remark}" : ''),
                    'record_no'          => $recordNo,
                ]);

                $created[] = ['item_id' => $itemId, 'quantity' => $qty, 'name' => $inv->name];
            }
        });

        return [
            'record_no'  => $recordNo,
            'item_count' => count($items),
            'items'      => $created,
        ];
    }

    /**
     * V1.2.7 P2-4: 生成下一条出入库单号 (OUT-YYYYMMDD-NNNN / IN-YYYYMMDD-NNNN)
     * 用 transaction 包裹 + count 当天 prefix, 避免同日并发重复
     */
    public function nextRecordNo(string $prefix = 'OUT'): string
    {
        $today = date('Ymd');
        $fullPrefix = "{$prefix}-{$today}-";
        $cnt = StockRecord::where('record_no', 'like', $fullPrefix . '%')->count();
        return $fullPrefix . str_pad((string) ($cnt + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * V1.3.5: 工具使用单 — 库存转工具 (支持选择数量)
     *
     * 把库存商品的部分数量转换成工具台账, 自动生成固定资产编号 GD-YYYYMMDD-NNNN。
     * 已转换过的商品跳过 (inventory_item_id 唯一)。
     *
     * @return array{created:array, skipped:array}
     */
    public function toolConvert(Request $request): array
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|integer|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $created = [];
        $skipped = [];
        foreach ($data['items'] as $it) {
            $itemId = (int) $it['inventory_item_id'];
            $qty    = (int) $it['quantity'];
            if (Tool::where('inventory_item_id', $itemId)->exists()) {
                $skipped[] = ['inventory_item_id' => $itemId, 'reason' => '该商品已是工具'];
                continue;
            }
            $item = InventoryItem::find($itemId);
            if (!$item) {
                $skipped[] = ['inventory_item_id' => $itemId, 'reason' => '商品不存在'];
                continue;
            }
            if ($qty > (int) $item->current_stock) {
                $skipped[] = ['inventory_item_id' => $itemId, 'reason' => "转换数量超过当前库存({$item->current_stock})"];
                continue;
            }
            $tool = Tool::create([
                'inventory_item_id' => $itemId,
                'fixed_asset_no'    => $this->nextFixedAssetNo(),
                'name'              => $item->name,
                'code'              => $item->code,
                'specification'     => $item->specification ?: ($item->spec ?? null),
                'unit'              => $item->unit,
                'quantity'          => $qty,
                'warehouse_id'      => $item->warehouse_id,
                'status'            => 'in_stock',
                'created_by'        => $request->user()->id,
            ]);
            $created[] = $tool->fresh();
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * V1.3.4: 工具使用单 — 工具领用/退还
     *
     * @param  string  $direction  'checkout'=领用(出库,扣库存,工具置为已领用) | 'return'=退还(入库,加库存,工具置为在库)
     * @return array{record_no:string,item_count:int,records:array}
     */
    public function toolMovement(Request $request, string $direction): array
    {
        $isCheckout = $direction === 'checkout';
        $data = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.tool_id'  => 'required|integer|exists:tools,id',
            'items.*.quantity' => 'required|integer|min:1',
            'remark'           => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $isCheckout, $request) {
            $recordNo = $this->nextRecordNo('TU');
            $records  = [];
            foreach ($data['items'] as $it) {
                $tool = Tool::with('inventoryItem')->findOrFail($it['tool_id']);
                if (!$tool->inventoryItem) {
                    throw new RuntimeException("工具「{$tool->name}」未关联库存商品, 无法操作");
                }
                $item = InventoryItem::lockForUpdate()->findOrFail($tool->inventory_item_id);
                $qty  = (int) $it['quantity'];
                $borrowed = $this->toolBorrowedCount($tool->inventory_item_id);

                if ($isCheckout) {
                    $available = (int) $tool->quantity - $borrowed;
                    if ($qty > $available) {
                        throw new RuntimeException("工具「{$tool->name}」可领用件数不足(台账 {$tool->quantity} 件, 已借出 {$borrowed} 件, 可用 {$available} 件)");
                    }
                    if ((int) $item->current_stock < $qty) {
                        throw new RuntimeException("工具「{$tool->name}」库存不足(当前 {$item->current_stock}, 需 {$qty})");
                    }
                    $item->decrement('current_stock', $qty);
                } else {
                    if ($qty > $borrowed) {
                        throw new RuntimeException("工具「{$tool->name}」已借出 {$borrowed} 件, 退还数量超出");
                    }
                    $item->increment('current_stock', $qty);
                    if ($item->warehouse_id === null && $tool->warehouse_id) {
                        $item->warehouse_id = (int) $tool->warehouse_id;
                        $item->save();
                    }
                }

                $newBorrowed = $isCheckout ? $borrowed + $qty : $borrowed - $qty;
                $tool->update(['status' => $newBorrowed >= (int) $tool->quantity ? 'out' : 'in_stock']);

                $records[] = StockRecord::create([
                    'record_no'         => $recordNo,
                    'inventory_item_id' => $item->id,
                    'warehouse_id'      => $tool->warehouse_id,
                    'type'              => $isCheckout ? 'tool_checkout' : 'tool_return',
                    'quantity'          => $qty,
                    'remaining_stock'   => (int) $item->current_stock,
                    'operator_id'       => $request->user()->id,
                    'remark'            => $data['remark'] ?? null,
                ]);
            }

            return [
                'record_no'  => $recordNo,
                'item_count' => count($records),
                'records'    => $records,
            ];
        });
    }

    /**
     * V1.3.5: 指定库存商品累计借出件数 (领用 - 退还)
     */
    private function toolBorrowedCount(int $inventoryItemId): int
    {
        $a = StockRecord::where('inventory_item_id', $inventoryItemId)
            ->whereIn('type', ['tool_checkout', 'tool_return'])
            ->selectRaw("SUM(CASE WHEN type = 'tool_checkout' THEN quantity ELSE 0 END) AS out_qty, SUM(CASE WHEN type = 'tool_return' THEN quantity ELSE 0 END) AS in_qty")
            ->first();
        return $a ? (int) $a->out_qty - (int) $a->in_qty : 0;
    }

    /**
     * V1.3.4: 工具使用明细列表 — 直接平铺显示领用/归还流水
     */
    public function paginateToolRecords(Request $request)
    {
        $query = StockRecord::with([
                'inventoryItem:id,code,name,specification,unit',
                'operator:id,name',
            ])
            ->whereIn('type', ['tool_checkout', 'tool_return']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('record_no', 'like', "%{$kw}%")
                  ->orWhereHas('inventoryItem', fn ($x) => $x->where('name', 'like', "%{$kw}%")->orWhere('code', 'like', "%{$kw}%"))
                  ->orWhereHas('operator', fn ($x) => $x->where('name', 'like', "%{$kw}%"));
            });
        }

        $list = $query->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 15));

        // 附加固定资产编号 (按 inventory_item_id 关联工具台账)
        $itemIds = collect($list->items())->pluck('inventory_item_id')->unique()->all();
        $toolMap = $itemIds ? Tool::whereIn('inventory_item_id', $itemIds)
            ->get(['inventory_item_id', 'fixed_asset_no'])
            ->keyBy('inventory_item_id') : collect();
        foreach ($list as $rec) {
            $rec->tool = $toolMap->get($rec->inventory_item_id);
        }

        return $list;
    }

    /**
     * V1.3.4: 工具台账列表 (供领用/归还选择器 + 管理)
     */
    public function listTools(Request $request)
    {
        $query = Tool::with(['inventoryItem:id,code,name,specification,unit,current_stock', 'warehouse:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('fixed_asset_no', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        $tools = $query->orderByDesc('created_at')->get();

        // 每件工具的借出数 (累计领用 - 累计退还), 供退还数量参考
        $itemIds = $tools->pluck('inventory_item_id')->all();
        $agg = StockRecord::whereIn('inventory_item_id', $itemIds)
            ->whereIn('type', ['tool_checkout', 'tool_return'])
            ->selectRaw('inventory_item_id, SUM(CASE WHEN type = \'tool_checkout\' THEN quantity ELSE 0 END) AS out_qty, SUM(CASE WHEN type = \'tool_return\' THEN quantity ELSE 0 END) AS in_qty')
            ->groupBy('inventory_item_id')
            ->get()
            ->keyBy('inventory_item_id');

        foreach ($tools as $tool) {
            $a = $agg->get($tool->inventory_item_id);
            $tool->borrowed = $a ? (int) $a->out_qty - (int) $a->in_qty : 0;
            $tool->available = max(0, (int) $tool->quantity - $tool->borrowed);
            $tool->current_stock = (int) ($tool->inventoryItem->current_stock ?? 0);
        }

        return $tools;
    }

    /**
     * V1.3.4: 生成固定资产编号 GD-YYYYMMDD-NNNN
     */
    public function nextFixedAssetNo(): string
    {
        $prefix = 'GD-' . date('Ymd') . '-';
        $cnt = Tool::where('fixed_asset_no', 'like', $prefix . '%')->count();
        return $prefix . str_pad((string) ($cnt + 1), 4, '0', STR_PAD_LEFT);
    }


    // ============================================================
    // === 批量导入（CSV/XLSX）— 业务逻辑层 ===
    // ============================================================

    /**
     * 解析 + 导入
     * @return array{created:array, skipped:array, errors:array}
     */
    public function batchImport(string $realPath, string $ext): array
    {
        $rows = $this->parseSpreadsheet($realPath, $ext);
        if (empty($rows)) {
            throw new RuntimeException('文件为空或解析失败');
        }

        $header = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            $h = trim((string) $h);
            $h = strtolower($h);
            $map = [
                '名称' => 'name', 'name' => 'name',
                '编码' => 'code', '物料编码' => 'code', 'code' => 'code',
                '分类' => 'category_name', '分类名称' => 'category_name', 'category' => 'category_name', 'category_name' => 'category_name',
                '规格' => 'specification', '规格型号' => 'specification', 'specification' => 'specification',
                '单位' => 'unit', 'unit' => 'unit',
                '最低库存' => 'min_stock', 'min_stock' => 'min_stock',
                '安全库存' => 'safety_stock', 'safety_stock' => 'safety_stock',
                '当前库存' => 'current_stock', 'current_stock' => 'current_stock',
                '成本价' => 'cost_price', 'cost_price' => 'cost_price',
                '销售价' => 'sell_price', 'sell_price' => 'sell_price',
                '仓库' => 'warehouse_name', 'warehouse_name' => 'warehouse_name',
                '库位' => 'location', 'location' => 'location',
            ];
            return $map[$h] ?? null;
        }, $rows[0]);

        foreach (['name', 'code', 'category_name', 'unit'] as $must) {
            if (!in_array($must, $header, true)) {
                throw new RuntimeException("表头缺少必填列: {$must}");
            }
        }

        $categories = InventoryCategory::all()->keyBy('name');
        $warehouses = Warehouse::all()->keyBy('name');
        $existingCodes = InventoryItem::pluck('code')->map(fn($c) => (string) $c)->flip()->toArray();

        $created = []; $skipped = []; $errors = [];

        foreach (array_slice($rows, 1) as $lineNo => $row) {
            if (!is_array($row) || count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $get = function ($key) use ($row, $header) {
                $i = array_search($key, $header, true);
                if ($i === false) return null;
                $v = $row[$i] ?? null;
                return is_string($v) ? trim($v) : $v;
            };

            $name = $get('name'); $code = $get('code');
            $catName = $get('category_name'); $unit = $get('unit');
            $spec = $get('specification');
            $min = $get('min_stock'); $safety = $get('safety_stock'); $current = $get('current_stock');
            $cost = $get('cost_price'); $sell = $get('sell_price');
            $whName = $get('warehouse_name'); $loc = $get('location');
            $excelLine = $lineNo + 2;

            $missing = [];
            if (!$name) $missing[] = 'name';
            if (!$code) $missing[] = 'code';
            if (!$catName) $missing[] = 'category_name';
            if (!$unit) $missing[] = 'unit';
            if ($missing) { $errors[] = ['row' => $excelLine, 'code' => $code, 'reason' => '缺少必填字段: ' . implode(',', $missing)]; continue; }

            if (isset($existingCodes[(string) $code])) {
                $skipped[] = ['row' => $excelLine, 'code' => $code, 'name' => $name, 'reason' => '编码已存在'];
                continue;
            }

            if (!isset($categories[$catName])) {
                $cat = InventoryCategory::create([
                    'name'        => $catName,
                    'code'        => 'CAT-' . strtoupper(Str::random(6)),
                    'parent_id'   => null,
                    'sort_order'  => 999,
                    'description' => '批量导入自动创建',
                ]);
                $categories[$catName] = $cat;
            }
            $categoryId = $categories[$catName]->id;

            $warehouseId = null;
            if ($whName) {
                if (isset($warehouses[$whName])) {
                    $warehouseId = $warehouses[$whName]->id;
                } else {
                    $errors[] = ['row' => $excelLine, 'code' => $code, 'reason' => "仓库不存在: {$whName}"];
                    continue;
                }
            }

            try {
                $item = InventoryItem::create([
                    'name'          => $name,
                    'code'          => $code,
                    'category'      => $catName,
                    'category_id'   => $categoryId,
                    'specification' => $spec,
                    'unit'          => $unit,
                    'min_stock'     => is_numeric($min) ? (int) $min : 0,
                    'safety_stock'  => is_numeric($safety) ? (int) $safety : 0,
                    'current_stock' => is_numeric($current) ? (int) $current : 0,
                    'cost_price'    => is_numeric($cost) ? (float) $cost : 0,
                    'sell_price'    => is_numeric($sell) ? (float) $sell : 0,
                    'warehouse_id'  => $warehouseId,
                    'location'      => $loc,
                    'status'        => 'active',
                ]);
                $existingCodes[(string) $code] = true;
                $created[] = ['row' => $excelLine, 'id' => $item->id, 'code' => $code, 'name' => $name];
            } catch (\Throwable $e) {
                \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage()]);
                $errors[] = ['row' => $excelLine, 'code' => $code, 'reason' => '数据库错误: ' . $e->getMessage()];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }

    /**
     * 简单 CSV/XLSX 解析
     */
    public function parseSpreadsheet(string $path, string $ext): array
    {
        if ($ext === 'csv') {
            $rows = [];
            if (($h = fopen($path, 'r')) !== false) {
                $bom = fread($h, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($h);
                while (($r = fgetcsv($h)) !== false) { $rows[] = $r; }
                fclose($h);
            }
            return $rows;
        }
        if ($ext === 'xlsx') {
            return $this->parseXlsx($path);
        }
        return [];
    }

    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return [];

        // 1. 共享字符串
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $s = @simplexml_load_string($xml);
            if ($s) {
                foreach ($s->si as $si) {
                    if (isset($si->t)) {
                        $strings[] = (string) $si->t;
                    } else {
                        // 富文本拼接
                        $txt = '';
                        foreach ($si->r as $r) $txt .= (string)($r->t ?? '');
                        $strings[] = $txt;
                    }
                }
            }
        }

        // 2. sheet1
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheet) return [];

        $s = @simplexml_load_string($sheet);
        if (!$s) return [];

        $rows = [];
        foreach ($s->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $c) {
                $t = (string) $c['t'];
                $v = (string) ($c->v ?? '');
                if ($t === 's') {
                    $rowData[] = $strings[(int) $v] ?? '';
                } else {
                    $rowData[] = $v;
                }
            }
            $rows[] = $rowData;
        }
        return $rows;
    }

    // ============================================================
    // === 导出 ===
    // ============================================================

    public function batchExport(Request $request): array
    {
        $query = InventoryItem::with(['warehouse:id,name', 'categoryRef:id,name']);
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->warehouse_id);

        $rows = $query->orderBy('code')->get()->map(function ($i) {
            return [
                $i->code, $i->name, $i->category, $i->specification, $i->unit,
                (int) $i->min_stock, (int) $i->safety_stock, (int) $i->current_stock,
                (float) $i->cost_price, (float) $i->sell_price,
                $i->warehouse?->name, $i->location,
            ];
        });

        return [
            'filename' => 'inventory_' . date('Ymd_His') . '.csv',
            'headers'  => ['编码', '名称', '分类', '规格', '单位', '最低库存', '安全库存', '当前库存', '成本价', '销售价', '仓库', '库位'],
            'rows'     => $rows,
        ];
    }

    public function exportTemplate(): array
    {
        return [
            'filename' => 'inventory_template.csv',
            'headers'  => ['name', 'code', 'category_name', 'specification', 'unit', 'min_stock', 'safety_stock', 'current_stock', 'cost_price', 'sell_price', 'warehouse_name', 'location'],
            'rows'     => [
                ['示例物料', 'DEMO-001', '安防设备', '4K 红外', '台', 5, 10, 20, 800, 1200, '主仓', 'A-01'],
            ],
        ];
    }
}
