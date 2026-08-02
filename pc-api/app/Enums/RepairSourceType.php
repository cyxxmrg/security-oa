<?php

namespace App\Enums;

/**
 * V0.5.5 返修单来源
 */
enum RepairSourceType: string
{
    case CUSTOMER   = 'customer';    // 客户送修
    case WORK_ORDER = 'work_order';  // 维修工单转单
    case INTERNAL   = 'internal';    // 公司内部送修

    public function label(): string
    {
        return match($this) {
            self::CUSTOMER   => '客户送修',
            self::WORK_ORDER => '维修工单',
            self::INTERNAL   => '内部送修',
        };
    }
}
