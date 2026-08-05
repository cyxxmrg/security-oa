<?php
/**
 * V0.5.1 - 字段级脱敏 (L4 授权) — DB 化
 *
 * V0.5.2 升级:
 *  - 脱敏规则从静态 $protected 改为 DB (field_masks 表)
 *  - 启动时从 DB 加载一次到 cache, 后续命中
 *  - admin 可调 /api/field-masks CRUD 改规则 (热更新)
 *  - 找不到 module 时回退到默认规则 (兼容 V0.5.1 老代码)
 */

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FieldMask
{
    /**
     * 默认规则 (DB 没配置时回退) — 兼容 V0.5.1
     * [endpoint_prefix => [field_name, ...]]
     * 注意: 这里没有 allowed_roles, 全部按 privilegedRoles 放行判断
     */
    public static array $fallback = [
        'finance' => [
            'amount', 'received_amount', 'paid_amount', 'remaining_amount',
            'total', 'total_amount', 'price', 'cost', 'balance',
        ],
        'projects' => [
            'budget', 'contract_amount', 'actual_cost', 'revenue',
        ],
        'sales' => [
            'amount', 'commission',
        ],
        'employee' => [
            'salary', 'bank_account', 'id_card',
        ],
    ];

    /**
     * 哪些角色能看 sensitive 字段 (写死, 不走 DB)
     */
    public static array $privilegedRoles = ['admin', 'finance'];

    /**
     * 入口 — apply 入口 (与 V0.5.1 同)
     */
    public static function apply($data, $user, string $endpoint)
    {
        if (!is_array($data) || !$user) {
            return $data;
        }
        $matchedModule = self::matchModule($endpoint);
        if (!$matchedModule) {
            return $data;
        }
        $fields = self::fieldsFor($matchedModule);
        if (!$fields) {
            return $data;
        }

        // 用户角色
        $userRoles = [];
        try {
            $userRoles = $user->roles->pluck('name')->all();
        } catch (\Throwable $e) {
            self::logFailure(__METHOD__, $e);
        }

        // DB 中该 module 的 allowed_roles 配 (默认 'admin')
        $allowedRoles = self::allowedRolesFor($matchedModule);
        $isAllowed = (bool) array_intersect($userRoles, $allowedRoles);
        if ($isAllowed) {
            return $data;
        }

        // 脱敏 (单条 / 列表 / 分页)
        if (isset($data['data']) && is_array($data['data']) && !array_is_list($data)) {
            foreach ($data['data'] as $i => $row) {
                if (is_array($row)) $data['data'][$i] = self::maskRow($row, $fields);
            }
        } elseif (array_is_list($data) || (isset($data[0]) && is_array($data[0]))) {
            foreach ($data as $i => $row) {
                if (is_array($row)) $data[$i] = self::maskRow($row, $fields);
            }
        } elseif (!empty($data) && is_array($data)) {
            $data = self::maskRow($data, $fields);
        }
        return $data;
    }

    /**
     * 拿 module 下的字段列表 (从 cache / DB / fallback)
     */
    public static function fieldsFor(string $module): array
    {
        $all = self::loadAll();
        return $all[$module]['fields'] ?? self::$fallback[$module] ?? [];
    }

    /**
     * 拿 module 下的 allowed_roles
     */
    public static function allowedRolesFor(string $module): array
    {
        $all = self::loadAll();
        $roles = $all[$module]['allowed_roles'] ?? null;
        if (!$roles) {
            return self::$privilegedRoles; // 回退
        }
        return $roles;
    }

    /**
     * 加载全部配置 (5 min cache)
     * 返回: [module => ['fields' => [string, ...], 'allowed_roles' => [string, ...]]]
     *
     * DB schema: 1 行 = 1 个 (module, field) 组合
     *
     * 兼容性: 在 Unit Test 环境 (无 Laravel boot) 用 try/catch 防止 facade 抛错
     */
    public static function loadAll(): array
    {
        try {
            return Cache::remember('field_masks:all', 300, function () {
                $rows = DB::table('field_masks')->where('enabled', true)->get();
                $out = [];
                foreach ($rows as $r) {
                    if (!isset($out[$r->endpoint])) {
                        $out[$r->endpoint] = [
                            'fields' => [],
                            'allowed_roles' => array_filter(explode(',', $r->allowed_roles)),
                        ];
                    }
                    $out[$r->endpoint]['fields'][] = $r->field;
                }
                return $out;
            });
        } catch (\Throwable $e) {
            self::logFailure(__METHOD__, $e);
            // facade 没初始化 (unit test) → 返回空, 走 fallback
            return [];
        }
    }

    /**
     * 清缓存 (改完配置后调)
     */
    public static function flushCache(): void
    {
        try {
            Cache::forget('field_masks:all');
        } catch (\Throwable $e) {
            self::logFailure(__METHOD__, $e);
            // ignore
        }
    }

    /**
     * 端点匹配 — 与 V0.5.1 同
     */
    public static function matchModule(string $endpoint): ?string
    {
        $endpoint = ltrim($endpoint, '/');
        if (str_starts_with($endpoint, 'api/')) {
            $endpoint = substr($endpoint, 4);
        }
        $parts = explode('/', $endpoint);
        $first = $parts[0] ?? '';
        // 优先 DB 已知 module
        $all = self::loadAll();
        if (isset($all[$first])) return $first;
        if (isset(self::$fallback[$first])) return $first;
        // 短路径兼容
        $shortMap = [
            'receivables'      => 'finance',
            'payables'         => 'finance',
            'expense-claims'   => 'finance',
            'expenses'         => 'finance',
            'finance-accounts' => 'finance',
            'contracts'        => 'sales',
            'opportunities'    => 'sales',
        ];
        return $shortMap[$first] ?? null;
    }

    public static function maskRow(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = '***';
            }
        }
        return $row;
    }

    private static function logFailure(string $method, \Throwable $e): void
    {
        try {
            Log::error($method . ': catch', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
        } catch (\Throwable) {
            // Pure unit tests intentionally run without a Laravel facade root.
        }
    }
}
