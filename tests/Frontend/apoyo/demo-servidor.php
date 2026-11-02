<?php
/** Solo arnés local: vistas/controladores/modelos reales, PDO temporal y reloj inyectado; cero resultados sintéticos. */
if (getenv('ZOOKI_ENTORNO_PRUEBAS') !== '1' || !getenv('ZOOKI_TEST_MYSQL_HOST')) {
    http_response_code(404);
    exit;
}
$raizDemo = dirname(__DIR__, 3);
$rutaDemo = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($rutaDemo !== '/index.php' && $rutaDemo !== '/') {
    if (str_starts_with($rutaDemo, '/__dependencia/')) {
        $nombre = basename($rutaDemo);
        $archivo = $raizDemo . '/scratch/r/userscript/dependencias/' . $nombre;
        if (!is_file($archivo)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/javascript');
        readfile($archivo);
        exit;
    }
    $publica = realpath($raizDemo . '/public');
    $recurso = realpath($publica . $rutaDemo);
    if ($recurso && str_starts_with($recurso, $publica . DIRECTORY_SEPARATOR) && is_file($recurso)) {
        if (pathinfo($recurso, PATHINFO_EXTENSION) === 'js') {
            $dependencias = json_decode(file_get_contents($raizDemo . '/scratch/r/userscript/dependencias/mapa.json'), true);
            header('Content-Type: application/javascript');
            echo str_replace(array_keys($dependencias), array_values($dependencias), file_get_contents($recurso));
            exit;
        }
        return false;
    }
    http_response_code(404);
    exit;
}
require $raizDemo . '/tests/bootstrap.php';
require $raizDemo . '/scripts/demo/SemillaDemo.php';
$db = (new Database(Database::configuracionPruebas()))->getConnection();
session_start();
if (isset($_GET['__persona'])) {
    $_SESSION = [];
    $alias = $_GET['__persona'] === 'portal' ? 'portal' : 'sanroque-vet-1';
    $usuario = (new Usuario($db))->buscarPorEmail(GuionDemo::correo($alias));
    Contexto::iniciarIdentidad((int) $usuario['id_usuario'], $usuario['nombre_completo'], false, 'password');
    $q = $db->prepare('SELECT id_clinica FROM clinicas WHERE email_contacto=?');
    $q->execute([GuionDemo::correo('sanroque-clinica')]);
    $clinica = (int) $q->fetchColumn();
    Contexto::activar($alias === 'portal' ? Contexto::dePropietario() : Contexto::deClinica($clinica, 'Centro Veterinario San Roque', Roles::VETERINARIO), 1);
    $_SESSION['version_sesion'] = (int) $usuario['version_sesion'];
}
$usuarios = new Usuario($db);
Security::definirFuenteDeContextos(static fn (int $id): ?array => $usuarios->contextosDe($id));
Security::definirAuditoria(new Auditoria($db));
$fecha = new DateTimeImmutable('2026-11-02T10:30:00-05:00');
$reloj = static fn () => $fecha;
$accion = $_GET['action'] ?? 'vet_agenda';
file_put_contents($raizDemo . '/scratch/r/userscript/peticiones.log', ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . $accion . PHP_EOL, FILE_APPEND);
// El arnés permite el único alta autorizada por el dueño: evaluación de la solicitud demo, nunca guardar/reservar.
$permitidasPost = ['sugerir_diagnosticos_ajax', 'sugerir_tratamientos_ajax', 'validar_prescripcion_ajax', 'calcular_triage_ajax'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($accion, $permitidasPost, true)) {
    http_response_code(403);
    exit('Mutación ajena al guion');
}
try {
    Security::autorizar($accion);
    $controladores = [
        'listar_citas_ajax' => ['CitaController', 'listarSemanaAjax'],
        'listar_veterinarios_ajax' => ['CitaController', 'listarVeterinariosAjax'],
        'listar_tipos_cita_ajax' => ['CitaController', 'listarTiposCitaAjax'],
        'get_cita_ajax' => ['CitaController', 'getCitaAjax'],
        'portal_get_tipos_cita_ajax' => ['CitaController', 'portalTiposCitaAjax'],
        'portal_get_vets_ajax' => ['CitaController', 'portalVeterinariosAjax'],
        'portal_get_sugerencias_ajax' => ['CitaController', 'portalSugerenciasAjax'],
        'listar_solicitudes_agenda_ajax' => ['CitaController', 'listarSolicitudesAjax'],
        'listar_llegadas_ajax' => ['LlegadaAgendaController', 'leer'],
        'listar_urgencias_ajax' => ['UrgenciaAgendaController', 'leer'],
        'listar_reasignaciones_ajax' => ['ReasignacionController', 'listarAjax'],
        'get_mascota_ajax' => ['MascotaController', 'getMascotaAjax'],
        'listar_mascotas_ajax' => ['MascotaController', 'listarMascotasAjax'],
        'listar_propietarios_ajax' => ['MascotaController', 'listarPropietariosAjax'],
        'listar_especies_ajax' => ['MascotaController', 'listarEspeciesAjax'],
        'listar_razas_ajax' => ['MascotaController', 'listarRazasAjax'],
        'listar_ausencias_ajax' => ['AusenciaAgendaController', 'listar'],
        'get_notificaciones_ajax' => ['NotificacionController', 'obtenerNotificaciones'],
        'listar_alertas_medicas_ajax' => ['AlertaMedicaController', 'listarAjax'],
        'consulta_contexto_ajax' => ['ControlConsultaController', 'contextoAjax'],
        'listar_historial_ajax' => ['ConsultaController', 'listarHistorialAjax'],
        'buscar_sintomas_ajax' => ['TriageController', 'buscarAjax'],
        'calcular_triage_ajax' => ['TriageController', 'calcularAjax'],
        'sugerir_diagnosticos_ajax' => ['DiagnosticoController', 'sugerirAjax'],
        'buscar_farmaco_ajax' => ['PrescripcionController', 'buscarFarmacosAjax'],
        'sugerir_tratamientos_ajax' => ['PrescripcionController', 'sugerirAjax'],
        'validar_prescripcion_ajax' => ['PrescripcionController', 'validarAjax'],
    ];
    if (isset($controladores[$accion])) {
        [$clase, $metodo] = $controladores[$accion];
        require_once $raizDemo . '/controllers/' . $clase . '.php';
        $argumentos = [$db];
        if (in_array($clase, ['CitaController', 'LlegadaAgendaController', 'ReasignacionController'], true)) $argumentos[] = $reloj;
        if (in_array($clase, ['TriageController', 'DiagnosticoController'], true)) $argumentos[] = $fecha;
        (new $clase(...$argumentos))->$metodo();
        exit;
    }
    ob_start();
    switch ($accion) {
        case 'vet_atencion':
            require_once $raizDemo . '/controllers/CitaController.php';
            (new CitaController($db, $reloj))->atencion();
            break;
        case 'vet_agenda':
            require_once $raizDemo . '/controllers/CitaController.php';
            $contextoAgenda = (new CitaController($db, $reloj))->contextoAgenda();
            $content_view = $raizDemo . '/views/vet/calendario.php';
            require $raizDemo . '/views/vet/layout.php';
            break;
        case 'vet_pacientes':
            require_once $raizDemo . '/controllers/MascotaController.php';
            $mascotas = (new MascotaController($db))->listar();
            $content_view = $raizDemo . '/views/vet/pacientes.php';
            require $raizDemo . '/views/vet/layout.php';
            break;
        case 'portal_propietario':
            require_once $raizDemo . '/controllers/PortalController.php';
            (new PortalController($db))->index();
            break;
        default:
            http_response_code(404);
    }
    $html = (string) ob_get_clean();
    // Solo transporte de dependencias externas: mismos bytes del CDN, sin falsificar respuestas ni resultados.
    $dependencias = json_decode(file_get_contents($raizDemo . '/scratch/r/userscript/dependencias/mapa.json'), true);
    echo str_replace(array_keys($dependencias), array_values($dependencias), $html);
} catch (AccesoDenegado $e) {
    Security::responder($e, $accion);
} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    error_log((string) $e);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
