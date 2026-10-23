<?php

/** B1/B1.1: claves de mapas sin orden; valores/tipos y posiciones de listas exactos. */
trait ComparaRespuestaIdempotente
{
    protected function assertRespuestaIdempotenteIgual(array $esperada, array $actual): void
    {
        $this->assertSame(self::normalizarRespuestaIdempotente($esperada), self::normalizarRespuestaIdempotente($actual));
    }

    private static function normalizarRespuestaIdempotente(mixed $valor): mixed
    {
        if (!is_array($valor)) {
            return $valor;
        }
        $esLista = array_is_list($valor);
        foreach ($valor as $clave => $contenido) {
            $valor[$clave] = self::normalizarRespuestaIdempotente($contenido);
        }
        if (!$esLista) {
            ksort($valor, SORT_STRING);
        }
        // Conservar la categoría antes de ordenar evita convertir un mapa numérico en lista.
        return ['es_lista' => $esLista, 'contenido' => $valor];
    }
}
