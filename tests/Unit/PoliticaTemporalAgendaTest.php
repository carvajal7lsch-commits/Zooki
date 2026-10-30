<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/GrafoAgenda.php';

final class PoliticaTemporalAgendaTest extends TestCase
{
    public static function casosM(): array
    {
        return [
            'sin iniciar' => ['confirmada', null, null, [], '08:00:00', '09:30:00', '09:40:00'],
            'inicio tardío completo' => ['en_curso', '09:20:00', null, [], '09:25:00', '09:50:00', '10:00:00'],
            'margen exacto' => ['en_curso', '09:00:00', null, [], '09:40:00', '09:30:00', '09:40:00'],
            'un segundo sobre margen' => ['en_curso', '09:00:00', null, [], '09:40:01', '09:30:00', '09:40:01'],
            'pausa confirmada M' => ['completada', '09:00:00', '09:50:00', [['09:10:00','09:30:00']], '11:00:00', '09:50:00', '09:50:00'],
            'reanudada' => ['en_curso', '09:00:00', null, [['09:10:00','09:30:00']], '09:40:00', '09:50:00', '10:00:00'],
            'varias pausas' => ['en_curso', '09:00:00', null, [['09:10:00','09:20:00'],['09:25:00','09:40:00']], '09:50:00', '09:55:00', '10:05:00'],
            'sobrepaso reanudada' => ['en_curso', '09:00:00', null, [['09:10:00','09:20:00'],['09:25:00','09:40:00']], '10:06:00', '09:55:00', '10:06:00'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('casosM')]
    public function testMargenUnicoPorEstado(string $estado, ?string $inicio, ?string $fin, array $pausas, string $ahora, string $nominal, string $extremo): void
    {
        $fecha = '2030-01-07 ';
        $cita = ['fecha'=>'2030-01-07','hora'=>'09:00:00','duracion_activa_objetivo_segundos'=>1800,'margen_minutos'=>10,'estado'=>$estado,'hora_inicio_real'=>$inicio ? $fecha.$inicio : null,'hora_fin_real'=>$fin ? $fecha.$fin : null];
        $segmentos = array_map(static fn ($p) => ['inicio'=>$fecha.$p[0],'fin'=>$fecha.$p[1]],$pausas);
        $e = PoliticaTemporalAgenda::calcular($cita,$segmentos,IntervalosAgenda::fecha($fecha.$ahora));
        $this->assertSame($fecha.$nominal,PoliticaTemporalAgenda::sello($e['nominal']));
        $this->assertSame($fecha.$extremo,PoliticaTemporalAgenda::sello($e['extremo']));
        $this->assertSame($e,PoliticaTemporalAgenda::calcular($cita,$segmentos,IntervalosAgenda::fecha($fecha.$ahora)));
    }

    public function testPausaAbiertaNoFabricaFinNiCapacidad(): void
    {
        $c = ['fecha'=>'2030-01-07','hora'=>'09:00:00','duracion_activa_objetivo_segundos'=>1800,'margen_minutos'=>10,'estado'=>'pausada','hora_inicio_real'=>'2030-01-07 09:00:00'];
        $this->expectException(InvalidArgumentException::class);
        PoliticaTemporalAgenda::calcular($c,[['inicio'=>'2030-01-07 09:10:00','fin'=>null]],IntervalosAgenda::fecha('2030-01-07 09:30:00'));
    }
}
