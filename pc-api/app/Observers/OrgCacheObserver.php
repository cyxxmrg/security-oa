<?php

namespace App\Observers;

use App\Models\Department;
use App\Models\Position;
use App\Services\CacheHelper;

/**
 * V1.2.7 P2-3 性能优化 — 组织架构缓存自动清理
 *
 * 触发条件: Department / Position 任意 save/update/delete
 * 清理目标:
 *   - 'departments:all'        (EmployeeController 部门树缓存)
 *   - 'positions:all'          (EmployeeController 岗位列表缓存)
 *
 * 用 CacheHelper::flushTag('org') 一键清, 不需要记每个 key
 */
class OrgCacheObserver
{
    public function created(Department|Position $model): void
    {
        $this->flush();
    }

    public function updated(Department|Position $model): void
    {
        $this->flush();
    }

    public function deleted(Department|Position $model): void
    {
        $this->flush();
    }

    public function saved(Department|Position $model): void
    {
        // saved 在 create/update 后都触发, 兜底
        $this->flush();
    }

    private function flush(): void
    {
        try {
            CacheHelper::flushTag('org');
        } catch (\Throwable $e) {
            // 缓存清理失败不影响主流程, 仅警告
            \Illuminate\Support\Facades\Log::warning('OrgCacheObserver flush failed', [
                'msg' => $e->getMessage(),
            ]);
        }
    }
}