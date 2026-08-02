<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\EmployeeProfile;
use App\Models\ExpenseClaim;
use App\Models\InventoryItem;
use App\Models\KnowledgeArticle;
use App\Models\Payable;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\ServiceOrder;
use App\Models\VehicleUsageRequest;
use App\Observers\AuditObserver;
use App\Services\ErrorReporter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 慢查询阈值（毫秒）— 超过则记 warning
     * 生产环境建议 200~500ms
     */
    private const SLOW_QUERY_THRESHOLD_MS = 500;

    /** V1.2.7: 严重慢查询阈值 — 触发 SlowSqlAlertJob */
    private const CRITICAL_SQL_THRESHOLD_MS = 2000;

    public function register(): void
    {
        // V1.2.7 P2-1: Horizon 鉴权, 必须在 register() 阶段注册 (早于 HorizonApplicationServiceProvider::boot 的 authorization())
        \Laravel\Horizon\Horizon::auth(function ($request) {
            $user = $request->user();
            if (!$user) return false;
            return ($user->user_type ?? 'business') === 'system' || $user->hasRole('admin');
        });

        // V1.2.7 P2-2: Scramble 文档 gate (生产环境允许 admin/system 看)
        // V1.2.6: 必须配合 config/scramble.php 的 'auth:sanctum' middleware,
        //          Gate 跑时 request user 已就绪
        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
            $u = $user ?: (request() ? request()->user() : null);
            if (!$u) return false;
            if (($u->user_type ?? null) === 'system') return true;
            if (isset($u->is_system) && $u->is_system === true) return true;
            if (method_exists($u, 'hasRole') && $u->hasRole('admin')) return true;
            return false;
        });
    }

    public function boot(): void
    {
        // 这里手动注册 — 全局默认 1200 req/min, 按 IP 维度
        // (600/min 在 10 并发就接近上限, 调到 1200 给生产余量)
        // 登录和改密的 throttle:5,1 单独走更严的限流, 不受这个影响
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(1200)->by($request->ip());
        });

        // 注册审计 Observer 到所有核心业务 Model
        $watched = [
            Project::class,
            Customer::class,
            EmployeeProfile::class,
            ServiceOrder::class,
            ExpenseClaim::class,
            VehicleUsageRequest::class,
            InventoryItem::class,
            KnowledgeArticle::class,
            PurchaseOrder::class,
            Receivable::class,
            Payable::class,
            Role::class,
            Permission::class,
        ];
        foreach ($watched as $modelClass) {
            $modelClass::observe(AuditObserver::class);
        }

        // ===== T6 慢 SQL 监控 (V1.2.7 P2-3 升级) =====
        // 只在生产环境启用 — dev 环境查询慢是常态
        if (app()->environment('production')) {
            DB::listen(function (QueryExecuted $q) {
                $timeMs = (float) $q->time;

                // 只关心超过阈值的查询
                if ($timeMs < self::SLOW_QUERY_THRESHOLD_MS) {
                    return;
                }

                $payload = [
                    'sql'        => $this->normalizeSql($q->sql),
                    'bindings'   => $this->sanitizeBindings($q->bindings),
                    'time_ms'    => $timeMs,
                    'connection' => $q->connectionName,
                    'request_id' => request()?->header('X-Request-Id'),
                    'path'       => request()?->path(),
                ];

                // 严重慢 (>2s) — dispatch 告警 Job + 立即 warn
                if ($timeMs >= self::CRITICAL_SQL_THRESHOLD_MS) {
                    \Illuminate\Support\Facades\Log::error('CRITICAL_SQL', $payload + ['level' => 'critical']);
                    ErrorReporter::warn('CRITICAL_SQL', $payload);
                    $this->dispatchSlowSqlAlert($payload);
                    return;
                }

                // 慢 (>500ms) — 普通 warn
                \Illuminate\Support\Facades\Log::warning('SLOW_SQL', $payload);
                ErrorReporter::warn('SLOW_SQL', $payload);
            });
        }

        // V0.4.1 注册 Observer（实时记录项目实际成本）
        \App\Models\StockRecord::observe(\App\Observers\StockRecordObserver::class);
        \App\Models\ExpenseClaim::observe(\App\Observers\ExpenseClaimObserver::class);

        // V0.4.3 施工链路 Observer
        \App\Models\ConstructionLog::observe(\App\Observers\ConstructionLogObserver::class);
        \App\Models\ProjectCommencementOrder::observe(\App\Observers\CommencementOrderObserver::class);
        \App\Models\ExternalConstructionBid::observe(\App\Observers\ExternalConstructionBidObserver::class);

        // V1.0 网盘 Observer — 创建项目/员工时自动建网盘子目录
        \App\Models\Project::observe(\App\Observers\ProjectDiskObserver::class);
        \App\Models\User::observe(\App\Observers\UserDiskObserver::class);

        // V1.2.4v 考勤 Observer — 员工入职自动排默认班
        \App\Models\User::observe(\App\Observers\UserScheduleObserver::class);

        // V1.2.7 P2-3: 部门/岗位缓存自动清理 Observer
        \App\Models\Department::observe(\App\Observers\OrgCacheObserver::class);
        \App\Models\Position::observe(\App\Observers\OrgCacheObserver::class);
    }

    /**
     * 截断过长的 binding (避免日志爆掉)
     */
    private function sanitizeBindings(array $bindings): array
    {
        return array_map(function ($b) {
            if (is_string($b) && strlen($b) > 200) {
                return substr($b, 0, 200) . '...(truncated)';
            }
            return $b;
        }, $bindings);
    }

    /**
     * 规范化 SQL — 去除多余空白便于聚合去重
     */
    private function normalizeSql(string $sql): string
    {
        // 压缩多余空白
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        // 截断过长 SQL (避免日志超长)
        if (mb_strlen($sql) > 1000) {
            $sql = mb_substr($sql, 0, 1000) . '...(truncated)';
        }
        return $sql;
    }

    /**
     * 派发严重慢 SQL 告警 Job
     */
    private function dispatchSlowSqlAlert(array $payload): void
    {
        try {
            // V1.2.7: 用通用 SlowRequestAlertJob 复用告警通道
            \App\Jobs\SlowRequestAlertJob::dispatch(
                ['type' => 'CRITICAL_SQL', 'sql' => $payload],
                'critical-sql',
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SlowSql alert dispatch failed', [
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
