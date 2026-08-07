<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Csrf.php';

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset/mock session and post arrays
        $_SESSION = [];
        $_POST = [];
    }

    public function testTokenGeneratesNewTokenIfEmpty()
    {
        $token = Csrf::token('test_form');
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
        $this->assertEquals($token, $_SESSION['csrf_test_form']);
    }

    public function testTokenReusesExistingToken()
    {
        $token1 = Csrf::token('test_form');
        $token2 = Csrf::token('test_form');
        $this->assertEquals($token1, $token2);
    }

    public function testValidateReturnsTrueForValidToken()
    {
        $token = Csrf::token('test_form');
        $_POST['csrf_token'] = $token;

        $this->assertTrue(Csrf::validate('test_form'));
    }

    public function testValidateReturnsFalseForInvalidToken()
    {
        Csrf::token('test_form');
        $_POST['csrf_token'] = 'invalid_token';

        $this->assertFalse(Csrf::validate('test_form'));
    }

    public function testValidateReturnsFalseIfNoTokenProvided()
    {
        Csrf::token('test_form');
        $this->assertFalse(Csrf::validate('test_form'));
    }

    public function testRegenerateChangesTokenValue()
    {
        $token1 = Csrf::token('test_form');
        Csrf::regenerate('test_form');
        $token2 = Csrf::token('test_form');

        $this->assertNotEquals($token1, $token2);
    }
}
