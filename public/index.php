<?php
session_start();

// Configurar manejo de errores para que no se muestre HTML en respuestas AJAX
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Este es el Front Controller, el único archivo al que el usuario accede directamente.
$action = isset($_GET["action"]) ? $_GET["action"] : "login";

// Middleware de seguridad global: CSRF + Rate Limiting + Session AJAX
require_once "../helpers/Security.php";
Security::check($action);

// Enrutador básico
switch ($action) {
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
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../views/auth/cambiar_password.php";
        break;

    case "cambiar_password_ajax":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->cambiarPasswordAjax();
        break;

    case "admin_configuracion":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/admin/horarios.php";
        require_once "../views/admin/layout.php";
        break;

    case "logout":
        require_once "../controllers/AuthController.php";
        $controller = new AuthController();
        $controller->logout();
        break;

    case "get_dashboard_stats_ajax":
        require_once "../controllers/ConsultaController.php"; // Or create a specific dashboard controller
        $controller = new ConsultaController();
        $controller->getDashboardStatsAjax();
        break;

    // ═══════════════════════════════════════════════════════════════
    // RUTAS ADMIN (id_rol = 1) - Panel de Pacientes
    // ═══════════════════════════════════════════════════════════════
    case "admin_panel":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../views/admin/layout.php";
        break;

    case "admin_usuarios":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/admin/usuarios.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_pacientes":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/admin/clientes.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_nuevo_paciente":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir al listado de pacientes
        header("Location: index.php?action=admin_pacientes");
        exit();

    case "admin_editar_paciente":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $mascota = $controller->editar();
        $content_view = "../views/admin/editar_paciente.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_personal":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir a la vista unificada de usuarios
        header("Location: index.php?action=admin_pacientes");
        exit();

    case "admin_estadisticas":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir al panel principal (son lo mismo)
        header("Location: index.php?action=admin_panel");
        exit();

    case "admin_citas":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/admin/citas.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_reportes":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/admin/reportes.php";
        require_once "../views/admin/layout.php";
        break;

    case "admin_auditoria":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->listarAuditoria();
        break;

    case "get_auditoria_ajax":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            echo json_encode(["success" => false, "message" => "No autorizado"]);
            exit();
        }
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getAuditoriaAjax();
        break;

    // ═══════════════════════════════════════════════════════════════
    // RUTAS VETERINARIO (id_rol = 2) - Área Clínica
    // ═══════════════════════════════════════════════════════════════
    case "vet_area":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../views/vet/layout.php";
        break;

    case "vet_atencion":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->atencion();
        break;

    case "vet_consultas":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/ConsultaController.php";
        $controller = new ConsultaController();
        $consultas = $controller->listar();
        $content_view = "../views/vet/consultas.php";
        require_once "../views/vet/layout.php";
        break;

    case "vet_nueva_consulta":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/vet/modal_consulta.php";
        require_once "../views/vet/layout.php";
        break;

    case "vet_pacientes":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $mascotas = $controller->listar();
        $content_view = "../views/vet/pacientes.php";
        require_once "../views/vet/layout.php";
        break;

    case "vet_agenda":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/vet/calendario.php";
        require_once "../views/vet/layout.php";
        break;

    case "vet_historial":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 2) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/ConsultaController.php";
        $controller = new ConsultaController();
        $controller->listar();
        $content_view = "../views/vet/consultas.php";
        require_once "../views/vet/layout.php";
        break;

    // ═══════════════════════════════════════════════════════════════
    // RUTAS RECEPCIONISTA (id_rol = 3) - Dashboard de Recepción
    // ═══════════════════════════════════════════════════════════════
    case "reception_dashboard":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 3) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../views/reception/layout.php";
        break;

    case "reception_agenda":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 3) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/reception/agenda.php";
        require_once "../views/reception/layout.php";
        break;

    case "reception_nueva_cita":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 3) {
            header("Location: index.php?action=login");
            exit();
        }
        $content_view = "../views/reception/calendario.php";
        require_once "../views/reception/layout.php";
        break;

    case "reception_pacientes":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 3) {
            header("Location: index.php?action=login");
            exit();
        }
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $mascotas = $controller->listar();
        $content_view = "../views/reception/pacientes.php";
        require_once "../views/reception/layout.php";
        break;

    case "reception_nuevo_paciente":
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 3) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir al listado de pacientes
        header("Location: index.php?action=reception_pacientes");
        exit();

    // ═══════════════════════════════════════════════════════════════
    // RUTAS LEGACY (Mantener compatibilidad con rutas antiguas)
    // ═══════════════════════════════════════════════════════════════
    case "dashboard":
        // Redirigir al dashboard correspondiente según rol
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        if ($_SESSION["usuario_id_rol"] == 1) {
            header("Location: index.php?action=admin_panel");
        } elseif ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=vet_area");
        } elseif ($_SESSION["usuario_id_rol"] == 3) {
            header("Location: index.php?action=reception_dashboard");
        } elseif ($_SESSION["usuario_id_rol"] == 4) {
            header("Location: index.php?action=portal_propietario");
        }
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

    case "listar_mascotas":
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir según rol
        if ($_SESSION["usuario_id_rol"] == 1) {
            header("Location: index.php?action=admin_pacientes");
        } elseif ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=vet_pacientes");
        } elseif ($_SESSION["usuario_id_rol"] == 3) {
            header("Location: index.php?action=reception_pacientes");
        } else {
            header("Location: index.php?action=dashboard");
        }
        exit();

    case "nueva_mascota":
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir según rol
        if ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=vet_pacientes");
        } elseif ($_SESSION["usuario_id_rol"] == 1) {
            header("Location: index.php?action=admin_pacientes");
        } elseif ($_SESSION["usuario_id_rol"] == 3) {
            header("Location: index.php?action=reception_pacientes");
        } else {
            header("Location: index.php?action=dashboard");
        }
        exit();

    case "editar_mascota":
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir según rol
        if ($_SESSION["usuario_id_rol"] == 1) {
            require_once "../controllers/MascotaController.php";
            $controller = new MascotaController();
            $mascota = $controller->editar();
            $content_view = "../views/admin/editar_paciente.php";
            require_once "../views/admin/layout.php";
        } else {
            header("Location: index.php?action=dashboard");
            exit();
        }
        break;

    case "guardar_mascota":
        if ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=dashboard");
            exit();
        }
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->registrar();
        break;

    case "actualizar_mascota":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->actualizar();
        break;

    case "nuevo_propietario":
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        if ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=vet_area");
            exit();
        }
        // Redirigir según rol
        if ($_SESSION["usuario_id_rol"] == 1) {
            header("Location: index.php?action=admin_pacientes");
        } elseif ($_SESSION["usuario_id_rol"] == 3) {
            header("Location: index.php?action=reception_pacientes");
        } else {
            $content_view = "../views/admin/propietario_registro.php";
            require_once "../views/dashboard/index.php";
        }
        break;

    case "guardar_propietario":
        if ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=dashboard");
            exit();
        }
        require_once "../controllers/PropietarioController.php";
        $controller = new PropietarioController();
        $controller->registrar();
        break;

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
    case "registrar_color_ajax":
        require_once "../controllers/MascotaController.php";
        $controller = new MascotaController();
        $controller->registrarColorAjax();
        break;

    // RUTAS SPRINT 2: CONSULTAS MÉDICAS
    case "listar_consultas":
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir según rol
        if ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=vet_consultas");
        } elseif ($_SESSION["usuario_id_rol"] == 1) {
            header("Location: index.php?action=admin_panel");
        } else {
            require_once "../controllers/ConsultaController.php";
            $controller = new ConsultaController();
            $controller->listar();
        }
        exit();
    case "registrar_consulta_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 2
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
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
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 2
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->registrarAjax();
        break;
    case "listar_vacunas_pendientes_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->listarPendientesAjax();
        break;
    case "get_vacunas_por_especie_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->getVacunasPorEspecieAjax();
        break;
    case "registrar_nueva_vacuna_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 2
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->registrarNuevaVacunaAjax();
        break;
    case "get_laboratorios_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->getLaboratoriosAjax();
        break;

    case "get_vacunas_pendientes_panel_ajax":
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->getVacunasPendientesPanelAjax();
        break;
    case "registrar_nuevo_laboratorio_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 2
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
        require_once "../controllers/VacunaController.php";
        $controller = new VacunaController();
        $controller->registrarNuevoLaboratorioAjax();
        break;

    // RUTAS DESPARASITACIÓN
    case "registrar_desparasitacion_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 2
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
        require_once "../controllers/DesparasitacionController.php";
        $controller = new DesparasitacionController();
        $controller->registrarAjax();
        break;
    case "listar_desparasitaciones_pendientes_ajax":
        require_once "../controllers/DesparasitacionController.php";
        $controller = new DesparasitacionController();
        $controller->listarPendientesAjax();
        break;
    case "get_productos_desparasitacion_ajax":
        require_once "../controllers/DesparasitacionController.php";
        $controller = new DesparasitacionController();
        $controller->getProductosAjax();
        break;
    case "registrar_nuevo_producto_desparasitacion_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 2
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
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
    case "listar_calendario_ajax":
        require_once "../controllers/CitaController.php";
        $controller = new CitaController();
        $controller->listarCalendarioAjax();
        break;
    case "calendario":
        if (!isset($_SESSION["usuario_doc"])) {
            header("Location: index.php?action=login");
            exit();
        }
        // Redirigir según rol
        if ($_SESSION["usuario_id_rol"] == 1) {
            header("Location: index.php?action=admin_citas");
        } elseif ($_SESSION["usuario_id_rol"] == 2) {
            header("Location: index.php?action=vet_agenda");
        } elseif ($_SESSION["usuario_id_rol"] == 3) {
            header("Location: index.php?action=reception_agenda");
        } else {
            header("Location: index.php?action=portal_propietario");
        }
        exit();
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

    // RUTAS SPRINT 5: GESTIÓN DE USUARIOS (ADMIN)
    case "listar_usuarios":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_rol"] != "administrador"
        ) {
            header("Location: index.php?action=dashboard");
            exit();
        }
        header("Location: index.php?action=admin_personal");
        exit();

    case "registrar_usuario_ajax":
        try {
            if (
                !isset($_SESSION["usuario_doc"]) ||
                $_SESSION["usuario_id_rol"] != 1
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => "No autorizado",
                ]);
                exit();
            }
            require_once "../controllers/UsuarioController.php";
            $controller = new UsuarioController();
            $controller->registrarAjax();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case "actualizar_usuario_ajax":
        try {
            if (
                !isset($_SESSION["usuario_doc"]) ||
                $_SESSION["usuario_id_rol"] != 1
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => "No autorizado",
                ]);
                exit();
            }
            require_once "../controllers/UsuarioController.php";
            $controller = new UsuarioController();
            $controller->actualizarAjax();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case "get_usuario_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 1
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->getUsuarioAjax();
        break;

    case "cambiar_estado_usuario_ajax":
        if (
            !isset($_SESSION["usuario_doc"]) ||
            $_SESSION["usuario_id_rol"] != 1
        ) {
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
        require_once "../controllers/UsuarioController.php";
        $controller = new UsuarioController();
        $controller->cambiarEstadoAjax();
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
        if (!isset($_SESSION["usuario_doc"])) {
            echo json_encode(["success" => false]);
            exit();
        }
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getStatsAjax();
        break;

    case "get_charts_data_ajax":
        if (!isset($_SESSION["usuario_doc"])) {
            echo json_encode(["success" => false]);
            exit();
        }
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getChartsDataAjax();
        break;

    case "get_pendientes_ajax":
        if (!isset($_SESSION["usuario_doc"])) {
            echo json_encode(["success" => false]);
            exit();
        }
        require_once "../controllers/DashboardController.php";
        $controller = new DashboardController();
        $controller->getPendientesAjax();
        break;

    case "get_timeline_ajax":
        if (!isset($_SESSION["usuario_doc"])) {
            echo json_encode(["success" => false]);
            exit();
        }
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
?>
