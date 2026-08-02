<?php

namespace App\Scopes;

use App\Support\AuthScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * B 数据权限全局作用域 (V0.4.6)
 *
 * 规则（按优先级短路）:
 *  1. admin/finance 直接放行 (不挂任何 where)
 *  2. 自己创建/负责的永不限制
 *  3. 自己参与的（中间表）也可见
 *
 * V0.4.7 收口:
 *  - adds a `denied_log` channel: when a request via handlesDataScope
 *    finds a record that's blocked by scope, it writes a system_logs row
 *    with action='data_scope_denied'
 */
class DataScope implements Scope
{
    /**
     * 各表特定的 scope 闭包 (key = 表名)
     * 返回 array of [column, op, value] 三元组, 用 OR 拼接
     */
    public static function tableClauses(string $table, int $userId): array
    {
        $myProjects = AuthScope::myProjectsByProjectIdSubquery($userId, $table);

        switch ($table) {
            case 'projects':
                // projects 自己: 自己是 manager OR 自己是 member
                return [
                    ['__raw__', sprintf(
                        "(projects.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = projects.id AND pm.user_id = %d AND pm.status = 'active'))",
                        $userId, $userId
                    )],
                ];

            case 'customer_receivables':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'purchase_orders':
                return [
                    ['approved_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'purchase_plans':
                // V0.6.3: 采购计划 — 自己创建 OR 关联项目可访问
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'purchase_contracts':
                // V0.6.3: 采购合同 — 自己签字 OR 关联项目可访问
                // V1.0.2: 修 signed_by → signer_id (表里实际列名)
                return [
                    ['signer_id', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'construction_logs':
                return [
                    ['user_id', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'rectifications':
                return [
                    ['created_by', '=', $userId],
                    ['responsible_id', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'warranties':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'warranty_service_orders':
                // service_order 没有 project_id, 用 warranty_id 间接判断
                return [
                    ['created_by', '=', $userId],
                    ['technician_id', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM warranties w WHERE w.id = warranty_service_orders.warranty_id AND (w.created_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = w.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))))",
                        $userId, $userId, $userId
                    )],
                ];

            case 'warranty_deposits':
                return [
                    ['created_by', '=', $userId],
                    ['approved_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'receivables':
                // 表: receivables (V0.4.5 前的 finance 表, 与 customer_receivables 区别)
                return [
                    ['__raw__', $myProjects],  // 没 created_by, 只走 project
                ];

            case 'payables':
                return [
                    ['__raw__', $myProjects],
                ];

            default:
                return [];
        }
    }

    public function apply(Builder $builder, Model $model)
    {
        // 1. 取当前用户 (后台任务 / seeder 时 $user = null → 放行)
        $user = Auth::user();
        if (!$user) return;

        // 2. admin/finance 直接放行
        if (AuthScope::isUnrestricted($user)) return;

        // 3. 拿到表名 + 拼 OR 条件
        $table = $model->getTable();
        $clauses = self::tableClauses($table, (int) $user->id);
        if (empty($clauses)) return;

        $builder->where(function (Builder $q) use ($clauses) {
            foreach ($clauses as $c) {
                if ($c[0] === '__raw__') {
                    $q->orWhereRaw($c[1]);
                } else {
                    $q->orWhere($c[0], $c[1], $c[2]);
                }
            }
        });
    }

    /**
     * V0.4.7 收口: scope 拒绝访问审计
     *  - 调用方: HandlesDataScope::findScoped 找不到 + 排查后确认是 scope 拦 → 写日志
     *  - 写 system_logs 表 (已有), action='data_scope_denied'
     *  - 不抛异常 (审计不影响业务流)
     *
     * @param string $table  被访问的表名
     * @param int $userId    访问者 user_id
     * @param int $recordId  被尝试访问的 record id
     * @param string $reason  'find' / 'update' / 'delete'
     */
    public static function logDeniedAccess(string $table, int $userId, int $recordId, string $reason = 'find'): void
    {
        try {
            DB::table('system_logs')->insert([
                'user_id'     => $userId,
                'type'        => 'security',
                'module'      => $table,
                'action'      => 'data_scope_denied',
                'description' => "尝试访问 {$table}#{$recordId} 被 scope 拒绝 (reason={$reason})",
                'ip'          => request()->ip(),
                'user_agent'  => substr((string) request()->userAgent(), 0, 250),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // 审计失败不抛, 避免影响主流程
            \Log::warning('data_scope_denied log failed: ' . $e->getMessage());
        }
    }
}
