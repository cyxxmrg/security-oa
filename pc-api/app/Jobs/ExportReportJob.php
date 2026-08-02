<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 通用报表导出 Job
 *
 * 异步生成 CSV/Excel 报表, 写完成后通知发起人
 * 适合大数据量导出, 避免请求超时
 *
 * 用法: ExportReportJob::dispatch($userId, $reportType, $params);
 */
class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; // 10 分钟上限

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public int $userId,
        public string $reportType,
        public array $params = [],
        public ?string $callbackClass = null, // 回调类名 (如 App\Exports\AttendanceExport)
    ) {}

    public function handle(): void
    {
        Log::info('ExportReportJob started', [
            'user_id' => $this->userId,
            'report'  => $this->reportType,
        ]);

        // 写生成进度
        DB::table('export_tasks')->updateOrInsert(
            ['user_id' => $this->userId, 'report_type' => $this->reportType, 'status' => 'running'],
            ['started_at' => now(), 'params' => json_encode($this->params), 'updated_at' => now()],
        );

        try {
            // 调用具体的报表导出类
            $exporter = app($this->callbackClass ?: "\\App\\Exports\\" . ucfirst($this->reportType) . 'Export');
            $filePath = $exporter->export($this->params);

            DB::table('export_tasks')->where('user_id', $this->userId)
                ->where('report_type', $this->reportType)
                ->update([
                    'status'       => 'done',
                    'file_path'    => $filePath,
                    'finished_at'  => now(),
                    'updated_at'   => now(),
                ]);

            // 通知发起人
            SendNotificationJob::dispatch(
                $this->userId,
                'export',
                '报表已生成',
                "您的 {$this->reportType} 报表已生成, 点击下载",
                ['file_path' => $filePath, 'report_type' => $this->reportType],
            );
        } catch (\Throwable $e) {
            DB::table('export_tasks')->where('user_id', $this->userId)
                ->where('report_type', $this->reportType)
                ->update([
                    'status'      => 'failed',
                    'error'       => $e->getMessage(),
                    'finished_at' => now(),
                    'updated_at'  => now(),
                ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ExportReportJob permanently failed', [
            'user_id' => $this->userId,
            'report'  => $this->reportType,
            'error'   => $e->getMessage(),
        ]);
    }
}
