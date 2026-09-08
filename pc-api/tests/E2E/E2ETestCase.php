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
}
