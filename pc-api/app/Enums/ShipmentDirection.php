<?php

namespace App\Enums;

/**
 * V0.5.5 物流方向 (单表 + direction 区分)
 */
enum ShipmentDirection: string
{
    case OUTBOUND = 'outbound';  // 去程 - 寄出
    case INBOUND  = 'inbound';   // 回程 - 寄回

    public function label(): string
    {
        return match($this) {
            self::OUTBOUND => '去程 (寄出)',
            self::INBOUND  => '回程 (寄回)',
        };
    }
}
