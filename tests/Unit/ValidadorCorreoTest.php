<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';

/**
 * M5 C4a: el aviso del correo dice qué le falta. Los mismos casos y mensajes
 * se prueban en el navegador (M5C4aPortal.test.cjs, motivoCorreo).
 */
final class ValidadorCorreoTest extends TestCase
{
    public const CASOS = [
        'fabio' => 'Al correo le falta la @.',
        'fabio@' => 'Escribe el dominio completo, por ejemplo gmail.com.',
        '@gmail.com' => 'Escribe lo que va antes de la @.',
        'a@b@c.com' => 'El correo lleva una sola @.',
        'fabio@gmail' => 'Escribe el dominio completo, por ejemplo gmail.com.',
        'fabio@gmail.' => 'Escribe el dominio completo, por ejemplo gmail.com.',
        'fabio@.com' => 'Escribe el dominio completo, por ejemplo gmail.com.',
        'fabio@gmail.com' => null,
        'Fabio.Dueno@Zooki.Test' => null,
    ];

    public function testElAvisoDiceQueLeFaltaAlCorreo(): void
    {
        foreach (self::CASOS as $correo => $esperado) {
            $this->assertSame($esperado, ValidadorCuenta::correo($correo), $correo);
        }
    }

    public function testUnCorreoMasLargoQueElLimiteLoDice(): void
    {
        $largo = str_repeat('a', 250) . '@gmail.com';
        $this->assertSame('El correo admite hasta ' . ValidadorCuenta::CORREO_MAX . ' caracteres.', ValidadorCuenta::correo($largo));
    }
}
