<?php

namespace Tests\Unit\Auth;

use App\Support\TemporaryPassword;
use PHPUnit\Framework\TestCase;

class TemporaryPasswordTest extends TestCase
{
    public function test_it_generates_unique_passwords_matching_the_policy(): void
    {
        $passwords = array_map(fn () => TemporaryPassword::generate(), range(1, 10));

        $this->assertCount(10, array_unique($passwords));
        foreach ($passwords as $password) {
            $this->assertGreaterThanOrEqual(20, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
            $this->assertMatchesRegularExpression('/\d/', $password);
        }
    }
}
