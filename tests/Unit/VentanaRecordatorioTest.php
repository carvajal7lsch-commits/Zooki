<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/VentanaRecordatorio.php';

/**
 * Ventana de los recordatorios de dosis (RN-303, RE-37.1, RE-37.3).
 */
class VentanaRecordatorioTest extends TestCase
{
    public function testElPrimerAvisoCubreDelDiaSieteAlDos(): void
    {
        $this->assertSame(VentanaRecordatorio::PRIMER_AVISO, VentanaRecordatorio::aviso('2026-09-29', '2026-09-22'));
        $this->assertSame(VentanaRecordatorio::PRIMER_AVISO, VentanaRecordatorio::aviso('2026-09-24', '2026-09-22'));
    }

    /** RE-37.1: si la tarea no corrió el día anterior, el aviso final sale el mismo día. */
    public function testElUltimoAvisoCubreElDiaAnteriorYElMismoDia(): void
    {
        $this->assertSame(VentanaRecordatorio::ULTIMO_AVISO, VentanaRecordatorio::aviso('2026-09-23', '2026-09-22'));
        $this->assertSame(VentanaRecordatorio::ULTIMO_AVISO, VentanaRecordatorio::aviso('2026-09-22', '2026-09-22'));
    }

    public function testFueraDeLaVentanaNoHayAviso(): void
    {
        $this->assertNull(VentanaRecordatorio::aviso('2026-09-30', '2026-09-22'));
        $this->assertNull(VentanaRecordatorio::aviso('2026-09-21', '2026-09-22'));
    }

    public function testLaVentanaTerminaSieteDiasDespues(): void
    {
        $this->assertSame('2026-10-02', VentanaRecordatorio::hasta('2026-09-25'));
    }

    /** RE-37.3: a las 3:00 UTC todavía es el día anterior en Bogotá. */
    public function testHoyEsElDiaDeLaClinica(): void
    {
        $ahora = new DateTimeImmutable('2026-09-23 03:00:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-09-22', VentanaRecordatorio::hoy($ahora));
    }
}
