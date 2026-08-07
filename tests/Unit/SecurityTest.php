<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Security.php';

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testCheckRateLimitAllowsInitially()
    {
        $this->assertTrue(Security::checkRateLimit());
    }

    public function testCheckRateLimitBlocksAfterMaxFailedLogins()
    {
        // Max attempts is 5
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(Security::checkRateLimit());
            Security::recordFailedLogin();
        }

        // 6th attempt should fail
        $this->assertFalse(Security::checkRateLimit());
        $this->assertArrayHasKey('error_login', $_SESSION);
        $this->assertStringContainsString('Demasiados intentos', $_SESSION['error_login']);
    }

    public function testResetRateLimitClearsCounter()
    {
        for ($i = 0; $i < 5; $i++) {
            Security::recordFailedLogin();
        }

        $this->assertFalse(Security::checkRateLimit());

        Security::resetRateLimit();

        $this->assertTrue(Security::checkRateLimit());
    }
}
