<?php
/**
 * Lo que el panel muestra de una ejecución de SemillaDemo. Lista blanca: solo
 * conteos, día y hora de la escena, niveles del guion y las cuentas sin clave.
 * La clave nunca sale en la respuesta (decisión del dueño, 2026-11-03).
 */
final class PresentacionDemo
{
    public static function resumen(array $resultado): array
    {
        $resumen = ['conteos' => []];
        foreach ($resultado['conteos'] ?? [] as $nombre => $valor) {
            if (is_string($nombre) && is_int($valor)) {
                $resumen['conteos'][$nombre] = $valor;
            }
        }
        foreach (['dia', 'hora'] as $campo) {
            if (isset($resultado[$campo]) && is_string($resultado[$campo])) {
                $resumen[$campo] = $resultado[$campo];
            }
        }
        foreach ($resultado['niveles'] ?? [] as $nombre => $nivel) {
            if (is_string($nombre) && is_string($nivel['nivel'] ?? null)) {
                $resumen['niveles'][$nombre] = ['nivel' => $nivel['nivel'], 'revision' => (bool) ($nivel['revision'] ?? false)];
            }
        }
        foreach ($resultado['cuentas'] ?? [] as $cuenta) {
            $resumen['cuentas'][] = ['rol' => (string) ($cuenta['rol'] ?? ''), 'clinica' => (string) ($cuenta['clinica'] ?? ''), 'correo' => (string) ($cuenta['correo'] ?? '')];
        }
        return $resumen;
    }

    /** Frase corta del aviso breve. */
    public static function mensaje(string $modo, array $resumen): string
    {
        return match ($modo) {
            'si' => 'Demo creada. Las cuentas usan la clave que escribiste.',
            'escena' => 'Escena preparada para las ' . ($resumen['hora'] ?? '—') . '.',
            'borrar' => 'Demo borrada.',
        };
    }
}
