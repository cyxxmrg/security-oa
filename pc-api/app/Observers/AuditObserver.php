<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\EmployeeProfile;
use App\Models\ExpenseClaim;
use App\Models\InventoryItem;
use App\Models\KnowledgeArticle;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\ServiceOrder;
use App\Models\VehicleUsageRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 审计 Observer: 监听所有写操作 (created/updated/deleted)
 * 写入 system_logs 表
 *
 * 模块 → 操作类型映射表 (modules 字段用于前端筛选)
 */
class AuditObserver
{
    /** 哪些 Model 需要审计 (业务写操作) */
    public const WATCHED = [
        Project::class           => '项目',
        Customer::class          => '客户',
        EmployeeProfile::class   => '员工',
        ServiceOrder::class      => '售后',
        ExpenseClaim::class      => '报销',
        VehicleUsageRequest::class => '车辆',
        InventoryItem::class     => '库存',
        KnowledgeArticle::class  => '知识库',
        PurchaseOrder::class     => '采购',
        Receivable::class        => '应收',
        Payable::class           => '应付',
        Role::class              => '角色',
        Permission::class        => '权限',
    ];

    public function created(Model $model): void
    {
        $this->writeLog($model, '新增', 'created');
    }

    public function updated(Model $model): void
    {
        // 跳过纯 updated_at 变更
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if (empty($changes)) return;
        $this->writeLog($model, '修改', 'updated', $changes, $model->getOriginal());
    }

    public function deleted(Model $model): void
    {
        $this->writeLog($model, '删除', 'deleted');
    }

    /**
     * 写入 system_logs
     */
    private function writeLog(
        Model $model,
        string $action,
        string $type,
        ?array $newValues = null,
        ?array $oldValues = null
    ): void {
        $module = self::WATCHED[get_class($model)] ?? class_basename($model);

        $user = Auth::user();
        $userId   = $user?->id;
        $userName = $user?->name ?? 'system';

        // 模型可读描述（优先 name/title/claim_no/vehicle_no）
        $label = $model->name
            ?? $model->title
            ?? $model->claim_no
            ?? $model->vehicle_no
            ?? $model->order_no
            ?? "#{$model->getKey()}";

        $request = request();
        $ip = $request?->ip();

        // 描述
        $description = match ($type) {
            'created' => "新增{$module}：{$label}",
            'updated' => "修改{$module}：{$label}（变更 " . count($newValues ?? []) . " 项）",
            'deleted' => "删除{$module}：{$label}",
            default   => "{$action}：{$label}",
        };

        $requestData = [];
        if ($type === 'created') {
            $requestData = $newValues ?? $model->getAttributes();
        } elseif ($type === 'updated') {
            $requestData = ['changed' => array_keys($newValues ?? [])];
        }

        try {
            DB::table('system_logs')->insert([
                'user_id'       => $userId,
                'type'          => 'operation',
                'module'        => $module,
                'action'        => $action,
                'description'   => $description,
                'ip'            => $ip,
                'user_agent'    => substr((string) ($request?->userAgent() ?? ''), 0, 255),
                'request_data'  => json_encode($requestData, JSON_UNESCAPED_UNICODE),
                'response_code' => 200,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // 审计失败不能影响主业务
            \Log::warning('AuditObserver write failed: ' . $e->getMessage());
        }
    }
}
