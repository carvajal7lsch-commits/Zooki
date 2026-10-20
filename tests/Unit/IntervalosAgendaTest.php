<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/IntervalosAgenda.php';

final class IntervalosAgendaTest extends TestCase
{
    public function testInterseccionRestaYBordesConSegundosNoUnenUnaInterrupcion(): void
    {
        $base = IntervalosAgenda::intersecar([[0, 100], [200, 300]], [[50, 250]]);
        $this->assertSame([[50, 100], [200, 250]], $base);
        $resto = IntervalosAgenda::restar($base, [[70, 80], [0, 50], [250, 400]]);
        $this->assertSame([[50, 70], [80, 100], [200, 250]], $resto);
        $this->assertTrue(IntervalosAgenda::contiene($resto, 50, 70));
        $this->assertFalse(IntervalosAgenda::contiene($resto, 50, 71));
        $this->assertFalse(IntervalosAgenda::contiene($resto, 69, 81));
        $this->assertFalse(IntervalosAgenda::contiene($resto, 70, 70));
        $this->assertSame([[0, 100], [101, 120]], IntervalosAgenda::unir([[50, 100], [0, 50], [101, 120]]));
    }

    public function testFechaBogotaCompletaSinNormalizarFechasInexistentes(): void
    {
        $fecha = IntervalosAgenda::fecha('2030-01-07 10:00:01');
        $this->assertSame('America/Bogota', $fecha->getTimezone()->getName());
        $this->assertSame('2030-01-07 10:00:01', $fecha->format('Y-m-d H:i:s'));
        $this->expectException(InvalidArgumentException::class);
        IntervalosAgenda::fecha('2030-02-30 10:00:01');
    }
}
