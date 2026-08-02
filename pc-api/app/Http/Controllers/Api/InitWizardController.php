<?php
// V1.2.9 - 新增 /api/system/init-wizard-data
// system 账号初始化向导需要的数据聚合接口
// 一次返回 departments + roles + system_info, 避免前端调 3 个业务接口都 403
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Department;
use Spatie\Permission\Models\Role;
use Illuminate\Http\JsonResponse;

class InitWizardController extends Controller
{
    /**
     * GET /api/system/init-wizard-data
     *
     * system 专属 — 跳过 ensure_business 中间件
     * 一次性返回: 部门列表 + 角色列表 + 系统设置
     */
    public function index(): JsonResponse
    {
        try {
            // 1. 部门列表 (只取 active 状态)
            $departments = Department::where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'parent_id', 'manager_id', 'description'])
                ->map(function ($d) {
                    return [
                        'id'          => $d->id,
                        'name'        => $d->name,
                        'parent_id'   => $d->parent_id,
                        'manager_id'  => $d->manager_id,
                        'description' => $d->description,
                    ];
                })
                ->values()
                ->toArray();

            // 2. 角色列表 (spatie)
            $roles = Role::orderBy('id')
                ->get(['id', 'name', 'guard_name'])
                ->map(function ($r) {
                    $permCount = $r->permissions()->count();
                    return [
                        'id'             => $r->id,
                        'name'           => $r->name,
                        'description'    => $r->name === 'admin' ? '业务管理员 (默认)' : $r->name,
                        'perm_count'     => $permCount,
                    ];
                })
                ->values()
                ->toArray();

            // 3. 系统设置
            $rows = SystemSetting::all();
            $systemInfo = [];
            foreach ($rows as $r) {
                $systemInfo[$r->key] = $this->normalizeValue($r->value);
            }
            $systemInfo['version'] = config('oa.app_version', 'v1.0.0');

            return response()->json([
                'code'    => 0,
                'message' => 'ok',
                'data'    => [
                    'departments' => $departments,
                    'roles'       => $roles,
                    'system_info' => $systemInfo,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('init-wizard-data failed: ' . $e->getMessage());
            return response()->json([
                'code'    => 500,
                'message' => '加载初始化数据失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeValue($v)
    {
        if (is_string($v)) {
            $decoded = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $v;
    }
}
