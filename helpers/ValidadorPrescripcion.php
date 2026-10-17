<?php
require_once __DIR__ . '/CampoInvalido.php';

final class ValidadorPrescripcion
{
    public const CONCILIACION_MAX = 500;
    public const CODIGO_MAX = 100;

    public static function fila(array $fila, int $indice = 0): array
    {
        $farmaco = $fila['farmaco'] ?? null;
        if ($farmaco === '') {
            $farmaco = null;
        }
        if ($farmaco !== null && (!is_string($farmaco) || strlen($farmaco) > self::CODIGO_MAX || !preg_match('/^[a-z0-9][a-z0-9._-]*$/D', $farmaco))) {
            self::error($indice, 'farmaco', 'Fármaco no válido.');
        }
        $decision = $fila['decision'] ?? null;
        if ($decision === '') {
            $decision = null;
        }
        if ($decision !== null && $decision !== 'confirmar') {
            self::error($indice, 'decision', 'La decisión debe ser confirmar.');
        }
        $conciliacion = $fila['conciliacion'] ?? '';
        if (!is_string($conciliacion)) {
            self::error($indice, 'conciliacion', 'La conciliación no es válida.');
        }
        $conciliacion = preg_replace('/\s+/u', ' ', trim($conciliacion));
        if ($conciliacion === null || mb_strlen($conciliacion) > self::CONCILIACION_MAX || preg_match('/\p{C}/u', $conciliacion)) {
            self::error($indice, 'conciliacion', 'La conciliación admite hasta ' . self::CONCILIACION_MAX . ' caracteres, sin caracteres de control.');
        }
        $huella = $fila['huella'] ?? '';
        if (!is_string($huella) || ($huella !== '' && !preg_match('/^[a-f0-9]{64}$/D', $huella))) {
            self::error($indice, 'huella', 'Revisa de nuevo las advertencias antes de confirmar.');
        }
        return ['farmaco' => $farmaco, 'decision' => $decision, 'conciliacion' => $conciliacion, 'huella' => $huella];
    }

    public static function error(int $indice, string $campo, string $mensaje): never
    {
        throw new CampoInvalido('tratamientos.' . $indice . '.' . $campo, $mensaje, 'Tratamiento ' . ($indice + 1) . ': ' . $mensaje);
    }
}
