<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public static function next(string $scope, ?Closure $currentMax = null): int
    {
        return DB::transaction(function () use ($scope, $currentMax): int {
            $sequence = DB::table('number_sequences')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $nextValue = $currentMax ? max(1, (int) $currentMax() + 1) : 1;
                DB::table('number_sequences')->insertOrIgnore([
                    'scope'      => $scope,
                    'next_value' => $nextValue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence = DB::table('number_sequences')
                    ->where('scope', $scope)
                    ->lockForUpdate()
                    ->first();
            }

            $value = (int) $sequence->next_value;
            DB::table('number_sequences')
                ->where('scope', $scope)
                ->update([
                    'next_value' => $value + 1,
                    'updated_at' => now(),
                ]);

            return $value;
        });
    }
}
