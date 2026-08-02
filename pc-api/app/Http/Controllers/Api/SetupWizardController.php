<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\EmployeeProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * V0.5.7 块 A — 系统初始化向导
 *
 * 5 步引导:
 *   Step 1: 基础设置 (系统名/版权/ICP/邮箱) — 复用 SystemSettingsController::update
 *   Step 2: 数据现状摘要
 *   Step 3: 补齐员工 (批量创建)
 *   Step 4: CSV 导入 (批量)
 *   Step 5: 完成 (标记 setup_completed = true)
 *
 * 端点:
 *   GET  /api/setup/summary       — 现状摘要 + 是否完成
 *   POST /api/setup/step1         — Step1 基础设置
 *   POST /api/setup/step3         — Step3 批量员工
 *   POST /api/setup/step4         — Step4 CSV 解析+导入
 *   POST /api/setup/complete      — 标记完成
 *   GET  /api/setup/sample-csv    — 下载员工 CSV 模板
 */
class SetupWizardController extends Controller
{
    /** Setup 标记 key */
    private const SETUP_COMPLETED_KEY = 'setup_completed';

    /**
     * GET /api/setup/summary
     * 返回: 系统数据现状 + 是否已完成初始化
     */
    public function summary(): JsonResponse
    {
        $data = [];

        // 1. 基础设置完成度
        $requiredSettings = ['system_name', 'system_short_name', 'copyright', 'icp', 'contact_email'];
        $all = DB::table('system_settings')->get()->keyBy('key');
        $filled = 0;
        $settings = [];
        foreach ($requiredSettings as $k) {
            $val = $all->has($k) ? self::normalizeValueStatic($all[$k]->value) : null;
            $filled += ($val !== null && $val !== '') ? 1 : 0;
            $settings[$k] = [
                'label'  => self::getLabel($k),
                'value'  => $val,
                'filled' => $val !== null && $val !== '',
            ];
        }

        // 2. 数据计数
        $counts = [
            'users'       => DB::table('users')->whereNull('deleted_at')->count(),
            'admins'      => DB::table('users')
                            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereNull('users.deleted_at')
                            ->where('roles.name', 'admin')
                            ->distinct()
                            ->count('users.id'),
            'roles'       => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'customers'   => DB::table('customers')->count(),
            'projects'    => DB::table('projects')->count(),
            'work_orders' => DB::table('work_orders')->count(),
            'departments' => DB::table('departments')->count(),
            'positions'   => DB::table('positions')->count(),
            'suppliers'   => DB::table('suppliers')->count(),
        ];

        // 3. 完成度评分
        $score = $this->calcScore($settings, $counts);

        // 4. 是否已完成
        $completed = $this->getSetting(self::SETUP_COMPLETED_KEY, false);
        $completedAt = $this->getSetting('setup_completed_at', null);

        return response()->json([
            'code' => 0,
            'data' => [
                'setup_completed'    => (bool) $completed,
                'setup_completed_at' => $completedAt,
                'score'              => $score, // 0-100
                'settings'           => $settings,
                'counts'             => $counts,
                'suggestions'        => $this->buildSuggestions($settings, $counts, (bool) $completed),
            ],
        ]);
    }

    /**
     * POST /api/setup/step1
     * 基础设置: 复用 SystemSettingsController 同样的允许列表
     */
    public function step1(Request $request): JsonResponse
    {
        $data = $request->validate([
            'system_name'         => 'required|string|max:64',
            'system_short_name'   => 'required|string|max:32',
            'copyright'           => 'required|string|max:255',
            'icp'                 => 'nullable|string|max:64',
            'contact_email'       => 'required|email|max:128',
        ]);

        $userId = $request->user()?->id;
        foreach ($data as $k => $v) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $k],
                [
                    'value'       => json_encode($v, JSON_UNESCAPED_UNICODE),
                    'updated_at'  => now(),
                    'updated_by'  => $userId,
                ]
            );
        }

        return response()->json([
            'code'    => 0,
            'message' => '基础设置已保存',
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/setup/step3
     * 批量创建员工
     * body: { employees: [{name, username, phone?, email?, password, role?, department_id?, position_id?}, ...] }
     */
    public function step3(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employees'                    => 'required|array|min:1|max:50',
            'employees.*.name'             => 'required|string|max:50',
            'employees.*.username'         => 'required|string|max:64|distinct',
            'employees.*.phone'            => 'nullable|string|max:20',
            'employees.*.email'            => 'nullable|email|max:100',
            'employees.*.password'         => 'required|string|min:6|max:32',
            'employees.*.role'             => 'nullable|string|max:50',
            'employees.*.department_id'    => 'nullable|integer',
            'employees.*.position_id'      => 'nullable|integer',
        ]);

        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];
        $created = [];

        // 修复 PG 序列滞后 (V0.3.7.8 踩坑: 非 Laravel 通道 INSERT 后 sequence 不会更新)
        try {
            $maxId = DB::table('users')->max('id');
            if ($maxId) {
                DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), ?)", [$maxId]);
            }
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 忽略
        }

        DB::beginTransaction();
        try {
            foreach ($data['employees'] as $idx => $emp) {
                // 查重: username
                $exists = User::where('username', $emp['username'])->exists();
                if ($exists) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'row'    => $idx + 1,
                        'reason' => "用户名 {$emp['username']} 已存在",
                    ];
                    continue;
                }
                // phone UNIQUE
                if (!empty($emp['phone']) && User::where('phone', $emp['phone'])->exists()) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'row'    => $idx + 1,
                        'reason' => "手机号 {$emp['phone']} 已存在",
                    ];
                    continue;
                }
                // email UNIQUE
                if (!empty($emp['email']) && User::where('email', $emp['email'])->exists()) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'row'    => $idx + 1,
                        'reason' => "邮箱 {$emp['email']} 已存在",
                    ];
                    continue;
                }

                $user = User::create([
                    'name'          => $emp['name'],
                    'username'      => $emp['username'],
                    'phone'         => $emp['phone'] ?? mb_substr('TEL-' . $emp['username'] . '-' . bin2hex(random_bytes(2)), 0, 20),
                    'email'         => $emp['email'] ?? null,
                    'password'      => $emp['password'],
                    'department_id' => $emp['department_id'] ?? null,
                    'position_id'   => $emp['position_id'] ?? null,
                    'status'        => 'active',
                ]);

                // 角色 (按 name 查)
                if (!empty($emp['role'])) {
                    $roleId = DB::table('roles')->where('name', $emp['role'])->value('id');
                    if ($roleId) {
                        $user->roles()->sync([$roleId]);
                    }
                }

                // profile (employee_profiles.NOT NULL 字段兜底)
                $user->profile()->create([
                    'hire_date'        => now()->format('Y-m-d'),
                    'employee_no'      => 'EMP' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'contract_type'    => 'open',
                    'base_salary'      => 0,
                    'salary_allowance' => 0,
                ]);

                $created[] = ['id' => $user->id, 'name' => $user->name, 'username' => $user->username];
                $results['created']++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            DB::rollBack();
            return response()->json([
                'code'    => 500,
                'message' => '批量创建失败: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'code'    => 0,
            'message' => "成功创建 {$results['created']} 个员工, 跳过 {$results['skipped']} 个",
            'data'    => [
                'created' => $created,
                'stats'   => $results,
            ],
        ]);
    }

    /**
     * POST /api/setup/step4
     * CSV 文本解析后调用 step3 逻辑
     * body: { csv_text: "name,username,phone,email,password,role,department_id,position_id\n..." }
     * 也支持 multipart: file=@xxx.csv
     */
    public function step4(Request $request): JsonResponse
    {
        $csvText = $request->input('csv_text', '');
        if (!$csvText && $request->hasFile('file')) {
            $csvText = file_get_contents($request->file('file')->getRealPath());
        }
        if (!trim($csvText)) {
            return response()->json([
                'code'    => 422,
                'message' => '请提供 csv_text 或上传 file',
            ], 422);
        }

        $rows = $this->parseCsv($csvText);
        if (empty($rows)) {
            return response()->json([
                'code'    => 422,
                'message' => 'CSV 解析失败: 至少需要表头 + 1 行数据',
            ], 422);
        }

        // 调 step3 逻辑 (转发请求, 但去掉 csrf 等)
        $subRequest = Request::create('/api/setup/step3', 'POST', ['employees' => $rows]);
        $subRequest->setUserResolver(fn() => $request->user());
        return $this->step3($subRequest);
    }

    /**
     * POST /api/setup/complete
     * body: { skip_remaining: false } — 标记完成 (写 setup_completed=true + 时间)
     */
    public function complete(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $now = now()->toIso8601String();
        foreach ([
            self::SETUP_COMPLETED_KEY => true,
            'setup_completed_at'      => $now,
            'setup_completed_by'      => $userId,
        ] as $k => $v) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $k],
                [
                    'value'      => json_encode($v, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'updated_by' => $userId,
                ]
            );
        }
        return response()->json([
            'code'    => 0,
            'message' => '🎉 系统初始化完成',
            'data'    => ['completed_at' => $now],
        ]);
    }

    /**
     * GET /api/setup/sample-csv
     * 返回员工 CSV 模板 (下载用)
     */
    public function sampleCsv()
    {
        $sample = "name,username,phone,email,password,role,department_id,position_id\n";
        $sample .= "张三,zhangsan,13800000001,zhangsan@example.com,Pass1234,user,1,1\n";
        $sample .= "李四,lisi,13800000002,lisi@example.com,Pass1234,sales,2,2\n";
        $sample .= "王五,wangwu,13800000003,wangwu@example.com,Pass1234,technician,3,3\n";
        return response($sample, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="employees_template.csv"',
        ]);
    }

    // ==================== 私有方法 ====================

    /**
     * SystemSetting::get 的内联版本 (绕开未定义 model 引用)
     * 取一个 key 的 value, 自动 normalize
     */
    private function getSetting(string $key, mixed $default = null): mixed
    {
        $row = DB::table('system_settings')->where('key', $key)->first();
        if (!$row) return $default;
        return $this->normalizeValueStatic($row->value);
    }

    /**
     * normalize 的静态版本 (供 use 闭包)
     */
    public static function normalizeValueStatic($v): mixed
    {
        if ($v === null) return null;
        if (is_array($v) || is_bool($v) || is_int($v) || is_float($v)) return $v;
        $s = (string) $v;
        $decoded = json_decode($s, true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        return $s;
    }

    /**
     * 中文 label 映射
     */
    public static function getLabel(string $key): string
    {
        return [
            'system_name'         => '系统名称',
            'system_short_name'   => '系统简称',
            'copyright'           => '版权信息',
            'copyright_url'       => '版权链接',
            'icp'                 => 'ICP 备案号',
            'contact_email'       => '联系邮箱',
            'announcement'        => '公告',
            'custom_web_port'     => '网站端口',
            'idle_enabled'        => '启用闲置超时',
            'idle_timeout_minutes'=> '闲置超时(分钟)',
            'idle_warning_seconds'=> '提前提示(秒)',
        ][$key] ?? $key;
    }

    /**
     * CSV 解析 (支持双引号, 转义, BOM)
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(string $text): array
    {
        // 去 BOM
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $lines = preg_split("/\r\n|\n|\r/", trim($text));
        if (count($lines) < 2) return [];

        $header = $this->parseCsvLine($lines[0]);
        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            if (!trim($lines[$i])) continue;
            $cells = $this->parseCsvLine($lines[$i]);
            if (count($cells) !== count($header)) {
                continue; // 列数不匹配, 跳过
            }
            $row = array_combine($header, $cells);
            // 数字字段转换
            if (isset($row['department_id']) && $row['department_id'] !== '') {
                $row['department_id'] = (int) $row['department_id'];
            } else {
                unset($row['department_id']);
            }
            if (isset($row['position_id']) && $row['position_id'] !== '') {
                $row['position_id'] = (int) $row['position_id'];
            } else {
                unset($row['position_id']);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 单行 CSV 解析 (简单实现, 支持双引号)
     */
    private function parseCsvLine(string $line): array
    {
        $result = [];
        $cur = '';
        $inQuote = false;
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($c === '"' && !$inQuote) {
                $inQuote = true;
            } elseif ($c === '"' && $inQuote) {
                if ($i + 1 < $len && $line[$i + 1] === '"') {
                    $cur .= '"';
                    $i++;
                } else {
                    $inQuote = false;
                }
            } elseif ($c === ',' && !$inQuote) {
                $result[] = trim($cur);
                $cur = '';
            } else {
                $cur .= $c;
            }
        }
        $result[] = trim($cur);
        return $result;
    }

    /**
     * 完成度评分 (0-100)
     */
    private function calcScore(array $settings, array $counts): int
    {
        $score = 0;
        // 基础设置 5 项 = 25 分
        $filled = collect($settings)->filter(fn($s) => $s['filled'])->count();
        $score += intval($filled / count($settings) * 25);

        // 员工 >= 3 = 15 分
        $score += $counts['users'] >= 3 ? 15 : intval($counts['users'] / 3 * 15);

        // 客户 >= 1 = 15 分
        $score += $counts['customers'] >= 1 ? 15 : 0;

        // 项目 >= 1 = 15 分
        $score += $counts['projects'] >= 1 ? 15 : 0;

        // 角色 >= 3 = 10 分
        $score += $counts['roles'] >= 3 ? 10 : intval($counts['roles'] / 3 * 10);

        // 部门 >= 3 = 10 分
        $score += $counts['departments'] >= 3 ? 10 : intval($counts['departments'] / 3 * 10);

        // 工单/业务活跃度 = 10 分
        $score += $counts['work_orders'] >= 1 ? 10 : 0;

        return min(100, $score);
    }

    /**
     * 生成建议 (基于现状)
     */
    private function buildSuggestions(array $settings, array $counts, bool $completed): array
    {
        $tips = [];
        // 设置未填
        foreach ($settings as $key => $s) {
            if (!$s['filled']) {
                $tips[] = [
                    'type'  => 'settings',
                    'level' => 'warning',
                    'msg'   => "未设置: {$s['label']}",
                ];
            }
        }
        // 员工不足
        if ($counts['users'] < 3) {
            $tips[] = [
                'type'  => 'employee',
                'level' => 'warning',
                'msg'   => "员工数仅 {$counts['users']} 人, 建议至少 3 人 (admin/销售/技术)",
            ];
        }
        // 客户/项目
        if ($counts['customers'] < 1) {
            $tips[] = [
                'type'  => 'business',
                'level' => 'info',
                'msg'   => '尚无客户, 建议录入第 1 个客户',
            ];
        }
        if ($counts['projects'] < 1) {
            $tips[] = [
                'type'  => 'business',
                'level' => 'info',
                'msg'   => '尚无项目, 建议创建第 1 个项目',
            ];
        }
        // 部门/职位
        if ($counts['departments'] < 3) {
            $tips[] = [
                'type'  => 'org',
                'level' => 'info',
                'msg'   => '部门架构不完整, 建议建立 销售/技术/财务/行政 等基础部门',
            ];
        }
        // 没完成过
        if (!$completed) {
            $tips[] = [
                'type'  => 'wizard',
                'level' => 'primary',
                'msg'   => '系统尚未完成初始化, 推荐先完成引导',
            ];
        }
        return $tips;
    }
}
