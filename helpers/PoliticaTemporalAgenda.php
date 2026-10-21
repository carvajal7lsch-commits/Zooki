<?php
require_once __DIR__ . '/IntervalosAgenda.php';

/** RN-412: reconstruir desde hechos; nunca sumar el retraso de un cálculo anterior. */
final class PoliticaTemporalAgenda
{
    public static function calcular(array $cita, array $pausas, DateTimeImmutable $ahora, ?int $predecesor = null): array
    {
        $pactado = IntervalosAgenda::fecha($cita['fecha'] . ' ' . self::hora($cita['hora']))->getTimestamp();
        $duracion = (int) ($cita['duracion_activa_objetivo_segundos'] ?? 0);
        if ($duracion <= 0 && in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
            $duracion = (int) ($cita['duracion_minutos'] ?? 0) * 60;
        }
        $margen = $cita['margen_minutos'] ?? null;
        if ($duracion <= 0 || $margen === null || (int) $margen < 0) {
            throw new InvalidArgumentException('La duración histórica requiere revisión.');
        }
        $real = $cita['hora_inicio_real'] ?? null;
        if ($real === null) {
            if (!in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
                throw new InvalidArgumentException('Falta el inicio real acreditado.');
            }
            $inicio = max($pactado, $predecesor ?? $pactado, $ahora->getTimestamp(), (int) ($cita['no_antes_de'] ?? 0));
            $nominal = $inicio + $duracion;
            $fin = $nominal;
        } else {
            $inicio = IntervalosAgenda::fecha($real)->getTimestamp();
            $segundosPausa = 0;
            $anterior = $inicio;
            foreach ($pausas as $pausa) {
                $desde = IntervalosAgenda::fecha($pausa['inicio'])->getTimestamp();
                if ($pausa['fin'] === null || $desde < $anterior) {
                    throw new InvalidArgumentException('Pausa abierta o segmentos sin cronología demostrable.');
                }
                $hasta = IntervalosAgenda::fecha($pausa['fin'])->getTimestamp();
                if ($hasta <= $desde) {
                    throw new InvalidArgumentException('Intervalo de pausa inválido.');
                }
                $segundosPausa += $hasta - $desde;
                $anterior = $hasta;
            }
            if ($cita['estado'] === 'pausada') {
                throw new InvalidArgumentException('La pausa abierta requiere revisión; no libera capacidad.');
            }
            $nominal = $inicio + $duracion + $segundosPausa;
            if ($cita['estado'] === 'completada') {
                if (empty($cita['hora_fin_real'])) {
                    throw new InvalidArgumentException('Falta el fin real acreditado.');
                }
                $fin = IntervalosAgenda::fecha($cita['hora_fin_real'])->getTimestamp();
                if ($fin < $anterior) {
                    throw new InvalidArgumentException('El fin real precede los segmentos.');
                }
            } else {
                $fin = max($nominal, $ahora->getTimestamp());
            }
        }
        return ['inicio' => $inicio, 'nominal' => $nominal, 'fin' => $fin, 'extremo' => max($nominal + (int) $margen * 60, $fin)];
    }

    public static function hora(string $hora): string
    {
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

    public static function sello(int $instante): string
    {
        return (new DateTimeImmutable('@' . $instante))->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s');
    }
}
