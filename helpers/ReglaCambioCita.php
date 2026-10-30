<?php
require_once __DIR__ . '/ReglaAtencion.php';

/** RN-430: límite para cambios ordinarios; el cero desactiva la ventana. */
final class ReglaCambioCita
{
    public const MIN = 0;
    public const MAX = 1440;
    public const DEFECTO = 30;
    public const MENSAJE = 'Ya no se puede cambiar: comunícate con la clínica';

    public static function permite(array $cita, DateTimeImmutable $ahora, int $minutos): bool
    {
        if (($cita['origen'] ?? '') === 'urgencia' || ($cita['prioridad'] ?? '') === 'rojo' || $minutos === 0) {
            return true;
        }
        return ReglaAtencion::inicio($cita['fecha'], $cita['hora'])->getTimestamp() - $ahora->getTimestamp() >= $minutos * 60;
    }
}
