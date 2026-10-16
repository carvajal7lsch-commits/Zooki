<?php

/** RE-2.9.3: lista cerrada de campos; ningún dato protegido atraviesa esta frontera. */
final class PrescripcionPublica
{
    /** Campos bibliográficos que se muestran; una URL que no sea https no se ofrece como enlace. */
    private const CAMPOS_FUENTE = ['entidad', 'titulo', 'url', 'fundamento'];

    public static function desde(array $interna, ConocimientoClinico $grafo): array
    {
        $advertencias = [];
        $hayProtegida = false;
        foreach ($interna['advertencias'] as $advertencia) {
            if ($advertencia['protegida']) {
                $hayProtegida = true;
                continue;
            }
            $aviso = self::visible($advertencia, $grafo);
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
            'motivos' => array_map(static fn (array $motivo): array => self::visible($motivo, $grafo), $interna['motivos']),
            'advertencias' => $advertencias,
            'evaluacion_incompleta' => $interna['evaluacion_incompleta'],
            'requiere_decision' => $interna['requiere_decision'],
            'requiere_conciliacion' => $interna['requiere_conciliacion'],
        ];
    }

    /**
     * Revisión de C3, nota para C5: la toxicidad nombra la especie o la raza del
     * paciente y su fuente; es conocimiento global (RN-212), no un dato protegido.
     * Solo se llama con evidencia visible; una alergia no lleva fuente bibliográfica.
     */
    private static function visible(array $evidencia, ConocimientoClinico $grafo): array
    {
        $publica = ['tipo' => $evidencia['tipo'], 'mensaje' => $evidencia['mensaje']];
        if ($evidencia['tipo'] === 'toxicidad' && isset($evidencia['origen'])) {
            $publica['paciente'] = $grafo->nodos[$evidencia['origen']]['etiqueta'] ?? null;
        }
        if (in_array($evidencia['tipo'], ['toxicidad', 'interaccion'], true) && isset($evidencia['fuentes'])) {
            $publica['fuentes'] = self::fuentes($evidencia['fuentes']);
        }
        return $publica;
    }

    private static function fuentes(array $fuentes): array
    {
        $salida = [];
        foreach ($fuentes as $fuente) {
            $fila = [];
            foreach (self::CAMPOS_FUENTE as $campo) {
                $fila[$campo] = isset($fuente[$campo]) ? (string) $fuente[$campo] : null;
            }
            if ($fila['url'] !== null && !str_starts_with($fila['url'], 'https://')) {
                $fila['url'] = null;
            }
            $salida[] = $fila;
        }
        return $salida;
    }
}
