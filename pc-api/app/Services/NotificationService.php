<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * 站内通知服务
 *
 * 设计目标:
 * - 业务代码可同步调用 send() 立即落库 (Controller 直接发)
 * - 大量通知通过 SendNotificationJob 异步发 (避免阻塞)
 * - 数据库直写, 不依赖第三方推送 (短信/邮件另走单独 Job)
 */
class NotificationService
{
    /**
     * 发送一条站内通知
     *
     * @param  int     $userId   接收用户
     * @param  string  $type     类型: system/approval/finance/schedule/export/...
     * @param  string  $title    标题
     * @param  string  $content  内容
     * @param  array   $payload  附加数据 (跳转链接 / 关联业务ID)
     */
    public function send(int $userId, string $type, string $title, string $content, array $payload = []): Notification
    {
        return Notification::create([
            'user_id'  => $userId,
            'type'     => $type,
            'title'    => $title,
            'content'  => $content,
            'payload'  => $payload,
            'is_read'  => false,
        ]);
    }

    /**
     * 批量发送给多个用户 (例: 审批通知给所有管理员)
     */
    public function sendMany(array $userIds, string $type, string $title, string $content, array $payload = []): int
    {
        $rows = [];
        $now  = now();
        foreach ($userIds as $uid) {
            $rows[] = [
                'user_id'    => $uid,
                'type'       => $type,
                'title'      => $title,
                'content'    => $content,
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'is_read'    => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                Notification::insert($rows);
                $rows = [];
            }
        }
        if (!empty($rows)) {
            Notification::insert($rows);
        }
        return count($userIds);
    }

    /**
     * 标记已读
     */
    public function markRead(int $userId, int|string $notificationId): bool
    {
        $count = Notification::where('user_id', $userId)
            ->where('id', $notificationId)
            ->update(['is_read' => true, 'read_at' => now()]);
        return $count > 0;
    }

    /**
     * 全部标已读
     */
    public function markAllRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * 获取未读数量
     */
    public function unreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)->where('is_read', false)->count();
    }
}
