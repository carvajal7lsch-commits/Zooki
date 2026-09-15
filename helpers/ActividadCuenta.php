<?php
require_once __DIR__ . '/ResumenPanel.php';

/**
 * HU-42: presentación de la actividad reciente de la propia cuenta.
 *
 * La auditoría guarda fecha_hora con el reloj del servidor de base de datos
 * (en Docker suele ser UTC), así que cada fecha se convierte a la hora de la
 * clínica antes de mostrarla.
 */
final class ActividadCuenta
{
    /**
     * Qué pasó en una fila de auditoría:
     *  - acceso: inicio de sesión correcto, con su método.
     *  - fallo:  intento de acceso rechazado.
     *  - cambio: modificación de la cuenta (contraseña, contacto...).
     */
    public static function describir(array $fila): array
    {
        $descripcion = (string) ($fila['descripcion'] ?? '');

        switch ($fila['accion'] ?? '') {
            case 'LOGIN':
                $google = stripos($descripcion, 'google') !== false;
                return ['tipo' => 'acceso', 'titulo' => $google ? 'Inicio de sesión con Google' : 'Inicio de sesión con contraseña'];
            case 'LOGIN_FAIL':
                return ['tipo' => 'fallo', 'titulo' => stripos($descripcion, 'inactiva') !== false
                    ? 'Intento de acceso con la cuenta inactiva'
                    : 'Intento de acceso fallido'];
            default:
                if (stripos($descripcion, 'contrase') !== false) {
                    return ['tipo' => 'cambio', 'titulo' => 'Cambio de contraseña'];
                }
                if (stripos($descripcion, 'contacto') !== false) {
                    return ['tipo' => 'cambio', 'titulo' => 'Datos de contacto actualizados'];
                }
                return ['tipo' => 'cambio', 'titulo' => $descripcion !== '' ? $descripcion : 'Cambio en la cuenta'];
        }
    }

    /** Fecha de la base convertida a la hora de la clínica. */
    public static function aHoraClinica(string $fechaBd, string $desfaseBd): DateTimeImmutable
    {
        return (new DateTimeImmutable($fechaBd, new DateTimeZone($desfaseBd)))
            ->setTimezone(new DateTimeZone(ReglaAtencion::ZONA));
    }

    /** «Hoy, 2:10 p. m.», «Ayer, 9:00 a. m.» o «lun 14 sep, 9:00 a. m.». */
    public static function momento(DateTimeImmutable $fecha, DateTimeImmutable $ahora): string
    {
        $dia = $fecha->format('Y-m-d');
        $diferencia = (int) $ahora->setTime(0, 0)->diff($fecha->setTime(0, 0))->format('%r%a');
        $etiqueta = $diferencia === -1 ? 'Ayer' : ResumenPanel::etiquetaDia($dia, $ahora);
        return $etiqueta . ', ' . ResumenPanel::hora($fecha->format('H:i'));
    }

    /** «mayo de 2026». */
    public static function mesYAnio(DateTimeImmutable $fecha): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $meses[(int) $fecha->format('n') - 1] . ' de ' . $fecha->format('Y');
    }

    /** Desfase «+HH:MM» a partir de lo que devuelve TIMEDIFF(NOW(), UTC_TIMESTAMP()). */
    public static function normalizarDesfase(?string $timediff): string
    {
        if (!$timediff || !preg_match('/^(-)?(\d{1,2}):(\d{2})/', trim($timediff), $m)) {
            return '+00:00';
        }
        return ($m[1] === '-' ? '-' : '+') . str_pad($m[2], 2, '0', STR_PAD_LEFT) . ':' . $m[3];
    }
}
