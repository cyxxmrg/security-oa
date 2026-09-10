<?php

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;

abstract class E2ETestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::requireMutationOptIn();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::requireMutationOptIn();
    }

    protected static function requireMutationOptIn(): void
    {
        if (getenv('OA_E2E_ALLOW_MUTATION') !== '1') {
            throw new \RuntimeException(
                'E2E tests mutate the configured OA instance and Redis. '
                . 'Set OA_E2E_ALLOW_MUTATION=1 only for an isolated test deployment.'
            );
        }
    }

    /**
     * V1.4.5 (REVIEW P2-7/P1-4): E2E 账号密码从环境变量读取, 不硬编码入库。
     * 未配置时返回 '' (调用方应 markTestSkipped, 避免 CI 无凭据时失败)。
     */
    protected static function e2ePass(string $envKey): string
    {
        $v = getenv($envKey);
        return ($v === false || $v === '') ? '' : $v;
    }
}
