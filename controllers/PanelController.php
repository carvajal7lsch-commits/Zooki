<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Panel.php';
require_once __DIR__ . '/../models/DisponibilidadAgenda.php';
require_once __DIR__ . '/../models/SolicitudAgenda.php';
require_once __DIR__ . '/../models/CascadaAgenda.php';
require_once __DIR__ . '/../helpers/ResumenPanel.php';
require_once __DIR__ . '/../helpers/DiaVeterinario.php';
require_once __DIR__ . '/../helpers/LimitePlan.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/**
 * Datos de los paneles de inicio. Se calculan en el servidor y la vista los
 * pinta directamente: sin peticiones AJAX al cargar, sin datos de ejemplo y
 * con el "hoy" de la clínica, no el del servidor. Todo es de la clínica
 * activa (Panel extiende ModeloClinica).
 */
class PanelController
{
    /** RE-6.1.11: cuántos pacientes recientes se piden; la vista muestra los que quepan. */
    public const RECIENTES = 8;

    private PDO $db;
    private Panel $panel;
    private Closure $reloj;

    public function __construct(?PDO $db = null, ?callable $reloj = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->panel = new Panel($this->db);
        $this->reloj = Closure::fromCallable($reloj ?? fn (): DateTimeImmutable => $this->ahora());
    }

    /**
     * RE-6.1.8 y RE-6.1.9: «Tu día» de otra fecha (?fecha=AAAA-MM-DD) o el
     * calendario de otro mes (?mes=AAAA-MM), con los mismos parciales del
     * primer render. Solo lectura y solo la agenda propia: el veterinario sale
     * de la sesión, nunca de la petición (RE-6.1.4, RNF-11).
     */
    public function diaAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $ahora = ($this->reloj)();
            $idVeterinario = (int) Contexto::idUsuario();
            if (isset($_GET['mes'])) {
                $mes = self::validarMes((string) $_GET['mes']);
                $elegido = self::validarFecha((string) ($_GET['elegido'] ?? $ahora->format('Y-m-d')));
                RespuestaJson::enviar(['success' => true, 'mes' => $mes, 'html' => $this->render('partials/inicio_calendario', ['mes' => $this->mes($idVeterinario, $mes, $ahora, $elegido)])]);
                return;
            }
            $fecha = self::validarFecha((string) ($_GET['fecha'] ?? ''));
            $siguiente = $fecha === $ahora->format('Y-m-d')
                ? ResumenPanel::siguiente($this->conAccion($this->panel->citasDelDia($fecha, $idVeterinario), $ahora), $ahora)
                : null;
            $dia = $this->dia($idVeterinario, $fecha, $ahora, $siguiente);
            RespuestaJson::enviar(['success' => true, 'fecha' => $fecha, 'html' => $this->render('vet/partials/inicio_dia', ['dia' => $dia])]);
        }, 'Inicio del veterinario');
    }

    /**
     * RE-6.2.8 y RE-6.2.9: la agenda por veterinario de otra fecha (?fecha=)
     * o el calendario de otro mes (?mes=), con los mismos parciales del
     * primer render. Solo lectura, solo para el administrador y solo de la
     * clínica activa (RNF-11).
     */
    public function diaAdminAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $ahora = ($this->reloj)();
            if (isset($_GET['mes'])) {
                $mes = self::validarMes((string) $_GET['mes']);
                $elegido = self::validarFecha((string) ($_GET['elegido'] ?? $ahora->format('Y-m-d')));
                RespuestaJson::enviar(['success' => true, 'mes' => $mes, 'html' => $this->render('partials/inicio_calendario', ['mes' => $this->mes(null, $mes, $ahora, $elegido)])]);
                return;
            }
            $fecha = self::validarFecha((string) ($_GET['fecha'] ?? ''));
            $agenda = $this->agendaPorVeterinario($fecha, $ahora);
            RespuestaJson::enviar(['success' => true, 'fecha' => $fecha, 'html' => $this->render('admin/partials/inicio_agenda', ['agenda' => $agenda])]);
        }, 'Inicio del administrador');
    }

    /** HU-6.1: el día del veterinario en la clínica activa (RE-6.1.4). */
    public function datosVeterinario(int $idVeterinario, ?DateTimeImmutable $ahora = null): array
    {
        $ahora = $ahora ?? ($this->reloj)();
        $this->revisarAtencionesAbiertas($ahora);
        $hoy = $ahora->format('Y-m-d');

        $agenda = $this->conAccion($this->panel->citasDelDia($hoy, $idVeterinario), $ahora);
        $siguiente = ResumenPanel::siguiente($agenda, $ahora);
        if ($siguiente !== null) {
            $siguiente += [
                'edad' => ResumenPanel::edad($siguiente['fecha_nacimiento'] ?? null, $ahora),
                'cuenta' => ResumenPanel::cuentaRegresiva($siguiente['fecha'], $siguiente['hora'], $ahora),
                'alertas' => $this->panel->alertasActivas((int) $siguiente['id_mascota']),
            ];
        }

        // Las atenciones de hoy ya se ven en la agenda con su botón; aquí solo
        // se listan las que exigen algo aparte: las abiertas de días anteriores
        // y las que quedaron sin cerrar.
        $pendientes = array_values(array_filter(
            $this->conAccion($this->panel->atencionesAbiertas($idVeterinario), $ahora),
            fn($c) => $c['estado'] === 'sin_cerrar' || $c['fecha'] !== $hoy
        ));

        $recordatorios = $this->panel->recordatoriosDeSusPacientes(
            $idVeterinario,
            $hoy,
            $ahora->modify('+7 days')->format('Y-m-d')
        );

        return [
            'hoy' => $hoy,
            'fecha' => ResumenPanel::fechaLarga($ahora),
            'saludo' => $this->saludo($ahora),
            'contadores' => ResumenPanel::contadores($agenda),
            'siguiente' => $siguiente,
            'agenda' => $agenda,
            'dia' => $this->dia($idVeterinario, $hoy, $ahora, $siguiente),
            'mes' => $this->mes($idVeterinario, $ahora->format('Y-m'), $ahora, $hoy),
            'pendientes' => array_map(fn($c) => $c + ['dia' => ResumenPanel::etiquetaDia($c['fecha'], $ahora)], $pendientes),
            'triages' => $this->leerOpcional(fn () => (new SolicitudAgenda($this->db, fn () => $ahora))->listar()['solicitudes'], 'triages por revisar'),
            'reasignaciones' => $this->leerOpcional(fn () => (new CascadaAgenda($this->db, fn () => $ahora))->listar(), 'reasignaciones'),
            'recordatorios' => ResumenPanel::agruparPorDia($recordatorios, $ahora),
            'total_recordatorios' => count($recordatorios),
            'recientes' => array_map(
                fn($r) => $r + ['cuando' => ResumenPanel::momentoReciente((string) $r['fecha_hora'], $ahora)],
                $this->panel->pacientesRecientes($idVeterinario, self::RECIENTES)
            ),
        ];
    }

    /**
     * RE-6.1.9: las filas de «Tu día» de una fecha. Si la disponibilidad no se
     * puede calcular (una cita con datos incompletos la frena), se muestran
     * las citas con el motivo, sin inventar huecos.
     */
    private function dia(int $idVeterinario, string $fecha, DateTimeImmutable $ahora, ?array $siguiente): array
    {
        $filas = $this->filasDelDia($idVeterinario, $fecha, $ahora, $siguiente === null ? null : (int) $siguiente['id_cita']);
        $lineas = $filas['lineas'];
        $disponibilidad = $filas['disponibilidad'];
        $totalCitas = DiaVeterinario::totalCitas($lineas);
        return [
            'fecha' => $fecha,
            'titulo' => ResumenPanel::tituloDia($fecha, $ahora),
            'pasado' => $fecha < $ahora->format('Y-m-d'),
            'lineas' => $lineas,
            'citas' => $totalCitas,
            'minutos_libres' => DiaVeterinario::minutosLibres($lineas),
            'sin_jornada' => $disponibilidad !== null && $disponibilidad['jornada'] === [],
            'aviso' => $filas['aviso'],
            'proximo' => $totalCitas === 0 ? $this->proximo($idVeterinario, $fecha, $ahora) : null,
        ];
    }

    /**
     * Las filas del día de un veterinario: sus citas y la disponibilidad
     * real. La comparten el Inicio del veterinario (RE-6.1.9) y la agenda por
     * veterinario del administrador (RE-6.2.9), para que digan lo mismo.
     */
    private function filasDelDia(int $idVeterinario, string $fecha, DateTimeImmutable $ahora, ?int $idSiguiente): array
    {
        $disponibilidad = null;
        $aviso = null;
        try {
            $disponibilidad = (new DisponibilidadAgenda($this->db, fn () => $ahora))->diaDelVeterinario($idVeterinario, $fecha);
        } catch (HorarioOcupado $e) {
            $aviso = $e->getMessage();
        }
        $lineas = DiaVeterinario::lineas(
            $this->panel->citasDelDia($fecha, $idVeterinario),
            $disponibilidad,
            $fecha,
            $ahora,
            $idSiguiente,
            $this->panel->duracionMinimaCita()
        );
        return ['lineas' => $lineas, 'disponibilidad' => $disponibilidad, 'aviso' => $aviso];
    }

    /**
     * RE-6.2.9: la agenda de una fecha con una columna por veterinario activo
     * de la clínica, incluso sin citas (RE-6.2.2), ordenadas por nombre para
     * que no cambien de lugar al pasar de un día a otro.
     */
    private function agendaPorVeterinario(string $fecha, DateTimeImmutable $ahora): array
    {
        $columnas = [];
        foreach ($this->panel->cargaPorVeterinario($fecha) as $veterinario) {
            $id = (int) $veterinario['id_usuario'];
            $filas = $this->filasDelDia($id, $fecha, $ahora, null);
            $disponibilidad = $filas['disponibilidad'];
            $columnas[] = [
                'id' => $id,
                'nombre' => (string) $veterinario['veterinario'],
                'citas' => DiaVeterinario::totalCitas($filas['lineas']),
                'ocupacion' => $disponibilidad === null ? null : DiaVeterinario::ocupacion($disponibilidad),
                'sin_jornada' => $disponibilidad !== null && $disponibilidad['jornada'] === [],
                'lineas' => $filas['lineas'],
                'aviso' => $filas['aviso'],
            ];
        }
        usort($columnas, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return [
            'fecha' => $fecha,
            'titulo' => ResumenPanel::tituloDia($fecha, $ahora),
            'citas' => array_sum(array_column($columnas, 'citas')),
            'columnas' => $columnas,
        ];
    }

    private function proximo(int $idVeterinario, string $fecha, DateTimeImmutable $ahora): ?array
    {
        $proximo = $this->panel->proximoDiaConCitas($idVeterinario, max($fecha, $ahora->modify('-1 day')->format('Y-m-d')));
        return $proximo === null ? null : ['fecha' => $proximo, 'texto' => ResumenPanel::tituloDia($proximo, $ahora)];
    }

    /** RE-6.1.8 y RE-6.2.8: el calendario de un mes con los días con citas del veterinario o, sin él, de la clínica. */
    private function mes(?int $idVeterinario, string $mes, DateTimeImmutable $ahora, string $elegido): array
    {
        $primero = new DateTimeImmutable($mes . '-01', $ahora->getTimezone());
        $dias = $this->panel->diasConCitas($idVeterinario, $primero->format('Y-m-d'), $primero->modify('last day of this month')->format('Y-m-d'));
        return ResumenPanel::mes($mes, $dias, $ahora->format('Y-m-d'), $elegido);
    }

    /**
     * Pendientes que vienen de otros módulos de la agenda (RE-6.1.10). Si uno
     * falla, el Inicio sigue sin esa fila; un acceso denegado sí se propaga.
     */
    private function leerOpcional(callable $leer, string $que): array
    {
        try {
            return $leer();
        } catch (AccesoDenegado $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('Inicio, ' . $que . ': ' . $e->getMessage());
            return [];
        }
    }

    /** Renderiza un parcial de un Inicio (ruta dentro de views/) con sus datos y devuelve el HTML. */
    private function render(string $parcial, array $datos): string
    {
        extract($datos);
        ob_start();
        require __DIR__ . '/../views/' . $parcial . '.php';
        return (string) ob_get_clean();
    }

    private static function validarFecha(string $valor): string
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }
        return $valor;
    }

    private static function validarMes(string $valor): string
    {
        $mes = DateTimeImmutable::createFromFormat('!Y-m', $valor);
        if ($mes === false || $mes->format('Y-m') !== $valor) {
            throw new InvalidArgumentException('El mes no es válido.');
        }
        return $valor;
    }

    /** HU-6.2 y RE-6.4.1: la operación de la clínica activa para el administrador. */
    public function datosAdministrador(?DateTimeImmutable $ahora = null): array
    {
        $ahora = $ahora ?? ($this->reloj)();
        $this->revisarAtencionesAbiertas($ahora);
        $hoy = $ahora->format('Y-m-d');

        $citas = $this->panel->citasDelDia($hoy);
        $inicioHoy = $ahora->setTime(0, 0)->format('Y-m-d H:i:s');
        $inicioManana = $ahora->modify('+1 day')->setTime(0, 0)->format('Y-m-d H:i:s');
        $agenda = $this->agendaPorVeterinario($hoy, $ahora);

        // El mes en curso se compara con el mismo tramo del mes anterior (del 1
        // al día de hoy), no con el mes completo, que siempre saldría mayor.
        $inicioMes = $ahora->modify('first day of this month')->setTime(0, 0);
        $manana = $ahora->modify('+1 day')->setTime(0, 0);
        $inicioAnterior = $inicioMes->modify('-1 month');
        $diasTranscurridos = (int) $ahora->format('j');
        $finAnterior = min(
            $inicioAnterior->modify("+$diasTranscurridos days"),
            $inicioMes
        );
        $consultasMes = $this->panel->contarConsultas($inicioMes->format('Y-m-d H:i:s'), $manana->format('Y-m-d H:i:s'));
        $consultasAnterior = $this->panel->contarConsultas($inicioAnterior->format('Y-m-d H:i:s'), $finAnterior->format('Y-m-d H:i:s'));

        $abiertas = $this->panel->atencionesAbiertas();
        $desdeTendencia = $inicioMes->modify('-5 months')->format('Y-m-d');
        $sinCerrar = array_values(array_filter($abiertas, fn($c) => $c['estado'] === 'sin_cerrar' || ($c['estado'] === 'en_curso' && $c['fecha'] !== $hoy)));
        $asistencia = $this->panel->asistenciaDelPeriodo($inicioMes->format('Y-m-d'), $hoy);
        $finApertura = (new DisponibilidadAgenda($this->db, fn () => $ahora))->finDeApertura($hoy);

        return [
            'hoy' => $hoy,
            'fecha' => ResumenPanel::fechaLarga($ahora),
            'saludo' => $this->saludo($ahora),
            'contadores' => ResumenPanel::contadores($citas),
            // RE-6.2.7: «en espera» y «urgencias» según la decisión del dueño (2026-10-24).
            'en_espera' => $this->panel->contarEnEspera($hoy, $inicioHoy, $inicioManana),
            'urgencias' => $this->panel->contarUrgencias($hoy, $inicioHoy, $inicioManana),
            'veterinarios_hoy' => count(array_filter($agenda['columnas'], fn($c) => $c['ocupacion'] !== null)),
            'abierta_hasta' => $finApertura === null ? null : ResumenPanel::hora($finApertura->format('H:i')),
            'mes' => $this->mes(null, $ahora->format('Y-m'), $ahora, $hoy),
            'agenda' => $agenda,
            'plan' => $this->usoDelPlan($hoy),
            'mes_actual' => $this->nombreMes($ahora),
            'propietarios_nuevos' => $this->panel->contarPropietariosNuevos($inicioMes->format('Y-m-d H:i:s'), $inicioManana),
            'no_asistencia' => $asistencia + ['porcentaje' => ResumenPanel::porcentaje($asistencia['no_asistio'], $asistencia['agendadas'])],
            'sin_cerrar' => array_map(fn($c) => $c + ['dia' => ResumenPanel::momentoReciente($c['fecha'] . ' ' . $c['hora'], $ahora)], $sinCerrar),
            'ausencias_por_aprobar' => array_map(
                fn($a) => $a + ['dia' => ResumenPanel::etiquetaDia(substr((string) $a['fecha_hora_inicio'], 0, 10), $ahora)],
                $this->panel->ausenciasPorAprobar()
            ),
            'propuestas_por_aprobar' => $this->panel->propuestasPorAprobar(),
            'consultas_mes' => $consultasMes,
            'pacientes_activos' => $this->panel->contarPacientesActivos(),
            'propietarios' => $this->panel->contarPropietarios(),
            'variacion_consultas' => ResumenPanel::variacion($consultasMes, $consultasAnterior),
            'mes_anterior' => $this->nombreMes($inicioAnterior),
            'citas' => $citas,
            'carga' => $this->panel->cargaPorVeterinario($hoy),
            'pendientes' => [
                'sin_cerrar' => $this->porVeterinario(array_filter($abiertas, fn($c) => $c['estado'] === 'sin_cerrar')),
                'en_curso_otro_dia' => $this->porVeterinario(array_filter($abiertas, fn($c) => $c['estado'] === 'en_curso' && $c['fecha'] !== $hoy)),
                'por_confirmar' => $this->panel->contarPorConfirmar($hoy, $ahora->modify('+7 days')->format('Y-m-d')),
                'sin_marcar' => $this->panel->contarSinMarcar(
                    $ahora->modify('-30 days')->format('Y-m-d'),
                    $hoy,
                    $ahora->format('H:i:s')
                ),
            ],
            'tendencia' => ResumenPanel::serieMensual(
                $this->panel->tendenciaMensual($desdeTendencia, $hoy),
                $ahora
            ),
        ];
    }

    /**
     * RE-6.2.10: el uso del plan de la clínica activa con LimitePlan, el mismo
     * cálculo que aplica los límites (HU-0.4). Si no se puede leer, el Inicio
     * sigue sin la tarjeta y lo dice; un acceso denegado sí se propaga.
     */
    private function usoDelPlan(string $hoy): ?array
    {
        try {
            $limite = new LimitePlan($this->db, (int) Contexto::clinicaActiva());
            return ['nombre' => $limite->nombrePlan(), 'recursos' => ResumenPanel::usoDelPlan($limite->resumen($hoy))];
        } catch (AccesoDenegado $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('Inicio, uso del plan: ' . $e->getMessage());
            return null;
        }
    }

    /** Cuenta citas por veterinario: ['Ana Gómez' => 2, ...], de más a menos. */
    private function porVeterinario(array $citas): array
    {
        $conteo = [];
        foreach ($citas as $cita) {
            $nombre = $cita['veterinario'];
            $conteo[$nombre] = ($conteo[$nombre] ?? 0) + 1;
        }
        arsort($conteo);
        return $conteo;
    }

    private function conAccion(array $citas, DateTimeImmutable $ahora): array
    {
        return array_map(fn($c) => $c + ['accion' => ResumenPanel::accion($c, $ahora)], $citas);
    }

    // RN-409: el panel es la primera pantalla del día, así que también revisa
    // las atenciones abiertas, igual que el calendario. Un fallo no lo tumba.
    private function revisarAtencionesAbiertas(DateTimeImmutable $ahora): void
    {
        try {
            require_once __DIR__ . '/../helpers/VigilanteAtenciones.php';
            (new VigilanteAtenciones($this->db))->revisar($ahora);
        } catch (Throwable $e) {
            error_log('No se pudieron revisar las atenciones abiertas: ' . $e->getMessage());
        }
    }

    private function saludo(DateTimeImmutable $ahora): string
    {
        $hora = (int) $ahora->format('G');
        if ($hora < 12) {
            return 'Buenos días';
        }
        return $hora < 19 ? 'Buenas tardes' : 'Buenas noches';
    }

    private function nombreMes(DateTimeImmutable $mes): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $meses[(int) $mes->format('n') - 1];
    }

    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
    }
}
