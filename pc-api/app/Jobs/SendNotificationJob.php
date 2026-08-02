<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 通用通知发送 Job
 *
 * 异步发送站内通知, 不阻塞业务请求
 * 失败重试 3 次, 退避指数 30s/2m/10m
 *
 * 用法: SendNotificationJob::dispatch($userId, $type, $title, $content, $payload);
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Job 失败前最大重试次数 */
    public int $tries = 3;

    /** 单次超时秒数 */
    public int $timeout = 30;

    /** 退避策略: 失败后 30s / 2min / 10min 重试 */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public string $content,
        public array $payload = [],
    ) {}

    public function handle(NotificationService $service): void
    {
        $service->send($this->userId, $this->type, $this->title, $this->content, $this->payload);
    }

    /**
     * 永久失败时记录日志, 不抛异常 (避免 Horizon 报警)
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SendNotificationJob permanently failed', [
            'user_id' => $this->userId,
            'type'    => $this->type,
            'title'   => $this->title,
            'error'   => $e->getMessage(),
        ]);
    }
}
