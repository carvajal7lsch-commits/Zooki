<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/ComparaRespuestaIdempotente.php';

final class ComparaRespuestaIdempotenteTest extends TestCase
{
    use ComparaRespuestaIdempotente;

    public function testAceptaClavesReordenadasDelResultadoPersistidoYMapasAnidados(): void
    {
        $original = ['id_veterinario' => 2, 'afectadas' => [11, 12], 'franjas' => [
            ['dia_semana' => 1, 'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00'],
            ['dia_semana' => 2, 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00'],
        ]];
        $replay = json_decode('{"franjas":[{"hora_fin":"10:00:00","dia_semana":1,"hora_inicio":"09:00:00"},{"hora_inicio":"10:00:00","hora_fin":"11:00:00","dia_semana":2}],"afectadas":[11,12],"id_veterinario":2}', true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotSame($original, $replay, 'La comparación anterior detectaría solo el orden de claves.');
        $this->assertRespuestaIdempotenteIgual($original, $replay);
        $this->assertRespuestaIdempotenteIgual(['id_cierre' => 3, 'activo' => 0, 'afectadas' => []], ['afectadas' => [], 'activo' => 0, 'id_cierre' => 3]);
    }

    #[DataProvider('respuestasDistintas')]
    public function testRechazaCambiosRealesSinIgualdadDebil(array $esperada, array $actual): void
    {
        $this->expectException(ExpectationFailedException::class);
        $this->assertRespuestaIdempotenteIgual($esperada, $actual);
    }

    public static function respuestasDistintas(): array
    {
        return [
            'valor distinto' => [['id_veterinario' => 2], ['id_veterinario' => 3]],
            'entero frente a cadena' => [['id_veterinario' => 2], ['id_veterinario' => '2']],
            'entero frente a decimal' => [['id_veterinario' => 2], ['id_veterinario' => 2.0]],
            'booleano frente a entero' => [['activo' => false], ['activo' => 0]],
            'clave ausente frente a null' => [['motivo' => null], []],
            'clave adicional' => [['id_cierre' => 3], ['id_cierre' => 3, 'activo' => 0]],
            'clave distinta' => [['id_cierre' => 3], ['id_veterinario' => 3]],
            'lista reordenada' => [['afectadas' => [11, 12]], ['afectadas' => [12, 11]]],
            'franjas reordenadas' => [['franjas' => [['dia_semana' => 1], ['dia_semana' => 2]]], ['franjas' => [['dia_semana' => 2], ['dia_semana' => 1]]]],
            'tipo anidado distinto' => [['vista' => ['afectadas' => [11]]], ['vista' => ['afectadas' => ['11']]]],
            'mapa numerico frente a lista' => [['datos' => [1 => 'b', 0 => 'a']], ['datos' => ['a', 'b']]],
        ];
    }
}
