<?php

namespace Tests\Unit\Auth;

use App\Support\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    public function test_it_redacts_nested_sensitive_keys_in_common_naming_styles(): void
    {
        $payload = [
            'username' => 'tester',
            'new_password' => 'secret-1',
            'phone' => '13800000000',
            'supplier_code' => 'SUP-001',
            'user' => [
                'id_card' => '330000000000000000',
                'accessToken' => 'secret-2',
                'profile' => ['bank_account' => '6222000000000000'],
            ],
        ];

        $result = SensitiveDataRedactor::redact($payload);

        $this->assertSame('tester', $result['username']);
        $this->assertSame('[REDACTED]', $result['new_password']);
        $this->assertSame('[REDACTED]', $result['phone']);
        $this->assertSame('[REDACTED]', $result['supplier_code']);
        $this->assertSame('[REDACTED]', $result['user']['id_card']);
        $this->assertSame('[REDACTED]', $result['user']['accessToken']);
        $this->assertSame('[REDACTED]', $result['user']['profile']['bank_account']);
    }
}
