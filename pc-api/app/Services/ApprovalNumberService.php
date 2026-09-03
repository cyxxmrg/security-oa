<?php

namespace App\Services;

use App\Models\ApprovalRecord;

class ApprovalNumberService
{
    public static function next(string $prefix): string
    {
        $year = now()->format('Y');
        $value = NumberSequenceService::next(
            "approval:{$prefix}:{$year}",
            static function () use ($prefix, $year): int {
                return ApprovalRecord::where('code', 'like', "{$prefix}-{$year}-%")
                    ->pluck('code')
                    ->map(static function (string $code): int {
                        $parts = explode('-', $code);
                        return (int) end($parts);
                    })
                    ->max() ?? 0;
            }
        );

        return sprintf('%s-%s-%04d', $prefix, $year, $value);
    }
}
