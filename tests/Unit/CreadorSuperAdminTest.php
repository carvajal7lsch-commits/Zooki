<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/CreadorSuperAdmin.php';

/**
 * Plan M0, §4.5 — Validación de los datos del super-administrador antes de
 * tocar la base. La creación en sí se prueba contra MySQL en BaseV2MysqlTest.
 */
class CreadorSuperAdminTest extends TestCase
{
    private const CLAVE_VALIDA = 'Plataforma#Vet2026';

    public function testAceptaDatosCompletosYUnaContrasenaQueCumpleLaPolitica(): void
    {
        $this->assertNull(CreadorSuperAdmin::validar('operador@zooki.test', 'Operador Zooki', self::CLAVE_VALIDA));
    }

    public function testRechazaUnCorreoInvalido(): void
    {
        $this->assertNotNull(CreadorSuperAdmin::validar('no-es-correo', 'Operador Zooki', self::CLAVE_VALIDA));
        $this->assertNotNull(CreadorSuperAdmin::validar('', 'Operador Zooki', self::CLAVE_VALIDA));
    }

    public function testExigeElNombre(): void
    {
        $this->assertNotNull(CreadorSuperAdmin::validar('operador@zooki.test', '', self::CLAVE_VALIDA));
        $this->assertNotNull(CreadorSuperAdmin::validar('operador@zooki.test', str_repeat('a', CreadorSuperAdmin::NOMBRE_MAX + 1), self::CLAVE_VALIDA));
    }

    /** RN-G10: la misma política que el resto de las cuentas. */
    public function testAplicaLaPoliticaDeContrasenas(): void
    {
        $this->assertNotNull(CreadorSuperAdmin::validar('operador@zooki.test', 'Operador Zooki', 'corta1!'));
        $this->assertNotNull(CreadorSuperAdmin::validar('operador@zooki.test', 'Operador Zooki', ''));
    }

    public function testNoAceptaElCorreoComoContrasena(): void
    {
        $this->assertNotNull(CreadorSuperAdmin::validar('Operador.Zooki2026@zooki.test', 'Operador', 'Operador.Zooki2026@zooki.test'));
    }
}
