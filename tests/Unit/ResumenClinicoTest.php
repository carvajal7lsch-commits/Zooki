<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ResumenClinico.php';

/**
 * Resumen clínico de la pantalla de atención: qué se le avisa al veterinario
 * antes de atender (dosis vencidas o próximas, última consulta, último peso).
 */
class ResumenClinicoTest extends TestCase
{
    private DateTimeImmutable $hoy;

    protected function setUp(): void
    {
        $this->hoy = new DateTimeImmutable('2026-09-10');
    }

    public function testUnaVacunaConFechaProximaPasadaEstaVencida(): void
    {
        $resumen = ResumenClinico::construir([], [
            ['nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2025-08-01', 'fecha_proxima_dosis' => '2026-08-01'],
        ], [], $this->hoy);

        $this->assertCount(1, $resumen['alertas']);
        $this->assertSame('vencida', $resumen['alertas'][0]['estado']);
        $this->assertSame('Rabia', $resumen['alertas'][0]['nombre']);
    }

    /** Si ya se aplicó el refuerzo, la dosis anterior no cuenta como vencida. */
    public function testSoloCuentaLaAplicacionMasReciente(): void
    {
        $resumen = ResumenClinico::construir([], [
            ['nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2025-08-01', 'fecha_proxima_dosis' => '2026-08-01'],
            ['nombre_vacuna' => 'rabia', 'fecha_aplicacion' => '2026-08-02', 'fecha_proxima_dosis' => '2027-08-02'],
        ], [], $this->hoy);

        $this->assertSame([], $resumen['alertas']);
    }

    public function testDosisDentroDeTreintaDiasEsProximaYMasLejanaNoAvisa(): void
    {
        $resumen = ResumenClinico::construir([], [], [
            ['tipo' => 'interna', 'fecha_aplicacion' => '2026-06-20', 'fecha_proxima' => '2026-09-20'],
            ['tipo' => 'externa', 'fecha_aplicacion' => '2026-09-01', 'fecha_proxima' => '2026-12-01'],
        ], $this->hoy);

        $this->assertCount(1, $resumen['alertas']);
        $this->assertSame('proxima', $resumen['alertas'][0]['estado']);
        $this->assertSame('desparasitacion', $resumen['alertas'][0]['clase']);
    }

    public function testLasVencidasVanPrimero(): void
    {
        $resumen = ResumenClinico::construir([], [
            ['nombre_vacuna' => 'Parvovirus', 'fecha_aplicacion' => '2025-09-15', 'fecha_proxima_dosis' => '2026-09-15'],
            ['nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2025-08-01', 'fecha_proxima_dosis' => '2026-08-01'],
        ], [], $this->hoy);

        $this->assertSame(['vencida', 'proxima'], array_column($resumen['alertas'], 'estado'));
    }

    public function testUltimaConsultaYUltimoPesoRegistrado(): void
    {
        $resumen = ResumenClinico::construir([
            ['fecha_hora' => '2026-09-01 10:00:00', 'diagnostico' => 'Otitis', 'veterinario' => 'Ana', 'peso' => null],
            ['fecha_hora' => '2026-05-01 10:00:00', 'diagnostico' => 'Control', 'veterinario' => 'Ana', 'peso' => '12.40'],
        ], [], [], $this->hoy);

        $this->assertSame('Otitis', $resumen['ultima_consulta']['diagnostico']);
        $this->assertSame(12.4, $resumen['ultimo_peso']);
    }

    public function testSinConsultasNoHayResumenDeConsulta(): void
    {
        $resumen = ResumenClinico::construir([], [], [], $this->hoy);

        $this->assertNull($resumen['ultima_consulta']);
        $this->assertNull($resumen['ultimo_peso']);
    }

    public function testEdadLegible(): void
    {
        $this->assertSame('3 años', ResumenClinico::edadLegible('2023-01-10', $this->hoy));
        $this->assertSame('5 meses', ResumenClinico::edadLegible('2026-04-01', $this->hoy));
        $this->assertSame('Desconocida', ResumenClinico::edadLegible(null, $this->hoy));
    }
}
