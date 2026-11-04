<?php
/**
 * Botón de demo del panel de plataforma (decisión del dueño, 2026-11-03).
 * ZOOKI_DEMO_HABILITADA=1 lo enciende; ausente o con otro valor, la tarjeta no
 * se dibuja y sus rutas responden 404. En producción se enciende antes de la
 * sustentación y se apaga después.
 */
final class InterruptorDemo
{
    public const VARIABLE = 'ZOOKI_DEMO_HABILITADA';
    public const ACCIONES = ['plataforma_demo_crear_ajax', 'plataforma_demo_escena_ajax', 'plataforma_demo_borrar_ajax'];

    public static function habilitada(): bool
    {
        $valor = getenv(self::VARIABLE);
        // Las pruebas solo ven la variable que ponen ellas, nunca el .env de trabajo.
        if ($valor === false && !defined('ZOOKI_PRUEBAS') && getenv('ZOOKI_ENTORNO_PRUEBAS') !== '1' && is_file(__DIR__ . '/../.env')) {
            $configuracion = parse_ini_file(__DIR__ . '/../.env') ?: [];
            $valor = $configuracion[self::VARIABLE] ?? false;
        }
        return $valor !== false && trim((string) $valor) === '1';
    }
}
