<?php

namespace App\Observers;

use App\Jobs\AutoScheduleOnboardJob;
use App\Models\Shift;
use App\Models\User;

/**
 * V1.2.4v 考勤 — 员工入职自动排默认班
 *
 * 触发时机: User::created
 * 行为:
 *  - V1.2.7 P2-1 改造: 不再同步执行, 改为派发 AutoScheduleOnboardJob
 *    - 优点: 不阻塞用户创建接口 (快业务)
 *    - 优点: 失败可重试, 不影响主流程
 *  - 行为: 拿 is_default=true 的班次, 从今天起 30 天每天排一个
 *  - system 账号不排 (user_type='system')
 */
class UserScheduleObserver
{
    public function created(User $user): void
    {
        // system 账号不入排班
        if (($user->user_type ?? 'business') === 'system') {
            return;
        }

        // 找默认班次 ID (按优先级: is_default=true > code='day' > 第一个 active 班次)
        $shift = Shift::where('is_default', true)->where('is_active', true)->first()
            ?? Shift::where('code', 'day')->where('is_active', true)->first()
            ?? Shift::where('is_active', true)->orderBy('sort_order')->first();

        if (!$shift) {
            return; // 没有任何班次, 跳过
        }

        // V1.2.7 P2-1: 派发异步任务, 30 天排班放队列里跑
        AutoScheduleOnboardJob::dispatch(
            userId: $user->id,
            defaultShiftId: $shift->id,
            days: 30,
        );
    }
}
