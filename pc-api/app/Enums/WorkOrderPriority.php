<?php

namespace App\Enums;

/**
 * V0.5.5 维修工单优先级
 */
enum WorkOrderPriority: string
{
    case LOW      = 'low';
    case MEDIUM   = 'medium';
    case HIGH     = 'high';
    case URGENT   = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::LOW    => '低',
            self::MEDIUM => '中',
            self::HIGH   => '高',
            self::URGENT => '紧急',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOW    => 'info',
            self::MEDIUM => 'primary',
            self::HIGH   => 'warning',
            self::URGENT => 'danger',
        };
    }

    /** 优先级转返修单的 severity */
    public function toSeverity(): string
    {
        return match($this) {
            self::LOW, self::MEDIUM => 'low',
            self::HIGH              => 'medium',
            self::URGENT            => 'high',
        };
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
