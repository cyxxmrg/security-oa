<?php

namespace App\Support;

final class SensitiveDataRedactor
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd', 'oldpassword', 'newpassword', 'confirmpassword',
        'token', 'accesstoken', 'refreshtoken', 'authorization', 'cookie', 'secret',
        'apikey', 'privatekey', 'phonesuffix', 'idcard', 'identitynumber',
        'phone', 'mobile', 'email', 'address', 'suppliercode', 'driverlicense',
        'driverlicenseno', 'emergencycontact', 'emergencyphone', 'latitude', 'longitude',
        'bankaccount', 'accountno', 'cardnumber', 'basesalary', 'salaryallowance',
        'finalsalaryamount', 'leavebalancepayout', 'severancepay', 'totalsettlement',
    ];

    public static function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }
            $redacted[$key] = is_array($item) ? self::redact($item) : $item;
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));
        return in_array($normalized, self::SENSITIVE_KEYS, true);
    }
}
