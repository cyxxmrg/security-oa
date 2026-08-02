<?php

namespace App\Concerns;

trait GeneratesUniqueCode
{
    protected static function uniqueCode(string $prefix, int $randomDigits = 3): string
    {
        $datePart = now()->format('YmdHisv');
        $randomMin = 10 ** ($randomDigits - 1);
        $randomMax = (10 ** $randomDigits) - 1;

        return sprintf('%s-%s-%d', $prefix, $datePart, random_int($randomMin, $randomMax));
    }
}
