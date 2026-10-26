<?php
require_once __DIR__ . '/ResumenPanel.php';
require_once __DIR__ . '/IntervalosAgenda.php';

/**
 * «Tu día» del Inicio del veterinario (HU-6.1, RE-6.1.9): las filas de un día
 * en orden de hora, sin acceso a datos.
 *
 * Mezcla las citas del veterinario con lo que dice la disponibilidad real
 * (DisponibilidadAgenda::diaDelVeterinario): los huecos libres que todavía se
 * pueden agendar y lo que corta la jornada (el almuerzo entre franjas, los
 * cierres puntuales y las ausencias aprobadas). Nada se inventa: sin
 * disponibilidad calculada no hay huecos.
 */
final class DiaVeterinario
{
    /** Estado de la fila de una cita → texto que se muestra. */
    public const ESTADOS = [
        'atendida' => 'Atendida',
        'sin_cerrar' => 'Sin cerrar',
        'en_atencion' => 'En atención',
        'siguiente' => 'Siguiente',
        'por_atender' => 'Por atender',
        'no_asistio' => 'No asistió',
    ];

    /** Un hueco de hoy empieza en el siguiente múltiplo de 5 minutos desde ahora. */
    private const REDONDEO_SEGUNDOS = 300;

    /** Orden de las filas que empiezan a la misma hora. */
    private const ORDEN_TIPO = ['bloqueo' => 0, 'cita' => 1, 'libre' => 2];

    /**
     * @param array      $citas   filas de Panel::citasDelDia del veterinario.
     * @param array|null $dia     DisponibilidadAgenda::diaDelVeterinario, o null si no se pudo calcular.
     * @param int|null   $idSiguiente la cita que el Inicio destaca como siguiente.
     * @param int|null   $minimoMinutos el tipo de cita activo más corto; un hueco menor no se ofrece.
     */
    public static function lineas(array $citas, ?array $dia, string $fecha, DateTimeImmutable $ahora, ?int $idSiguiente, ?int $minimoMinutos): array
    {
        $lineas = [];
        foreach ($citas as $cita) {
            $estado = self::estadoDeCita($cita, $idSiguiente);
            if ($estado === null) {
                continue;
            }
            $lineas[] = [
                'tipo' => 'cita',
                'inicio' => ReglaAtencion::inicio((string) $cita['fecha'], (string) $cita['hora'])->getTimestamp(),
                'hora' => ResumenPanel::hora((string) $cita['hora']),
                'estado' => $estado,
                'etiqueta' => self::ESTADOS[$estado],
                'mascota' => (string) $cita['mascota'],
                'texto' => trim((string) ($cita['motivo'] ?? '')) !== '' ? (string) $cita['motivo'] : (string) ($cita['tipo'] ?? ''),
                // RE-6.2.9: un caso rojo se distingue en la agenda del administrador.
                'urgencia' => ($cita['prioridad'] ?? '') === 'rojo',
                'cita' => $cita,
            ];
        }
        if ($dia !== null) {
            $lineas = array_merge(
                $lineas,
                self::libres($dia['libres'], $fecha, $ahora, $minimoMinutos),
                self::bloqueos($dia)
            );
        }
        usort($lineas, static fn ($a, $b) => [$a['inicio'], self::ORDEN_TIPO[$a['tipo']]] <=> [$b['inicio'], self::ORDEN_TIPO[$b['tipo']]]);
        return $lineas;
    }

    /** Estado de la fila; null si la cita no se muestra (cancelada). */
    public static function estadoDeCita(array $cita, ?int $idSiguiente): ?string
    {
        switch ($cita['estado'] ?? '') {
            case 'completada':
                return 'atendida';
            case 'sin_cerrar':
                return 'sin_cerrar';
            case 'en_curso':
            case 'pausada':
                return 'en_atencion';
            case 'pendiente':
            case 'confirmada':
                return $idSiguiente !== null && (int) $cita['id_cita'] === $idSiguiente ? 'siguiente' : 'por_atender';
            case 'no_asistio':
                return 'no_asistio';
            default:
                return null;
        }
    }

    /** Minutos libres que todavía se pueden agendar ese día. */
    public static function minutosLibres(array $lineas): int
    {
        $total = 0;
        foreach ($lineas as $linea) {
            if ($linea['tipo'] === 'libre') {
                $total += $linea['minutos'];
            }
        }
        return $total;
    }

    /**
     * RE-6.2.9: porcentaje de la jornada efectiva (jornada − cierres −
     * ausencias) que no está libre; null si ese día no hay jornada.
     */
    public static function ocupacion(array $dia): ?int
    {
        $cortes = array_merge(array_map(static fn ($c) => [$c[0], $c[1]], $dia['cierres']), $dia['ausencias']);
        $efectiva = self::segundos(IntervalosAgenda::restar($dia['jornada'], $cortes));
        if ($efectiva === 0) {
            return null;
        }
        $ocupada = max(0, $efectiva - self::segundos($dia['libres']));
        return (int) round($ocupada / $efectiva * 100);
    }

    /** Cuántas citas hay en las filas. */
    public static function totalCitas(array $lineas): int
    {
        return count(array_filter($lineas, static fn ($l) => $l['tipo'] === 'cita'));
    }

    /**
     * Huecos libres. De un día pasado no se agenda nada; de hoy, solo desde
     * ahora. Un hueco más corto que la cita más corta no se puede agendar.
     */
    private static function libres(array $libres, string $fecha, DateTimeImmutable $ahora, ?int $minimoMinutos): array
    {
        $hoy = $ahora->format('Y-m-d');
        if ($fecha < $hoy) {
            return [];
        }
        $corte = $fecha === $hoy ? (int) (ceil($ahora->getTimestamp() / self::REDONDEO_SEGUNDOS) * self::REDONDEO_SEGUNDOS) : PHP_INT_MIN;
        $minimo = max(1, (int) $minimoMinutos) * 60;
        $lineas = [];
        foreach ($libres as [$desde, $hasta]) {
            $desde = max($desde, $corte);
            if ($hasta - $desde < $minimo) {
                continue;
            }
            $inicio = self::instante($desde);
            $minutos = intdiv($hasta - $desde, 60);
            $lineas[] = [
                'tipo' => 'libre',
                'inicio' => $desde,
                'hora' => ResumenPanel::hora($inicio->format('H:i')),
                'hora_iso' => $inicio->format('H:i'),
                'fecha' => $inicio->format('Y-m-d'),
                'minutos' => $minutos,
                'texto' => 'Libre · ' . ResumenPanel::duracion($minutos),
            ];
        }
        return $lineas;
    }

    /** Almuerzo entre franjas, cierres puntuales y ausencias aprobadas, dentro de la jornada. */
    private static function bloqueos(array $dia): array
    {
        $jornada = $dia['jornada'];
        if ($jornada === []) {
            return [];
        }
        $lineas = [];
        for ($i = 1, $n = count($jornada); $i < $n; $i++) {
            $lineas[] = self::bloqueo($jornada[$i - 1][1], $jornada[$i][0], 'Almuerzo · hasta las ' . self::horaDe($jornada[$i][0]), 'almuerzo');
        }
        $primero = $jornada[0][0];
        $ultimo = $jornada[count($jornada) - 1][1];
        $cortes = array_merge(
            array_map(static fn ($c) => [$c[0], $c[1], 'Clínica cerrada' . (trim($c[2]) !== '' ? ' · ' . trim($c[2]) : ''), 'cierre'], $dia['cierres']),
            array_map(static fn ($a) => [$a[0], $a[1], 'Ausencia', 'ausencia'], $dia['ausencias'])
        );
        foreach ($cortes as [$desde, $hasta, $texto, $clase]) {
            $desde = max($desde, $primero);
            if ($desde >= min($hasta, $ultimo)) {
                continue;
            }
            $lineas[] = self::bloqueo($desde, $hasta, $texto . ($hasta >= $ultimo ? ' · resto del día' : ' · hasta las ' . self::horaDe($hasta)), $clase);
        }
        return $lineas;
    }

    /** $clase: almuerzo, cierre o ausencia, para que cada pantalla los distinga. */
    private static function bloqueo(int $desde, int $hasta, string $texto, string $clase): array
    {
        return ['tipo' => 'bloqueo', 'clase' => $clase, 'inicio' => $desde, 'fin' => $hasta, 'hora' => self::horaDe($desde), 'texto' => $texto];
    }

    private static function segundos(array $intervalos): int
    {
        return array_sum(array_map(static fn ($i) => $i[1] - $i[0], $intervalos));
    }

    private static function horaDe(int $instante): string
    {
        return ResumenPanel::hora(self::instante($instante)->format('H:i'));
    }

    private static function instante(int $segundos): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $segundos))->setTimezone(new DateTimeZone(ReglaAtencion::ZONA));
    }
}
