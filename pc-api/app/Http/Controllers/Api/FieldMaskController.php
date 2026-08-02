<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\FieldMask;
use App\Models\FieldMask as FieldMaskModel; // V1.2.10 替代 DB::table
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * V0.5.2 - 字段脱敏规则管理 (admin 限定)
 * GET    /api/field-masks
 * POST   /api/field-masks
 * PUT    /api/field-masks/{id}
 * DELETE /api/field-masks/{id}
 * POST   /api/field-masks/flush-cache
 */
class FieldMaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = FieldMaskModel::orderBy('endpoint')->orderBy('id')->get();

        // 按 endpoint 分组给前端
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->endpoint][] = [
                'id'            => $r->id,
                'field'         => $r->field,
                'allowed_roles' => $r->allowed_roles,
                'description'   => $r->description,
                'enabled'       => (bool) $r->enabled,
            ];
        }
        $list = [];
        foreach ($grouped as $endpoint => $items) {
            $list[] = [
                'endpoint'      => $endpoint,
                'allowed_roles' => $items[0]['allowed_roles'] ?? 'admin',
                'items'         => $items,
            ];
        }
        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'      => ['required', 'string', 'max:64'],
            'field'         => ['required', 'string', 'max:64'],
            'allowed_roles' => ['required', 'string', 'max:128'],
            'description'   => ['nullable', 'string', 'max:255'],
            'enabled'       => ['boolean'],
        ]);
        $data['enabled'] = $data['enabled'] ?? true;

        $mask = FieldMaskModel::create($data);
        FieldMask::flushCache();

        return response()->json(['code' => 0, 'data' => ['id' => $mask->id], 'message' => '已添加']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'allowed_roles' => ['sometimes', 'string', 'max:128'],
            'description'   => ['nullable', 'string', 'max:255'],
            'enabled'       => ['boolean'],
        ]);
        $data['updated_at'] = now();
        $affected = FieldMaskModel::where('id', $id)->update($data);
        if (!$affected) {
            return response()->json(['code' => 1002, 'message' => '记录不存在'], 404);
        }
        FieldMask::flushCache();
        return response()->json(['code' => 0, 'message' => '已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $affected = FieldMaskModel::destroy($id);
        if (!$affected) {
            return response()->json(['code' => 1002, 'message' => '记录不存在'], 404);
        }
        FieldMask::flushCache();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function flushCache(): JsonResponse
    {
        FieldMask::flushCache();
        return response()->json(['code' => 0, 'message' => '缓存已清']);
    }

    /**
     * V0.5.4 端点元数据 - 给前端"脱敏测试"用
     * GET /api/field-masks/endpoints
     * 返回: [{endpoint, fields: [], default_allowed_roles: ''}]
     */
    public function endpoints(): JsonResponse
    {
        // 从已存在的 field_masks 表聚合 endpoint 列表
        $rows = FieldMaskModel::select('endpoint', 'allowed_roles')
            ->distinct()
            ->orderBy('endpoint')
            ->get();

        $list = [];
        $seen = [];
        foreach ($rows as $r) {
            if (in_array($r->endpoint, $seen, true)) continue;
            $seen[] = $r->endpoint;
            $fields = FieldMaskModel::where('endpoint', $r->endpoint)
                ->where('enabled', true)
                ->pluck('field')
                ->all();
            $list[] = [
                'endpoint'            => $r->endpoint,
                'fields'              => $fields,
                'default_allowed_roles' => $r->allowed_roles,
            ];
        }
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * POST /api/field-masks/preview
     * V0.5.6 B5 — 批量测试脱敏效果
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'     => 'required|string|max:255',
            'test_data'    => 'required|array|min:1|max:50',
            'as_user_id'   => 'nullable|integer',
        ]);

        $matched = \App\Support\FieldMask::matchModule($data['endpoint']);
        if (!$matched) {
            return response()->json([
                'code' => 422,
                'message' => "端点 {$data['endpoint']} 不在脱敏规则覆盖范围内",
            ], 422);
        }

        $fields = \App\Support\FieldMask::fieldsFor($matched);
        $rules = FieldMaskModel::where('endpoint', $matched)
            ->where('enabled', true)
            ->get(['field', 'allowed_roles', 'description'])
            ->map(fn ($r) => (array) $r)
            ->all();

        if (!$fields || !$rules) {
            return response()->json([
                'code' => 0,
                'data' => [
                    'matched_module' => $matched,
                    'fields'         => $fields,
                    'rules'          => $rules,
                    'note'           => '该 module 没有任何脱敏规则',
                    'original'       => $data['test_data'],
                    'masked'         => $data['test_data'],
                ],
            ]);
        }

        $allowedRoles = \App\Support\FieldMask::allowedRolesFor($matched);
        $asUser = $data['as_user_id'] ?? null;
        $isAllowed = false;
        $userRoles = [];
        if ($asUser) {
            $userRoles = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_type', 'App\\Models\\User')
                ->where('mhr.model_id', $asUser)
                ->where(function ($q) {
                    $q->whereNull('mhr.expires_at')->orWhere('mhr.expires_at', '>', now());
                })
                ->pluck('r.name')
                ->all();
            $isAllowed = (bool) array_intersect($userRoles, $allowedRoles);
        }

        $original = $data['test_data'];
        $masked = [];
        // 抽出字段名 (rules 是 [{field, allowed_roles, description}])
        $fieldNames = array_map(fn ($r) => $r['field'], $rules);
        foreach ($original as $i => $row) {
            $masked[$i] = \App\Support\FieldMask::maskRow($row, $fieldNames);
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'matched_module' => $matched,
                'fields'         => $fields,
                'rules'          => $rules,
                'allowed_roles'  => $allowedRoles,
                'is_allowed'     => $isAllowed,
                'user_roles'     => $userRoles,
                'original'       => $original,
                'masked'         => $masked,
            ],
        ]);
    }
}
