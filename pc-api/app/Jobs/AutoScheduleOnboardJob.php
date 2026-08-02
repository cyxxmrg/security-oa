<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 新员工入职自动排班 Job
 *
 * 在 User::created 观察者中 dispatch, 不阻塞用户创建接口
 * 自动从入职当天起 30 天排到默认班次 (is_default=true 的 Shift)
 *
 * 触发: UserScheduleObserver (User::created)
 * 队列: schedules (低优先级)
 */
class AutoScheduleOnboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    /**
     * 队列名 — 用 onQueue() 方法覆盖 (PHP 8.2+ 不能用 public $queue 同名属性)
     */
    public function onQueue(): string
    {
        return 'schedules';
    }

    public function __construct(
        public int $userId,
        public ?int $defaultShiftId = null,
        public int $days = 30,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning('AutoScheduleOnboardJob: user not found', ['user_id' => $this->userId]);
            return;
        }

        // 取默认班次 (is_default=true), 没传或查不到则用 code='day' 兜底
        $shift = $this->defaultShiftId
            ? Shift::find($this->defaultShiftId)
            : (Shift::where('is_default', true)->first() ?? Shift::where('code', 'day')->first());

        if (!$shift) {
            Log::warning('AutoScheduleOnboardJob: no default shift found', ['user_id' => $this->userId]);
            return;
        }

        $start = Carbon::today();
        $end   = $start->copy()->addDays($this->days - 1);
        $rows  = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $rows[] = [
                'user_id'    => $user->id,
                'shift_id'   => $shift->id,
                'date'       => $d->format('Y-m-d'),
                'status'     => 'scheduled',
                'created_by' => null, // 系统自动
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // 1000 条批量插入一次, 避免大 SQL
            if (count($rows) >= 1000) {
                DB::table('schedules')->insertOrIgnore($rows);
                $rows = [];
            }
        }
        if (!empty($rows)) {
            DB::table('schedules')->insertOrIgnore($rows);
        }

        Log::info('AutoScheduleOnboardJob done', [
            'user_id'  => $user->id,
            'shift_id' => $shift->id,
            'days'     => $this->days,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AutoScheduleOnboardJob permanently failed', [
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);
    }
}
