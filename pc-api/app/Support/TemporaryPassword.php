<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TemporaryPassword
{
    public static function generate(int $length = 20): string
    {
        // Explicit suffix guarantees the configured letter + digit requirement.
        return Str::password(max(10, $length - 2)) . 'A1';
    }
}
