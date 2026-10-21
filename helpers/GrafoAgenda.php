<?php
require_once __DIR__ . '/PoliticaTemporalAgenda.php';

/** HU-4.14: Grafo II efímero; no contiene conocimiento clínico ni tablas de aristas. */
final class GrafoAgenda
{
    public array $secuencia = [];
    public array $conflictos = [];
    public array $emparejamientos = [];
    public array $estimaciones = [];

    public function calcular(array $citas, array $pausas, DateTimeImmutable $ahora): array
    {
        $this->secuencia = [];
        $this->conflictos = [];
        $this->emparejamientos = [];
        $this->estimaciones = [];
        $porVeterinario = [];
        foreach ($citas as $cita) {
            if (!in_array($cita['estado'], ['cancelada', 'no_asistio'], true)) {
                $porVeterinario[(int) $cita['id_veterinario']][] = $cita;
            }
        }
        foreach ($porVeterinario as $cola) {
            usort($cola, static fn ($a, $b) => [empty($a['hora_inicio_real']), $a['hora'], $a['id_cita']] <=> [empty($b['hora_inicio_real']), $b['hora'], $b['id_cita']]);
            // Reordenar las posiciones naranjas del mismo bloque sin adelantar la atención iniciada.
            $bloques = [];
            foreach ($cola as $indice => $cita) {
                if ($cita['prioridad'] === 'naranja' && !empty($cita['id_bloque']) && empty($cita['hora_inicio_real'])) {
                    $bloques[$cita['id_bloque']][] = $indice;
                }
            }
            foreach ($bloques as $indices) {
                $naranjas = array_map(static fn ($i) => $cola[$i], $indices);
                usort($naranjas, static fn ($a, $b) => [$a['orden_sobrecupo'] ?? PHP_INT_MAX, $a['fecha_registro'], $a['id_cita']] <=> [$b['orden_sobrecupo'] ?? PHP_INT_MAX, $b['fecha_registro'], $b['id_cita']]);
                foreach ($indices as $i => $posicion) {
                    $cola[$posicion] = $naranjas[$i];
                }
            }
            $anterior = null;
            $extremo = null;
            $incierta = false;
            foreach ($cola as $cita) {
                $id = (int) $cita['id_cita'];
                if ($anterior !== null) {
                    $this->secuencia[] = [$anterior, $id];
                }
                try {
                    if ($incierta && empty($cita['hora_inicio_real'])) {
                        throw new InvalidArgumentException('El predecesor requiere revisión.');
                    }
                    $estimacion = PoliticaTemporalAgenda::calcular($cita, $pausas[$id] ?? [], $ahora, $extremo);
                    $this->estimaciones[$id] = $estimacion;
                    $extremo = max($extremo ?? 0, $estimacion['extremo']);
                } catch (InvalidArgumentException $error) {
                    $this->estimaciones[$id] = ['revision' => $error->getMessage()];
                    $incierta = true;
                }
                $anterior = $id;
            }
        }
        foreach ($citas as $a) {
            foreach ($citas as $b) {
                $ea = $this->estimaciones[$a['id_cita']] ?? [];
                $eb = $this->estimaciones[$b['id_cita']] ?? [];
                if ($a['id_cita'] < $b['id_cita'] && isset($ea['inicio'], $eb['inicio']) && ($a['id_mascota'] === $b['id_mascota'] || $a['id_veterinario'] === $b['id_veterinario']) && $ea['inicio'] < $eb['extremo'] && $eb['inicio'] < $ea['extremo']) {
                    $this->conflictos[] = [(int) $a['id_cita'], (int) $b['id_cita']];
                }
            }
        }
        return $this->estimaciones;
    }
}
