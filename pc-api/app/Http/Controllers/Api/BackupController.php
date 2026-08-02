<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\TriggerBackupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class BackupController extends Controller
{
    protected string $backupDir = '';

    public function __construct()
    {
        // P0-4 安全修复: 兜底守门, 任何方法执行前都先验证必须是 system 账号
        // 即使 route 中间件被绕过 (例如未来路由配置回归), 控制器内部仍然拒绝
        $user = auth()->user();
        if (!$user || $user->user_type !== 'system') {
            abort(403, '备份管理仅限 system 账号');
        }
        $this->backupDir = storage_path('app/backups');
    }

    public function index(): JsonResponse
    {
        if (!is_dir($this->backupDir)) {
            return response()->json(['code' => 0, 'data' => []]);
        }

        $files = [];
        foreach (scandir($this->backupDir, SCANDIR_SORT_DESCENDING) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $this->backupDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'id' => $file,
                'filename' => $file,
                'size' => round(filesize($path) / 1024 / 1024, 2) . ' MB',
                'time' => date('Y-m-d H:i:s', filemtime($path)),
                'status' => '完成',
            ];
        }

        return response()->json(['code' => 0, 'data' => $files]);
    }

    public function store(TriggerBackupRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->createBackup($data['label'] ?? 'manual');
    }

    public function schedule(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->scheduleConfig()]);
    }

    public function updateSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'time' => ['required', 'date_format:H:i'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $config = [
            'enabled' => (bool) $data['enabled'],
            'time' => $data['time'],
            'retention_days' => (int) ($data['retention_days'] ?? 30),
            'timezone' => config('app.timezone', 'Asia/Shanghai'),
        ];

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'backup_schedule'],
            [
                'value' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'description' => '数据库自动备份计划',
                'updated_at' => now(),
                'updated_by' => $request->user()?->id,
            ]
        );

        return response()->json(['code' => 0, 'message' => '自动备份设置已保存', 'data' => $config]);
    }

    public function runDue(Request $request): JsonResponse
    {
        $expectedToken = $this->settingString('backup_cron_token', '');
        if ($expectedToken !== '' && !hash_equals($expectedToken, (string) $request->query('token', ''))) {
            return response()->json(['code' => 403, 'message' => '备份任务 token 不正确'], 403);
        }

        $config = $this->scheduleConfig();
        if (!$config['enabled']) {
            return response()->json(['code' => 0, 'message' => '自动备份未启用', 'data' => ['ran' => false, 'config' => $config]]);
        }

        $lastRunDate = $this->settingString('backup_last_run_date', '');
        if (now()->format('H:i') < $config['time'] || $lastRunDate === now()->toDateString()) {
            return response()->json([
                'code' => 0,
                'message' => '未到自动备份时间或今日已备份',
                'data' => ['ran' => false, 'config' => $config, 'last_run_date' => $lastRunDate ?: null],
            ]);
        }

        $response = $this->createBackup('auto');
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'backup_last_run_date'],
            [
                'value' => json_encode(now()->toDateString(), JSON_UNESCAPED_UNICODE),
                'description' => '最近一次自动备份日期',
                'updated_at' => now(),
            ]
        );
        $this->cleanupOldAutoBackups($config['retention_days']);

        $payload = $response->getData(true);
        $payload['data']['ran'] = true;
        $payload['data']['config'] = $config;
        return response()->json($payload, $response->getStatusCode());
    }

    public function download(Request $request, string $filename)
    {
        $path = $this->backupDir . '/' . basename($filename);
        if (!file_exists($path)) {
            return response()->json(['code' => 1001, 'message' => '文件不存在'], 404); // V1.2.10 业务码用 1001, HTTP 用 404
        }
        return response()->download($path, $filename);
    }

    public function destroy(Request $request, string $filename): JsonResponse
    {
        $path = $this->backupDir . '/' . basename($filename);
        if (file_exists($path)) {
            unlink($path);
        }
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    private function createBackup(string $label): JsonResponse
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $label = preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $label) ? $label : 'manual';
        $filename = 'oa_backup_' . $label . '_' . now()->format('Ymd_His') . '.sql';
        $filepath = $this->backupDir . '/' . $filename;
        $db = config('database.connections.pgsql');

        try {
            $result = Process::env(['PGPASSWORD' => $db['password']])
                ->path($this->backupDir)
                ->run([
                    'pg_dump',
                    '-h', $db['host'],
                    '-p', (string) $db['port'],
                    '-U', $db['username'],
                    '-d', $db['database'],
                    '-F', 'p',
                    '--no-owner',
                    '--no-acl',
                    '-f', $filename,
                ]);

            if (!$result->successful()) {
                Log::error('BACKUP_PG_DUMP_FAILED', [
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);
                return response()->json(['code' => 1001, 'message' => '备份失败: pg_dump 退出码 ' . $result->exitCode()], 500);
            }
        } catch (\Throwable $e) {
            Log::error('BACKUP_PG_DUMP_EXCEPTION', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['code' => 1001, 'message' => '备份异常: ' . $e->getMessage()], 500);
        }

        if (!file_exists($filepath) || filesize($filepath) === 0) {
            return response()->json(['code' => 1001, 'message' => '备份文件生成失败'], 500);
        }

        $finalFilename = $filename;
        try {
            $gzip = Process::path($this->backupDir)->run(['gzip', '-f', $filename]);
            if ($gzip->successful() && file_exists($filepath . '.gz')) {
                $finalFilename = $filename . '.gz';
            }
        } catch (\Throwable $e) {
            Log::warning('BACKUP_GZIP_FAILED', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'data' => ['filename' => $finalFilename]]);
    }

    private function scheduleConfig(): array
    {
        $raw = $this->settingValue('backup_schedule', []);
        $config = is_array($raw) ? $raw : [];
        $time = (string) ($config['time'] ?? '02:00');
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '02:00';
        }

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'time' => $time,
            'retention_days' => max(1, min(365, (int) ($config['retention_days'] ?? 30))),
            'timezone' => (string) ($config['timezone'] ?? config('app.timezone', 'Asia/Shanghai')),
            'last_run_date' => $this->settingString('backup_last_run_date', '') ?: null,
            'cron_url' => url('/api/backups/run-due'),
        ];
    }

    private function cleanupOldAutoBackups(int $retentionDays): void
    {
        if (!is_dir($this->backupDir)) {
            return;
        }

        $deadline = now()->subDays($retentionDays)->getTimestamp();
        foreach (scandir($this->backupDir) ?: [] as $file) {
            if (!preg_match('/^oa_backup_auto_\d{8}_\d{6}\.sql(?:\.gz)?$/', $file)) {
                continue;
            }
            $path = $this->backupDir . '/' . $file;
            if (is_file($path) && filemtime($path) < $deadline) {
                @unlink($path);
            }
        }
    }

    private function settingString(string $key, string $default): string
    {
        $value = $this->settingValue($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $value = DB::table('system_settings')->where('key', $key)->value('value');
        if ($value === null) {
            return $default;
        }
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
