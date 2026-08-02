<?php

namespace App\Jobs;

use App\Models\ApprovalRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 审批超时自动提醒 Job
 *
 * 调度: 每天早上 9:00 由 scheduler 触发
 * 扫描 24 小时内未审批的记录, 给审批人发提醒通知
 *
 * 用法: $schedule->job(new ApprovalTimeoutReminderJob)->dailyAt('09:00');
 */
class ApprovalTimeoutReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    /** 24 小时内未审批的算超时 */
    public int $timeoutHours = 24;

    public function handle(): void
    {
        $threshold = now()->subHours($this->timeoutHours);

        $pending = ApprovalRecord::where('status', ApprovalRecord::STATUS_PENDING)
            ->where('created_at', '<=', $threshold)
            ->whereNotNull('current_approver_id')
            ->get();

        $count = 0;
        foreach ($pending as $record) {
            if (!$record->current_approver_id) {
                continue;
            }

            SendNotificationJob::dispatch(
                $record->current_approver_id,
                'approval_timeout',
                '审批超时提醒',
                "申请 {$record->code} 已等待超过 {$this->timeoutHours} 小时, 请尽快处理",
                [
                    'approval_id' => $record->id,
                    'code'        => $record->code,
                    'title'       => $record->title,
                ],
            );
            $count++;
        }

        Log::info('ApprovalTimeoutReminderJob done', [
            'pending_count' => $pending->count(),
            'notified'      => $count,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ApprovalTimeoutReminderJob failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
