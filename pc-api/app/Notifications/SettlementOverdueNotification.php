<?php

namespace App\Notifications;

use App\Models\ReferralSettlement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * 推荐人结算逾期提醒 (v0.3.14 P0)
 *
 * 触发：每日调度任务 oa:remind-overdue-settlements
 * 对象：所有 manager / finance / admin 角色用户
 * 渠道：database（站内信）
 * 频控：每个 settlement_id + 角色组组合 24h 内只发 1 次（避免重复打扰）
 */
class SettlementOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ReferralSettlement $settlement,
        public int $overdueDays,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): DatabaseMessage
    {
        $referrerName = $this->settlement->referrer?->name ?? "推荐人#{$this->settlement->referrer_id}";
        $oppName = $this->settlement->opportunity?->name ?? "商机#{$this->settlement->opportunity_id}";
        $title = "结算单逾期 {$this->overdueDays} 天待处理";
        $content = "推荐人「{$referrerName}」的居间费结算单（商机：{$oppName}，金额 ¥{$this->settlement->amount}）已挂起 {$this->overdueDays} 天，请尽快审核。";

        // 定制 schema：title/content 必须是顶级列（非空约束）
        // data 字段也保留完整 data 给前端用
        return new DatabaseMessage([
            'type'         => 'settlement_overdue',
            'title'        => $title,
            'content'      => $content,
            'level'        => $this->overdueDays >= 14 ? 'danger' : 'warning',
            'sender_id'    => null,
            'data'         => [
                'settlement_id'  => $this->settlement->id,
                'opportunity_id' => $this->settlement->opportunity_id,
                'referrer_id'    => $this->settlement->referrer_id,
                'amount'         => (string) $this->settlement->amount,
                'overdue_days'   => $this->overdueDays,
                'action_url'     => "/sales/settlements?id={$this->settlement->id}",
            ],
        ]);
    }

    /**
     * 频控：24h 内同一 settlement_id + 同一用户不重复发
     * 通过在 data 里带 last_remind_at + Notification::where('data->settlement_id', $id) 判断
     */
}
