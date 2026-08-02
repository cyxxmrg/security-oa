<?php

namespace App\Services;

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

        return $query->orderByDesc('created_at')->paginate((int) $request->integer('per_page', 15));
    }

    private function categoryDescendantIds(int $id): array
    {
        $ids = [$id];
        $all = AssetCategory::pluck('parent_id', 'id')->all();
        $stack = [$id];
        while ($stack) {
            $p = array_pop($stack);
            foreach ($all as $cid => $pid) {
                if ((int) $pid === $p) {
                    $ids[] = $cid;
                    $stack[] = $cid;
                }
            }
        }
        return $ids;
    }

    public function store(Request $request): FixedAsset
    {
        $data = $this->validateAsset($request);
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
        $fields = [
            'category_id', 'name', 'specification', 'unit', 'quantity',
            'original_value', 'net_residual_value', 'useful_life_months', 'acquisition_date',
            'status', 'location', 'keeper_id', 'remark',
        ];
        $payload = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) $payload[$f] = $data[$f];
        }
        // 原值变更时重算净值 (累计折旧不变)
        if (isset($data['original_value'])) {
            $payload['net_book_value'] = round((float) $data['original_value'] - (float) $asset->accumulated_depreciation, 2);
        }
        $asset->update($payload);
        return $asset->fresh(['category:id,name', 'keeper:id,name']);
    }

    public function destroy(FixedAsset $asset): void
    {
        foreach (['depreciations', 'maintenances', 'transfers', 'disposals'] as $rel) {
            if ($asset->{$rel}()->exists()) {
                throw new RuntimeException('该资产已有折旧/维修/调拨/报废记录, 不能删除');
            }
        }
        $asset->delete();
    }

    private function validateAsset(Request $request, bool $required = true): array
    {
        $rules = [
            'name'                => ($required ? 'required' : 'sometimes') . '|string|max:200',
            'category_id'         => 'nullable|integer|exists:asset_categories,id',
            'specification'       => 'nullable|string|max:255',
            'unit'                => 'nullable|string|max:20',
            'quantity'            => 'nullable|integer|min:1',
            'original_value'      => 'nullable|numeric|min:0',
            'net_residual_value'  => 'nullable|numeric|min:0',
            'useful_life_months'  => 'nullable|integer|min:1|max:600',
            'acquisition_date'    => 'nullable|date',
            'status'              => 'nullable|in:in_use,idle,repair,scrapped',
            'location'            => 'nullable|string|max:200',
            'keeper_id'           => 'nullable|integer|exists:users,id',
            'remark'              => 'nullable|string|max:1000',
        ];
        return $request->validate($rules);
    }

    // ============================================================
    // === 折旧 (直线法) ===
    // ============================================================

    /** 对指定月份执行折旧计提 (幂等: 已计提过/已提满/已报废的跳过) */
    public function depreciate(Request $request): array
    {
        $period = (string) $request->input('period');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new RuntimeException('期间格式应为 YYYY-MM');
        }
        $assets = FixedAsset::where('status', '!=', 'scrapped')->get();
        $count = 0;
        $skipped = 0;
        DB::transaction(function () use ($assets, $period, $request, &$count, &$skipped) {
            foreach ($assets as $asset) {
                if ((float) $asset->net_book_value <= (float) $asset->net_residual_value + 0.001) { $skipped++; continue; }
                if (AssetDepreciation::where('asset_id', $asset->id)->where('period', $period)->exists()) { $skipped++; continue; }
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
            ->paginate((int) $request->integer('per_page', 15));
    }

    // ============================================================
    // === 维修保养 ===
    // ============================================================

    public function maintenances(Request $request)
    {
        $query = AssetMaintenance::with(['asset:id,asset_no,name', 'handler:id,name']);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('date')->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));
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
        return AssetMaintenance::create([
            'asset_id'    => $data['asset_id'],
            'date'        => $data['date'] ?? now()->toDateString(),
            'type'        => $data['type'] ?? 'repair',
            'cost'        => $data['cost'] ?? 0,
            'description' => $data['description'] ?? null,
            'result'      => $data['result'] ?? null,
            'handler_id'  => $data['handler_id'] ?? $request->user()->id,
        ]);
    }

    // ============================================================
    // === 盘点 ===
    // ============================================================

    public function inventories(Request $request)
    {
        return AssetInventory::with(['items.asset:id,asset_no,name'])
            ->orderByDesc('id')->paginate((int) $request->integer('per_page', 15));
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
        $inventory = AssetInventory::create([
            'no'         => $this->nextInventoryNo(),
            'date'       => $data['date'] ?? now()->toDateString(),
            'status'     => 'pending',
            'remark'     => $data['remark'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        foreach ($data['items'] as $it) {
            $asset = FixedAsset::findOrFail($it['asset_id']);
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
    }

    public function completeInventory(AssetInventory $inventory): AssetInventory
    {
        $inventory->update(['status' => 'done']);
        return $inventory->fresh(['items.asset:id,asset_no,name']);
    }

    private function nextInventoryNo(): string
    {
        $prefix = 'PD-' . date('Ymd') . '-';
        $cnt = AssetInventory::where('no', 'like', $prefix . '%')->count();
        return $prefix . str_pad((string) ($cnt + 1), 4, '0', STR_PAD_LEFT);
    }

    // ============================================================
    // === 报废处置 ===
    // ============================================================

    public function disposals(Request $request)
    {
        $query = AssetDisposal::with(['asset:id,asset_no,name', 'handler:id,name']);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('date')->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));
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
        $disposal = AssetDisposal::create([
            'asset_id'   => $data['asset_id'],
            'date'       => $data['date'] ?? now()->toDateString(),
            'method'     => $data['method'] ?? 'scrap',
            'amount'     => $data['amount'] ?? 0,
            'reason'     => $data['reason'] ?? null,
            'handler_id' => $request->user()->id,
            'remark'     => $data['remark'] ?? null,
        ]);
        FixedAsset::where('id', $data['asset_id'])->update(['status' => 'scrapped']);
        return $disposal->fresh(['asset:id,asset_no,name']);
    }

    // ============================================================
    // === 调拨 ===
    // ============================================================

    public function transfers(Request $request)
    {
        $query = AssetTransfer::with(['asset:id,asset_no,name']);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        return $query->orderByDesc('date')->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));
    }

    public function storeTransfer(Request $request): AssetTransfer
    {
        $data = $request->validate([
            'asset_id'        => 'required|integer|exists:fixed_assets,id',
            'date'            => 'nullable|date',
            'from_location'   => 'nullable|string|max:200',
            'to_location'     => 'nullable|string|max:200',
            'from_keeper_id'  => 'nullable|integer|exists:users,id',
            'to_keeper_id'    => 'nullable|integer|exists:users,id',
            'remark'          => 'nullable|string|max:500',
        ]);
        $transfer = AssetTransfer::create([
            'asset_id'       => $data['asset_id'],
            'date'           => $data['date'] ?? now()->toDateString(),
            'from_location'  => $data['from_location'] ?? null,
            'to_location'    => $data['to_location'] ?? null,
            'from_keeper_id' => $data['from_keeper_id'] ?? null,
            'to_keeper_id'   => $data['to_keeper_id'] ?? null,
            'remark'         => $data['remark'] ?? null,
            'created_by'     => $request->user()->id,
        ]);
        // 调拨后更新资产存放地/保管人
        $asset = FixedAsset::find($data['asset_id']);
        $asset->update([
            'location'  => $data['to_location'] ?? $asset->location,
            'keeper_id' => $data['to_keeper_id'] ?? $asset->keeper_id,
        ]);
        return $transfer->fresh(['asset:id,asset_no,name']);
    }
}
