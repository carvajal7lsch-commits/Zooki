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

    /** «Canino · Criollo», con la raza que escribió el propietario mientras la clínica no la confirma. */
    public static function especieYRaza(array $mascota): string
    {
        $raza = !empty($mascota['raza_indicada']) ? $mascota['raza_indicada'] . ' (por confirmar)' : ($mascota['nombre_raza'] ?? '');
        return trim(($mascota['nombre_especie'] ?? '') . ' · ' . $raza, ' ·');
    }
}
