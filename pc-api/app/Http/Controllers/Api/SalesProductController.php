<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\SalesProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 销售产品库 — 报价单下拉用,可关联库存分类
 */
class SalesProductController extends Controller
{
    /**
     * GET /api/sales/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesProduct::with('categoryRef');
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%")
                  ->orWhere('spec', 'like', "%{$kw}%");
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        $perPage = (int) ($request->per_page ?? 15);
        return response()->json([
            'code' => 0,
            'data' => $query->orderBy('created_at', 'desc')->paginate(max(1, min($perPage, 200))),
        ]);
    }

    /**
     * POST /api/sales/products
     */
    public function store(Request $request): JsonResponse
    {
        // V1.2.10: 前端 price/cost 别名映射到 sale_price/cost_price
        if ($request->has('price') && !$request->has('sale_price')) {
            $request->merge(['sale_price' => $request->input('price')]);
        }
        if ($request->has('cost') && !$request->has('cost_price')) {
            $request->merge(['cost_price' => $request->input('cost')]);
        }
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'code'        => 'nullable|string|max:50|unique:sales_products,code',
            'category_id' => 'nullable|integer|exists:inventory_categories,id',
            'unit'        => 'nullable|string|max:20',
            'spec'        => 'nullable|string|max:200',
            'price'       => 'nullable|numeric|min:0',  // 别名
            'cost'        => 'nullable|numeric|min:0',  // 别名
            'sale_price'  => 'required|numeric|min:0',
            'cost_price'  => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status'      => 'nullable|string|in:active,inactive',
        ]);
        unset($data['price'], $data['cost']);

        $data['unit']       = $data['unit'] ?? '件';
        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['status']     = $data['status'] ?? 'active';

        $prod = SalesProduct::create($data);
        return response()->json(['code' => 0, 'data' => $prod->load('categoryRef')]);
    }

    /**
     * GET /api/sales/products/{id}
     */
    public function show(SalesProduct $product): JsonResponse
    {
        $product->load('categoryRef');
        return response()->json(['code' => 0, 'data' => $product]);
    }

    /**
     * PUT /api/sales/products/{id}
     */
    public function update(Request $request, SalesProduct $product): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:200',
            'code'        => 'nullable|string|max:50|unique:sales_products,code,' . $product->id,
            'category_id' => 'nullable|integer|exists:inventory_categories,id',
            'unit'        => 'nullable|string|max:20',
            'spec'        => 'nullable|string|max:200',
            'sale_price'  => 'sometimes|numeric|min:0',
            'cost_price'  => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status'      => 'nullable|string|in:active,inactive',
        ]);

        $product->update($data);
        return response()->json(['code' => 0, 'data' => $product->fresh()->load('categoryRef')]);
    }

    /**
     * DELETE /api/sales/products/{id}
     */
    public function destroy(SalesProduct $product): JsonResponse
    {
        // 检查是否被报价单引用 — 暂不强约束,只软约束:被引用时仅停用不删
        $refCount = DB::table('quotation_items')->where('inventory_item_id', $product->id)->count();
        if ($refCount > 0) {
            return response()->json([
                'code' => 1,
                'message' => "产品已被 {$refCount} 条报价单引用, 不可删除, 已自动停用",
            ], 409);
        }
        $product->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    /**
     * GET /api/sales/products/categories
     * 产品库下拉用的分类列表(树形 + 含每级产品数)
     */
    public function categories(): JsonResponse
    {
        $flat = InventoryCategory::withCount(['items', 'children'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byId = [];
        foreach ($flat as $c) {
            $byId[$c->id] = [
                'id'            => $c->id,
                'name'          => $c->name,
                'parent_id'     => $c->parent_id,
                'sort_order'    => $c->sort_order,
                'items_count'   => (int)($c->items_count ?? 0),
                'children_count'=> (int)($c->children_count ?? 0),
                'children'      => [],
            ];
        }
        $roots = [];
        foreach ($byId as $id => $node) {
            if ($node['parent_id'] && isset($byId[$node['parent_id']])) {
                $byId[$node['parent_id']]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }
        unset($byId);

        return response()->json(['code' => 0, 'data' => $roots]);
    }
}
