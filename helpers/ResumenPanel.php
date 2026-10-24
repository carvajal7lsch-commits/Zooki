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
    private const DIAS_LARGOS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    private const MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * Acción disponible para el veterinario sobre una cita suya:
     *  - continuar: la atención está en curso o sin cerrar (RN-406, RN-409).
     *  - iniciar:   es hoy y ya se alcanzó la ventana de inicio (RN-407).
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

    /**
     * Totales del día que muestran los contadores. Las canceladas no cuentan.
     * RE-6.1.10: «sin cerrar» va aparte; una atención pausada sigue por atender.
     */
    public static function contadores(array $citas): array
    {
        $c = ['citas' => 0, 'atendidas' => 0, 'por_atender' => 0, 'no_asistio' => 0, 'sin_cerrar' => 0];
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
            } elseif ($estado === 'sin_cerrar') {
                $c['sin_cerrar']++;
            } elseif (in_array($estado, ['pendiente', 'confirmada', 'en_curso', 'pausada'], true)) {
                $c['por_atender']++;
            }
        }
        return $c;
    }

    /** RE-6.2.10: desde este porcentaje de uso, el Inicio del administrador advierte. */
    public const AVISO_PLAN = 80;

    /**
     * RE-6.2.10: cada recurso del plan con su uso, su límite, el porcentaje y
     * el nivel: «libre» sin límite, «ok», «aviso» desde AVISO_PLAN o «limite».
     */
    public static function usoDelPlan(array $resumen): array
    {
        $uso = [];
        foreach ($resumen as $recurso => $valores) {
            $limite = $valores['limite'];
            $porcentaje = $limite === null || $limite <= 0 ? null : min(100, (int) round($valores['uso'] / $limite * 100));
            if ($porcentaje === null) {
                $nivel = 'libre';
            } elseif ($valores['uso'] >= $limite) {
                $nivel = 'limite';
            } elseif ($porcentaje >= self::AVISO_PLAN) {
                $nivel = 'aviso';
            } else {
                $nivel = 'ok';
            }
            $uso[$recurso] = ['uso' => (int) $valores['uso'], 'limite' => $limite, 'porcentaje' => $porcentaje, 'nivel' => $nivel];
        }
        return $uso;
    }

    /**
     * Las barras del Inicio no usan estilos en línea: su largo va en clases
     * de 5 en 5 (inicio-ancho--0 … inicio-ancho--100). Redondea a esa escala.
     */
    public static function escala5(?int $porcentaje): int
    {
        return (int) (round(max(0, min(100, (int) $porcentaje)) / 5) * 5);
    }

    /** «Beto Norte» → «BN»; una sola palabra da su inicial. */
    public static function iniciales(string $nombre): string
    {
        $partes = array_slice(preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY), 0, 2);
        return mb_strtoupper(implode('', array_map(fn($parte) => mb_substr($parte, 0, 1), $partes)));
    }

    /** RE-6.2.12: porcentaje entero de una parte; 0 si no hay total. */
    public static function porcentaje(int $parte, int $total): int
    {
        return $total <= 0 ? 0 : (int) round($parte / $total * 100);
    }

    /** 30 → «30 min», 60 → «1 h», 90 → «1 h 30 min». */
    public static function duracion(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;
        if ($horas === 0) {
            return $resto . ' min';
        }
        return $horas . ' h' . ($resto > 0 ? ' ' . $resto . ' min' : '');
    }

    /** RE-6.1.5: «en 20 min», «ahora» o «hace 10 min» respecto de la hora de la cita. */
    public static function cuentaRegresiva(string $fecha, string $hora, DateTimeImmutable $ahora): string
    {
        $minutos = (int) floor((ReglaAtencion::inicio($fecha, $hora)->getTimestamp() - $ahora->getTimestamp()) / 60);
        if ($minutos === 0) {
            return 'ahora';
        }
        return $minutos > 0 ? 'en ' . self::duracion($minutos) : 'hace ' . self::duracion(-$minutos);
    }

    /** Edad legible desde la fecha de nacimiento: «4 años», «1 año», «7 meses»; null sin dato. */
    public static function edad(?string $nacimiento, DateTimeImmutable $hoy): ?string
    {
        if (!$nacimiento || !preg_match('/^\d{4}-\d{2}-\d{2}/', $nacimiento)) {
            return null;
        }
        $diferencia = (new DateTimeImmutable(substr($nacimiento, 0, 10), $hoy->getTimezone()))->diff($hoy->setTime(0, 0));
        if ($diferencia->invert) {
            return null;
        }
        if ($diferencia->y > 0) {
            return $diferencia->y . ($diferencia->y === 1 ? ' año' : ' años');
        }
        if ($diferencia->m > 0) {
            return $diferencia->m . ($diferencia->m === 1 ? ' mes' : ' meses');
        }
        return 'menos de 1 mes';
    }

    /** RE-6.1.11: cuándo fue la consulta: «Hoy 9:30 a. m.», «Ayer» o «lun 19 oct». */
    public static function momentoReciente(string $fechaHora, DateTimeImmutable $hoy): string
    {
        $fecha = substr($fechaHora, 0, 10);
        $dias = (int) $hoy->setTime(0, 0)->diff(new DateTimeImmutable($fecha, $hoy->getTimezone()))->format('%r%a');
        if ($dias === 0) {
            return 'Hoy ' . self::hora(substr($fechaHora, 11, 5));
        }
        return $dias === -1 ? 'Ayer' : self::etiquetaDia($fecha, $hoy);
    }

    /** Título de «Tu día»: «hoy» o «jueves 22». */
    public static function tituloDia(string $fecha, DateTimeImmutable $hoy): string
    {
        if ($fecha === $hoy->format('Y-m-d')) {
            return 'hoy';
        }
        $dia = new DateTimeImmutable($fecha, $hoy->getTimezone());
        return self::DIAS_LARGOS[(int) $dia->format('w')] . ' ' . $dia->format('j');
    }

    /**
     * RE-6.1.8: el mes del calendario del Inicio, de lunes a domingo. Cada
     * celda dice su fecha, si ya pasó, si es hoy, si está elegida y si el
     * veterinario tiene citas ese día; los huecos antes del día 1 son null.
     */
    public static function mes(string $mes, array $diasConCitas, string $hoy, string $elegido): array
    {
        $primero = new DateTimeImmutable($mes . '-01', new DateTimeZone(ReglaAtencion::ZONA));
        $conCitas = array_flip($diasConCitas);
        $celdas = array_fill(0, (int) $primero->format('N') - 1, null);
        for ($dia = $primero; $dia->format('Y-m') === $mes; $dia = $dia->modify('+1 day')) {
            $fecha = $dia->format('Y-m-d');
            $tieneCitas = isset($conCitas[$fecha]);
            $celdas[] = [
                'fecha' => $fecha,
                'numero' => (int) $dia->format('j'),
                'pasado' => $fecha < $hoy,
                'hoy' => $fecha === $hoy,
                'elegido' => $fecha === $elegido,
                'citas' => $tieneCitas,
                'etiqueta' => self::DIAS_LARGOS[(int) $dia->format('w')] . ' ' . $dia->format('j') . ' de '
                    . self::MESES_LARGOS[(int) $dia->format('n') - 1] . ($tieneCitas ? ', con citas' : ''),
            ];
        }
        return [
            'mes' => $mes,
            'titulo' => ucfirst(self::MESES_LARGOS[(int) $primero->format('n') - 1]) . ' ' . $primero->format('Y'),
            'anterior' => $primero->modify('-1 month')->format('Y-m'),
            'siguiente' => $primero->modify('+1 month')->format('Y-m'),
            'celdas' => $celdas,
        ];
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
        return self::DIAS_LARGOS[(int) $dia->format('w')] . ' ' . $dia->format('j') . ' de ' . self::MESES_LARGOS[(int) $dia->format('n') - 1];
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
