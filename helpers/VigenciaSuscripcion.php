<?php
require_once __DIR__ . '/PoliticaSuscripcion.php';

/** RN-007/014 y D2/D6: cálculo puro; el cron solo materializa su resultado. */
final class VigenciaSuscripcion
{
    // El fin exclusivo del día siguiente debe seguir dentro de DATETIME de MySQL.
    public const FECHA_FIN_MAX = '9999-12-30';

    /** Presentación del intervalo exclusivo, S2 revisión; no cambia la vigencia. */
    public static function fechaIncluida(?string $fin): string
    {
        return $fin === null ? 'Sin fecha' : self::fecha($fin)->modify('-1 second')->format('d/m/Y');
    }

    public static function fecha(string $valor): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valor, new DateTimeZone(PoliticaSuscripcion::ZONA));
        if (!$fecha || $fecha->format('Y-m-d H:i:s') !== $valor) {
            throw new InvalidArgumentException('La fecha de suscripción no es válida.');
        }
        return $fecha;
    }

    public static function calcular(array $clinica, ?array $suscripcion, array $contratado, array $gratuito, DateTimeImmutable $ahora): array
    {
        $fin = $suscripcion['periodo_fin'] ?? null;
        if ($fin === null && !empty($suscripcion['fecha_fin'])) {
            $fin = self::fecha($suscripcion['fecha_fin'] . ' 00:00:00')->modify('+1 day')->format('Y-m-d H:i:s');
        }
        // RN-003/008 y D6: una tarifa personalizada (incluso cero) no cambia el plan.
        $pago = (int) $contratado['id_plan'] !== (int) $gratuito['id_plan'];
        $legado = $pago && $suscripcion && ($suscripcion['gestion_cobro'] ?? 'legado') === 'legado' && $fin === null;
        $situacion = 'sin_vigencia';
        $gracia = $fin === null ? null : self::fecha($fin)->modify('+' . max(0, (int) $clinica['suscripcion_gracia_dias']) . ' days');
        if ($pago && $suscripcion) {
            $activa = $suscripcion['estado'] === 'activa';
            if ($legado) {
                $situacion = $activa && (int) $suscripcion['al_dia'] === 1 ? 'al_dia' : 'congelada';
            } elseif ($fin !== null) {
                $inicio = $suscripcion['periodo_inicio'] ?? ($suscripcion['fecha_inicio'] . ' 00:00:00');
                $situacion = !$activa || $ahora < self::fecha($inicio) ? 'sin_vigencia'
                    : ($ahora < self::fecha($fin) ? 'al_dia' : ($ahora < $gracia ? 'gracia' : 'congelada'));
            }
        }
        $beneficios = in_array($situacion, ['al_dia', 'gracia'], true);
        // La mora no cambia clinicas.estado; su suspensión administrativa prevalece.
        $efectivo = $beneficios && $clinica['estado'] === 'activa' ? $contratado : $gratuito;
        return ['plan_contratado' => $contratado, 'plan_gratuito' => $gratuito, 'plan_efectivo' => $efectivo, 'situacion' => $situacion,
            'legado_sin_fecha' => (bool) $legado, 'periodo_fin' => $fin,
            'gracia_hasta' => $gracia?->format('Y-m-d H:i:s'), 'suscripcion' => $suscripcion,
            'clinica' => $clinica, 'ciclo' => $suscripcion['ciclo'] ?? 'mensual'];
    }

    /** Conservar ancla 31 en febrero y recuperarla en marzo; no sumar meses a día 31. */
    public static function extender(DateTimeImmutable $inicio, string $ciclo, ?int $diaAncla = null, ?int $mesAncla = null): DateTimeImmutable
    {
        if (!in_array($ciclo, ['mensual', 'anual'], true)) {
            throw new InvalidArgumentException('El ciclo no es válido.');
        }
        $dia = $diaAncla ?? (int) $inicio->format('d');
        $mes = $mesAncla ?? (int) $inicio->format('m');
        if ($dia < 1 || $dia > 31 || $mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('El ancla no es válida.');
        }
        $base = $inicio->setDate((int) $inicio->format('Y'), (int) $inicio->format('m'), 1);
        $base = $ciclo === 'mensual' ? $base->modify('+1 month') : $base->setDate((int) $base->format('Y') + 1, $mes, 1);
        return $base->setDate((int) $base->format('Y'), (int) $base->format('m'), min($dia, (int) $base->format('t')));
    }

    /** D2/D3, sin cobrar: prepara la fecha para la acreditación de S2. */
    public static function inicioRenovacion(array $estado, DateTimeImmutable $ahora): DateTimeImmutable
    {
        return $estado['periodo_fin'] !== null && in_array($estado['situacion'], ['al_dia', 'gracia'], true)
            ? self::fecha($estado['periodo_fin']) : $ahora;
    }
}
