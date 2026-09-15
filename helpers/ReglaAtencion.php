<?php
/**
 * Reglas de tiempo de la atención de una cita (RN-408 y RN-410).
 *
 * Funciones puras: reciben la hora actual en vez de leer el reloj, así se
 * prueban sin base de datos y la tarea programada, el calendario y el
 * controlador aplican exactamente la misma regla.
 */
final class ReglaAtencion
{
    public const ZONA = 'America/Bogota';

    /** RN-408: la atención se puede iniciar desde 15 minutos antes de la cita. */
    public const MINUTOS_ANTES_DE_INICIAR = 15;

    /** RN-410: margen después de la hora de fin antes de avisar que sigue abierta. */
    public const MINUTOS_GRACIA_AVISO = 10;

    /** Duración que se asume si la cita no la tiene guardada. */
    private const DURACION_POR_DEFECTO = 30;

    public static function inicio(string $fecha, string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable("$fecha $hora", new DateTimeZone(self::ZONA));
    }

    /** Momento desde el que se puede iniciar la atención. */
    public static function iniciaDesde(string $fecha, string $hora): DateTimeImmutable
    {
        return self::inicio($fecha, $hora)->modify('-' . self::MINUTOS_ANTES_DE_INICIAR . ' minutes');
    }

    /**
     * RN-408: el día de la cita, desde 15 minutos antes de su hora. Después de
     * la hora se sigue pudiendo iniciar ese día: el paciente puede llegar tarde.
     */
    public static function puedeIniciar(string $fecha, string $hora, DateTimeImmutable $ahora): bool
    {
        return $ahora->format('Y-m-d') === $fecha && $ahora >= self::iniciaDesde($fecha, $hora);
    }

    /** Hora prevista de fin: hora_fin si es válida; si no, hora + duración. */
    public static function fin(string $fecha, string $hora, ?string $horaFin, $duracion): DateTimeImmutable
    {
        $inicio = self::inicio($fecha, $hora);
        if (!empty($horaFin)) {
            $fin = new DateTimeImmutable("$fecha $horaFin", new DateTimeZone(self::ZONA));
            if ($fin > $inicio) {
                return $fin;
            }
        }
        $minutos = (int) $duracion > 0 ? (int) $duracion : self::DURACION_POR_DEFECTO;
        return $inicio->modify("+$minutos minutes");
    }

    /** RN-410: la atención sigue en curso 10 minutos después de su hora de fin y aún no se avisó. */
    public static function debeAvisarAbierta(array $cita, DateTimeImmutable $ahora): bool
    {
        if (($cita['estado'] ?? '') !== 'en_curso' || !empty($cita['aviso_atencion_abierta'])) {
            return false;
        }
        $fin = self::fin($cita['fecha'], $cita['hora'], $cita['hora_fin'] ?? null, $cita['duracion_minutos'] ?? null);
        return $ahora >= $fin->modify('+' . self::MINUTOS_GRACIA_AVISO . ' minutes');
    }

    /** RN-410: terminado el día de la cita, una atención que sigue en curso queda "sin cerrar". */
    public static function quedaSinCerrar(array $cita, DateTimeImmutable $ahora): bool
    {
        return ($cita['estado'] ?? '') === 'en_curso' && $cita['fecha'] < $ahora->format('Y-m-d');
    }
}
