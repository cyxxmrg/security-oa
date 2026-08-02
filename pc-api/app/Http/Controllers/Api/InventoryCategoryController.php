<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $flat = InventoryCategory::withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        return response()->json(['code' => 0, 'data' => $flat]);
    }

    public function tree(): JsonResponse
    {
        $flat = InventoryCategory::withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 转成纯 array, 避免 Eloquent Model 动态属性 children_list 引发 json_encode 循环引用
        $byId = [];
        foreach ($flat as $c) {
            $byId[$c->id] = [
                'id'           => (int) $c->id,
                'parent_id'    => $c->parent_id ? (int) $c->parent_id : null,
                'name'         => $c->name,
                'code'         => $c->code,
                'sort_order'   => (int) $c->sort_order,
                'description'  => $c->description,
                'items_count'  => (int) $c->items_count,
                'children'     => [],
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => 'nullable|exists:inventory_categories,id',
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:inventory_categories,code',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $row = InventoryCategory::create($data);
        return response()->json(['code' => 0, 'message' => '分类已添加', 'data' => $row->loadCount('items')]);
    }

    public function update(Request $request, InventoryCategory $category): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => 'nullable|exists:inventory_categories,id',
            'name' => 'sometimes|string|max:100',
            'code' => 'nullable|string|max:50|unique:inventory_categories,code,' . $category->id,
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);
        // 防止循环引用: 新父不能是自身或自身子孙 (向下 BFS 找所有子孙)
        if (!empty($data['parent_id'])) {
            $newParentId = (int) $data['parent_id'];
            if ($newParentId === (int) $category->id) {
                return response()->json(['code' => 1001, 'message' => '父级不能是自己'], 422);
            }
            $descendants = $this->collectDescendantIds((int) $category->id);
            if (in_array($newParentId, $descendants, true)) {
                return response()->json(['code' => 1001, 'message' => '父级不能是自身的子孙'], 422);
            }
        }
        $category->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $category->fresh()->loadCount('items')]);
    }

    public function destroy(InventoryCategory $category): JsonResponse
    {
        // 业务规则: 该分类或子分类下有关联物品时禁止删除
        $total = InventoryCategory::where('parent_id', $category->id)->count() + $category->items()->count();
        if ($total > 0) {
            return response()->json([
                'code' => 1001,
                'message' => "该分类下还有 {$total} 个物品/子分类, 不允许删除"
            ], 422);
        }
        $category->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /**
     * POST /api/inventory-categories/{category}/move
     * body: { parent_id: int|null, target_position: int|null }
     * 防循环引用：向下 BFS 找 category 自身所有子孙，新父不能在子孙集合里
     */
    public function moveCategory(Request $request, InventoryCategory $category): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => 'nullable|integer|min:1',
            'target_position' => 'nullable|integer|min:0',
        ]);

        $newParentId = $data['parent_id'] ?? null;

        // 顶级（parent_id=null）允许
        if ($newParentId === null) {
            $category->parent_id = null;
            if (isset($data['target_position'])) $category->sort_order = $data['target_position'];
            $category->save();
            return response()->json([
                'code' => 0,
                'message' => '已移动到根级',
                'data' => $category->fresh()->loadCount('items'),
            ]);
        }

        $newParentId = (int) $newParentId;

        // 1) 不能把自己设为父
        if ($newParentId === (int) $category->id) {
            return response()->json(['code' => 1001, 'message' => '父级不能是自己'], 422);
        }

        // 2) 新父必须存在
        $newParent = InventoryCategory::find($newParentId);
        if (!$newParent) {
            return response()->json(['code' => 1004, 'message' => '目标父分类不存在'], 422);
        }

        // 3) 向下 BFS 找 $category 自身所有子孙 ID，若 newParentId 在子孙集合里 → 循环
        $descendants = $this->collectDescendantIds((int) $category->id);
        if (in_array($newParentId, $descendants, true)) {
            return response()->json([
                'code' => 1001,
                'message' => '目标父分类是自身的子孙, 会造成循环引用',
            ], 422);
        }

        $category->parent_id = $newParentId;
        if (isset($data['target_position'])) $category->sort_order = $data['target_position'];
        $category->save();

        return response()->json([
            'code' => 0,
            'message' => '已移动',
            'data' => $category->fresh()->loadCount('items'),
        ]);
    }

    /**
     * 向下 BFS 收集分类 $rootId 自身及所有子孙 id
     * @return int[]
     */
    private function collectDescendantIds(int $rootId): array
    {
        $allCats = InventoryCategory::all()->keyBy('id');
        $descendants = [$rootId];
        $queue = [$rootId];
        $guard = 0;
        while ($queue && $guard++ < 1000) {
            $cur = array_shift($queue);
            foreach ($allCats as $c) {
                if ((int) $c->parent_id === (int) $cur && !in_array((int) $c->id, $descendants, true)) {
                    $descendants[] = (int) $c->id;
                    $queue[] = (int) $c->id;
                }
            }
        }
        return $descendants;
    }
}
