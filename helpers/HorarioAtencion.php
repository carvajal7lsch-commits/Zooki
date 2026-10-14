<?php
require_once __DIR__ . '/ReglaAtencion.php';
require_once __DIR__ . '/ResumenPanel.php';

/**
 * Horario de atención de la clínica tal como lo ve el propietario en el portal.
 *
 * Reemplaza el banner fijo «Médicos calificados las 24 horas», que no era
 * cierto: la clínica atiende en las franjas de Configuración de horarios
 * (HU-43). Lógica pura sobre las filas de horarios_clinica para poder probarla.
 */
final class HorarioAtencion
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * Franjas abiertas de cada día, en orden de lunes a domingo.
     *
     * @param array<int, array<string, mixed>> $filas filas de horarios_clinica
     * @return array<int, array{dia: string, franjas: list<array{inicio: string, fin: string}>}>
     */
    public static function semana(array $filas): array
    {
        $porDia = [];
        foreach ($filas as $fila) {
            $porDia[(int) $fila['dia_semana']] = $fila;
        }

        $semana = [];
        foreach (self::DIAS as $n => $nombre) {
            $fila = $porDia[$n] ?? null;
            $franjas = [];
            if ($fila && (int) $fila['activo'] === 1) {
                foreach (['morning', 'afternoon'] as $bloque) {
                    $inicio = self::hhmm($fila["bloque_{$bloque}_inicio"] ?? null);
                    $fin = self::hhmm($fila["bloque_{$bloque}_fin"] ?? null);
                    if ((int) ($fila["bloque_{$bloque}_activo"] ?? 0) === 1 && $inicio && $fin && $inicio < $fin) {
                        $franjas[] = ['inicio' => $inicio, 'fin' => $fin];
                    }
                }
            }
            $semana[$n] = ['dia' => $nombre, 'franjas' => $franjas];
        }
        return $semana;
    }

    /**
     * Estado de la clínica en este momento y cuándo cambia.
     *
     * @return array{abierta: bool, texto: string, detalle: string, hoy: int}
     */
    public static function ahora(array $semana, DateTimeImmutable $ahora): array
    {
        $ahora = $ahora->setTimezone(new DateTimeZone(ReglaAtencion::ZONA));
        $hoy = (int) $ahora->format('N');
        $hora = $ahora->format('H:i');

        foreach ($semana[$hoy]['franjas'] ?? [] as $franja) {
            if ($hora >= $franja['inicio'] && $hora < $franja['fin']) {
                return [
                    'abierta' => true,
                    'texto' => 'Abierto ahora',
                    'detalle' => 'Hasta las ' . ResumenPanel::hora($franja['fin']),
                    'hoy' => $hoy,
                ];
            }
        }

        // Cerrada: busca la próxima apertura, hoy más tarde o en los próximos 7 días.
        for ($salto = 0; $salto <= 7; $salto++) {
            $dia = (($hoy - 1 + $salto) % 7) + 1;
            foreach ($semana[$dia]['franjas'] ?? [] as $franja) {
                if ($salto === 0 && $franja['inicio'] <= $hora) {
                    continue;
                }
                $cuando = match ($salto) {
                    0 => 'hoy',
                    1 => 'mañana',
                    default => 'el ' . mb_strtolower($semana[$dia]['dia']),
                };
                return [
                    'abierta' => false,
                    'texto' => 'Cerrado ahora',
                    'detalle' => "Abre $cuando a las " . ResumenPanel::hora($franja['inicio']),
                    'hoy' => $hoy,
                ];
            }
        }

        return ['abierta' => false, 'texto' => 'Sin horario publicado', 'detalle' => 'La clínica aún no ha configurado su horario.', 'hoy' => $hoy];
    }

    /** «8:00 a. m. – 12:00 p. m. · 2:00 p. m. – 6:00 p. m.» o «Cerrado». */
    public static function texto(array $franjas): string
    {
        if (!$franjas) {
            return 'Cerrado';
        }
        return implode(' · ', array_map(
            fn ($f) => ResumenPanel::hora($f['inicio']) . ' – ' . ResumenPanel::hora($f['fin']),
            $franjas
        ));
    }

    /** M5 C4a: nombres cortos para la tabla agrupada de la semana. */
    public const DIAS_CORTOS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /**
     * M5 C4a (captura 02 del usuario): la semana en pocas filas, juntando los
     * días seguidos con el mismo horario: «Lun–Vie 8:00–12:00 · 14:00–18:00»,
     * «Sáb–Dom Cerrado». La fila que contiene a hoy va marcada.
     *
     * @return list<array{dias: string, texto: string, cerrado: bool, hoy: bool}>
     */
    public static function agrupar(array $semana, int $hoy): array
    {
        $grupos = [];
        foreach (array_keys(self::DIAS) as $n) {
            $franjas = $semana[$n]['franjas'] ?? [];
            $ultimo = array_key_last($grupos);
            if ($ultimo !== null && $grupos[$ultimo]['franjas'] === $franjas) {
                $grupos[$ultimo]['hasta'] = $n;
                continue;
            }
            $grupos[] = ['desde' => $n, 'hasta' => $n, 'franjas' => $franjas];
        }

        $filas = [];
        foreach ($grupos as $grupo) {
            $filas[] = [
                'dias' => self::rango($grupo['desde'], $grupo['hasta']),
                'texto' => self::textoCorto($grupo['franjas']),
                'cerrado' => !$grupo['franjas'],
                'hoy' => $hoy >= $grupo['desde'] && $hoy <= $grupo['hasta'],
            ];
        }
        return $filas;
    }

    /** «8:00–12:00 · 14:00–18:00» o «Cerrado»: cabe en una línea de la columna lateral. */
    public static function textoCorto(array $franjas): string
    {
        if (!$franjas) {
            return 'Cerrado';
        }
        return implode(' · ', array_map(
            fn ($f) => self::horaCorta($f['inicio']) . '–' . self::horaCorta($f['fin']),
            $franjas
        ));
    }

    private static function rango(int $desde, int $hasta): string
    {
        if ($desde === $hasta) {
            return self::DIAS_CORTOS[$desde];
        }
        return self::DIAS_CORTOS[$desde] . '–' . self::DIAS_CORTOS[$hasta];
    }

    /** «08:00» → «8:00». */
    private static function horaCorta(string $hhmm): string
    {
        return (int) substr($hhmm, 0, 2) . substr($hhmm, 2, 3);
    }

    private static function hhmm($valor): ?string
    {
        return is_string($valor) && preg_match('/^(\d{2}:\d{2})/', $valor, $m) ? $m[1] : null;
    }
}
