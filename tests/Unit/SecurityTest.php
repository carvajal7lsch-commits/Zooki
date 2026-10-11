<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Security.php';

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Estas pruebas cubren la capa de sesion. Se desactiva el almacen en
        // base de datos que agrego HU-38 para que no toquen la base real ni
        // arrastren bloqueos de una corrida a la siguiente; esa capa tiene su
        // propia suite en tests/Integration/IntentoLoginTest.php.
        Security::definirAlmacenDeIntentos(null);
        Auditoria::definirProxiesConfiables([]);
    }

    protected function tearDown(): void
    {
        Auditoria::definirProxiesConfiables(null);
    }

    public function testCheckRateLimitAllowsInitially()
    {
        $this->assertTrue(Security::checkRateLimit());
    }

    public function testCheckRateLimitBlocksAfterMaxFailedLogins()
    {
        // D2.1 (RE-T.13.4): 20 fallos en 15 minutos desde una IP.
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue(Security::checkRateLimit());
            Security::recordFailedLogin();
        }

        // El intento 21 se rechaza.
        $this->assertFalse(Security::checkRateLimit());
        $this->assertArrayHasKey('error_login', $_SESSION);
        $this->assertStringContainsString('Demasiados intentos', $_SESSION['error_login']);
    }

    /** Un acceso correcto limpia el contador de la sesión (el de la IP en la base sigue: D21CorreccionTest). */
    public function testResetRateLimitClearsCounter()
    {
        for ($i = 0; $i < 20; $i++) {
            Security::recordFailedLogin();
        }

        $this->assertFalse(Security::checkRateLimit());

        Security::resetRateLimit();

        $this->assertTrue(Security::checkRateLimit());
    }
}
