<?php
/**
 * M5 C2 (Inicio del portal): la próxima dosis de cada mascota, entre sus
 * vacunas y desparasitaciones de todas las clínicas (RN-114).
 *
 * Una dosis renovada ya no cuenta: si hay una aplicación posterior de la
 * misma vacuna o del mismo tipo de desparasitación, la fecha vieja no
 * aplica (la misma regla de Recordatorio, RE-3.6.4).
 */
final class ProximaDosis
{
    /**
     * @param array<int, array<string, mixed>> $vacunas           filas de vacunas (con id_mascota)
     * @param array<int, array<string, mixed>> $desparasitaciones filas de desparasitaciones (con id_mascota)
     * @return array<int, array{nombre: string, fecha: string, clinica: string}> por id_mascota
     */
    public static function porMascota(array $vacunas, array $desparasitaciones, string $hoy): array
    {
        $vigentes = [];
        foreach ($vacunas as $vacuna) {
            $clave = $vacuna['id_mascota'] . '|vacuna|' . $vacuna['nombre_vacuna'];
            $vigentes = self::conservarUltima($vigentes, $clave, (string) $vacuna['fecha_aplicacion'], [
                'id_mascota' => (int) $vacuna['id_mascota'],
                'nombre' => (string) $vacuna['nombre_vacuna'],
                'fecha' => (string) ($vacuna['fecha_proxima_dosis'] ?? ''),
                'clinica' => (string) ($vacuna['clinica_nombre'] ?? ''),
            ]);
        }
        foreach ($desparasitaciones as $desparasitacion) {
            $clave = $desparasitacion['id_mascota'] . '|desparasitacion|' . $desparasitacion['tipo'];
            $vigentes = self::conservarUltima($vigentes, $clave, (string) $desparasitacion['fecha_aplicacion'], [
                'id_mascota' => (int) $desparasitacion['id_mascota'],
                'nombre' => (string) $desparasitacion['producto'],
                'fecha' => (string) ($desparasitacion['fecha_proxima'] ?? ''),
                'clinica' => (string) ($desparasitacion['clinica_nombre'] ?? ''),
            ]);
        }

        $proximas = [];
        foreach ($vigentes as $dosis) {
            if (!self::esFechaValida($dosis['fecha']) || $dosis['fecha'] < $hoy) {
                continue;
            }
            $actual = $proximas[$dosis['id_mascota']] ?? null;
            if ($actual === null || $dosis['fecha'] < $actual['fecha']) {
                $proximas[$dosis['id_mascota']] = ['nombre' => $dosis['nombre'], 'fecha' => $dosis['fecha'], 'clinica' => $dosis['clinica']];
            }
        }
        return $proximas;
    }

    private static function conservarUltima(array $vigentes, string $clave, string $aplicacion, array $dosis): array
    {
        $previa = $vigentes[$clave] ?? null;
        if ($previa === null || $aplicacion > $previa['aplicacion']) {
            $vigentes[$clave] = $dosis + ['aplicacion' => $aplicacion];
        }
        return $vigentes;
    }

    private static function esFechaValida(string $fecha): bool
    {
        return $fecha !== '' && $fecha !== '0000-00-00';
    }
}
