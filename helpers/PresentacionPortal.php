<?php
/**
 * M5 C2: textos y agrupaciones del portal del propietario, probados sin base
 * de datos. Las fechas y las iniciales usan los helpers del resto del sistema
 * (PresentacionPlataforma, ResumenPanel).
 */
final class PresentacionPortal
{
    /** Citas que todavía no se resuelven: van a «Próximas» si son de hoy en adelante. */
    public const ABIERTAS = ['pendiente', 'confirmada', 'en_curso', 'sin_cerrar'];

    /** Estados como los ve el propietario; para él, «sin cerrar» sigue en atención (los mismos de portal.js). */
    private const ESTADOS = [
        'pendiente' => 'Activa',
        'confirmada' => 'Activa',
        'en_curso' => 'En atención',
        'sin_cerrar' => 'En atención',
        'completada' => 'Completada',
        'cancelada' => 'Cancelada',
        'no_asistio' => 'No asistió',
    ];

    public static function estado(string $estado): string
    {
        return self::ESTADOS[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
    }

    /** Solo azul (en curso) y gris (lo demás): la identidad de Zooki (M0.2). */
    public static function claseEstado(string $estado): string
    {
        return in_array($estado, self::ABIERTAS, true) ? 'tag-mini tag-mini--azul' : 'tag-mini';
    }

    /**
     * «Próximas»: abiertas de hoy en adelante, la más cercana primero.
     * «Pasadas»: todo lo demás, la más reciente primero.
     *
     * @return array{proximas: list<array>, pasadas: list<array>}
     */
    public static function separarCitas(array $citas, string $hoy): array
    {
        $proximas = [];
        $pasadas = [];
        foreach ($citas as $cita) {
            if (in_array($cita['estado'], self::ABIERTAS, true) && $cita['fecha'] >= $hoy) {
                $proximas[] = $cita;
            } else {
                $pasadas[] = $cita;
            }
        }
        usort($proximas, static fn (array $a, array $b): int => strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']));
        usort($pasadas, static fn (array $a, array $b): int => strcmp($b['fecha'] . $b['hora'], $a['fecha'] . $a['hora']));
        return ['proximas' => $proximas, 'pasadas' => $pasadas];
    }

    /** M5 C4a (decisión 1 del usuario): cuántos eventos lleva «Próximo en salud» del Inicio. */
    public const PROXIMO_EN_SALUD = 5;

    /**
     * «Próximo en salud»: las próximas citas y dosis de todas las mascotas,
     * por fecha, hasta el tope. Sale de lo que el portal ya leyó, sin otra
     * consulta. Solo entra lo de las mascotas del propietario ($mascotas, por
     * id): un registro ajeno no se cuela aunque llegue en la lista (RN-G02).
     *
     * @param list<array> $proximasCitas  «Próximas» de separarCitas()
     * @param list<array> $dosis          ProximaDosis::pendientes()
     * @param array<int, array> $mascotas las del propietario, por id_mascota
     * @return list<array{tipo: string, fecha: string, hora: string, mascota: string, titulo: string, clinica: string, id_cita: int}>
     */
    public static function proximoEnSalud(array $proximasCitas, array $dosis, array $mascotas, int $tope = self::PROXIMO_EN_SALUD): array
    {
        $eventos = [];
        foreach ($proximasCitas as $cita) {
            $mascota = $mascotas[(int) $cita['id_mascota']] ?? null;
            if ($mascota === null) {
                continue;
            }
            $eventos[] = [
                'tipo' => 'cita',
                'fecha' => (string) $cita['fecha'],
                'hora' => substr((string) $cita['hora'], 0, 5),
                'mascota' => (string) $mascota['nombre'],
                'titulo' => (string) ($cita['nombre_tipo'] ?? '') ?: 'Cita',
                'clinica' => (string) ($cita['clinica_nombre'] ?? ''),
                'id_cita' => (int) $cita['id_cita'],
            ];
        }
        foreach ($dosis as $una) {
            $mascota = $mascotas[(int) $una['id_mascota']] ?? null;
            if ($mascota === null) {
                continue;
            }
            $eventos[] = [
                'tipo' => 'dosis',
                'fecha' => (string) $una['fecha'],
                'hora' => '',
                'mascota' => (string) $mascota['nombre'],
                'titulo' => (string) $una['nombre'],
                'clinica' => (string) $una['clinica'],
                'id_cita' => 0,
            ];
        }
        usort($eventos, static fn (array $a, array $b): int => strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']));
        return array_slice($eventos, 0, $tope);
    }

    /** «Canino · Criollo», con la raza que escribió el propietario mientras la clínica no la confirma. */
    public static function especieYRaza(array $mascota): string
    {
        $raza = !empty($mascota['raza_indicada']) ? $mascota['raza_indicada'] . ' (por confirmar)' : ($mascota['nombre_raza'] ?? '');
        return trim(($mascota['nombre_especie'] ?? '') . ' · ' . $raza, ' ·');
    }
}
