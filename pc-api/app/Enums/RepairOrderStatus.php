<?php

namespace App\Enums;

/**
 * V0.5.5 返修单状态
 *
 * 8 状态 (v0.5.8 加 shipped_back 兼容历史脏数据, 等价 SENT_BACK):
 *  - received         接件 (默认入口, 含工单转单)
 *  - sent_for_repair  寄修 (去程)
 *  - in_repair        维修中
 *  - repaired         修好
 *  - sent_back        寄回 (回程)
 *  - shipped_back     寄回 (兼容老数据)
 *  - closed           关闭
 *  - cancelled        取消
 */
enum RepairOrderStatus: string
{
    case RECEIVED         = 'received';
    case SENT_FOR_REPAIR  = 'sent_for_repair';
    case IN_REPAIR        = 'in_repair';
    case REPAIRED         = 'repaired';
    case SENT_BACK        = 'sent_back';
    case SHIPPED_BACK     = 'shipped_back';  // v0.5.8 兼容老数据
    case CLOSED           = 'closed';
    case CANCELLED        = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::RECEIVED         => '已接件',
            self::SENT_FOR_REPAIR  => '寄修中',
            self::IN_REPAIR        => '维修中',
            self::REPAIRED         => '已修好',
            self::SENT_BACK        => '寄回中',
            self::SHIPPED_BACK     => '寄回中',
            self::CLOSED           => '已关闭',
            self::CANCELLED        => '已取消',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::RECEIVED         => 'primary',
            self::SENT_FOR_REPAIR  => 'warning',
            self::IN_REPAIR        => 'warning',
            self::REPAIRED         => 'success',
            self::SENT_BACK        => 'warning',
            self::SHIPPED_BACK     => 'warning',
            self::CLOSED           => 'info',
            self::CANCELLED        => 'info',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CLOSED, self::CANCELLED], true);
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::RECEIVED         => [self::SENT_FOR_REPAIR, self::CANCELLED],
            self::SENT_FOR_REPAIR  => [self::IN_REPAIR, self::REPAIRED, self::CANCELLED], // 直接 SENT_FOR_REPAIR → REPAIRED: 厂家直接修好未在系统中标 in_repair
            self::IN_REPAIR        => [self::REPAIRED, self::CANCELLED],
            self::REPAIRED         => [self::SENT_BACK, self::SHIPPED_BACK, self::CLOSED, self::CANCELLED],
            self::SENT_BACK        => [self::CLOSED, self::CANCELLED],
            self::SHIPPED_BACK     => [self::CLOSED, self::CANCELLED],
            default                => [],
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
