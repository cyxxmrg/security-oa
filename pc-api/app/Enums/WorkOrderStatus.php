<?php

namespace App\Enums;

/**
 * V0.5.5 维修工单状态
 *
 * 7 状态 (v0.5.8 加 closed 兼容历史脏数据):
 *  - pending            待派单
 *  - assigned           已派单
 *  - in_progress        进行中
 *  - resolved           已解决
 *  - closed             已关闭 (兼容老数据, 等价 resolved)
 *  - cancelled          取消
 *  - converted_to_repair 转为返修（V0.5.5 关键：终态之一）
 */
enum WorkOrderStatus: string
{
    case PENDING             = 'pending';
    case ASSIGNED            = 'assigned';
    case IN_PROGRESS         = 'in_progress';
    case RESOLVED            = 'resolved';
    case CLOSED              = 'closed';
    case CANCELLED           = 'cancelled';
    case CONVERTED_TO_REPAIR = 'converted_to_repair';

    public function label(): string
    {
        return match($this) {
            self::PENDING             => '待派单',
            self::ASSIGNED            => '已派单',
            self::IN_PROGRESS         => '进行中',
            self::RESOLVED            => '已解决',
            self::CLOSED              => '已关闭',
            self::CANCELLED           => '已取消',
            self::CONVERTED_TO_REPAIR => '已转返修',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING             => 'info',
            self::ASSIGNED            => 'primary',
            self::IN_PROGRESS         => 'warning',
            self::RESOLVED            => 'success',
            self::CLOSED              => 'info',
            self::CANCELLED           => 'info',
            self::CONVERTED_TO_REPAIR => 'danger',
        };
    }

    /** 是否终态（不可再转换） */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::RESOLVED,
            self::CLOSED,
            self::CANCELLED,
            self::CONVERTED_TO_REPAIR,
        ], true);
    }

    /**
     * 允许的状态转换 (from => [to, ...])
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDING     => [self::ASSIGNED, self::CANCELLED],
            self::ASSIGNED    => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::RESOLVED, self::CLOSED, self::CONVERTED_TO_REPAIR, self::CANCELLED],
            default           => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
