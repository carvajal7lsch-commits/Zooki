<?php
require_once __DIR__ . '/ReglaAtencion.php';

/**
 * Qué recordatorio le toca a una dosis según los días que faltan (RN-303, HU-37).
 *
 * Antes el envío buscaba fechas exactas (hoy + 7 y hoy + 1): si la tarea no
 * corría ese día, el aviso se perdía para siempre. Ahora cada aviso tiene una
 * ventana y se envía el primer día que la tarea corra dentro de ella (RE-37.1).
 * Funciones puras para probarlas sin base de datos ni reloj.
 */
final class VentanaRecordatorio
{
    public const PRIMER_AVISO = 'recordatorio_7_dias';
    public const ULTIMO_AVISO = 'recordatorio_1_dia';

    /** RN-303: el primer aviso sale 7 días antes del vencimiento. */
    public const DIAS_PRIMER_AVISO = 7;

    /** RN-303: el último aviso, 1 día antes. */
    public const DIAS_ULTIMO_AVISO = 1;

    /** RE-37.2: tras estos envíos fallidos se deja de reintentar el mismo aviso. */
    public const MAX_INTENTOS = 3;

    /**
     * RE-37.3: el día de hoy en la zona de la clínica. CURDATE() de MySQL
     * depende de la zona del servidor de base de datos, que en Docker es UTC.
     */
    public static function hoy(DateTimeImmutable $ahora): string
    {
        return $ahora->setTimezone(new DateTimeZone(ReglaAtencion::ZONA))->format('Y-m-d');
    }

    /** Último día de la ventana: las dosis que vencen de hoy a esta fecha. */
    public static function hasta(string $hoy): string
    {
        return (new DateTimeImmutable($hoy))->modify('+' . self::DIAS_PRIMER_AVISO . ' days')->format('Y-m-d');
    }

    /**
     * Aviso que corresponde a una dosis, o null si está fuera de la ventana.
     * Del día 7 al 2 toca el primero; el día anterior y el mismo día, el último,
     * así un día sin tarea no deja a la dosis sin su aviso final.
     */
    public static function aviso(string $fechaProxima, string $hoy): ?string
    {
        $dias = (int) (new DateTimeImmutable($hoy))->diff(new DateTimeImmutable($fechaProxima))->format('%r%a');

        if ($dias < 0 || $dias > self::DIAS_PRIMER_AVISO) {
            return null;
        }
        return $dias <= self::DIAS_ULTIMO_AVISO ? self::ULTIMO_AVISO : self::PRIMER_AVISO;
    }
}
