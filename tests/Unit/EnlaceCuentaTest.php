<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/EnlaceCuenta.php';

final class EnlaceCuentaTest extends TestCase
{
    public function testElDominioConfiguradoPrevaleceSobreUnHostManipulado(): void
    {
        $this->assertSame('https://zooki.test/index.php',EnlaceCuenta::validarBase('https://zooki.test/','atacante.test','/index.php',false));
        $this->assertSame('http://127.0.0.1:8097/index.php',EnlaceCuenta::validarBase('','127.0.0.1:8097','/index.php',false));
        $this->assertSame('http://localhost/zooki/index.php',EnlaceCuenta::validarBase('','localhost','/zooki/index.php',false));
    }
    public function testSinUrlConfiguradaNoSeEnviaUnSecretoAHostArbitrario(): void
    {
        $this->expectException(RuntimeException::class);
        EnlaceCuenta::validarBase('','atacante.test','/index.php',true);
    }
    public function testLaUrlBaseNoAdmiteCredencialesNiProtocolosInseguros(): void
    {
        foreach (['javascript:alert(1)','https://usuario:clave@zooki.test/','https://zooki.test/?token=otro'] as $base) {
            try { EnlaceCuenta::validarBase($base,'localhost','/index.php',false); $this->fail('Debió rechazar'); }
            catch (RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
    }
}
