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

            case 'purchase_items':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM purchase_orders po WHERE po.id = purchase_items.purchase_order_id AND (%s)))",
                        self::purchaseOrderAccessSql($userId, 'po')
                    )],
                ];

            case 'purchase_plans':
                // V0.6.3: 采购计划 — 自己创建 OR 关联项目可访问
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'purchase_requirements':
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

            case 'purchase_contract_items':
            case 'purchase_contract_files':
            case 'purchase_shipping_plans':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM purchase_contracts pc WHERE pc.id = %s.contract_id AND (%s)))",
                        $table,
                        self::purchaseContractAccessSql($userId, 'pc')
                    )],
                ];

            case 'purchase_payment_requests':
                return [
                    ['applicant_id', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM purchase_contracts pc WHERE pc.id = purchase_payment_requests.contract_id AND (pc.signer_id = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = pc.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))))",
                        $userId, $userId, $userId
                    )],
                ];

            case 'purchase_payments':
                return [
                    ['operator_id', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM purchase_contracts pc WHERE pc.id = purchase_payments.contract_id AND (pc.signer_id = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = pc.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))))",
                        $userId, $userId, $userId
                    )],
                ];

            case 'purchase_shipments':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM purchase_contracts pc WHERE pc.id = purchase_shipments.contract_id AND (pc.signer_id = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = pc.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))))",
                        $userId, $userId, $userId
                    )],
                ];

            case 'purchase_shipment_items':
            case 'purchase_logistics':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM purchase_shipments ps WHERE ps.id = %s.shipment_id AND (%s)))",
                        $table,
                        self::purchaseShipmentAccessSql($userId, 'ps')
                    )],
                ];

            case 'contract_payment_nodes':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM project_contracts pc WHERE pc.id = contract_payment_nodes.contract_id AND (%s)))",
                        self::projectContractAccessSql($userId, 'pc')
                    )],
                ];

            case 'construction_logs':
                return [
                    ['user_id', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'work_processes':
                // 通用工序 (project_id 为空) 对所有业务用户可见，项目工序按项目权限隔离
                return [
                    ['__raw__', sprintf(
                        "(work_processes.project_id IS NULL OR %s)",
                        $myProjects
                    )],
                ];

            case 'project_budgets':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'construction_teams':
                // 通用团队 (project_id 为空) 可复用，项目团队按项目权限隔离
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', sprintf(
                        "(construction_teams.project_id IS NULL OR %s)",
                        $myProjects
                    )],
                ];

            case 'project_commencement_orders':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'external_construction_works':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'external_construction_bids':
                return [
                    ['bidder_user_id', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM external_construction_works ecw WHERE ecw.id = external_construction_bids.work_id AND (%s)))",
                        self::externalConstructionWorkAccessSql($userId, 'ecw')
                    )],
                ];

            case 'construction_team_members':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM construction_teams ct WHERE ct.id = construction_team_members.team_id AND (%s)))",
                        self::constructionTeamAccessSql($userId, 'ct')
                    )],
                ];

            case 'project_budget_items':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM project_budgets pb WHERE pb.id = project_budget_items.budget_id AND (%s)))",
                        self::projectBudgetAccessSql($userId, 'pb')
                    )],
                ];

            case 'rectification_daily_required':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'work_process_progress':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'project_actual_costs':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'project_stage_logs':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'project_contracts':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'project_materials':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'project_settlements':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'stock_records':
                // 共享流水可见；项目流水按项目权限隔离；本人操作的流水始终可见
                return [
                    ['operator_id', '=', $userId],
                    ['__raw__', sprintf(
                        "(stock_records.project_id IS NULL OR %s)",
                        $myProjects
                    )],
                ];

            case 'service_orders':
                return [
                    ['created_by', '=', $userId],
                    ['assigned_to', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'service_order_logs':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM service_orders so WHERE so.id = service_order_logs.service_order_id AND (%s)))",
                        self::serviceOrderAccessSql($userId, 'so')
                    )],
                ];

            case 'service_order_parts':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM service_orders so WHERE so.id = service_order_parts.service_order_id AND (%s)))",
                        self::serviceOrderAccessSql($userId, 'so')
                    )],
                ];

            case 'customer_devices':
                // 未绑定项目的客户设备作为公共客户资产可见，项目设备按项目权限隔离
                return [
                    ['__raw__', sprintf(
                        "(customer_devices.project_id IS NULL OR %s)",
                        $myProjects
                    )],
                ];

            case 'device_serial_numbers':
                // 库存序列号共享可见；已安装到项目/客户设备的序列号按关联项目权限隔离
                return [
                    ['__raw__', sprintf(
                        "(((device_serial_numbers.project_id IS NULL AND (device_serial_numbers.customer_device_id IS NULL OR %s) AND (device_serial_numbers.stock_record_id IS NULL OR %s))) OR %s)",
                        self::customerDeviceAccessSql($userId, 'cd'),
                        self::stockRecordAccessSql($userId, 'sr'),
                        $myProjects
                    )],
                ];

            case 'tender_projects':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', self::tenderProjectAccessSql($userId, 'tender_projects')],
                ];

            case 'tender_bids':
                return [
                    ['__raw__', self::tenderBidAccessSql($userId, 'tender_bids')],
                ];

            case 'tender_bid_items':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM tender_bids tb WHERE tb.id = tender_bid_items.tender_bid_id AND (%s)))",
                        self::tenderBidAccessSql($userId, 'tb')
                    )],
                ];

            case 'tender_attachments':
                return [
                    ['uploaded_by_user_id', '=', $userId],
                    ['__raw__', self::tenderAttachmentAccessSql($userId, 'tender_attachments')],
                ];

            case 'tender_deposit_rules':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM tender_projects tp WHERE tp.id = tender_deposit_rules.tender_project_id AND (%s)))",
                        self::tenderProjectAccessSql($userId, 'tp')
                    )],
                ];

            case 'tender_deposits':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM tender_projects tp WHERE tp.id = tender_deposits.tender_project_id AND (%s)))",
                        self::tenderProjectAccessSql($userId, 'tp')
                    )],
                ];

            case 'repair_orders':
                return [
                    ['created_by', '=', $userId],
                    ['received_by', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'repair_attachments':
                return [
                    ['uploaded_by', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM repair_orders ro WHERE ro.id = repair_attachments.repair_order_id AND (%s)))",
                        self::repairOrderAccessSql($userId, 'ro')
                    )],
                ];

            case 'repair_methods':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM repair_orders ro WHERE ro.id = repair_methods.repair_order_id AND (%s)))",
                        self::repairOrderAccessSql($userId, 'ro')
                    )],
                ];

            case 'repair_progress_logs':
                return [
                    ['action_by', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM repair_orders ro WHERE ro.id = repair_progress_logs.repair_order_id AND (%s)))",
                        self::repairOrderAccessSql($userId, 'ro')
                    )],
                ];

            case 'repair_shipments':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM repair_orders ro WHERE ro.id = repair_shipments.repair_order_id AND (%s)))",
                        self::repairOrderAccessSql($userId, 'ro')
                    )],
                ];

            case 'repair_step_photos':
                return [
                    ['uploaded_by', '=', $userId],
                    ['__raw__', sprintf(
                        "((repair_step_photos.target_type = 'repair_order' AND EXISTS (SELECT 1 FROM repair_orders ro WHERE ro.id = repair_step_photos.target_id AND (%s))) OR (repair_step_photos.target_type = 'work_order' AND EXISTS (SELECT 1 FROM work_orders wo WHERE wo.id = repair_step_photos.target_id AND (%s))))",
                        self::repairOrderAccessSql($userId, 'ro'),
                        self::workOrderAccessSql($userId, 'wo')
                    )],
                ];

            case 'warranty_deposit_logs':
                return [
                    ['operator_id', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM warranty_deposits wd WHERE wd.id = warranty_deposit_logs.deposit_id AND (%s)))",
                        self::warrantyDepositAccessSql($userId, 'wd')
                    )],
                ];

            case 'work_orders':
                return [
                    ['created_by', '=', $userId],
                    ['assigned_to', '=', $userId],
                    ['__raw__', $myProjects],
                ];

            case 'customer_receipts':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', $myProjects],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM customer_receivables cr WHERE cr.id IN (SELECT (item->>'receivable_id')::bigint FROM jsonb_array_elements(COALESCE(customer_receipts.allocations, '[]'::jsonb)) AS item WHERE (item->>'receivable_id') ~ '^[0-9]+$') AND %s))",
                        AuthScope::myProjectsByProjectIdSubquery($userId, 'cr')
                    )],
                ];

            case 'supplier_payments':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM supplier_payables sp WHERE sp.id IN (SELECT (item->>'payable_id')::bigint FROM jsonb_array_elements(COALESCE(supplier_payments.allocations, '[]'::jsonb)) AS item WHERE (item->>'payable_id') ~ '^[0-9]+$') AND %s))",
                        AuthScope::myProjectsByProjectIdSubquery($userId, 'sp')
                    )],
                ];

            case 'finance_payments':
                return [
                    ['__raw__', $myProjects],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM receivables r WHERE r.id = finance_payments.receivable_id AND %s))",
                        AuthScope::myProjectsByProjectIdSubquery($userId, 'r')
                    )],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM payables pbl WHERE pbl.id = finance_payments.payable_id AND %s))",
                        AuthScope::myProjectsByProjectIdSubquery($userId, 'pbl')
                    )],
                ];

            case 'finance_invoices':
                return [
                    ['applicant_id', '=', $userId],
                    ['__raw__', $myProjects],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM receivables r WHERE r.id = finance_invoices.receivable_id AND %s))",
                        AuthScope::myProjectsByProjectIdSubquery($userId, 'r')
                    )],
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM project_contracts pc WHERE pc.id = finance_invoices.contract_id AND %s))",
                        AuthScope::myProjectsByProjectIdSubquery($userId, 'pc')
                    )],
                ];

            case 'inspection_plans':
                return [
                    ['created_by', '=', $userId],
                    ['__raw__', self::inspectionPlanAccessSql($userId, 'inspection_plans')],
                ];

            case 'maintenance_contracts':
                return [
                    ['__raw__', self::maintenanceContractAccessSql($userId, 'maintenance_contracts')],
                ];

            case 'inspection_schedules':
                return [
                    ['__raw__', self::inspectionPlanRelationSql($userId, 'inspection_schedules')],
                ];

            case 'inspection_tasks':
                return [
                    ['assigned_to', '=', $userId],
                    ['__raw__', self::inspectionPlanRelationSql($userId, 'inspection_tasks')],
                ];

            case 'inspection_records':
                return [
                    ['user_id', '=', $userId],
                    ['__raw__', self::inspectionTaskRelationSql($userId, 'inspection_records')],
                ];

            case 'inspection_issues':
                return [
                    ['__raw__', self::inspectionPlanRelationSql($userId, 'inspection_issues')],
                ];

            case 'process_instances':
                return [
                    ['__raw__', $myProjects],
                ];

            case 'process_inspections':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM process_instances pi WHERE pi.id = process_inspections.process_instance_id AND EXISTS (SELECT 1 FROM projects p WHERE p.id = pi.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active')))))",
                        $userId, $userId
                    )],
                ];

            case 'process_images':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM process_instances pi WHERE pi.id = process_images.process_instance_id AND EXISTS (SELECT 1 FROM projects p WHERE p.id = pi.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active')))))",
                        $userId, $userId
                    )],
                ];

            case 'process_signatures':
                return [
                    ['__raw__', sprintf(
                        "(EXISTS (SELECT 1 FROM process_instances pi WHERE pi.id = process_signatures.process_instance_id AND EXISTS (SELECT 1 FROM projects p WHERE p.id = pi.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active')))))",
                        $userId, $userId
                    )],
                ];

            case 'expense_claims':
                return [
                    ['user_id', '=', $userId],
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

        // 2. admin/finance/system 直接放行
        if (
            AuthScope::isUnrestricted($user)
            || ($user->is_system ?? false) === true
            || ($user->user_type ?? null) === 'system'
        ) {
            return;
        }

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

    private static function inspectionPlanAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by::text = '%d' OR COALESCE(%s.assigned_to, '[]')::jsonb @> '[%d]'::jsonb OR COALESCE(%s.assigned_to, '[]')::jsonb @> '[\"%d\"]'::jsonb)",
            $alias,
            $userId,
            $alias,
            $userId,
            $alias,
            $userId
        );
    }

    private static function inspectionPlanRelationSql(int $userId, string $alias): string
    {
        return sprintf(
            "(EXISTS (SELECT 1 FROM inspection_plans ip WHERE ip.id = %s.plan_id AND %s))",
            $alias,
            self::inspectionPlanAccessSql($userId, 'ip')
        );
    }

    private static function inspectionTaskRelationSql(int $userId, string $alias): string
    {
        return sprintf(
            "(EXISTS (SELECT 1 FROM inspection_tasks it JOIN inspection_plans ip ON ip.id = it.plan_id WHERE it.id = %s.task_id AND (it.assigned_to = %d OR %s)) OR EXISTS (SELECT 1 FROM inspection_plans ip2 WHERE ip2.id = %s.plan_id AND %s))",
            $alias,
            $userId,
            self::inspectionPlanAccessSql($userId, 'ip'),
            $alias,
            self::inspectionPlanAccessSql($userId, 'ip2')
        );
    }

    private static function maintenanceContractAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(EXISTS (SELECT 1 FROM customers c WHERE c.id = %s.customer_id AND c.assigned_user_id = %d) OR EXISTS (SELECT 1 FROM projects p WHERE p.customer_id = %s.customer_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))) OR EXISTS (SELECT 1 FROM inspection_plans ip WHERE ip.contract_id = %s.id AND %s))",
            $alias,
            $userId,
            $alias,
            $userId,
            $userId,
            $alias,
            self::inspectionPlanAccessSql($userId, 'ip')
        );
    }

    private static function externalConstructionWorkAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function tenderProjectAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))) OR EXISTS (SELECT 1 FROM external_quote_requests erq WHERE erq.id = %s.rfq_id AND (erq.created_by = %d OR EXISTS (SELECT 1 FROM projects ep WHERE ep.id = erq.project_id AND (ep.manager_id = %d OR EXISTS (SELECT 1 FROM project_members epm WHERE epm.project_id = ep.id AND epm.user_id = %d AND epm.status = 'active'))))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $userId,
            $alias,
            $userId,
            $userId,
            $userId
        );
    }

    private static function tenderBidAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.submitter_user_id = %d OR EXISTS (SELECT 1 FROM tender_projects tp WHERE tp.id = %s.tender_project_id AND (%s)))",
            $alias,
            $userId,
            $alias,
            self::tenderProjectAccessSql($userId, 'tp')
        );
    }

    private static function tenderAttachmentAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(EXISTS (SELECT 1 FROM tender_projects tp WHERE tp.id = %s.tender_project_id AND (%s)) OR EXISTS (SELECT 1 FROM tender_bids tb WHERE tb.id = %s.tender_bid_id AND (%s)))",
            $alias,
            self::tenderProjectAccessSql($userId, 'tp'),
            $alias,
            self::tenderBidAccessSql($userId, 'tb')
        );
    }

    private static function constructionTeamAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR %s.project_id IS NULL OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $alias,
            $userId,
            $userId
        );
    }

    private static function projectBudgetAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function serviceOrderAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR %s.assigned_to = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function customerDeviceAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.project_id IS NULL OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $alias,
            $userId,
            $userId
        );
    }

    private static function stockRecordAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "EXISTS (SELECT 1 FROM stock_records %s WHERE %s.operator_id = %d OR %s.project_id IS NULL OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $alias,
            $userId,
            $alias,
            $alias,
            $userId,
            $userId
        );
    }

    private static function repairOrderAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR %s.received_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function workOrderAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR %s.assigned_to = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function warrantyDepositAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.created_by = %d OR %s.approved_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function purchaseOrderAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.approved_by = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function purchaseContractAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "(%s.signer_id = %d OR EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $alias,
            $userId,
            $alias,
            $userId,
            $userId
        );
    }

    private static function purchaseShipmentAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "EXISTS (SELECT 1 FROM purchase_contracts pc WHERE pc.id = %s.contract_id AND (%s))",
            $alias,
            self::purchaseContractAccessSql($userId, 'pc')
        );
    }

    private static function projectContractAccessSql(int $userId, string $alias): string
    {
        return sprintf(
            "EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active')))" ,
            $alias,
            $userId,
            $userId
        );
    }
}
