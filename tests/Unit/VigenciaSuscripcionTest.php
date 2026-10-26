<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/VigenciaSuscripcion.php';
require_once __DIR__ . '/../../helpers/TarifaSuscripcion.php';

final class VigenciaSuscripcionTest extends TestCase
{
    private function estado(string $ahora, array $cambios = []): array
    {
        $s = array_replace(['estado' => 'activa', 'al_dia' => 1, 'gestion_cobro' => 'gestionada', 'periodo_inicio' => '2026-09-25 00:00:00', 'periodo_fin' => '2026-10-25 00:00:00', 'fecha_inicio' => '2026-09-25', 'ciclo' => 'mensual'], $cambios);
        return VigenciaSuscripcion::calcular(['estado' => 'activa', 'suscripcion_gracia_dias' => 3], $s,
            ['id_plan' => 2, 'precio_mensual' => 89990], ['id_plan' => 1, 'precio_mensual' => 0], VigenciaSuscripcion::fecha($ahora));
    }

    public function testBordesExclusivosYSinCronConBanderasAntiguas(): void
    {
        foreach (['2026-10-24 23:59:59' => 'al_dia', '2026-10-25 00:00:00' => 'gracia', '2026-10-27 23:59:59' => 'gracia', '2026-10-28 00:00:00' => 'congelada', '2027-01-01 00:00:00' => 'congelada'] as $ahora => $esperado) {
            $e = $this->estado($ahora);
            $this->assertSame($esperado, $e['situacion']);
            $this->assertSame($esperado === 'congelada' ? 1 : 2, $e['plan_efectivo']['id_plan']);
        }
        $this->assertSame(2, $this->estado('2026-10-25 12:00:00', ['al_dia' => 0])['plan_efectivo']['id_plan']);
    }

    public function testLegadoSinFechaNoInventaVencimientoNiPago(): void
    {
        $e = $this->estado('2099-01-01 00:00:00', ['gestion_cobro' => 'legado', 'periodo_fin' => null]);
        $this->assertTrue($e['legado_sin_fecha']);
        $this->assertNull($e['gracia_hasta']);
        $this->assertSame(2, $e['plan_efectivo']['id_plan']);
        $this->assertSame(1, $this->estado('2026-10-26 00:00:00', ['estado' => 'suspendida'])['plan_efectivo']['id_plan']);
    }

    public function testAnclasCalendarioMesAnioYBisiestoSinProrrateo(): void
    {
        $enero = VigenciaSuscripcion::fecha('2028-01-31 13:45:00');
        $febrero = VigenciaSuscripcion::extender($enero, 'mensual', 31);
        $this->assertSame('2028-02-29 13:45:00', $febrero->format('Y-m-d H:i:s'));
        $this->assertSame('2028-03-31', VigenciaSuscripcion::extender($febrero, 'mensual', 31)->format('Y-m-d'));
        $this->assertSame('2029-02-28', VigenciaSuscripcion::extender($febrero, 'anual', 29, 2)->format('Y-m-d'));
        $this->assertSame('2027-01-31', VigenciaSuscripcion::extender(VigenciaSuscripcion::fecha('2026-12-31 00:00:00'), 'mensual')->format('Y-m-d'));
        foreach (['2026-10-24 12:00:00', '2026-10-26 12:00:00', '2026-10-29 12:00:00'] as $fecha) {
            $ahora = VigenciaSuscripcion::fecha($fecha);
            $inicio = VigenciaSuscripcion::inicioRenovacion($this->estado($fecha), $ahora);
            $this->assertSame($fecha < '2026-10-28' ? '2026-10-25 00:00:00' : $fecha, $inicio->format('Y-m-d H:i:s'));
        }
    }

    public function testTarifasEnCentavosSinIvaYCatalogoPersonalizado(): void
    {
        $plan = ['precio_mensual' => 89990, 'precio_anual' => 899900];
        $this->assertSame(8999000, TarifaSuscripcion::calcular($plan, 'mensual')['total_centavos']);
        $this->assertSame(89990000, TarifaSuscripcion::calcular($plan, 'anual')['total_centavos']);
        $this->assertSame(0, TarifaSuscripcion::calcular($plan, 'anual')['impuesto_centavos']);
        $this->assertSame(12345000, TarifaSuscripcion::calcular(['precio_mensual' => 123450], 'mensual')['total_centavos']);
        $this->expectException(InvalidArgumentException::class);
        TarifaSuscripcion::calcular($plan, 'semanal');
    }

    public function testGraciaCeroYGratuitoSinLegadoDePago(): void
    {
        $c = ['estado' => 'activa', 'suscripcion_gracia_dias' => 0];
        $s = ['estado' => 'activa', 'al_dia' => 1, 'ciclo' => 'mensual', 'gestion_cobro' => 'gestionada', 'periodo_inicio' => '2026-12-01 00:00:00', 'periodo_fin' => '2027-01-01 00:00:00'];
        $pro = ['id_plan' => 2, 'precio_mensual' => 89990];
        $gratis = ['id_plan' => 1, 'precio_mensual' => 0];
        $ahora = VigenciaSuscripcion::fecha('2027-01-01 00:00:00');
        $this->assertSame('congelada', VigenciaSuscripcion::calcular($c, $s, $pro, $gratis, $ahora)['situacion']);
        $s['gestion_cobro'] = 'legado';
        $s['periodo_fin'] = null;
        $this->assertFalse(VigenciaSuscripcion::calcular($c, $s, $gratis, $gratis, $ahora)['legado_sin_fecha']);
    }
}
