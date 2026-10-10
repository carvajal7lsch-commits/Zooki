<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../config/Database.php';

final class DatabasePruebasTest extends TestCase
{
    public function testLaConexionImplicitaDeLaAplicacionEstaProhibida(): void
    {
        $this->expectException(RuntimeException::class);
        new Database();
    }

    public function testUnaConfiguracionDeLaBaseDeTrabajoEstaProhibida(): void
    {
        $this->expectException(RuntimeException::class);
        new Database(['base' => 'zooki_v2_prueba']);
    }

    public function testElBootstrapNoPermiteEscriturasImplicitasDeIntentos(): void
    {
        Security::definirAlmacenDeIntentos(null);
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        Security::recordFailedLogin();
        $this->assertTrue(Security::checkVerificationLimit());
        $this->assertSame(1, $_SESSION['rate_limit_127.0.0.1']['count']);
        $_SESSION = [];
    }
}
