<?php

namespace App\Services;

use DateTimeImmutable;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\AssetInventory;
use App\Models\AssetInventoryItem;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 固定资产管理 — V1.4.0（全生命周期）
 *
 * 台账 CRUD / 分类树 / 折旧计提(直线法) / 维修保养 / 盘点 / 报废处置 / 调拨
 */
class AssetService
{
    public function __construct(private InventoryService $inventory) {}

    // ============================================================
    // === 资产分类 ===
    // ============================================================

    public function categoryTree(): array
    {
        $all = AssetCategory::orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id', 'name', 'sort_order']);
        $children = [];
        foreach ($all as $c) {
            $children[(int) $c->parent_id][] = $c;
        }
        $walk = function ($parentId) use (&$walk, $children) {
            $nodes = [];
            foreach ($children[$parentId] ?? [] as $c) {
                $nodes[] = ['id' => $c->id, 'name' => $c->name, 'children' => $walk((int) $c->id)];
            }
            return $nodes;
        };
        return $walk(0);
    }

    public function storeCategory(Request $request): AssetCategory
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'parent_id'  => 'nullable|integer|exists:asset_categories,id',
            'sort_order' => 'nullable|integer',
        ]);
        return AssetCategory::create([
            'name'       => $data['name'],
            'parent_id'  => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function updateCategory(Request $request, int $id): AssetCategory
    {
        $category = AssetCategory::findOrFail($id);
        $data = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'parent_id'  => 'nullable|integer|exists:asset_categories,id',
            'sort_order' => 'nullable|integer',
        ]);
        if (isset($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new RuntimeException('父分类不能是自己');
        }
        if (isset($data['parent_id']) && in_array((int) $data['parent_id'], $this->categoryDescendantIds($id), true)) {
            throw new RuntimeException('父分类不能设置为当前分类的子分类');
        }
        $category->update($data);
        return $category->fresh();
    }

    public function destroyCategory(int $id): void
    {
        $category = AssetCategory::findOrFail($id);
        if (AssetCategory::where('parent_id', $id)->exists()) {
            throw new RuntimeException('该分类下还有子分类, 请先删除子分类');
        }
        if (FixedAsset::where('category_id', $id)->exists()) {
            throw new RuntimeException('该分类下还有资产, 不能删除');
        }
        $category->delete();
    }

    // ============================================================
    // === 资产台账 ===
    // ============================================================

    public function index(Request $request)
    {
        $query = FixedAsset::with(['category:id,name', 'keeper:id,name', 'tool:id,fixed_asset_no,name,status,quantity']);

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('asset_no', 'like', "%{$kw}%")
                  ->orWhere('specification', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('category_id')) {
            // 树形: 含子分类
            $ids = $this->categoryDescendantIds((int) $request->category_id);
            $query->whereIn('category_id', $ids);
        }
        if ($request->filled('status'))  $query->where('status', $request->status);
        if ($request->filled('source'))  $query->where('source', $request->source);

        return $query->orderByDesc('created_at')->paginate($this->perPage($request));
    }

    private function categoryDescendantIds(int $id): array
    {
        $ids = [$id];
        $visited = [$id => true];
        $all = AssetCategory::pluck('parent_id', 'id')->all();
        $stack = [$id];
        while ($stack) {
            $p = array_pop($stack);
            foreach ($all as $cid => $pid) {
                if ((int) $pid === $p) {
                    $childId = (int) $cid;
                    if (!isset($visited[$childId])) {
                        $visited[$childId] = true;
                        $ids[] = $childId;
                        $stack[] = $childId;
                    }
                }
            }
        }
        return $ids;
    }

    public function store(Request $request): FixedAsset
    {
        $data = $this->validateAsset($request);
        $this->assertAssetValues($data);
        if (($data['status'] ?? null) === 'scrapped') {
            throw new RuntimeException('新增资产不能直接设为已报废, 请通过报废处置操作');
        }
        return DB::transaction(function () use ($data, $request) {
            $asset = FixedAsset::create([
                'asset_no'              => $this->inventory->nextAssetNumber(),
                'category_id'           => $data['category_id'] ?? null,
                'name'                  => $data['name'],
                'specification'         => $data['specification'] ?? null,
                'unit'                  => $data['unit'] ?? null,
                'quantity'              => $data['quantity'] ?? 1,
                'source'                => 'manual',
                'original_value'        => $data['original_value'] ?? 0,
                'net_residual_value'    => $data['net_residual_value'] ?? 0,
                'useful_life_months'    => $data['useful_life_months'] ?? 60,
                'acquisition_date'      => $data['acquisition_date'] ?? null,
                'net_book_value'        => $data['original_value'] ?? 0,
                'status'                => $data['status'] ?? 'in_use',
                'location'              => $data['location'] ?? null,
                'keeper_id'             => $data['keeper_id'] ?? null,
                'remark'                => $data['remark'] ?? null,
                'created_by'            => $request->user()->id,
            ]);
            return $asset->fresh(['category:id,name', 'keeper:id,name']);
        });
    }

    public function show(FixedAsset $asset): FixedAsset
    {
        return $asset->load([
            'category:id,name',
            'keeper:id,name',
            'tool:id,fixed_asset_no,name,status,quantity,warehouse_id',
            'depreciations',
            'maintenances:id,asset_id,date,type,cost,description,result,handler_id',
            'transfers:id,asset_id,date,from_location,to_location,from_keeper_id,to_keeper_id,remark',
            'disposals:id,asset_id,date,method,amount,reason,remark',
        ]);
    }

    public function update(Request $request, FixedAsset $asset): FixedAsset
    {
        $data = $this->validateAsset($request, false);
        return DB::transaction(function () use ($data, $asset) {
            $lockedAsset = FixedAsset::lockForUpdate()->findOrFail($asset->id);
            $this->assertAssetValues($data, $lockedAsset);

            if ($lockedAsset->source === 'tool' || $lockedAsset->tool_id !== null) {
                foreach (['name', 'specification', 'unit', 'quantity'] as $linkedField) {
                    if (array_key_exists($linkedField, $data)
                        && (string) $data[$linkedField] !== (string) $lockedAsset->{$linkedField}) {
                        throw new RuntimeException('工具台账生成的资产不能修改名称、规格、单位或数量');
                    }
                }
            }

            if (($data['status'] ?? null) === 'scrapped' && $lockedAsset->status !== 'scrapped') {
                throw new RuntimeException('不能直接将资产设为已报废, 请使用报废处置');
            }
            if ($lockedAsset->status === 'scrapped' && isset($data['status']) && $data['status'] !== 'scrapped') {
                throw new RuntimeException('已报废资产不能通过编辑恢复状态');
            }

            $fields = [
                'category_id', 'name', 'specification', 'unit', 'quantity',
                'original_value', 'net_residual_value', 'useful_life_months', 'acquisition_date',
                'status', 'location', 'keeper_id', 'remark',
            ];
            $payload = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
            if (array_key_exists('original_value', $data)) {
                $payload['net_book_value'] = round((float) $data['original_value'] - (float) $lockedAsset->accumulated_depreciation, 2);
            }
            $lockedAsset->update($payload);
            return $lockedAsset->fresh(['category:id,name', 'keeper:id,name']);
        });
    }

    public function destroy(FixedAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $lockedAsset = FixedAsset::lockForUpdate()->findOrFail($asset->id);
            if ($lockedAsset->source === 'tool' || $lockedAsset->tool_id !== null) {
                throw new RuntimeException('工具台账生成的固定资产不能单独删除');
            }
            foreach (['depreciations', 'maintenances', 'transfers', 'disposals'] as $relation) {
                if ($lockedAsset->{$relation}()->exists()) {
                    throw new RuntimeException('该资产已有折旧/维修/调拨/报废记录, 不能删除');
                }
            }
            if (AssetInventoryItem::where('asset_id', $lockedAsset->id)->exists()) {
                throw new RuntimeException('该资产已有盘点记录, 不能删除');
            }
            $lockedAsset->delete();
        });
    }

    private function validateAsset(Request $request, bool $required = true): array
    {
        $rules = [
            'name'                => ($required ? 'required' : 'sometimes') . '|string|max:200',
            'category_id'         => 'nullable|integer|exists:asset_categories,id',
            'specification'       => 'nullable|string|max:255',
            'unit'                => 'nullable|string|max:20',
            'quantity'            => 'sometimes|integer|min:1',
            'original_value'      => 'sometimes|numeric|min:0',
            'net_residual_value'  => 'sometimes|numeric|min:0',
            'useful_life_months'  => 'sometimes|integer|min:1|max:600',
            'acquisition_date'    => 'nullable|date',
            'status'              => 'sometimes|in:in_use,idle,repair,scrapped',
            'location'            => 'nullable|string|max:200',
            'keeper_id'           => 'nullable|integer|exists:users,id',
            'remark'              => 'nullable|string|max:1000',
        ];
        return $request->validate($rules);
    }

    private function assertAssetValues(array $data, ?FixedAsset $asset = null): void
    {
        $originalValue = (float) ($data['original_value'] ?? $asset?->original_value ?? 0);
        $residualValue = (float) ($data['net_residual_value'] ?? $asset?->net_residual_value ?? 0);
        $accumulated = (float) ($asset?->accumulated_depreciation ?? 0);

        if ($residualValue > $originalValue) {
            throw new RuntimeException('净残值不能大于资产原值');
        }
        if ($accumulated > round($originalValue - $residualValue, 2)) {
            throw new RuntimeException('原值和净残值变更后不能小于已累计折旧范围');
        }
    }

    // ============================================================
    // === 折旧 (直线法) ===
    // ============================================================

    /** 对指定月份执行折旧计提 (幂等: 已计提过/已提满/已报废的跳过) */
    public function depreciate(Request $request): array
    {
        $period = (string) $request->input('period');
        $periodDate = DateTimeImmutable::createFromFormat('!Y-m', $period);
        if (!$periodDate || $periodDate->format('Y-m') !== $period) {
            throw new RuntimeException('期间格式应为 YYYY-MM');
        }
        if ($period > now()->format('Y-m')) {
            throw new RuntimeException('不能计提未来期间的折旧');
        }
        $count = 0;
        $skipped = 0;
        DB::transaction(function () use ($period, $request, &$count, &$skipped) {
            $assets = FixedAsset::where('status', '!=', 'scrapped')
                ->lockForUpdate()
                ->get();
            foreach ($assets as $asset) {
                if ($asset->acquisition_date && substr((string) $asset->acquisition_date, 0, 7) > $period) { $skipped++; continue; }
                if ((float) $asset->net_book_value <= (float) $asset->net_residual_value + 0.001) { $skipped++; continue; }
                if (AssetDepreciation::where('asset_id', $asset->id)->where('period', $period)->exists()) { $skipped++; continue; }
                if (AssetDepreciation::where('asset_id', $asset->id)->where('period', '>', $period)->exists()) { $skipped++; continue; }
                $monthly = $asset->monthlyDepreciation();
                if ($monthly <= 0) { $skipped++; continue; }
                // 最后一期修正: 不超过 (原值-残值-已累计)
                $maxTotal = round((float) $asset->original_value - (float) $asset->net_residual_value, 2);
                $remain = round($maxTotal - (float) $asset->accumulated_depreciation, 2);
                if ($monthly > $remain) $monthly = $remain;
                if ($monthly <= 0) { $skipped++; continue; }
                $newAcc = round((float) $asset->accumulated_depreciation + $monthly, 2);
                $newNet = round((float) $asset->original_value - $newAcc, 2);
                AssetDepreciation::create([
                    'asset_id'           => $asset->id,
                    'period'             => $period,
                    'month_depreciation' => $monthly,
                    'accumulated_after'  => $newAcc,
                    'net_value_after'    => $newNet,
                    'created_by'         => $request->user()->id,
                ]);
                $asset->update(['accumulated_depreciation' => $newAcc, 'net_book_value' => $newNet]);
                $count++;
            }
        });
        return ['depreciated' => $count, 'skipped' => $skipped];
    }

    public function depreciations(Request $request)
    {
        $query = AssetDepreciation::with(['asset:id,asset_no,name']);
        if ($request->filled('period')) $query->where('period', $request->period);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('period')->orderByDesc('id')
            ->paginate($this->perPage($request));
    }

    // ============================================================
    // === 维修保养 ===
    // ============================================================

    public function maintenances(Request $request)
    {
        $query = AssetMaintenance::with(['asset:id,asset_no,name', 'handler:id,name']);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('date')->orderByDesc('id')
            ->paginate($this->perPage($request));
    }

    public function storeMaintenance(Request $request): AssetMaintenance
    {
        $data = $request->validate([
            'asset_id'    => 'required|integer|exists:fixed_assets,id',
            'date'        => 'nullable|date',
            'type'        => 'nullable|in:repair,maintain,inspect',
            'cost'        => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'result'      => 'nullable|string|max:1000',
            'handler_id'  => 'nullable|integer|exists:users,id',
        ]);
        return DB::transaction(function () use ($data, $request) {
            $asset = FixedAsset::lockForUpdate()->findOrFail($data['asset_id']);
            if ($asset->status === 'scrapped') {
                throw new RuntimeException('已报废资产不能新增维修保养记录');
            }
            return AssetMaintenance::create([
                'asset_id'    => $asset->id,
                'date'        => $data['date'] ?? now()->toDateString(),
                'type'        => $data['type'] ?? 'repair',
                'cost'        => $data['cost'] ?? 0,
                'description' => $data['description'] ?? null,
                'result'      => $data['result'] ?? null,
                'handler_id'  => $data['handler_id'] ?? $request->user()->id,
            ]);
        });
    }

    // ============================================================
    // === 盘点 ===
    // ============================================================

    public function inventories(Request $request)
    {
        return AssetInventory::with(['items.asset:id,asset_no,name'])
            ->orderByDesc('id')->paginate($this->perPage($request));
    }

    public function storeInventory(Request $request): AssetInventory
    {
        $data = $request->validate([
            'date'         => 'nullable|date',
            'remark'       => 'nullable|string|max:500',
            'items'        => 'required|array|min:1',
            'items.*.asset_id'     => 'required|integer|exists:fixed_assets,id',
            'items.*.actual_qty'   => 'required|integer|min:0',
            'items.*.note'         => 'nullable|string|max:500',
        ]);
        return DB::transaction(function () use ($data, $request) {
            $inventory = AssetInventory::create([
                'no'         => $this->nextInventoryNo(),
                'date'       => $data['date'] ?? now()->toDateString(),
                'status'     => 'pending',
                'remark'     => $data['remark'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $seen = [];
            foreach ($data['items'] as $it) {
                $assetId = (int) $it['asset_id'];
                if (isset($seen[$assetId])) {
                    throw new RuntimeException("资产 #{$assetId} 在盘点单中重复");
                }
                $seen[$assetId] = true;
                $asset = FixedAsset::lockForUpdate()->findOrFail($assetId);
                if ($asset->status === 'scrapped') {
                    throw new RuntimeException("已报废资产 #{$assetId} 不能加入盘点单");
                }
                $book = (int) $asset->quantity;
                $actual = (int) $it['actual_qty'];
                AssetInventoryItem::create([
                    'inventory_id' => $inventory->id,
                    'asset_id'     => $asset->id,
                    'book_qty'     => $book,
                    'actual_qty'   => $actual,
                    'difference'   => $actual - $book,
                    'note'         => $it['note'] ?? null,
                ]);
            }
            return $inventory->fresh(['items.asset:id,asset_no,name']);
        });
    }

    public function completeInventory(AssetInventory $inventory): AssetInventory
    {
        return DB::transaction(function () use ($inventory) {
            $lockedInventory = AssetInventory::lockForUpdate()->findOrFail($inventory->id);
            if ($lockedInventory->status !== 'pending') {
                throw new RuntimeException('该盘点单已经完成');
            }
            $lockedInventory->update(['status' => 'done']);
            return $lockedInventory->fresh(['items.asset:id,asset_no,name']);
        });
    }

    private function nextInventoryNo(): string
    {
        $today = now()->format('Ymd');
        $prefix = "PD-{$today}-";
        $next = NumberSequenceService::next(
            "asset-inventory:{$today}",
            fn () => (int) AssetInventory::where('no', 'like', $prefix . '%')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(no FROM 'PD-[0-9]{8}-([0-9]+)') AS INTEGER)), 0) as seq")
                ->value('seq')
        );
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // ============================================================
    // === 报废处置 ===
    // ============================================================

    public function disposals(Request $request)
    {
        $query = AssetDisposal::with(['asset:id,asset_no,name', 'handler:id,name']);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('date')->orderByDesc('id')
            ->paginate($this->perPage($request));
    }

    public function storeDisposal(Request $request): AssetDisposal
    {
        $data = $request->validate([
            'asset_id'   => 'required|integer|exists:fixed_assets,id',
            'date'       => 'nullable|date',
            'method'     => 'nullable|in:scrap,sell,donate',
            'amount'     => 'nullable|numeric|min:0',
            'reason'     => 'nullable|string|max:1000',
            'remark'     => 'nullable|string|max:500',
        ]);
        return DB::transaction(function () use ($data, $request) {
            $asset = FixedAsset::lockForUpdate()->findOrFail($data['asset_id']);
            if ($asset->status === 'scrapped') {
                throw new RuntimeException('该资产已报废, 不可重复处置');
            }
            $disposal = AssetDisposal::create([
                'asset_id'   => $asset->id,
                'date'       => $data['date'] ?? now()->toDateString(),
                'method'     => $data['method'] ?? 'scrap',
                'amount'     => $data['amount'] ?? 0,
                'reason'     => $data['reason'] ?? null,
                'handler_id' => $request->user()->id,
                'remark'     => $data['remark'] ?? null,
            ]);
            $asset->update(['status' => 'scrapped']);
            return $disposal->fresh(['asset:id,asset_no,name']);
        });
    }

    // ============================================================
    // === 调拨 ===
    // ============================================================

    public function transfers(Request $request)
    {
        $query = AssetTransfer::with(['asset:id,asset_no,name']);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('date')->orderByDesc('id')
            ->paginate($this->perPage($request));
    }

    public function storeTransfer(Request $request): AssetTransfer
    {
        $data = $request->validate([
            'asset_id'        => 'required|integer|exists:fixed_assets,id',
            'date'            => 'nullable|date',
            'to_location'     => 'nullable|string|max:200',
            'to_keeper_id'    => 'nullable|integer|exists:users,id',
            'remark'          => 'nullable|string|max:500',
        ]);
        return DB::transaction(function () use ($data, $request) {
            $asset = FixedAsset::lockForUpdate()->findOrFail($data['asset_id']);
            if ($asset->status === 'scrapped') {
                throw new RuntimeException('已报废资产不可调拨');
            }
            $hasLocation = array_key_exists('to_location', $data) && $data['to_location'] !== null;
            $hasKeeper = array_key_exists('to_keeper_id', $data) && $data['to_keeper_id'] !== null;
            if (!$hasLocation && !$hasKeeper) {
                throw new RuntimeException('调拨必须指定新的存放地或保管人');
            }
            if ((!$hasLocation || $data['to_location'] === $asset->location)
                && (!$hasKeeper || (int) $data['to_keeper_id'] === (int) $asset->keeper_id)) {
                throw new RuntimeException('调拨后的存放地或保管人必须发生变化');
            }
            $transfer = AssetTransfer::create([
                'asset_id'       => $asset->id,
                'date'           => $data['date'] ?? now()->toDateString(),
                'from_location'  => $asset->location,
                'to_location'    => $data['to_location'] ?? null,
                'from_keeper_id' => $asset->keeper_id,
                'to_keeper_id'   => $data['to_keeper_id'] ?? null,
                'remark'         => $data['remark'] ?? null,
                'created_by'     => $request->user()->id,
            ]);
            $asset->update([
                'location'  => $data['to_location'] ?? $asset->location,
                'keeper_id' => $data['to_keeper_id'] ?? $asset->keeper_id,
            ]);
            return $transfer->fresh(['asset:id,asset_no,name']);
        });
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 15), 1), 100);
    }
}
