<?php

/** RE-2.9.3: lista cerrada de campos; ningún dato protegido atraviesa esta frontera. */
final class PrescripcionPublica
{
    public static function desde(array $interna, ConocimientoClinico $grafo): array
    {
        $advertencias = [];
        $hayProtegida = false;
        foreach ($interna['advertencias'] as $advertencia) {
            if ($advertencia['protegida']) {
                $hayProtegida = true;
                continue;
            }
            $aviso = ['tipo' => $advertencia['tipo'], 'mensaje' => $advertencia['mensaje']];
            if (isset($advertencia['farmaco'])) {
                $aviso['farmaco'] = $grafo->nodos[$advertencia['farmaco']]['etiqueta'] ?? 'Medicación identificada';
            }
            $advertencias[] = $aviso;
        }
        if ($hayProtegida) {
            $advertencias[] = ['tipo' => 'interaccion_protegida', 'mensaje' => 'Posible interacción con medicación registrada por otra clínica; concilie con el propietario antes de confirmar.'];
        }
        return [
            'bloqueada' => $interna['bloqueada'],
            'motivos' => array_map(static fn (array $motivo): array => ['tipo' => $motivo['tipo'], 'mensaje' => $motivo['mensaje']], $interna['motivos']),
            'advertencias' => $advertencias,
            'evaluacion_incompleta' => $interna['evaluacion_incompleta'],
            'requiere_decision' => $interna['requiere_decision'],
            'requiere_conciliacion' => $interna['requiere_conciliacion'],
        ];
    }
}
