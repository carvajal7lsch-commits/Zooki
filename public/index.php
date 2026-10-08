<?php
// T-07: la cookie de sesion se configura ANTES de abrirla, si no los flags no
// aplican. HttpOnly la esconde de JavaScript, SameSite=Lax corta el CSRF por
// navegacion de terceros y Secure se activa solo bajo HTTPS para no romper el
// desarrollo local por HTTP.
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $esHttps,
    'samesite' => 'Lax',
]);

session_start();

// TR-04: cabeceras de seguridad para toda respuesta. Se emiten aqui, en el
// front controller, para que ninguna vista pueda olvidarlas.
header('X-Frame-Options: SAMEORIGIN');          // clickjacking sobre el panel
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($esHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Configurar manejo de errores para que no se muestre HTML en respuestas AJAX
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Este es el Front Controller, el único archivo al que el usuario accede directamente.
$action = isset($_GET["action"]) ? $_GET["action"] : "landing";

// Seguridad en un solo lugar (HU-T.10, HU-T.15): sesión, contexto activo,
// rol, clínica y CSRF. Las rutas no repiten comprobaciones de rol.
require_once "../helpers/Security.php";
Security::check($action);

// Enrutador básico. Un controlador que encuentra un recurso de otra clínica
// lanza AccesoDenegado (Security::denegarRecursoAjeno) y aquí se responde 403.
try {
switch ($action) {
    case "confirmar_vinculo_propietario":
        require_once '../controllers/PropietarioController.php';
        (new PropietarioController())->confirmarVinculo();
        break;

    case "solicitar_vinculo_propietario_ajax":
        require_once '../controllers/PropietarioController.php';
        (new PropietarioController())->solicitarVinculoAjax();
        break;

    case "buscar_propietario_exacto_ajax":
        require_once '../controllers/MascotaController.php';
        (new MascotaController())->buscarPropietarioExactoAjax();
        break;

    case "vincular_mascota_ajax":
        require_once '../controllers/MascotaController.php';
        (new MascotaController())->vincularMascotaAjax();
        break;

    case "landing":
        require_once "../controllers/LandingController.php";
        $controller = new LandingController();
        $controller->index();
        break;

    case "privacidad":
        require_once "../controllers/LandingController.php";
        $controller = new LandingController();
        $controller->privacidad();
        break;

    case "terminos":
        require_once "../controllers/LandingController.php";
        $controller = new LandingController();
        $controller->terminos();
        break;

    case "cookies":
        require_once "../controllers/LandingController.php";
        $controller = new LandingController();
        $controller->cookies();
        break;

    case "login":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->login();
        break;

    case "register":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->register();
        break;

    case "estado_verificacion_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->estadoVerificacionAjax();
        break;

    case "verificar_email":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->verificarEmail();
        break;

    case "process_register":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->processRegister();
        break;

    case "check_document_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->checkDocumentAjax();
        break;

    case "check_email_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->checkEmailAjax();
        break;

    case "google_login_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->processGoogleLoginAjax();
        break;

    case "complete_google_register_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->completeGoogleRegisterAjax();
        break;

    case "solicitar_reset_password_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->solicitarResetPasswordAjax();
        break;

    case "reset_password":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->mostrarResetPassword();
        break;

    case "procesar_reset_password_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->procesarResetPasswordAjax();
        break;

    case "cambiar_password":
        require_once "../views/auth/cambiar_password.php";
        break;

    case "cambiar_password_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->cambiarPasswordAjax();
        break;

    case "admin_configuracion":
        $content_view = "../views/admin/horarios.php";
        require_once "../views/admin/layout.php";
        break;

    case "logout":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->logout();
        break;

    // HU-T.17: elegir o cambiar el contexto activo sin cerrar sesión.
    case "seleccionar_contexto":
        require_once "../controllers/ContextoController.php";
        (new ContextoController())->mostrar();
        break;

    case "cambiar_contexto":
        require_once "../controllers/ContextoController.php";
        (new ContextoController())->cambiar();
        break;

    // Página mínima de la plataforma; el panel del super-administrador es de la etapa E.
    case "plataforma_inicio":
        require_once "../views/plataforma/inicio.php";
        break;

    // ═══════════════════════════════════════════════════════════════
    // RUTAS ADMIN (id_rol = 1) - Panel de Pacientes
    // ═══════════════════════════════════════════════════════════════
    case "admin_panel":
        require_once "../controllers/PanelController.php";
        $panel = (new PanelController())->datosAdministrador();
        require_once "../views/admin/layout.php";
        break;

    case "admin_usuarios":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $personal = $controller->listar();
        $propietarios = $controller->listarPropietarios();
        $content_view = "../views/admin/usuarios.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_citas":
        $content_view = "../views/admin/citas.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_auditoria":
        require_once "../controllers/AuditoriaController.php";
        (new AuditoriaController())->listar();
        break;

    case "get_auditoria_ajax":
        require_once "../controllers/AuditoriaController.php";
        (new AuditoriaController())->listarAjax();
        break;

    // ═══════════════════════════════════════════════════════════════
    // RUTAS VETERINARIO (id_rol = 2) - Área Clínica
    // ═══════════════════════════════════════════════════════════════
    case "vet_area":
        // PanelController pasa a id_usuario e id_clinica en C7; hasta
        // entonces recibe el id_usuario del contexto activo.
        require_once "../controllers/PanelController.php";
        $panel = (new PanelController())->datosVeterinario((string) Contexto::idUsuario());
        require_once "../views/vet/layout.php";
        break;

    case "vet_atencion":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->atencion();
        break;

    case "vet_consultas":
        require_once "../controllers/ConsultaController.php";
        $controller = new ConsultaController();
        $consultas = $controller->listar();
        $content_view = "../views/vet/consultas.php";
        require_once "../views/vet/layout.php";
        break;

    case "vet_pacientes":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $mascotas = $controller->listar();
        $content_view = "../views/vet/pacientes.php";
        require_once "../views/vet/layout.php";
        break;

    case "vet_agenda":
        $content_view = "../views/vet/calendario.php";
        require_once "../views/vet/layout.php";
        break;

    // ═══════════════════════════════════════════════════════════════
    // INICIO: cada contexto aterriza en su panel (RE-T.1.4)
    // ═══════════════════════════════════════════════════════════════
    case "dashboard":
        // Security ya exigió un contexto activo para llegar aquí.
        header("Location: index.php?action=" . Contexto::destino(Contexto::actual()));
        exit();

    case "portal_propietario":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->index();
        break;

    case "ver_detalle_mascota_propietario_ajax":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->verDetalleMascotaAjax();
        break;

    case "portal_registrar_mascota_ajax":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->registrarMascotaAjax();
        break;

    case "portal_actualizar_mascota_ajax":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->actualizarMascotaAjax();
        break;

    case "portal_get_detalle_cita_clinica_ajax":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->getDetalleCitaClinicaAjax();
        break;

    case "portal_imprimir_historial":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->imprimirHistorial();
        break;

    case "portal_actualizar_datos_contacto_ajax":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->actualizarDatosContactoAjax();
        break;



    case "portal_get_vets_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarVeterinariosAjax();
        break;

    case "portal_get_tipos_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarTiposCitaAjax();
        break;

    case "portal_agendar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->registrarAjax();
        break;

    case "nueva_mascota":
        // Redirigir según el rol del contexto activo
        $destino = Contexto::rolActivo() === Roles::VETERINARIO ? "vet_pacientes" : "admin_usuarios";
        header("Location: index.php?action=" . $destino);
        exit();

    case "actualizar_mascota":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->actualizar();
        break;

    case "nuevo_propietario":
        // Redirigir según el rol del contexto activo. La rama que cargaba
        // vistas inexistentes (propietario_registro, dashboard/index) se retiró.
        $destino = Contexto::rolActivo() === Roles::ADMIN ? "admin_usuarios" : "vet_area";
        header("Location: index.php?action=" . $destino);
        exit();

    case "guardar_propietario_ajax":
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->registrarAjax();
        break;
    case "buscar_mascotas":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->buscar();
        break;

    case "guardar_mascota_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->registrarAjax();
        break;

    case "actualizar_mascota_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->actualizarAjax();
        break;

    case "cambiar_estado_mascota_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->cambiarEstadoAjax();
        break;

    case "get_mascota_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->getMascotaAjax();
        break;

    case "listar_propietarios_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->listarPropietariosAjax();
        break;
    case "listar_mascotas_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->listarMascotasAjax();
        break;
    case "listar_mascotas_propietario_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->listarMascotasPorPropietarioAjax();
        break;
    case "get_propietario_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->getPropietarioAjax();
        break;
    case "actualizar_propietario_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->actualizarPropietarioAjax();
        break;
    case "listar_especies_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->listarEspeciesAjax();
        break;
    case "listar_razas_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->listarRazasAjax();
        break;
    case "listar_colores_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->listarColoresAjax();
        break;
    // RUTAS SPRINT 2: CONSULTAS MÉDICAS
    case "registrar_consulta_ajax":
        require_once "../controllers/ConsultaController.php";
        $controller = new ConsultaController();
        $controller->registrarAjax();
        break;
    case "listar_historial_ajax":
        require_once "../controllers/ConsultaController.php";
        $controller = new ConsultaController();
        $controller->listarHistorialAjax();
        break;

    // RUTAS SPRINT 3: VACUNACIÓN Y CALENDARIO
    case "registrar_vacuna_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->registrarAjax();
        break;
    case "get_vacunas_por_especie_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->getVacunasPorEspecieAjax();
        break;
    case "registrar_nueva_vacuna_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->registrarNuevaVacunaAjax();
        break;
    case "get_laboratorios_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->getLaboratoriosAjax();
        break;

    case "registrar_nuevo_laboratorio_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->registrarNuevoLaboratorioAjax();
        break;

    // RUTAS DESPARASITACIÓN
    case "registrar_desparasitacion_ajax":
        require_once "../controllers/DesparasitacionController.php";
        $controller = new DesparasitacionController();
        $controller->registrarAjax();
        break;
    case "get_productos_desparasitacion_ajax":
        require_once "../controllers/DesparasitacionController.php";
        $controller = new DesparasitacionController();
        $controller->getProductosAjax();
        break;
    case "registrar_nuevo_producto_desparasitacion_ajax":
        require_once "../controllers/DesparasitacionController.php";
        $controller = new DesparasitacionController();
        $controller->registrarNuevoProductoAjax();
        break;

    // RUTAS SPRINT 4: CITAS Y AGENDA
    case "registrar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->registrarAjax();
        break;
    case "enviar_email_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->enviarEmailAjax();
        break;
    case "listar_citas_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarSemanaAjax();
        break;
    case "listar_todas_citas_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarTodasCitasAjax();
        break;
    case "listar_veterinarios_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarVeterinariosAjax();
        break;
    case "listar_tipos_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarTiposCitaAjax();
        break;
    case "get_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->getCitaAjax();
        break;
    case "get_sugerencias_horario_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->getSugerenciasHorarioAjax();
        break;
    case "reprogramar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->reprogramarCitaAjax();
        break;
    case "cancelar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->cancelarAjax();
        break;
    case "iniciar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->iniciarAtencionAjax();
        break;
    case "completar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->completarAtencionAjax();
        break;
    case "confirmar_cita_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->confirmarAjax();
        break;
    case "marcar_no_asistio_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->marcarNoAsistioAjax();
        break;

    // RUTAS SPRINT 5: GESTIÓN DE USUARIOS (ADMIN)
    // HU-T.7: el controlador captura sus propios errores y responde un mensaje
    // genérico (T-04); aquí no se atrapa nada para no tragarse un 403.
    case "registrar_usuario_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->registrarAjax();
        break;

    case "actualizar_usuario_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->actualizarAjax();
        break;

    case "get_usuario_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->getUsuarioAjax();
        break;

    case "cambiar_estado_usuario_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->cambiarEstadoAjax();
        break;

    // HU-42: panel "Mi perfil" del personal (el propietario tiene el suyo en el portal).
    case "mi_perfil":
        require_once "../controllers/PerfilController.php";
        $controller = new PerfilController();
        $controller->mostrar();
        break;

    // HU-42: perfil propio, disponible para los cuatro roles.
    case "actualizar_mi_perfil_ajax":
        require_once "../controllers/PerfilController.php";
        $controller = new PerfilController();
        $controller->actualizarAjax();
        break;

    // HU-54: restablecer la contrasena de un usuario. El control de rol lo
    // aplica la matriz de Security, no hace falta repetirlo aqui.
    case "resetear_password_usuario_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->resetearPasswordAjax();
        break;

    case "verificar_documento_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->verificarDocumentoAjax();
        break;

    case "verificar_email_ajax":
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->verificarEmailAjax();
        break;

    case "get_role_stats_ajax":
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getStatsAjax();
        break;

    case "get_charts_data_ajax":
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getChartsDataAjax();
        break;

    case "get_pendientes_ajax":
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getPendientesAjax();
        break;

    case "get_timeline_ajax":
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getCitasHoyTimelineAjax();
        break;

    case "get_horarios_clinica_ajax":
        require_once "../controllers/HorarioClinicaController.php";
        $controller = new HorarioClinicaController();
        $controller->getHorariosAjax();
        break;

    case "guardar_horarios_clinica_ajax":
        require_once "../controllers/HorarioClinicaController.php";
        $controller = new HorarioClinicaController();
        $controller->guardarHorariosAjax();
        break;

    case "restaurar_horarios_defecto_ajax":
        require_once "../controllers/HorarioClinicaController.php";
        $controller = new HorarioClinicaController();
        $controller->restaurarPorDefectoAjax();
        break;

    case "get_horas_disponibles_ajax":
        require_once "../controllers/HorarioClinicaController.php";
        $controller = new HorarioClinicaController();
        $controller->obtenerHorasDisponiblesAjax();
        break;

    // RUTAS SPRINT 6: NOTIFICACIONES INTERNAS
    case "get_notificaciones_ajax":
        require_once "../controllers/NotificacionController.php";
        $controller = new NotificacionController();
        $controller->obtenerNotificaciones();
        break;

    case "marcar_notificacion_leida_ajax":
        require_once "../controllers/NotificacionController.php";
        $controller = new NotificacionController();
        $controller->marcarLeida();
        break;

    case "marcar_todas_notificaciones_leidas_ajax":
        require_once "../controllers/NotificacionController.php";
        $controller = new NotificacionController();
        $controller->marcarTodasLeidas();
        break;

    default:
        require_once "../views/auth/login.php";
        break;
}
} catch (AccesoDenegado $e) {
    Security::responder($e, $action);
}
?>
