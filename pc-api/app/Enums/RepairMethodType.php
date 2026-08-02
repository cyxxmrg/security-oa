<?php

namespace App\Enums;

/**
 * V0.5.5 维修方式 (4 选 1, 含 1 退回)
 *
 * 关键: 决定结算和成本归集
 */
enum RepairMethodType: string
{
    case FREE_WARRANTY  = 'free_warranty';   // 免费 - 保内
    case FREE_CONTRACT  = 'free_contract';   // 免费 - 合同
    case PAID_REPAIR    = 'paid_repair';     // 付费 - 维修
    case PAID_REPLACE   = 'paid_replace';    // 付费 - 换新
    case RETURNED       = 'returned';        // 退回 - 不修

    public function label(): string
    {
        return match($this) {
            self::FREE_WARRANTY  => '🆓 免费（保内）',
            self::FREE_CONTRACT  => '🆓 免费（合同）',
            self::PAID_REPAIR    => '💰 付费（维修）',
            self::PAID_REPLACE   => '💰 付费（换新）',
            self::RETURNED       => '↩️ 退回（不修）',
        };
    }

    public function isFree(): bool
    {
        return in_array($this, [self::FREE_WARRANTY, self::FREE_CONTRACT, self::RETURNED], true);
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::PAID_REPAIR, self::PAID_REPLACE], true);
    }

    /** 是否需要费用 (收费才计) */
    public function requiresCost(): bool
    {
        return $this->isPaid();
    }
}
