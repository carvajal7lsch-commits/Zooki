<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ResumenPanel.php';

/**
 * Lógica de los paneles de inicio (HU-18, HU-20, HU-57): qué acción ofrece
 * cada cita, quién es el siguiente paciente y cómo se resumen los datos.
 */
class ResumenPanelTest extends TestCase
{
    private function ahora(string $momento): DateTimeImmutable
    {
        return new DateTimeImmutable($momento, new DateTimeZone('America/Bogota'));
    }

    private function cita(string $hora, string $estado, string $fecha = '2026-09-15'): array
    {
        return ['id_cita' => 1, 'fecha' => $fecha, 'hora' => $hora, 'hora_fin' => null, 'duracion_minutos' => 30, 'estado' => $estado];
    }

    public function testUnaAtencionAbiertaSeContinua(): void
    {
        $ahora = $this->ahora('2026-09-15 10:00');
        $this->assertSame('continuar', ResumenPanel::accion($this->cita('08:00:00', 'en_curso'), $ahora)['tipo']);
        $this->assertSame('continuar', ResumenPanel::accion($this->cita('08:00:00', 'sin_cerrar', '2026-09-10'), $ahora)['tipo']);
    }

    /** RN-408: se inicia desde 15 minutos antes; antes se dice desde qué hora. */
    public function testLaAtencionSeIniciaDesdeQuinceMinutosAntes(): void
    {
        $cita = $this->cita('10:30:00', 'confirmada');

        $antes = ResumenPanel::accion($cita, $this->ahora('2026-09-15 10:00'));
        $this->assertSame('esperar', $antes['tipo']);
        $this->assertSame('10:15', $antes['desde']);

        $this->assertSame('iniciar', ResumenPanel::accion($cita, $this->ahora('2026-09-15 10:15'))['tipo']);
    }

    public function testSinAccionParaOtroDiaOCitasCerradas(): void
    {
        $ahora = $this->ahora('2026-09-15 10:00');
        $this->assertNull(ResumenPanel::accion($this->cita('10:30:00', 'pendiente', '2026-09-16'), $ahora)['tipo']);
        foreach (['completada', 'cancelada', 'no_asistio', 'cerrada_sin_consulta'] as $estado) {
            $this->assertNull(ResumenPanel::accion($this->cita('10:30:00', $estado), $ahora)['tipo'], $estado);
        }
    }

    public function testElSiguientePacienteEsLaAtencionEnCurso(): void
    {
        $citas = [$this->cita('09:00:00', 'confirmada'), $this->cita('11:00:00', 'en_curso')];
        $this->assertSame('11:00:00', ResumenPanel::siguiente($citas, $this->ahora('2026-09-15 08:00'))['hora']);
    }

    public function testElSiguientePacienteSaltaLasCitasQueYaTerminaron(): void
    {
        $citas = [
            $this->cita('08:00:00', 'confirmada'),
            $this->cita('09:00:00', 'completada'),
            $this->cita('10:00:00', 'pendiente'),
        ];
        $this->assertSame('10:00:00', ResumenPanel::siguiente($citas, $this->ahora('2026-09-15 09:45'))['hora']);
        $this->assertNull(ResumenPanel::siguiente($citas, $this->ahora('2026-09-15 10:30')));
    }

    public function testLosContadoresNoCuentanLasCanceladas(): void
    {
        $c = ResumenPanel::contadores([
            $this->cita('08:00:00', 'completada'),
            $this->cita('09:00:00', 'confirmada'),
            $this->cita('10:00:00', 'en_curso'),
            $this->cita('11:00:00', 'no_asistio'),
            $this->cita('12:00:00', 'cancelada'),
        ]);
        $this->assertSame(['citas' => 4, 'atendidas' => 1, 'por_atender' => 2, 'no_asistio' => 1], $c);
    }

    /** HU-20: los recordatorios se agrupan por día, en orden. */
    public function testLosRecordatoriosSeAgrupanPorDia(): void
    {
        $grupos = ResumenPanel::agruparPorDia([
            ['fecha' => '2026-09-18', 'mascota' => 'Toby'],
            ['fecha' => '2026-09-15', 'mascota' => 'Luna'],
            ['fecha' => '2026-09-16', 'mascota' => 'Max'],
            ['fecha' => '2026-09-15', 'mascota' => 'Rocky'],
        ], $this->ahora('2026-09-15 07:00'));

        $this->assertSame(['Hoy', 'Mañana', 'vie 18 sep'], array_column($grupos, 'etiqueta'));
        $this->assertCount(2, $grupos[0]['items']);
    }

    public function testLaVariacionNecesitaUnaBase(): void
    {
        $this->assertNull(ResumenPanel::variacion(12, 0));
        $this->assertSame(20, ResumenPanel::variacion(12, 10));
        $this->assertSame(-50, ResumenPanel::variacion(5, 10));
    }

    /** La tendencia muestra los seis meses aunque alguno no tenga citas. */
    public function testLaSerieMensualCompletaLosMesesVacios(): void
    {
        $serie = ResumenPanel::serieMensual(
            [['mes' => '2025-12', 'atendidas' => '4', 'no_asistidas' => '1']],
            $this->ahora('2026-02-10 09:00')
        );

        $this->assertSame(['sep', 'oct', 'nov', 'dic', 'ene', 'feb'], array_column($serie, 'etiqueta'));
        $this->assertSame(4, $serie[3]['atendidas']);
        $this->assertSame(0, $serie[5]['no_asistidas']);
    }

    public function testLaHoraSeMuestraEnFormatoDoceHoras(): void
    {
        $this->assertSame('2:05 p. m.', ResumenPanel::hora('14:05:00'));
        $this->assertSame('12:30 a. m.', ResumenPanel::hora('00:30'));
        $this->assertSame('12:00 p. m.', ResumenPanel::hora('12:00:00'));
        $this->assertSame('—', ResumenPanel::hora(null));
    }
}
