<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/RangosVitales.php';
require_once __DIR__ . '/../../helpers/ValidadorConfiguracionClinica.php';

final class RangosVitalesTest extends TestCase
{
    public function testReferenciasPorEspecieYTamanioSinRangoDePeso(): void
    {
        self::assertSame([37.5, 39.2], RangosVitales::para('Canino')['temperatura']);
        self::assertSame([90, 120], RangosVitales::para('Perro', 'pequeno')['frecuencia_cardiaca']);
        self::assertSame([70, 110], RangosVitales::para('Perro', 'mediano')['frecuencia_cardiaca']);
        self::assertSame([60, 90], RangosVitales::para('Perro', 'gigante')['frecuencia_cardiaca']);
        self::assertSame([60, 120], RangosVitales::para('Perro')['frecuencia_cardiaca']);
        self::assertSame([38.1, 39.2], RangosVitales::para('Felino')['temperatura']);
        self::assertSame([150, 200], RangosVitales::para('Gato')['frecuencia_cardiaca']);
        self::assertSame([], RangosVitales::para('Conejo'));
        self::assertArrayNotHasKey('peso', RangosVitales::para('Perro'));
    }

    public function testParametrosDelControlSoloAceptanUnoATrescientosSesentaYCinco(): void
    {
        foreach (['control_busqueda_dias', 'control_dias_sin_duracion'] as $campo) {
            foreach (['1', '7', '30', '365'] as $valor) self::assertNull(ValidadorConfiguracionClinica::campo($campo, $valor));
            foreach (['0', '-1', '366', '1.5', '1e2', '', []] as $valor) self::assertNotNull(ValidadorConfiguracionClinica::campo($campo, $valor));
        }
    }
}
