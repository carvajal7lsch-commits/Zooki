<?php
/**
 * Reglas de tiempo de la atención de una cita (RN-407 y RN-409).
 *
 * Funciones puras: reciben la hora actual en vez de leer el reloj, así se
 * prueban sin base de datos y la tarea programada, el calendario y el
 * controlador aplican exactamente la misma regla.
 */
final class ReglaAtencion
{
    public const ZONA = 'America/Bogota';

    /** RN-407: la atención se puede iniciar desde 15 minutos antes de la cita. */
    public const MINUTOS_ANTES_DE_INICIAR = 15;

    /** RN-409: margen después de la hora de fin antes de avisar que sigue abierta. */
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
     * RN-407: ventana del día, desde15 minutos antes; el inicio ordinario además
     * comprueba llegadaFueraDeTolerancia para no omitir Sala (RN-426).
     */
    public static function puedeIniciar(string $fecha, string $hora, DateTimeImmutable $ahora): bool
    {
        return $ahora->format('Y-m-d') === $fecha && $ahora >= self::iniciaDesde($fecha, $hora);
    }

    /** RN-426: la llegada real manda; el retraso del veterinario no penaliza al paciente puntual. */
    public static function llegadaFueraDeTolerancia(array $cita, int $minutos, DateTimeImmutable $ahora): bool
    {
        if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true) || $cita['fecha'] !== $ahora->format('Y-m-d')) {
            return false;
        }
        $limite = self::inicio($cita['fecha'], $cita['hora'])->modify('+' . $minutos . ' minutes');
        $llegada = empty($cita['hora_llegada']) ? $ahora : new DateTimeImmutable($cita['hora_llegada'], new DateTimeZone(self::ZONA));
        return $llegada > $limite;
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

    /** RN-409: la atención sigue en curso 10 minutos después de su hora de fin y aún no se avisó. */
    public static function debeAvisarAbierta(array $cita, DateTimeImmutable $ahora): bool
    {
        if (($cita['estado'] ?? '') !== 'en_curso' || !empty($cita['aviso_atencion_abierta'])) {
            return false;
        }
        $fin = self::fin($cita['fecha'], $cita['hora'], $cita['hora_fin'] ?? null, $cita['duracion_minutos'] ?? null);
        return $ahora >= $fin->modify('+' . self::MINUTOS_GRACIA_AVISO . ' minutes');
    }

    /** RN-409: terminado el día de la cita, una atención que sigue en curso queda "sin cerrar". */
    public static function quedaSinCerrar(array $cita, DateTimeImmutable $ahora): bool
    {
        return ($cita['estado'] ?? '') === 'en_curso' && $cita['fecha'] < $ahora->format('Y-m-d');
    }
}
