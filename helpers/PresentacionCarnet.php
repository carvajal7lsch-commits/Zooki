<?php
final class PresentacionCarnet
{
    public static function fecha(string $fecha, bool $conHora = false): string
    {
        $objeto = DateTimeImmutable::createFromFormat($conHora ? '!Y-m-d H:i:s' : '!Y-m-d', $fecha);
        return $objeto === false ? 'Sin fecha' : $objeto->format($conHora ? 'd/m/Y H:i:s' : 'd/m/Y');
    }

    /** En una emergencia el teléfono se marca con un toque desde el celular. */
    public static function enlaceTelefono(string $telefono): string
    {
        return 'tel:' . preg_replace('/[^0-9+]/', '', $telefono);
    }
}
