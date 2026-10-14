<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/HorarioAtencion.php';

/**
 * Horario de atención que ve el propietario en el portal (HU-43).
 * Reemplaza el banner fijo de «24 horas», que no era cierto.
 */
class HorarioAtencionTest extends TestCase
{
    /** Lunes a viernes 8–12 y 14–18, sábado solo mañana, domingo cerrado. */
    private function filas(): array
    {
        $filas = [];
        foreach (range(1, 5) as $dia) {
            $filas[] = $this->fila($dia, 1, 1, 1);
        }
        $filas[] = $this->fila(6, 1, 1, 0);
        $filas[] = $this->fila(7, 0, 0, 0);
        return $filas;
    }

    private function fila(int $dia, int $activo, int $manana, int $tarde): array
    {
        return [
            'dia_semana' => $dia, 'activo' => $activo,
            'bloque_morning_activo' => $manana, 'bloque_afternoon_activo' => $tarde,
            'bloque_morning_inicio' => '08:00:00', 'bloque_morning_fin' => '12:00:00',
            'bloque_afternoon_inicio' => '14:00:00', 'bloque_afternoon_fin' => '18:00:00',
        ];
    }

    private function ahora(string $momento): DateTimeImmutable
    {
        return new DateTimeImmutable($momento, new DateTimeZone('America/Bogota'));
    }

    public function testLaSemanaSoloListaLasFranjasActivas(): void
    {
        $semana = HorarioAtencion::semana($this->filas());

        $this->assertCount(7, $semana);
        $this->assertSame('Lunes', $semana[1]['dia']);
        $this->assertCount(2, $semana[1]['franjas']);
        $this->assertSame([['inicio' => '08:00', 'fin' => '12:00']], $semana[6]['franjas']);
        $this->assertSame([], $semana[7]['franjas']);
    }

    public function testUnDiaSinFilaQuedaCerrado(): void
    {
        $semana = HorarioAtencion::semana([$this->fila(1, 1, 1, 1)]);
        $this->assertSame([], $semana[3]['franjas']);
    }

    public function testDentroDeUnaFranjaEstaAbiertaHastaSuCierre(): void
    {
        // 2026-09-21 es lunes.
        $estado = HorarioAtencion::ahora(HorarioAtencion::semana($this->filas()), $this->ahora('2026-09-21 09:30'));

        $this->assertTrue($estado['abierta']);
        $this->assertSame('Abierto ahora', $estado['texto']);
        $this->assertSame('Hasta las 12:00 p. m.', $estado['detalle']);
        $this->assertSame(1, $estado['hoy']);
    }

    public function testAlMediodiaAbreEnLaTardeDelMismoDia(): void
    {
        $estado = HorarioAtencion::ahora(HorarioAtencion::semana($this->filas()), $this->ahora('2026-09-21 12:30'));

        $this->assertFalse($estado['abierta']);
        $this->assertSame('Abre hoy a las 2:00 p. m.', $estado['detalle']);
    }

    public function testLaHoraDeCierreYaCuentaComoCerrado(): void
    {
        $estado = HorarioAtencion::ahora(HorarioAtencion::semana($this->filas()), $this->ahora('2026-09-21 18:00'));

        $this->assertFalse($estado['abierta']);
        $this->assertSame('Abre mañana a las 8:00 a. m.', $estado['detalle']);
    }

    public function testElSabadoEnLaNocheAbreElLunes(): void
    {
        // 2026-09-26 es sábado; el domingo está cerrado.
        $estado = HorarioAtencion::ahora(HorarioAtencion::semana($this->filas()), $this->ahora('2026-09-26 15:00'));

        $this->assertSame('Abre el lunes a las 8:00 a. m.', $estado['detalle']);
    }

    /** El servidor puede estar en UTC; la clínica atiende en hora de Colombia. */
    public function testUsaLaHoraDeColombiaAunqueLlegueEnUtc(): void
    {
        // 14:30 UTC = 9:30 a. m. en Bogotá.
        $utc = new DateTimeImmutable('2026-09-21 14:30', new DateTimeZone('UTC'));
        $this->assertTrue(HorarioAtencion::ahora(HorarioAtencion::semana($this->filas()), $utc)['abierta']);
    }

    public function testSinHorarioConfiguradoLoDice(): void
    {
        $estado = HorarioAtencion::ahora(HorarioAtencion::semana([]), $this->ahora('2026-09-21 09:30'));

        $this->assertFalse($estado['abierta']);
        $this->assertSame('Sin horario publicado', $estado['texto']);
    }

    public function testTextoDeLasFranjas(): void
    {
        $this->assertSame('Cerrado', HorarioAtencion::texto([]));
        $this->assertSame(
            '8:00 a. m. – 12:00 p. m. · 2:00 p. m. – 6:00 p. m.',
            HorarioAtencion::texto([['inicio' => '08:00', 'fin' => '12:00'], ['inicio' => '14:00', 'fin' => '18:00']])
        );
    }
    // ── M5 C4a (punto 6): la semana agrupada ──

    /** Semana con las franjas dadas por día (1 = lunes); los demás días, cerrados. */
    private function semanaCon(array $porDia): array
    {
        $semana = [];
        foreach (HorarioAtencion::DIAS as $n => $nombre) {
            $semana[$n] = ['dia' => $nombre, 'franjas' => $porDia[$n] ?? []];
        }
        return $semana;
    }

    private const PARTIDO = [['inicio' => '08:00', 'fin' => '12:00'], ['inicio' => '14:00', 'fin' => '18:00']];
    private const MANANA = [['inicio' => '08:00', 'fin' => '12:00']];

    public function testLosDiasSeguidosConElMismoHorarioVanEnUnaFila(): void
    {
        $semana = $this->semanaCon([1 => self::PARTIDO, 2 => self::PARTIDO, 3 => self::PARTIDO, 4 => self::PARTIDO, 5 => self::PARTIDO, 6 => self::MANANA]);

        $filas = HorarioAtencion::agrupar($semana, 2);

        $this->assertSame(['Lun–Vie', 'Sáb', 'Dom'], array_column($filas, 'dias'));
        $this->assertSame(['8:00–12:00 · 14:00–18:00', '8:00–12:00', 'Cerrado'], array_column($filas, 'texto'));
        $this->assertSame([true, false, false], array_column($filas, 'hoy'), 'El martes cae en Lun–Vie.');
        $this->assertSame([false, false, true], array_column($filas, 'cerrado'));
    }

    public function testUnDiaDistintoEnMedioPartLaSemana(): void
    {
        $semana = $this->semanaCon([1 => self::PARTIDO, 2 => self::PARTIDO, 3 => self::MANANA, 4 => self::PARTIDO, 5 => self::PARTIDO]);

        $filas = HorarioAtencion::agrupar($semana, 7);

        $this->assertSame(['Lun–Mar', 'Mié', 'Jue–Vie', 'Sáb–Dom'], array_column($filas, 'dias'), 'Solo se juntan días seguidos.');
        $this->assertSame('Cerrado', $filas[3]['texto']);
        $this->assertTrue($filas[3]['hoy']);
    }

    public function testTodaLaSemanaIgualOTodaCerradaEsUnaSolaFila(): void
    {
        $igual = array_fill_keys(range(1, 7), self::PARTIDO);
        $this->assertSame([['dias' => 'Lun–Dom', 'texto' => '8:00–12:00 · 14:00–18:00', 'cerrado' => false, 'hoy' => true]], HorarioAtencion::agrupar($this->semanaCon($igual), 4));

        $cerrada = HorarioAtencion::agrupar($this->semanaCon([]), 1);
        $this->assertSame([['dias' => 'Lun–Dom', 'texto' => 'Cerrado', 'cerrado' => true, 'hoy' => true]], $cerrada);
    }

    public function testLaHoraCortaNoPierdeLaMedianoche(): void
    {
        $this->assertSame('0:30–9:05', HorarioAtencion::textoCorto([['inicio' => '00:30', 'fin' => '09:05']]));
    }
}
