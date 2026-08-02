<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 慢接口告警 Job (P2-3 增量)
 *
 * 触发: PerformanceMonitor 中间件检测到 critical 级别慢接口
 * 行为:
 *  1) 必写 Log::error
 *  2) 可选发钉钉/企业微信机器人 (默认关闭, 配 env 开启)
 *
 * 关闭推送的原因: 误报刷屏风险高
 *  开启方式: 配置 SLOW_ALERT_WEBHOOK_URL 即可
 */
class SlowRequestAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;   // 告警不重试, 失败就丢
    public int $timeout = 10; // 短超时, 阻塞不了别人

    /**
     * 队列名 — 用 onQueue() 方法 (避免 public $queue 与 trait 冲突, PHP 8.2 严格类型)
     */
    public function onQueue(): string
    {
        return 'default';
    }

    public function __construct(
        public array $record,
        public string $level, // 'critical' / 'slow' / 'warn'
    ) {}

    public function handle(): void
    {
        // 1) 必写日志 (结构化 JSON)
        Log::error('SLOW_REQUEST_ALERT ' . json_encode($this->record, JSON_UNESCAPED_UNICODE));

        // 2) 可选推送钉钉/企业微信
        $webhook = config('oa.alert_webhook', ''); // V1.2.10 走 config 避免 env 缓存失效
        if (!$webhook) {
            return; // 未配置就不发
        }

        $msg = sprintf(
            "🐢 慢接口告警 [%s]\n• %s %s\n• 耗时: %dms\n• 状态: %d\n• 用户: %s\n• IP: %s",
            strtoupper($this->level),
            $this->record['method'] ?? '?',
            $this->record['path']   ?? '?',
            $this->record['ms']     ?? 0,
            $this->record['status'] ?? 0,
            $this->record['user_id'] ?? 'guest',
            $this->record['ip']      ?? '-',
        );

        try {
            $this->sendWebhook($webhook, $msg);
        } catch (\Throwable $e) {
            Log::warning('SlowRequestAlert webhook failed', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * 钉钉 / 企业微信 / 飞书 通用 webhook 推送
     */
    private function sendWebhook(string $url, string $text): void
    {
        // 自动判断平台: 钉钉 sign / 企业微信 markdown
        $payload = str_contains($url, 'oapi.dingtalk.com')
            ? ['msgtype' => 'text', 'text' => ['content' => $text]]
            : ['msgtype' => 'markdown', 'markdown' => ['content' => $text]];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new \RuntimeException("webhook $code: $resp");
        }
    }
}
