<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ReglaAtencion.php';

/**
 * RN-408 / RN-410 — Reglas de tiempo de la atención de una cita.
 *
 * Cita de referencia: 10 de septiembre, de 10:00 a 10:30, en curso.
 */
class ReglaAtencionTest extends TestCase
{
    private static function en(string $momento): DateTimeImmutable
    {
        return new DateTimeImmutable($momento, new DateTimeZone(ReglaAtencion::ZONA));
    }

    private static function cita(array $cambios = []): array
    {
        return array_merge([
            'fecha' => '2026-09-10',
            'hora' => '10:00:00',
            'hora_fin' => '10:30:00',
            'duracion_minutos' => 30,
            'estado' => 'en_curso',
            'aviso_atencion_abierta' => null,
        ], $cambios);
    }

    /** RN-408: iniciar antes de tiempo permitía abrir por error una atención que aún no llegaba. */
    public function testLaAtencionSeIniciaDesdeQuinceMinutosAntes(): void
    {
        $this->assertFalse(ReglaAtencion::puedeIniciar('2026-09-10', '10:00:00', self::en('2026-09-10 09:44:59')));
        $this->assertTrue(ReglaAtencion::puedeIniciar('2026-09-10', '10:00:00', self::en('2026-09-10 09:45:00')));
    }

    public function testUnPacienteQueLlegaTardeTodaviaSeAtiendeEseDia(): void
    {
        $this->assertTrue(ReglaAtencion::puedeIniciar('2026-09-10', '10:00:00', self::en('2026-09-10 17:30:00')));
    }

    public function testNoSeIniciaEnOtroDia(): void
    {
        $this->assertFalse(ReglaAtencion::puedeIniciar('2026-09-10', '10:00:00', self::en('2026-09-11 09:50:00')));
        // Una cita a las 00:05 no se inicia la noche anterior aunque falten 15 minutos.
        $this->assertFalse(ReglaAtencion::puedeIniciar('2026-09-11', '00:05:00', self::en('2026-09-10 23:55:00')));
    }

    /** RN-410: el aviso llega 10 minutos después de la hora de fin. */
    public function testSeAvisaDiezMinutosDespuesDeLaHoraDeFin(): void
    {
        $this->assertFalse(ReglaAtencion::debeAvisarAbierta(self::cita(), self::en('2026-09-10 10:39:59')));
        $this->assertTrue(ReglaAtencion::debeAvisarAbierta(self::cita(), self::en('2026-09-10 10:40:00')));
    }

    public function testSinHoraDeFinSeUsaLaDuracion(): void
    {
        $cita = self::cita(['hora_fin' => null, 'duracion_minutos' => 45]);
        $this->assertFalse(ReglaAtencion::debeAvisarAbierta($cita, self::en('2026-09-10 10:54:59')));
        $this->assertTrue(ReglaAtencion::debeAvisarAbierta($cita, self::en('2026-09-10 10:55:00')));
    }

    public function testElAvisoSeEnviaUnaSolaVez(): void
    {
        $cita = self::cita(['aviso_atencion_abierta' => '2026-09-10 10:40:00']);
        $this->assertFalse(ReglaAtencion::debeAvisarAbierta($cita, self::en('2026-09-10 12:00:00')));
    }

    public function testSoloSeAvisaDeAtencionesEnCurso(): void
    {
        foreach (['confirmada', 'completada', 'sin_cerrar', 'cerrada_sin_consulta'] as $estado) {
            $this->assertFalse(
                ReglaAtencion::debeAvisarAbierta(self::cita(['estado' => $estado]), self::en('2026-09-10 12:00:00')),
                "No debería avisarse de una cita $estado"
            );
        }
    }

    /** RN-410: la atención queda sin cerrar cuando termina el día de la cita, no antes. */
    public function testAlTerminarElDiaLaAtencionQuedaSinCerrar(): void
    {
        $this->assertFalse(ReglaAtencion::quedaSinCerrar(self::cita(), self::en('2026-09-10 23:59:59')));
        $this->assertTrue(ReglaAtencion::quedaSinCerrar(self::cita(), self::en('2026-09-11 00:00:00')));
    }

    public function testUnaCitaYaCerradaNoQuedaSinCerrar(): void
    {
        $this->assertFalse(ReglaAtencion::quedaSinCerrar(self::cita(['estado' => 'completada']), self::en('2026-09-12 08:00:00')));
    }
}
