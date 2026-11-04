<?php
require_once __DIR__ . '/IntervalosAgenda.php';

/** RE-4.14.7: el aviso presenta la proyección; nunca cambia la hora pactada. */
final class PresentacionEstimacionAgenda
{
    public static function hora(string $valor): string
    {
        $hora = IntervalosAgenda::fecha(strlen($valor) <= 8 ? '2000-01-01 ' . $valor : $valor);
        return $hora->format('g:i') . ((int) $hora->format('G') < 12 ? ' a. m.' : ' p. m.');
    }

    public static function estimada(array $cita): string
    {
        $estimada = (string) ($cita['inicio_estimado'] ?? '');
        if ($estimada === '') return '';
        $prefijo = substr($estimada, 0, 10) === $cita['fecha'] ? '' : IntervalosAgenda::fecha($estimada)->format('d/m/Y') . ' · ';
        return $prefijo . self::hora($estimada);
    }

    public static function texto(array $cita): string
    {
        return 'Tu cita de las ' . self::hora($cita['hora_pactada'] ?? $cita['hora']) . ' ahora empieza aprox. a las ' . self::estimada($cita) . ': la atención anterior se extendió. Te avisaremos si cambia otra vez.';
    }

    public static function aviso(array $cita): string
    {
        if (empty($cita['inicio_estimado']) || !in_array($cita['estado'] ?? '', ['pendiente', 'confirmada'], true)) return '';
        $pactada = IntervalosAgenda::fecha($cita['fecha'] . ' ' . $cita['hora']);
        $minutos = (IntervalosAgenda::fecha($cita['inicio_estimado'])->getTimestamp() - $pactada->getTimestamp()) / 60;
        return $minutos > (int) $cita['umbral_aviso_min'] ? self::texto($cita) : '';
    }
}
