<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ActividadCuenta.php';

/**
 * HU-42: actividad reciente de la propia cuenta en «Mi perfil».
 */
class ActividadCuentaTest extends TestCase
{
    public function testClasificaAccesosFallosYCambios(): void
    {
        $this->assertSame(
            ['tipo' => 'acceso', 'titulo' => 'Inicio de sesión con Google'],
            ActividadCuenta::describir(['accion' => 'LOGIN', 'descripcion' => 'Inicio de sesion con Google'])
        );
        $this->assertSame('Inicio de sesión con contraseña', ActividadCuenta::describir(['accion' => 'LOGIN', 'descripcion' => 'Inicio de sesión exitoso'])['titulo']);
        $this->assertSame('fallo', ActividadCuenta::describir(['accion' => 'LOGIN_FAIL', 'descripcion' => 'Intento de login fallido: credenciales incorrectas'])['tipo']);
        $this->assertSame('Intento de acceso con la cuenta inactiva', ActividadCuenta::describir(['accion' => 'LOGIN_FAIL', 'descripcion' => 'Intento de login fallido: cuenta inactiva'])['titulo']);
        $this->assertSame('Cambio de contraseña', ActividadCuenta::describir(['accion' => 'UPDATE', 'descripcion' => 'Cambio de contraseña'])['titulo']);
        $this->assertSame('Datos de contacto actualizados', ActividadCuenta::describir(['accion' => 'UPDATE', 'descripcion' => 'Datos de contacto actualizados por el propio usuario'])['titulo']);
    }

    /** La base en Docker guarda en UTC; se muestra en hora de Colombia. */
    public function testConvierteLaHoraDeLaBaseALaDeLaClinica(): void
    {
        $fecha = ActividadCuenta::aHoraClinica('2026-09-15 19:10:00', '+00:00');
        $this->assertSame('2026-09-15 14:10', $fecha->format('Y-m-d H:i'));

        $local = ActividadCuenta::aHoraClinica('2026-09-15 14:10:00', '-05:00');
        $this->assertSame('2026-09-15 14:10', $local->format('Y-m-d H:i'));
    }

    public function testNormalizaElDesfaseDeMysql(): void
    {
        $this->assertSame('+00:00', ActividadCuenta::normalizarDesfase('00:00:00'));
        $this->assertSame('-05:00', ActividadCuenta::normalizarDesfase('-05:00:00'));
        $this->assertSame('+00:00', ActividadCuenta::normalizarDesfase(null));
    }

    public function testElMomentoEsRelativoAHoy(): void
    {
        $zona = new DateTimeZone('America/Bogota');
        $ahora = new DateTimeImmutable('2026-09-15 16:00', $zona);

        $this->assertSame('Hoy, 2:10 p. m.', ActividadCuenta::momento(new DateTimeImmutable('2026-09-15 14:10', $zona), $ahora));
        $this->assertSame('Ayer, 9:00 a. m.', ActividadCuenta::momento(new DateTimeImmutable('2026-09-14 09:00', $zona), $ahora));
        $this->assertSame('vie 11 sep, 8:05 p. m.', ActividadCuenta::momento(new DateTimeImmutable('2026-09-11 20:05', $zona), $ahora));
    }

    public function testMiembroDesdeMuestraMesYAnio(): void
    {
        $this->assertSame('mayo de 2026', ActividadCuenta::mesYAnio(new DateTimeImmutable('2026-05-08 13:34')));
    }
}
