<?php
require_once __DIR__ . '/ReglaAtencion.php';

/**
 * Lógica de presentación de los paneles de inicio, sin acceso a datos.
 *
 * Decide qué acción ofrece cada cita, cuál es el siguiente paciente, cómo se
 * agrupan los recordatorios y cuánto varía un indicador. Las reglas de tiempo
 * salen de ReglaAtencion, las mismas que aplica el servidor al iniciar.
 */
final class ResumenPanel
{
    private const ABIERTAS = ['pendiente', 'confirmada'];
    private const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /**
     * Acción disponible para el veterinario sobre una cita suya:
     *  - continuar: la atención está en curso o sin cerrar (RN-406, RN-410).
     *  - iniciar:   es hoy y ya se alcanzó la ventana de inicio (RN-408).
     *  - esperar:   es hoy pero todavía no; 'desde' dice a qué hora se habilita.
     *  - null:      no hay nada que hacer desde el panel.
     */
    public static function accion(array $cita, DateTimeImmutable $ahora): array
    {
        $estado = $cita['estado'] ?? '';

        if (in_array($estado, ['en_curso', 'sin_cerrar'], true)) {
            return ['tipo' => 'continuar'];
        }
        if (!in_array($estado, self::ABIERTAS, true) || $cita['fecha'] !== $ahora->format('Y-m-d')) {
            return ['tipo' => null];
        }

        $desde = ReglaAtencion::iniciaDesde($cita['fecha'], $cita['hora']);
        if (ReglaAtencion::puedeIniciar($cita['fecha'], $cita['hora'], $ahora)) {
            return ['tipo' => 'iniciar'];
        }
        return ['tipo' => 'esperar', 'desde' => $desde->format('H:i'), 'desde_iso' => $desde->format(DATE_ATOM)];
    }

    /**
     * Siguiente paciente del día: la atención en curso si la hay; si no, la
     * primera cita abierta que todavía no ha terminado.
     */
    public static function siguiente(array $citasDelDia, DateTimeImmutable $ahora): ?array
    {
        foreach ($citasDelDia as $cita) {
            if (($cita['estado'] ?? '') === 'en_curso') {
                return $cita;
            }
        }
        foreach ($citasDelDia as $cita) {
            if (!in_array($cita['estado'] ?? '', self::ABIERTAS, true)) {
                continue;
            }
            $fin = ReglaAtencion::fin($cita['fecha'], $cita['hora'], $cita['hora_fin'] ?? null, $cita['duracion_minutos'] ?? null);
            if ($fin > $ahora) {
                return $cita;
            }
        }
        return null;
    }

    /** Totales del día que muestran los contadores. Las canceladas no cuentan. */
    public static function contadores(array $citas): array
    {
        $c = ['citas' => 0, 'atendidas' => 0, 'por_atender' => 0, 'no_asistio' => 0];
        foreach ($citas as $cita) {
            $estado = $cita['estado'] ?? '';
            if ($estado === 'cancelada') {
                continue;
            }
            $c['citas']++;
            if ($estado === 'completada') {
                $c['atendidas']++;
            } elseif ($estado === 'no_asistio') {
                $c['no_asistio']++;
            } elseif (in_array($estado, ['pendiente', 'confirmada', 'en_curso'], true)) {
                $c['por_atender']++;
            }
        }
        return $c;
    }

    /** Agrupa filas con 'fecha' en días: «Hoy», «Mañana» o «jue 17 sep». */
    public static function agruparPorDia(array $filas, DateTimeImmutable $hoy): array
    {
        $grupos = [];
        foreach ($filas as $fila) {
            $fecha = substr((string) $fila['fecha'], 0, 10);
            if (!isset($grupos[$fecha])) {
                $grupos[$fecha] = ['fecha' => $fecha, 'etiqueta' => self::etiquetaDia($fecha, $hoy), 'items' => []];
            }
            $grupos[$fecha]['items'][] = $fila;
        }
        ksort($grupos);
        return array_values($grupos);
    }

    public static function etiquetaDia(string $fecha, DateTimeImmutable $hoy): string
    {
        $dia = new DateTimeImmutable($fecha, $hoy->getTimezone());
        $diferencia = (int) $hoy->setTime(0, 0)->diff($dia->setTime(0, 0))->format('%r%a');
        if ($diferencia === 0) {
            return 'Hoy';
        }
        if ($diferencia === 1) {
            return 'Mañana';
        }
        return self::DIAS[(int) $dia->format('w')] . ' ' . $dia->format('j') . ' ' . self::MESES[(int) $dia->format('n') - 1];
    }

    /** Fecha larga para el encabezado: «martes 15 de septiembre». */
    public static function fechaLarga(DateTimeImmutable $dia): string
    {
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $dias[(int) $dia->format('w')] . ' ' . $dia->format('j') . ' de ' . $meses[(int) $dia->format('n') - 1];
    }

    /** Variación entera en porcentaje; null si no hay base con qué comparar. */
    public static function variacion(int $actual, int $anterior): ?int
    {
        if ($anterior <= 0) {
            return null;
        }
        return (int) round(($actual - $anterior) / $anterior * 100);
    }

    /**
     * Completa la tendencia con los meses sin citas, para que el eje no salte
     * meses. Devuelve los últimos $meses meses hasta el de $hoy, en orden.
     */
    public static function serieMensual(array $filas, DateTimeImmutable $hoy, int $meses = 6): array
    {
        $porMes = [];
        foreach ($filas as $fila) {
            $porMes[$fila['mes']] = $fila;
        }

        $serie = [];
        $primero = $hoy->modify('first day of this month')->setTime(0, 0);
        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = $primero->modify("-$i months");
            $clave = $mes->format('Y-m');
            $serie[] = [
                'mes' => $clave,
                'etiqueta' => self::MESES[(int) $mes->format('n') - 1],
                'atendidas' => (int) ($porMes[$clave]['atendidas'] ?? 0),
                'no_asistidas' => (int) ($porMes[$clave]['no_asistidas'] ?? 0),
            ];
        }
        return $serie;
    }

    public const ETIQUETAS_ESTADO = [
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'en_curso' => 'En curso',
        'completada' => 'Completada',
        'cancelada' => 'Cancelada',
        'no_asistio' => 'No asistió',
        'sin_cerrar' => 'Sin cerrar',
        'cerrada_sin_consulta' => 'Cerrada sin consulta',
    ];

    public static function etiquetaEstado(string $estado): string
    {
        return self::ETIQUETAS_ESTADO[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
    }

    /** «14:30:00» → «2:30 p. m.», el mismo formato del calendario. */
    public static function hora(?string $hora): string
    {
        if (!$hora || !preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
            return '—';
        }
        $h = (int) $m[1];
        $sufijo = $h < 12 ? 'a. m.' : 'p. m.';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;
        return "$h12:{$m[2]} $sufijo";
    }

    /** «Juan Carlos Pérez» → «Juan». */
    public static function primerNombre(?string $nombre): string
    {
        $partes = preg_split('/\s+/', trim((string) $nombre));
        return $partes[0] ?? '';
    }
}
