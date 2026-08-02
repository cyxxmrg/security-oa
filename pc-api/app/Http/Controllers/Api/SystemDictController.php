<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemDict;
use App\Http\Requests\System\StoreSystemDictRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * V0.5.7 块B — 数据字典中心
 *
 * 7 端点:
 *   GET    /api/dict/kinds              — 所有 kind 列表
 *   GET    /api/dict                    — 列表 (按 kind 过滤, 含 kind 全部)
 *   GET    /api/dict/grouped            — 按 kind 分组返回
 *   POST   /api/dict                    — 新建
 *   PATCH  /api/dict/{id}               — 改 label/color/is_active/sort_order/is_default
 *   DELETE /api/dict/{id}               — 删 (软: 改 is_active=false)
 *   POST   /api/dict/reorder            — 批量改 sort_order
 *   POST   /api/dict/seed-defaults      — 一键导入 7 类默认字典
 */
class SystemDictController extends Controller
{
    /** GET /api/dict/kinds */
    public function kinds(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => SystemDict::kinds(),
        ]);
    }

    /** GET /api/dict?kind=repair_method&is_active=true */
    public function index(Request $request): JsonResponse
    {
        $kind = $request->query('kind');
        $isActive = $request->boolean('is_active', true);
        // V0.9.3: 字典读多写少, 加 5min 缓存
        $cacheKey = "dict:list:" . ($kind ?? 'all') . ':' . ($isActive ? '1' : '0');
        $rows = \Cache::remember($cacheKey, 300, function () use ($kind, $isActive) {
            $q = SystemDict::query();
            if ($kind) {
                $q->where('kind', $kind);
            }
            if ($isActive) {
                $q->where('is_active', true);
            }
            return $q->orderBy('kind')->orderBy('sort_order')->orderBy('id')->get();
        });

        return response()->json([
            'code' => 0,
            'data' => $rows,
        ]);
    }

    /** GET /api/dict/grouped */
    public function grouped(Request $request): JsonResponse
    {
        // V0.9.3: grouped 一次拉全量, 缓存 5min
        $data = \Cache::remember('dict:grouped:all', 300, function () {
            $kinds = SystemDict::kinds();
            $rows = SystemDict::orderBy('kind')->orderBy('sort_order')->orderBy('id')->get();
            $grouped = [];
            foreach ($kinds as $k => $label) {
            $grouped[] = [
                'kind'  => $k,
                'label' => $label,
                'count' => $rows->where('kind', $k)->count(),
                'items' => $rows->where('kind', $k)->values(),
            ];
        }
        return $grouped;
        });

        return response()->json([
            'code' => 0,
            'data' => $data,
        ]);
    }

    /** POST /api/dict */
    public function store(StoreSystemDictRequest $request): JsonResponse
    {
        $data = $request->validated();

        // 允许扩展 kind (前端 dynamic 添加), 但白名单优先
        $allowedKinds = array_keys(SystemDict::kinds());
        if (!in_array($data['kind'], $allowedKinds, true)) {
            return response()->json([
                'code'    => 1001,
                'message' => "未知的字典分类: {$data['kind']}",
            ], 422);
        }

        // 唯一性 (kind+code)
        if (SystemDict::where('kind', $data['kind'])->where('code', $data['code'])->exists()) {
            return response()->json([
                'code'    => 1001,
                'message' => "已存在: kind={$data['kind']}, code={$data['code']}",
            ], 422);
        }

        $data['created_by'] = $request->user()?->id;
        $dict = SystemDict::create($data);

        return response()->json([
            'code'    => 0,
            'message' => '字典项已创建',
            'data'    => $dict,
        ]);
    }

    /** PATCH /api/dict/{id} */
    public function update(StoreSystemDictRequest $request, int $id): JsonResponse
    {
        $dict = SystemDict::findOrFail($id);
        $data = $request->validated();

        $data['updated_by'] = $request->user()?->id;

        // 若 is_default=true, 同 kind 其他全部置 false
        if (!empty($data['is_default']) && $data['is_default'] === true) {
            SystemDict::where('kind', $dict->kind)
                ->where('id', '!=', $dict->id)
                ->update(['is_default' => false]);
        }

        $dict->update($data);
        return response()->json([
            'code'    => 0,
            'message' => '已更新',
            'data'    => $dict->fresh(),
        ]);
    }

    /** DELETE /api/dict/{id} — 软删 (is_active=false) */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $dict = SystemDict::findOrFail($id);
        $dict->update([
            'is_active'  => false,
            'updated_by' => $request->user()?->id,
        ]);
        return response()->json([
            'code'    => 0,
            'message' => '已停用 (软删)',
            'data'    => ['id' => $id, 'is_active' => false],
        ]);
    }

    /** POST /api/dict/reorder — 批量改 sort_order
     *  body: { items: [{id, sort_order}, ...] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.id'      => 'required|integer|exists:system_dicts,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);
        $userId = $request->user()?->id;
        DB::transaction(function () use ($data, $userId) {
            foreach ($data['items'] as $it) {
                SystemDict::where('id', $it['id'])->update([
                    'sort_order' => $it['sort_order'],
                    'updated_by' => $userId,
                ]);
            }
        });
        return response()->json([
            'code'    => 0,
            'message' => '已重新排序',
            'data'    => ['updated' => count($data['items'])],
        ]);
    }

    /** POST /api/dict/seed-defaults — 一键导入 7 类默认字典 (idempotent) */
    public function seedDefaults(Request $request): JsonResponse
    {
        $defaults = $this->defaultDicts();
        $userId = $request->user()?->id;
        $created = 0; $updated = 0;

        DB::transaction(function () use ($defaults, $userId, &$created, &$updated) {
            foreach ($defaults as $d) {
                $existing = SystemDict::where('kind', $d['kind'])->where('code', $d['code'])->first();
                if ($existing) {
                    // 不覆盖已有的 label/color (运营可能改过), 只补缺项
                    $update = [];
                    if (empty($existing->color) && !empty($d['color'])) $update['color'] = $d['color'];
                    if (empty($existing->description) && !empty($d['description'])) $update['description'] = $d['description'];
                    if ($update) {
                        $update['updated_by'] = $userId;
                        $existing->update($update);
                        $updated++;
                    }
                } else {
                    $d['created_by'] = $userId;
                    SystemDict::create($d);
                    $created++;
                }
            }
        });

        return response()->json([
            'code'    => 0,
            'message' => "已创建 {$created} 个, 更新 {$updated} 个默认字典",
            'data'    => ['created' => $created, 'updated' => $updated],
        ]);
    }

    /** 7 类默认字典 (运维一键 seed) */
    private function defaultDicts(): array
    {
        $now = now();
        return [
            // 维修方式
            ['kind' => 'repair_method', 'code' => 'free_warranty', 'label' => '免费保修', 'color' => 'success', 'sort_order' => 10, 'is_active' => true, 'description' => '质保期内免费维修'],
            ['kind' => 'repair_method', 'code' => 'free_contract', 'label' => '免费合同', 'color' => 'success', 'sort_order' => 20, 'is_active' => true, 'description' => '维保合同覆盖'],
            ['kind' => 'repair_method', 'code' => 'paid_repair',   'label' => '付费维修', 'color' => 'warning', 'sort_order' => 30, 'is_active' => true, 'description' => '过保后收费'],
            ['kind' => 'repair_method', 'code' => 'paid_replace',  'label' => '付费换件', 'color' => 'warning', 'sort_order' => 40, 'is_active' => true, 'description' => '换新零件'],
            ['kind' => 'repair_method', 'code' => 'returned',      'label' => '退回',     'color' => 'info',    'sort_order' => 50, 'is_active' => true, 'description' => '不修退回'],

            // 客户来源
            ['kind' => 'customer_source', 'code' => 'referral',  'label' => '老客介绍', 'sort_order' => 10, 'is_active' => true],
            ['kind' => 'customer_source', 'code' => 'marketing', 'label' => '市场推广', 'sort_order' => 20, 'is_active' => true],
            ['kind' => 'customer_source', 'code' => 'direct',    'label' => '直接到访', 'sort_order' => 30, 'is_active' => true],
            ['kind' => 'customer_source', 'code' => 'partner',   'label' => '合作伙伴', 'sort_order' => 40, 'is_active' => true],
            ['kind' => 'customer_source', 'code' => 'online',    'label' => '线上渠道', 'sort_order' => 50, 'is_active' => true],

            // 设备类型
            ['kind' => 'device_type', 'code' => 'camera',     'label' => '摄像头',     'sort_order' => 10, 'is_active' => true],
            ['kind' => 'device_type', 'code' => 'nvr',        'label' => '录像机',     'sort_order' => 20, 'is_active' => true],
            ['kind' => 'device_type', 'code' => 'switch',     'label' => '交换机',     'sort_order' => 30, 'is_active' => true],
            ['kind' => 'device_type', 'code' => 'access',     'label' => '门禁',       'sort_order' => 40, 'is_active' => true],
            ['kind' => 'device_type', 'code' => 'alarm',      'label' => '报警主机',   'sort_order' => 50, 'is_active' => true],
            ['kind' => 'device_type', 'code' => 'intercom',   'label' => '对讲',       'sort_order' => 60, 'is_active' => true],
            ['kind' => 'device_type', 'code' => 'server',     'label' => '服务器',     'sort_order' => 70, 'is_active' => true],

            // 区域
            ['kind' => 'region', 'code' => 'haishu',  'label' => '海曙区', 'sort_order' => 10, 'is_active' => true],
            ['kind' => 'region', 'code' => 'jiangbei','label' => '江北区', 'sort_order' => 20, 'is_active' => true],
            ['kind' => 'region', 'code' => 'yinzhou', 'label' => '鄞州区', 'sort_order' => 30, 'is_active' => true],
            ['kind' => 'region', 'code' => 'zhenhai', 'label' => '镇海区', 'sort_order' => 40, 'is_active' => true],
            ['kind' => 'region', 'code' => 'beilun',  'label' => '北仑区', 'sort_order' => 50, 'is_active' => true],

            // 故障类型
            ['kind' => 'fault_type', 'code' => 'power',     'label' => '电源故障', 'sort_order' => 10, 'is_active' => true],
            ['kind' => 'fault_type', 'code' => 'network',   'label' => '网络故障', 'sort_order' => 20, 'is_active' => true],
            ['kind' => 'fault_type', 'code' => 'image',     'label' => '图像问题', 'sort_order' => 30, 'is_active' => true],
            ['kind' => 'fault_type', 'code' => 'storage',   'label' => '存储问题', 'sort_order' => 40, 'is_active' => true],
            ['kind' => 'fault_type', 'code' => 'physical',  'label' => '物理损坏', 'sort_order' => 50, 'is_active' => true],
            ['kind' => 'fault_type', 'code' => 'software',  'label' => '软件问题', 'sort_order' => 60, 'is_active' => true],

            // 紧急度
            ['kind' => 'urgency', 'code' => 'low',      'label' => '一般', 'color' => 'info',    'sort_order' => 10, 'is_active' => true],
            ['kind' => 'urgency', 'code' => 'normal',   'label' => '正常', 'color' => 'primary', 'sort_order' => 20, 'is_active' => true, 'is_default' => true],
            ['kind' => 'urgency', 'code' => 'high',     'label' => '紧急', 'color' => 'warning', 'sort_order' => 30, 'is_active' => true],
            ['kind' => 'urgency', 'code' => 'critical', 'label' => '加急', 'color' => 'danger',  'sort_order' => 40, 'is_active' => true],

            // 支付方式
            ['kind' => 'payment_method', 'code' => 'cash',     'label' => '现金',     'sort_order' => 10, 'is_active' => true],
            ['kind' => 'payment_method', 'code' => 'transfer', 'label' => '银行转账', 'sort_order' => 20, 'is_active' => true],
            ['kind' => 'payment_method', 'code' => 'alipay',   'label' => '支付宝',   'sort_order' => 30, 'is_active' => true],
            ['kind' => 'payment_method', 'code' => 'wechat',   'label' => '微信',     'sort_order' => 40, 'is_active' => true],

            // 产品单位
            ['kind' => 'product_unit', 'code' => 'piece',  'label' => '个',  'sort_order' => 10, 'is_active' => true, 'is_default' => true],
            ['kind' => 'product_unit', 'code' => 'set',    'label' => '套',  'sort_order' => 20, 'is_active' => true],
            ['kind' => 'product_unit', 'code' => 'box',    'label' => '箱',  'sort_order' => 30, 'is_active' => true],
            ['kind' => 'product_unit', 'code' => 'meter',  'label' => '米',  'sort_order' => 40, 'is_active' => true],
            ['kind' => 'product_unit', 'code' => 'roll',   'label' => '卷',  'sort_order' => 50, 'is_active' => true],
        ];
    }
}
