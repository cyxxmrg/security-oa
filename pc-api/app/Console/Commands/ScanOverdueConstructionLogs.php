<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\RectificationDailyRequired;
use App\Models\User;
use App\Services\RectificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * V0.4.3 每日 22:00 扫描施工日志漏报
 *
 * 行为:
 *  1. 扫 rectification_daily_required 中 status=pending 且 work_date < today
 *  2. 状态置 overdue
 *  3. 发站内通知给: 项目负责人 + 团队 lead
 *  4. 发邮件给: 项目负责人
 *  5. 连续 3 天 overdue 自动触发整改流程 (V0.4.4 占位)
 */
class ScanOverdueConstructionLogs extends Command
{
    protected $signature = 'construction:scan-overdue-logs {--dry-run : 仅统计不修改}';

    protected $description = '每日 22:00 扫描施工日报漏报, 站内通知 + 邮件 + 连续 3 天触发整改';

    /**
     * 连续 overdue 触发整改的阈值 (天)
     */
    private const RECTIFICATION_THRESHOLD_DAYS = 3;

    public function handle(RectificationService $rectService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today  = now()->toDateString();

        $this->info("开始扫描施工日报漏报 (cutoff: {$today}, dry-run: " . ($dryRun ? 'yes' : 'no') . ")");

        // 1) 找出所有 pending 且 work_date < today 的记录
        $overdue = RectificationDailyRequired::with([
            'project:id,name,manager_id',
            'commencementOrder:id,code,team_id,project_id',
        ])
            ->where('status', RectificationDailyRequired::STATUS_PENDING)
            ->where('work_date', '<', $today)
            ->where('is_rectification', false)        // 只看正常日报, 整改日报另算
            ->get();

        $this->info("发现漏报记录: {$overdue->count()} 条");

        if ($overdue->isEmpty()) {
            return 0;
        }

        $stats = [
            'scanned'      => $overdue->count(),
            'marked'       => 0,
            'notified'     => 0,
            'rectified'    => 0,
            'errors'       => 0,
        ];

        foreach ($overdue as $req) {
            try {
                $this->processOne($req, $dryRun, $rectService, $stats);
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('ScanOverdueConstructionLogs error', [
                    'required_id' => $req->id,
                    'msg'         => $e->getMessage(),
                ]);
                $this->error("  ✗ #{$req->id}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            '扫描完成: scanned=%d marked=%d notified=%d rectified=%d errors=%d',
            $stats['scanned'],
            $stats['marked'],
            $stats['notified'],
            $stats['rectified'],
            $stats['errors'],
        ));

        return 0;
    }

    /**
     * 处理单条漏报
     */
    private function processOne(
        RectificationDailyRequired $req,
        bool $dryRun,
        RectificationService $rectService,
        array &$stats
    ): void {
        // 1) 状态置 overdue
        $stats['marked']++;
        if (!$dryRun) {
            $req->update([
                'status'       => RectificationDailyRequired::STATUS_OVERDUE,
                'overdue_days' => max(1, (int) now()->diffInDays($req->work_date)),
                'updated_at'   => now(),
            ]);
        }

        $project = $req->project;
        if (!$project) {
            return;
        }

        // 2) 接收方: 项目负责人 + 团队 lead
        $receivers = collect();

        if ($project->manager_id) {
            $manager = User::find($project->manager_id);
            if ($manager) {
                $receivers->push($manager);
            }
        }

        $team = $req->commencementOrder?->team_id
            ? \App\Models\ConstructionTeam::find($req->commencementOrder->team_id)
            : null;
        if ($team && $team->leader_user_id) {
            $leader = User::find($team->leader_user_id);
            if ($leader && !$receivers->contains('id', $leader->id)) {
                $receivers->push($leader);
            }
        }

        if ($receivers->isEmpty()) {
            $this->warn("  ! #{$req->id} 项目 {$project->name} 无负责人/团队 lead, 跳过通知");
            return;
        }

        // 3) 连续 overdue 累计检测
        $consecutiveOverdue = RectificationDailyRequired::where('project_id', $project->id)
            ->where('commencement_order_id', $req->commencement_order_id)
            ->where('is_rectification', false)
            ->where('status', RectificationDailyRequired::STATUS_OVERDUE)
            ->count();

        $needRectify = $consecutiveOverdue >= self::RECTIFICATION_THRESHOLD_DAYS;

        // 4) 发通知 + 邮件
        $title   = $needRectify
            ? "🚨 施工日报连续 {$consecutiveOverdue} 天漏报, 已自动触发整改"
            : "⚠️ 施工日报漏报提醒: {$req->work_date}";
        $content = $this->buildContent($req, $project, $consecutiveOverdue, $needRectify);

        foreach ($receivers as $u) {
            if ($dryRun) {
                $this->line("  [DRY] → {$u->name} ({$u->email}): {$title}");
                continue;
            }

            $this->sendDatabaseNotification($u, $title, $content, [
                'required_id'    => $req->id,
                'project_id'     => $project->id,
                'work_date'      => $req->work_date,
                'consecutive'    => $consecutiveOverdue,
                'rectify'        => $needRectify,
            ]);
            $stats['notified']++;

            // 邮件仅给项目负责人
            if ($u->id === $project->manager_id && $u->email) {
                try {
                    Mail::raw($content, function ($msg) use ($u, $title) {
                        $msg->to($u->email, $u->name)->subject($title);
                    });
                } catch (\Throwable $e) {
                    Log::warning('ScanOverdueConstructionLogs 邮件发送失败', [
                        'user_id' => $u->id,
                        'email'   => $u->email,
                        'msg'     => $e->getMessage(),
                    ]);
                }
            }
        }

        // 5) 连续 3 天 overdue → 触发整改
        if ($needRectify) {
            $stats['rectified']++;
            if (!$dryRun) {
                try {
                    $rectService->markOverdueAsRectification($req->id, 0);
                    $this->info("  ✓ #{$req->id} 已标记整改 (consecutive={$consecutiveOverdue})");
                } catch (\Throwable $e) {
                    Log::warning('ScanOverdueConstructionLogs 整改触发失败', [
                        'required_id' => $req->id,
                        'msg'         => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * 构建内容
     */
    private function buildContent(
        RectificationDailyRequired $req,
        Project $project,
        int $consecutiveOverdue,
        bool $needRectify
    ): string {
        $lines = [
            "项目「{$project->name}」在 {$req->work_date} 的施工日报未提交。",
            "",
            "项目 ID: {$project->id}",
            "漏报日期: {$req->work_date}",
            "开工单 ID: {$req->commencement_order_id}",
            "累计连续漏报: {$consecutiveOverdue} 天",
        ];
        if ($needRectify) {
            $lines[] = "";
            $lines[] = "已连续 " . self::RECTIFICATION_THRESHOLD_DAYS . " 天漏报, 系统已自动标记为整改任务 (V0.4.4 完善流程)。";
        }
        return implode("\n", $lines);
    }

    /**
     * 写数据库通知 (DatabaseNotification)
     *
     * 直接 DB insert, 避免强依赖 notifiable trait
     */
    private function sendDatabaseNotification(User $user, string $title, string $content, array $payload): void
    {
        try {
            DB::table('notifications')->insert([
                'type'            => 'construction_log_overdue',
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data'            => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'title'           => $title,
                'content'         => $content,
                'level'           => 'warning',
                'sender_id'       => null,
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 兜底: 通知表无 title/content 列也兼容
            DB::table('notifications')->insert([
                'type'            => 'construction_log_overdue',
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data'            => json_encode(array_merge($payload, [
                    'title'   => $title,
                    'content' => $content,
                ]), JSON_UNESCAPED_UNICODE),
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}
