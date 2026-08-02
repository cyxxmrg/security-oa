<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 系统设置 — 集中管理：
 *  - 系统名称 / 简称 / 版权 / 备案号 / 公告 / 联系邮箱
 *  - 审批流程模板 (data_management: 替换原前端 hardcoded)
 *  - admin 一键清理业务数据
 */
class SystemSettingsController extends Controller
{
    private function ensureDestructiveOperationAllowed(Request $request, string $operation): ?JsonResponse
    {
        $host = $request->getHost();
        $isPrivateHost = in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || str_starts_with($host, '10.')
            || str_starts_with($host, '192.168.')
            || preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host);

        $allowed = (bool) config('oa.allow_destructive_reset', false) // V1.2.10 走 config
            || app()->environment(['local', 'testing', 'staging'])
            || $isPrivateHost;

        if ($allowed) {
            Log::warning('destructive operation allowed', [
                'operation' => $operation,
                'user_id' => $request->user()?->id,
                'host' => $host,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
            return null;
        }

        Log::warning('destructive operation blocked', [
            'operation' => $operation,
            'user_id' => $request->user()?->id,
            'host' => $host,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
        ]);

        return response()->json([
            'code' => 1004,
            'message' => 'Destructive reset is disabled in this environment. Enable OA_ALLOW_DESTRUCTIVE_RESET only in a controlled test environment.',
        ], 403);
    }

    private function quoteTableIdentifier(string $table, array $allowedTables): string
    {
        if (!in_array($table, $allowedTables, true) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name: ' . $table);
        }

        return '"' . str_replace('"', '""', $table) . '"';
    }

    /**
     * GET /api/settings — 读全部设置
     * 注入应用版本号 (从 config 取, 不存 DB — 单一真相源 config/oa.php)
     */
    public function index(): JsonResponse
    {
        $rows = SystemSetting::all();
        $data = [];
        foreach ($rows as $r) {
            $data[$r->key] = $this->normalize($r->value);
        }
        $data['version'] = config('oa.app_version', 'v1.0.0');
        return response()->json([
            'code' => 0,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/settings/super-admin — 读取系统超级管理员信息
     * V1.2.9c: has_password 只看 password 非空 (不要被 must_change_password 影响)
     *         system 改密码后, welcome 页不能误报 "未设置密码"
     */
    public function getSuperAdmin(): JsonResponse
    {
        $systemUser = \App\Models\User::where('username', 'system')->first();
        // V1.2.9c: 只要 password 字段有值就算"已设过" (不管 must_change_password, 大哥可能不想改密)
        $hasPassword = $systemUser && !empty($systemUser->password);

        return response()->json([
            'code' => 0,
            'data' => [
                'exists'             => (bool) $systemUser,
                'username'           => $systemUser?->username ?? 'system',
                'display_name'       => $systemUser?->name ?? '系统超级管理员',
                'is_super_admin'     => $systemUser?->is_system === true,
                'has_password'       => $hasPassword,
                'must_set_password'  => $systemUser?->must_change_password ?? false,
                'can_reset_password' => true,
                'initialized_at'     => $systemUser?->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/system/init-wizard-data — V1.2.10: 向导页初始化数据
     * 确保有默认部门 + 业务管理员角色, 返回 departments + roles + system_info
     */
    public function initWizardData(): JsonResponse
    {
        // 1. 确保有默认部门
        $dept = DB::table('departments')->first();
        if (!$dept) {
            $deptId = DB::table('departments')->insertGetId([
                'name' => '总经办', 'parent_id' => null, 'sort_order' => 0, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $dept = DB::table('departments')->find($deptId);
        }

        // 2. 确保有业务管理员角色 (spatie admin, guard=web)
        $role = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->first();
        if (!$role) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'admin', 'guard_name' => 'web', 'description' => '业务管理员',
                'is_system' => false, 'color' => '#409EFF',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $role = DB::table('roles')->find($roleId);
        }

        $departments = DB::table('departments')->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $roles = DB::table('roles')->where('guard_name', 'web')->orderBy('id')->get(['id', 'name', 'description']);

        $sysInfo = [
            'app_version' => config('oa.app_version', '1.0.0'),
            'php_version' => PHP_VERSION,
            'initialized' => (bool) DB::table('system_settings')->where('key', 'system_initialized')->value('value'),
        ];

        return response()->json(['code' => 0, 'data' => [
            'departments' => $departments,
            'roles' => $roles,
            'system_info' => $sysInfo,
        ]]);
    }

    /**
     * POST /api/system/mark-initialized — V1.2: 标记初始化完成
     * 创新超级管理员 system 账号（不在组织权限中 — 超管独立）
     * 密码由首次登录者自行设置
     */
    public function markInitialized(Request $request): JsonResponse
    {
        try {
            \DB::table('system_settings')->updateOrInsert(
                ['key' => 'system_initialized'],
                [
                    'value'      => json_encode(true),
                    'updated_at' => now(),
                ]
            );

            $systemUser = \App\Models\User::where('username', 'system')->first();
            if (!$systemUser) {
                $systemUser = \App\Models\User::create([
                    'username'   => 'system',
                    'name'       => '系统超级管理员',
                    'email'      => 'system@local',
                    'phone'      => '13800000000',  // 必填
                    'is_system'  => true,
                    'is_admin'   => false,
                    'password'   => null,
                    'status'     => 'active',
                    'must_change_password' => true,
                ]);
            } else {
                $systemUser->update([
                    'is_system' => true,
                    'name'      => '系统超级管理员',
                    'must_change_password' => true,
                ]);
            }

            \DB::table('system_logs')->insert([
                'user_id'     => $request->user()?->id,
                'type'        => 'system',
                'module'      => 'init',
                'action'      => 'wizard_completed',
                'description' => 'system 超级管理员账号已创建/升级，密码由首次登录者自行设置',
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return response()->json([
                'code'    => 0,
                'message' => '系统初始化已完成，system 超级管理员已就绪',
                'data'    => [
                    'system_user_id'    => $systemUser->id,
                    'username'          => 'system',
                    'must_set_password' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('markInitialized failed: ' . $e->getMessage());
            return response()->json([
                'code'    => 500,
                'message' => '标记失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/system/super-admin/set-password — system 首次设置密码
     * body: { new_password, confirm_password }
     */
    public function setSuperAdminPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'new_password'     => 'required|string|min:8|max:128',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        $user = $request->user();
        if (!$user || $user->username !== 'system') {
            return response()->json([
                'code'    => 403,
                'message' => '仅 system 超级管理员可设置密码',
            ], 403);
        }
        if ($user->is_system !== true) {
            return response()->json([
                'code'    => 403,
                'message' => '当前账号不是 system 超级管理员',
            ], 403);
        }
        // V1.2.4i: 放宽"已有密码"拦截 — 只要 must_change=true 就能改, 不管之前是否设过
        // (修复: 之前大哥用 setSuperAdminPassword 设过, 但 must_change=true 时第二次调会被 422 拦截)
        if (!empty($user->password) && !($user->must_change_password ?? false)) {
            return response()->json([
                'code'    => 1004,
                'message' => 'system 账号已有密码, 请使用修改密码功能',
            ], 422);
        }

        $user->update([
            'password'              => \Hash::make($data['new_password']),
            'must_change_password'  => false,
            'password_set_at'       => now(),
        ]);

        return response()->json([
            'code'    => 0,
            'message' => '超级管理员密码已设置',
        ]);
    }

    /**
     * PG JSONB 经 PDO 返回来可能是：
     *   - 已经是 array (数值/对象)
     *   - 字符串 '"foo"' (带引号)
     *   - 字符串 'foo' (无引号 — bug 驱动)
     * 统一去掉首尾的 JSON 字符串引号
     */
    private function normalize($v)
    {
        if ($v === null) return null;
        if (is_array($v) || is_bool($v) || is_int($v) || is_float($v)) return $v;
        $s = (string) $v;
        $decoded = json_decode($s, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // 解出来是字符串就直接给；是 array/数字/布尔已递归处理
            return $decoded;
        }
        return $s;
    }

    /**
     * PUT /api/settings — 批量更新
     * body: { system_name: 'xxx', copyright: '...', ... }
     */
    public function update(Request $request): JsonResponse
    {
        $allowed = [
            'system_name'         => 'nullable|string|max:64',
            'system_short_name'   => 'nullable|string|max:32',
            'copyright'           => 'nullable|string|max:255',
            'copyright_url'       => 'nullable|string|max:255',
            'announcement'        => 'nullable|string|max:2000',
            'icp'                 => 'nullable|string|max:64',
            'contact_email'       => 'nullable|email|max:128',
            // 闲置超时配置
            'idle_enabled'        => 'nullable|boolean',
            'idle_timeout_minutes'=> 'nullable|integer|min:1|max:1440',  // 最多 24 小时
            'idle_warning_seconds'=> 'nullable|integer|min:0|max:600',   // 最多 10 分钟
        ];
        $data = $request->validate($allowed);

        // 闲置配置业务校验:警告秒数不能 >= 超时秒数
        $toMin = isset($data['idle_timeout_minutes']) ? (int) $data['idle_timeout_minutes'] : null;
        $toSec = isset($data['idle_warning_seconds']) ? (int) $data['idle_warning_seconds'] : null;
        if ($toMin !== null && $toSec !== null && $toSec >= $toMin * 60) {
            return response()->json([
                'code'    => 1001,
                'message' => '提前提示秒数不能大于等于总超时时间',
            ], 422);
        }

        $userId = $request->user()?->id;
        $updated = 0;
        foreach ($data as $key => $val) {
            // null 表示不更新；空串表示清空
            $jsonVal = json_encode($val, JSON_UNESCAPED_UNICODE);
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value'       => $jsonVal,
                    'updated_at'  => now(),
                    'updated_by'  => $userId,
                ]
            );
            $updated++;
        }

        // 重新读一次返回最新值
        $latest = [];
        foreach (SystemSetting::all() as $r) {
            $latest[$r->key] = $this->normalize($r->value);
        }
        return response()->json([
            'code'    => 0,
            'message' => "已更新 {$updated} 项设置",
            'data'    => $latest,
        ]);
    }

    /**
     * GET /api/settings/idle-config — 读取闲置超时配置
     * 前端 useIdleTimer 启动时调用
     * 返回: { enabled, timeout_minutes, warning_seconds, timeout_ms, warning_ms }
     */
    public function getIdleConfig(): JsonResponse
    {
        $enabled = SystemSetting::get('idle_enabled', true);
        $toMin   = SystemSetting::get('idle_timeout_minutes', 30);
        $toSec   = SystemSetting::get('idle_warning_seconds', 60);

        // 强转 int(从 DB 读出可能是 int 或 string)
        $enabled = (bool) $enabled;
        $toMin   = max(1, min(1440, (int) $toMin));
        $toSec   = max(0, min(600, (int) $toSec));
        // 警告秒数不能 >= 总秒数,自动夹一下
        if ($toSec >= $toMin * 60) {
            $toSec = max(0, $toMin * 60 - 1);
        }

        return response()->json([
            'code'    => 0,
            'data'    => [
                'enabled'         => $enabled,
                'timeout_minutes' => $toMin,
                'warning_seconds' => $toSec,
                'timeout_ms'      => $toMin * 60 * 1000,
                'warning_ms'      => $toSec * 1000,
            ],
        ]);
    }

    /**
     * GET /api/settings/port — 读端口配置
     * 返回: { port: number, default: number }
     */
    public function getPortConfig(): JsonResponse
    {
        $port = SystemSetting::get('custom_web_port', 80);
        // 兜底：DB 里的值可能是字符串/数组，统一强转 int
        if (!is_int($port) && !is_float($port)) {
            $port = (int) $port;
        }
        return response()->json([
            'code'    => 0,
            'data'    => [
                'port'    => $port,
                'default' => 80,
            ],
        ]);
    }

    /**
     * PUT /api/settings/port — 改端口配置
     * body: { port: 9000 }  — 1-65535 整数
     * ⚠ 改完需重启 web 服务（PHP-FPM + nginx/apache）才能生效
     */
    public function updatePortConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'port' => 'required|integer|min:1|max:65535',
        ]);

        $userId = $request->user()?->id;
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'custom_web_port'],
            [
                'value'      => json_encode((int) $data['port'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'updated_by' => $userId,
            ]
        );

        return response()->json([
            'code'    => 0,
            'message' => '端口配置已保存，需重启 web 服务后生效',
            'data'    => [
                'port'    => (int) $data['port'],
                'default' => 80,
            ],
        ]);
    }

    /**
     * POST /api/admin/wipe-data — admin 一键清空业务数据
     * body: { password, confirm_phrase }  — 二次确认密码
     * 保留: users (含 admin), roles, permissions, system_settings, departments, positions, skill_tags
     * 清理: 业务表（项目/客户/工单/车辆/库存/财务/审批/报销/考勤/网盘/知识库/消息...）
     */
    public function wipeData(Request $request): JsonResponse
    {
        if ($blocked = $this->ensureDestructiveOperationAllowed($request, 'wipeData')) {
            return $blocked;
        }

        $data = $request->validate([
            'password'      => 'required|string',
            'confirm_phrase'=> 'required|string|in:确认清空', // 防误操作
        ]);

        $user = $request->user();
        // V1.2: 一键清理业务数据仅 system 账号可执行
        // 旧逻辑: $user->id !== 1 → V1.2 后 system 用户 id 不是 1，需要改 is_system 标志位
        if (!$user || $user->is_system !== true) {
            return response()->json(['code' => 1003, 'message' => '仅 system 账号可执行此操作（administrator 无权）'], 403);
        }
        // 二次密码校验
        if (!\Hash::check($data['password'], $user->password)) {
            return response()->json(['code' => 1001, 'message' => '管理员密码不正确'], 422);
        }

        // 剩余独立表（不会被 CASCADE 覆盖的），按 FK 依赖倒序排列
        $standalone = [
            // 库存子表先于父表
            'stock_records', 'device_serial_numbers', 'inventory_items',
            'inventory_categories', 'warehouses',
            // 知识库
            'knowledge_articles', 'knowledge_categories',
            // 消息/日志
            'notifications', 'system_logs',
            // 入职/离职/技能/证书
            'employee_onboardings', 'employee_resignations',
            'employee_profiles', 'certificates',
            // 排班
            'shift_group_members', 'shift_groups',
            'schedules', 'schedule_shift_assignments',
            // 销售
            'sales_products', 'sales_quotes',
            // 审批
            'approval_records_v2', 'approval_records',
            'approval_templates',
            // 维保/网盘
            'maintenance_contracts',
            'disk_files', 'disk_folders',
        ];

        $deleted = [];

        // Phase 1: TRUNCATE CASCADE 清空核心业务表及其 FK 子表
        try {
            DB::statement('TRUNCATE TABLE projects, customers, service_orders, vehicles, expense_claims, fuel_cards RESTART IDENTITY CASCADE');
            $deleted['_cascade'] = 'projects+customers+service_orders+vehicles+expense_claims+fuel_cards (cascaded all FK children)';
        } catch (\Throwable $e1) {
            \Log::error(__METHOD__ . ': catch (TRUNCATE)', ['msg' => $e1->getMessage(), 'file' => $e1->getFile() . ':' . $e1->getLine()]);
            return response()->json([
                'code'    => 1002,
                'message' => 'TRUNCATE 清空失败: ' . $e1->getMessage(),
            ], 500);
        }

        // Phase 2: 逐表清理剩余独立表（每个表独立 try，不中断整体）
        foreach ($standalone as $t) {
            try {
                // 用独立事务避免前一个表的问题影响后续表
                DB::statement('DELETE FROM ' . $this->quoteTableIdentifier($t, $standalone));
                $deleted[$t] = 1;
            } catch (\Illuminate\Database\QueryException $qe) {
                \Log::error(__METHOD__ . ': catch (QueryException)', ['msg' => $qe->getMessage(), 'file' => $qe->getFile() . ':' . $qe->getLine()]);
                if (str_contains($qe->getMessage(), 'does not exist') || str_contains($qe->getMessage(), '42P01')) {
                    $deleted[$t] = 0;
                } else {
                    $deleted[$t] = 'ERR: ' . $qe->getMessage();
                }
            } catch (\Throwable $e2) {
                \Log::error(__METHOD__ . ': catch (Throwable)', ['msg' => $e2->getMessage(), 'file' => $e2->getFile() . ':' . $e2->getLine()]);
                $deleted[$t] = 'ERR: ' . $e2->getMessage();
            }
        }

        return response()->json([
            'code'    => 0,
            'message' => '业务数据已清空，admin / 部门 / 角色 / 权限 / 设置 已保留',
            'data'    => $deleted,
        ]);
    }

    /**
     * POST /api/system/wipe-all — admin 一键清空所有数据（含 admin、角色、权限、设置）
     * 用于首次初始化或彻底重置系统
     * 清空后系统将进入 0 数据状态，需重新执行初始化向导
     *
     * body: { password, confirm_phrase: '确认清空所有数据' }
     */
    public function wipeAll(Request $request): JsonResponse
    {
        if ($blocked = $this->ensureDestructiveOperationAllowed($request, 'wipeAll')) {
            return $blocked;
        }

        // V1.0.4: 一键清空所有数据 — 二次确认短语作为唯一凭证
        // 不再要求输密码, 因为可能系统已经密码丢失, 这是终极恢复手段
        $data = $request->validate([
            'password'       => 'required|string',
            'confirm_phrase' => 'required|string|in:确认清空所有数据',
        ]);

        $user = $request->user();
        if (!$user || !$user->is_system) {
            return response()->json([
                'code'    => 1003,
                'message' => '仅 system 超级管理员可执行此操作',
            ], 403);
        }
        if (empty($user->password) || !\Hash::check($data['password'], $user->password)) {
            return response()->json(['code' => 1001, 'message' => '管理员密码不正确'], 422);
        }

        $deleted = [];

        // 全部表（包含系统表）
        // V1.2.10: 动态获取所有业务表名, TRUNCATE 除了保留表之外的全部
        $keepTables = [
            'migrations', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
            'cache', 'cache_locks', 'horizon_jobs', 'horizon_workloads',
            'horizon_recent_jobs', 'horizon_pending_jobs', 'horizon_completed_jobs',
            'personal_access_tokens', 'password_reset_tokens',
            'system_settings', 'system_dict', 'shifts',
        ];
        // V1.3.1: 用 PG 原生查询替代 Doctrine DBAL (doctrine 未安装)
        try {
            $rows = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
            $allBusinessTables = array_map(fn($r) => $r->tablename, $rows);
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': list tables failed', ['msg' => $e->getMessage()]);
            return response()->json(['code' => 1002, 'message' => '获取表名失败: ' . $e->getMessage()], 500);
        }
        $tablesToTruncate = array_filter($allBusinessTables, fn($t) => !in_array($t, $keepTables));
        $allTables = array_values($tablesToTruncate);

        // Phase 1: TRUNCATE CASCADE 清空所有表
        try {
            DB::statement('TRUNCATE TABLE projects, customers, service_orders, vehicles, expense_claims, fuel_cards, users, roles, permissions, departments, positions, system_settings RESTART IDENTITY CASCADE');
            $deleted['_cascade'] = 'all core tables cascaded';
        } catch (\Throwable $e1) {
            \Log::error(__METHOD__ . ': catch (TRUNCATE)', ['msg' => $e1->getMessage(), 'file' => $e1->getFile() . ':' . $e1->getLine()]);
            return response()->json([
                'code'    => 1002,
                'message' => 'TRUNCATE 清空失败: ' . $e1->getMessage(),
            ], 500);
        }

        // Phase 2: 逐表清理剩余表
        foreach ($allTables as $t) {
            try {
                DB::statement('DELETE FROM ' . $this->quoteTableIdentifier($t, $allTables));
                $deleted[$t] = 1;
            } catch (\Illuminate\Database\QueryException $qe) {
                if (str_contains($qe->getMessage(), 'does not exist') || str_contains($qe->getMessage(), '42P01')) {
                    $deleted[$t] = 0;
                } else {
                    $deleted[$t] = 'ERR: ' . $qe->getMessage();
                }
            } catch (\Throwable $e2) {
                $deleted[$t] = 'ERR: ' . $e2->getMessage();
            }
        }

        // Phase 3: 重建 system 超级管理员账号（password=null, must_change_password=true）
        // 目的: 让 system 账号仍能登录，但首次登录必须强制设置密码 → 重新进入初始化向导
        try {
            $systemUser = \App\Models\User::create([
                'username'             => 'system',
                'name'                 => '系统超级管理员',
                'email'                => 'system@local',
                'phone'                => '13800000000',  // 必填, 随便填个
                'is_system'            => true,
                'is_admin'             => false,
                'password'             => null,
                'status'               => 'active',
                'user_type'            => 'system',
                'must_change_password' => true,
            ]);
            $deleted['_recreated_system_user'] = $systemUser->id;

            // 标记 system_initialized=false（让前端登录后跳向导）
            \DB::table('system_settings')->updateOrInsert(
                ['key' => 'system_initialized'],
                [
                    'value'      => json_encode(false),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e3) {
            \Log::error(__METHOD__ . ': recreate system user failed', ['msg' => $e3->getMessage()]);
            $deleted['_recreated_system_user'] = 'ERR: ' . $e3->getMessage();
        }

        // Phase 4: 自动重建"种子数据", 让 Wizard 不会卡住
        //  - 1 个默认部门"总经办" (供 Wizard Step 1 选部门)
        //  - 34 个业务权限 (admin 角色绑定, 业务管理员可访问业务)
        //  - 4 个 system 独占权限 (License授权/系统状态/字典/一键清除),  admin 角色**不绑**
        //  - 1 个默认业务管理员角色"admin" (授予 34 个业务权限)
        try {
            // 1) 默认部门 (列: name, sort_order, status)
            $deptId = \DB::table('departments')->insertGetId([
                'name'       => '总经办',
                'sort_order' => 0,
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $deleted['_seed_dept'] = $deptId;

            // 2) 业务权限 (admin 角色可绑, 给业务管理员用)
            $businessModules = [
                // 业务模块
                'project' => '项目管理',
                'service' => '维修中心',
                'customer' => '客户管理',
                'vehicle' => '车辆管理',
                'employee' => '员工管理',
                'attendance' => '考勤管理',
                'expense' => '报销管理',
                'finance' => '财务管理',
                'inventory' => '库存管理',
                'purchase' => '采购管理',
                'sales' => '销售管理',
                'knowledge' => '知识库',
                'disk' => '企业网盘',
                'message' => '消息中心',
                'approval' => '审批中心',
                'maintenance' => '维保合同',
                'schedule' => '排班管理',
                // 业务操作
                'report' => '报表统计',
                'dashboard' => '仪表盘',
                'profile' => '个人中心',
                'settings' => '系统设置',
                'system_log' => '系统日志',
                'data_export' => '数据导出',
                'data_import' => '数据导入',
                'backup' => '数据备份',
                'disk_admin' => '网盘管理',
                // 业务主数据
                'supplier' => '供应商',
                'product' => '产品',
                'contract' => '合同',
                'customer_pool' => '客户公海',
                'tag' => '标签',
                // 业务子项
                'workflow' => '工作流',
                'notification' => '通知',
                'comment' => '评论',
                'attachment' => '附件',
                'task' => '任务',
                'checkin' => '打卡',
                'leave' => '请假',
            ];
            $businessPermIds = [];
            foreach ($businessModules as $key => $label) {
                $businessPermIds[] = \DB::table('permissions')->insertGetId([
                    'name'         => $key,
                    'display_name' => $label,
                    'guard_name'   => 'web',
                    'module'       => $key,
                    'sort_order'   => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
            $deleted['_seed_business_perms'] = count($businessPermIds);

            // 2b) system 独占的 4 个权限 (admin 角色**不绑**)
            //   跟 Welcome.vue 的 3 个卡片 (数据字典/系统监控/License) + 一键清除对应
            $systemOnlyModules = [
                'system.license' => 'License授权',     // Welcome.vue 卡片 3: License 激活
                'system.status'  => '系统状态',        // Welcome.vue 卡片 2: 系统监控 (后端 admin/monitor)
                'system.dict'    => '字典',           // Welcome.vue 卡片 1: 数据字典
                'system.wipe'    => '一键清除系统数据', // Welcome.vue 底部危险区
            ];
            $systemOnlyPermIds = [];
            foreach ($systemOnlyModules as $key => $label) {
                $systemOnlyPermIds[] = \DB::table('permissions')->insertGetId([
                    'name'         => $key,
                    'display_name' => $label,
                    'guard_name'   => 'web',
                    'module'       => 'system',  // 全部分到 system 模块
                    'sort_order'   => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
            $deleted['_seed_system_only_perms'] = count($systemOnlyPermIds);
            $deleted['_total_perms'] = count($businessPermIds) + count($systemOnlyPermIds);

            // 3) 默认业务管理员角色 (列: name, guard_name, description, is_system)
            $adminRoleId = \DB::table('roles')->insertGetId([
                'name'       => 'admin',
                'guard_name' => 'web',
                'description'=> '业务管理员 (默认)',
                'is_system'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4) admin 角色 → 只绑 34 个业务权限, **不绑** 4 个 system 独占权限
            // (V1.2.9e: 大哥要求, 业务管理员不应有 License/系统状态/字典/一键清除 权限)
            $rows = [];
            foreach ($businessPermIds as $pid) {
                $rows[] = [
                    'role_id'       => $adminRoleId,
                    'permission_id' => $pid,
                ];
            }
            \DB::table('permission_role')->insert($rows);
            $deleted['_seed_admin_role'] = $adminRoleId;
            $deleted['_admin_role_perms'] = count($rows);

            // 5) V1.2.4v: 系统默认班次 "正常班" (08:00-17:00, is_default=true, 不可删)
            //   保证新员工入职自动排班可用
            $shiftId = \DB::table('shifts')->insertGetId([
                'name'         => '正常班',
                'code'         => 'day',
                'start_time'   => '08:00:00',
                'end_time'     => '17:00:00',
                'late_threshold_minutes'        => 5,
                'early_leave_threshold_minutes' => 5,
                'work_hours'   => 8.0,
                'color'        => '#0C447C',
                'is_overnight' => false,
                'is_active'    => true,
                'is_default'   => true,
                'sort_order'   => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $deleted['_seed_default_shift'] = $shiftId;
        } catch (\Throwable $e4) {
            \Log::error(__METHOD__ . ': seed data failed', ['msg' => $e4->getMessage(), 'file' => $e4->getFile() . ':' . $e4->getLine()]);
            $deleted['_seed'] = 'ERR: ' . $e4->getMessage();
        }

        CacheHelper::flushTag('dashboard');
        \Illuminate\Support\Facades\Cache::flush(); // V1.2.10: 清所有 Redis 缓存 (看板/配置/权限等)

        // V1.2.10: 刷新所有物化视图 (清除数据后 MV 还保留旧数据, 看板会显示脏数据)
        $mvs = ['mv_customer_rfm', 'mv_finance_pnl', 'mv_inventory_aging', 'mv_project_health', 'mv_revenue_monthly', 'mv_sales_funnel'];
        foreach ($mvs as $mv) {
            try { \DB::statement("REFRESH MATERIALIZED VIEW {$mv}"); } catch (\Throwable $e) {
                \Log::warning("REFRESH MV {$mv} failed: " . $e->getMessage());
            }
        }
        $deleted['_mv_refreshed'] = count($mvs);

        return response()->json([
            'code'    => 0,
            'message' => '所有数据已清空, system 账号已重建, 已自动建 1 个默认部门 + 34 业务权限 + 4 system 独占权限 + 1 个业务管理员角色(admin 绑 34 业务权限) + 1 个默认班次, 可直接进入初始化向导',
            'data'    => $deleted,
        ]);
    }

    /**
     * POST /api/system/reset-user-password — system 首次设置密码
     * 用于首次登录强制设置密码流程
     *
     * body: { new_password, confirm_password }
     */
    public function resetUserPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'new_password'     => 'required|string|min:8|max:128',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'code'    => 401,
                'message' => '未登录',
            ], 401);
        }

        $user->update([
            'password'              => \Hash::make($data['new_password']),
            'must_change_password'  => false,
            'password_set_at'       => now(),
        ]);

        return response()->json([
            'code'    => 0,
            'message' => '密码已设置，下次登录请使用新密码',
        ]);
    }

    /**
     * POST /api/system/mark-as-system — 标记用户为 system 超级管理员
     * 仅 system 账号可调用
     *
     * body: { user_id, is_system: true }
     */
    public function markAsSystem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'is_system'=> 'required|boolean',
        ]);

        $user = $request->user();
        if (!$user || !$user->is_system) {
            return response()->json([
                'code'    => 403,
                'message' => '仅 system 超级管理员可调用',
            ], 403);
        }

        $target = \App\Models\User::find($data['user_id']);
        if (!$target) {
            return response()->json([
                'code'    => 404,
                'message' => '目标用户不存在',
            ], 404);
        }

        $target->update([
            'is_system' => $data['is_system'],
        ]);

        return response()->json([
            'code'    => 0,
            'message' => $data['is_system'] ? '已标记为 system 超级管理员' : '已取消 system 超级管理员标记',
            'data'    => [
                'user_id'  => $target->id,
                'username' => $target->username,
                'is_system'=> $target->is_system,
            ],
        ]);
    }

    /**
     * POST /api/system/business-admin — 创建或重置业务管理员账号
     * V1.2.4: Wizard 第 1 步使用 — system 账号初始化系统时建业务管理员
     * 也可后续重置已存在业务管理员的密码
     *
     * body: {
     *   mode: 'create' | 'reset_password',   // 必填
     *   // create 模式必填
     *   username: 'admin',                  // 唯一, 仅允许 business 类型
     *   name:     '超级管理员',
     *   password: 'Admin@2026',              // 8-32 位
     *   phone:    '13800000001',             // 可选但 phone NOT NULL, 必传
     *   email:    'admin@local',             // 可选
     *   department_id: 1,                   // 可选
     *   position_id:   null,                 // 可选
     *   role_id:       1,                    // 可选, spatie 角色
     *   // reset_password 模式必填
     *   user_id: 7,                          // 要重置密码的业务管理员 ID
     *   new_password: 'NewPwd@2026',         // 8-32 位
     * }
     */
    public function businessAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode'         => 'required|in:create,reset_password',
            // create
            'username'     => 'required_if:mode,create|string|max:64',
            'name'         => 'required_if:mode,create|string|max:50',
            'password'     => 'required_if:mode,create|string|min:8|max:32',
            'phone'        => 'required_if:mode,create|string|max:20',
            'email'        => 'nullable|email|max:100',
            'department_id'=> 'nullable|exists:departments,id',
            'position_id'  => 'nullable|exists:positions,id',
            'role_id'      => 'nullable|exists:roles,id',
            // reset_password
            'user_id'      => 'required_if:mode,reset_password|integer|exists:users,id',
            'new_password' => 'required_if:mode,reset_password|string|min:8|max:32',
        ]);

        $user = $request->user();
        if (!$user || $user->is_system !== true) {
            return response()->json([
                'code'    => 403,
                'message' => '仅 system 超级管理员可调用此接口',
            ], 403);
        }

        if ($data['mode'] === 'create') {
            // 检查 username 唯一
            if (\App\Models\User::where('username', $data['username'])->exists()) {
                return response()->json([
                    'code'    => 1001,
                    'message' => "用户名 {$data['username']} 已存在, 请换一个",
                ], 422);
            }
            // 检查 phone 唯一
            if (\App\Models\User::where('phone', $data['phone'])->exists()) {
                return response()->json([
                    'code'    => 1001,
                    'message' => "手机号 {$data['phone']} 已被使用",
                ], 422);
            }

            $newUser = \App\Models\User::create([
                'username'             => $data['username'],
                'name'                 => $data['name'],
                'phone'                => $data['phone'],
                'email'                => $data['email'] ?? ($data['username'] . '@local'),
                'password'             => $data['password'],  // User 模型 casts 自动 bcrypt
                'is_system'            => false,  // 业务管理员, 不是 system
                'is_admin'             => ($data['role_id'] ?? null) ? false : true,  // 给角色 = 非裸 admin
                'status'               => 'active',
                'user_type'            => 'business',
                'must_change_password' => true,  // 首次登录强制改密
                'department_id'        => $data['department_id'] ?? null,
                'position_id'          => $data['position_id'] ?? null,
            ]);

            // 绑定 spatie 角色
            if (!empty($data['role_id'])) {
                $role = \Spatie\Permission\Models\Role::find($data['role_id']);
                if ($role) {
                    $newUser->assignRole($role);
                }
            }

            \DB::table('system_logs')->insert([
                'user_id'     => $user->id,
                'type'        => 'system',
                'module'      => 'init',
                'action'      => 'create_business_admin',
                'description' => "system 账号创建业务管理员: {$newUser->username} (id={$newUser->id})",
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return response()->json([
                'code'    => 0,
                'message' => "业务管理员 {$newUser->username} 创建成功, 首次登录会被强制改密",
                'data'    => [
                    'user_id'  => $newUser->id,
                    'username' => $newUser->username,
                    'name'     => $newUser->name,
                    'user_type'=> 'business',
                    'is_system'=> false,
                    'must_change_password' => true,
                ],
            ]);
        }

        // reset_password 模式
        $target = \App\Models\User::find($data['user_id']);
        if (!$target) {
            return response()->json([
                'code'    => 404,
                'message' => '目标用户不存在',
            ], 404);
        }
        // 不能重置 system 账号的密码
        if ($target->is_system === true || $target->username === 'system') {
            return response()->json([
                'code'    => 1002,
                'message' => '不能通过此接口重置 system 超级管理员密码, 请用 /api/system/super-admin/set-password',
            ], 422);
        }
        // 业务用户校验
        if ($target->user_type === 'system') {
            return response()->json([
                'code'    => 1002,
                'message' => '目标用户是 system 类型, 不允许通过此接口重置',
            ], 422);
        }

        $target->update([
            'password'              => $data['new_password'],  // User 模型 casts 自动 bcrypt
            'must_change_password'  => true,  // 强制下次登录改密
        ]);

        // 删掉该用户的所有 Sanctum token (强制下线)
        $target->tokens()->delete();

        \DB::table('system_logs')->insert([
            'user_id'     => $user->id,
            'type'        => 'system',
            'module'      => 'init',
            'action'      => 'reset_business_admin_password',
            'description' => "system 账号重置业务管理员密码: {$target->username} (id={$target->id})",
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'code'    => 0,
            'message' => "业务管理员 {$target->username} 密码已重置, 下次登录会被强制改密",
            'data'    => [
                'user_id'  => $target->id,
                'username' => $target->username,
                'must_change_password' => true,
            ],
        ]);
    }

    /**
     * V1.2.9 BUG FIX: system 账号拉部门列表专用端点
     * 原本 Welcome.vue 调 /api/departments 会被 EnsureBusinessUser 中间件拦 403
     * 这里 system 也能拿到完整部门树
     */
    public function systemDepartments(): JsonResponse
    {
        $user = request()->user();
        if (!$user || $user->is_system !== true) {
            return response()->json(['code' => 403, 'message' => '仅 system 账号可访问'], 403);
        }
        $list = \App\Models\Department::orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'parent_id']);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * V1.2.9 BUG FIX: system 账号拉角色列表专用端点
     * 原本 Welcome.vue 调 /api/roles 会被 EnsureBusinessUser 中间件拦 403
     */
    public function systemRoles(): JsonResponse
    {
        $user = request()->user();
        if (!$user || $user->is_system !== true) {
            return response()->json(['code' => 403, 'message' => '仅 system 账号可访问'], 403);
        }
        $list = \Spatie\Permission\Models\Role::orderBy('id')
            ->get(['id', 'name', 'description'])
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $this->roleDisplayName($role),
                'description' => $role->description,
            ]);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    private function roleDisplayName(\Spatie\Permission\Models\Role $role): string
    {
        $map = [
            'system_admin' => '系统管理员',
            'admin' => '业务管理员',
            'manager' => '部门经理',
            'finance' => '财务',
            'user' => '普通员工',
            'sales_manager' => '销售经理',
        ];

        return $map[$role->name] ?? ($role->description ?: $role->name);
    }
}
