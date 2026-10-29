<?php
// Inicio del administrador (HU-6.2) con vistas, layout y controlador reales sobre SQLite de
// pruebas; nunca usa la base de la aplicación ni lee .env.
// Uso: php inicio-admin-vista.php ESCENARIO [pagina|ajax] [consulta]
//   ESCENARIO: dos (dos veterinarios con pendientes), cuatro (cuatro veterinarios con pendientes),
//              cuatrosin (cuatro sin pendientes), sinpend, uno o vacio (clínica sin citas).
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/PanelController.php';
require_once __DIR__ . '/../../../config/App.php';

$escenario = $argv[1] ?? 'dos';
$modo = $argv[2] ?? 'pagina';
parse_str($argv[3] ?? '', $peticion);

$db = DosClinicas::sqlite();
DosClinicas::crearMascotasSqlite($db);
DosClinicas::crearCatalogosSqlite($db);
DosClinicas::crearHistoriaSqlite($db);
DosClinicas::poblarAgenda($db);
// Norte en el plan gratuito para que se vean los límites (RE-6.2.10).
$db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 1');
$db->exec('UPDATE suscripciones SET id_plan = 1 WHERE id_clinica = 1');

session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE, 'usuario_nombre' => 'Ana Norte'];
Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::ADMIN), 1);

$mascotas = [10 => 'Toby', 11 => 'Mia', 12 => 'Rocky', 13 => 'Max', 14 => 'Kira', 15 => 'Bruno', 16 => 'Nala', 17 => 'Simba', 18 => 'Coco', 19 => 'Lola'];
$alta = $db->prepare("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado) VALUES (?, 6, 1, ?, ?, 1, 1)");
foreach ($mascotas as $id => $nombre) {
    $alta->execute([$id, str_pad((string) $id, 43, 'm'), $nombre]);
    $db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES ($id, 1, 'activo')");
}
$cita = $db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado, duracion_minutos, margen_minutos, prioridad, hora_llegada)
    VALUES (1, ?, ?, 1, ?, ?, ?, ?, 30, 10, ?, ?)");
$c = fn ($mascota, $vet, $fecha, $hora, $motivo, $estado, $prioridad = 'verde', $llegada = null) => $cita->execute([$mascota, $vet, $fecha, $hora, $motivo, $estado, $prioridad, $llegada]);
$hoy = '2026-10-23';

if (str_starts_with($escenario, 'cuatro')) {
    // Dos veterinarios más en Norte, con la misma jornada del fixture.
    $persona = $db->prepare("INSERT INTO usuarios (id_usuario, documento, tipo_documento, nombre_completo, email, password, es_super_admin, estado) VALUES (?, ?, 'CC', ?, ?, 'x', 0, 1)");
    $franja = $db->prepare('INSERT INTO horarios_veterinario (id_clinica, id_veterinario, dia_semana, hora_inicio, hora_fin) VALUES (1, ?, ?, ?, ?)');
    foreach ([21 => 'Camilo Ríos', 22 => 'Daniela Mora'] as $id => $nombre) {
        $persona->execute([$id, '20000000' . $id, $nombre, "vet$id@zooki.test"]);
        $db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES ($id, 1, 2, 'activo')");
        for ($dia = 1; $dia <= 7; $dia++) {
            $franja->execute([$id, $dia, '08:00:00', '12:00:00']);
            $franja->execute([$id, $dia, '14:00:00', '18:00:00']);
        }
    }
    $c(17, 21, $hoy, '09:00:00', 'Consulta', 'completada');
    $c(18, 22, $hoy, '15:00:00', 'Control', 'confirmada');
}

if ($escenario !== 'vacio') {
    $c(10, DosClinicas::VET_NORTE, $hoy, '08:30:00', 'Vacunación', 'completada');
    $c(11, DosClinicas::VET_NORTE, $hoy, '09:30:00', 'Control', 'completada');
    $c(1, DosClinicas::VET_NORTE, $hoy, '14:00:00', 'Vómito', 'confirmada', 'naranja', $hoy . ' 13:30:00');
    $c(13, DosClinicas::VET_NORTE, $hoy, '15:00:00', 'Primera visita', 'confirmada');
    $c(14, DosClinicas::VET_NORTE, $hoy, '16:30:00', 'Desparasitación', 'confirmada');
    $c(16, DosClinicas::DOBLE, $hoy, '10:00:00', 'Vacunación', 'completada');
    $c(15, DosClinicas::DOBLE, $hoy, '13:00:00', 'Urgencia', 'completada', 'rojo');
    $c(19, DosClinicas::DOBLE, $hoy, '17:00:00', 'Post-operatorio', 'confirmada');
    $db->exec("INSERT INTO ausencias_veterinario (id_veterinario, id_clinica, fecha_hora_inicio, fecha_hora_fin, motivo, estado) VALUES (5, 1, '$hoy 14:00:00', '$hoy 16:00:00', 'Capacitación', 'aprobada')");
    // Meses anteriores para la minigráfica y la comparación (RE-6.2.12).
    foreach (['2026-06-10', '2026-07-08', '2026-08-12', '2026-09-09', '2026-09-16', '2026-10-05', '2026-10-06'] as $i => $fecha) {
        $c(10 + $i % 5, DosClinicas::VET_NORTE, $fecha, '10:00:00', 'Control', 'completada');
        $db->prepare("INSERT INTO consultas (id_clinica, id_mascota, id_veterinario, fecha_hora, motivo_consulta, anamnesis, diagnostico, plan_tratamiento) VALUES (1, ?, 2, ?, 'Control', '-', '-', '-')")
            ->execute([10 + $i % 5, $fecha . ' 10:30:00']);
    }
    $c(12, DosClinicas::VET_NORTE, '2026-10-07', '11:00:00', 'Control', 'no_asistio');
    $db->exec("UPDATE propietario_clinica SET fecha_vinculo = '2026-10-04 09:00:00' WHERE id_propietario = 6 AND id_clinica = 1");
}

if ($escenario === 'dos' || $escenario === 'cuatro') {
    $c(12, DosClinicas::VET_NORTE, '2026-10-22', '11:00:00', 'Consulta', 'sin_cerrar');
    $c(13, DosClinicas::DOBLE, '2026-10-22', '16:00:00', 'Control', 'confirmada');
    $c(14, DosClinicas::DOBLE, '2026-10-26', '09:00:00', 'Control', 'pendiente');
    $db->exec("INSERT INTO ausencias_veterinario (id_veterinario, id_clinica, fecha_hora_inicio, fecha_hora_fin, motivo, estado) VALUES (5, 1, '2026-10-28 08:00:00', '2026-10-28 18:00:00', 'Vacaciones', 'solicitada')");
    $db->exec("INSERT INTO propuestas_horario (id_veterinario, id_clinica, franjas, estado) VALUES (2, 1, '[]', 'pendiente')");
    $db->exec("INSERT INTO solicitudes_agenda (id_clinica, id_mascota, id_actor, origen, estado, estado_revision, nivel_calculado, contexto_triage, huella_entrada, fecha_llegada)
        VALUES (1, 17, 2, 'llegada', 'espera_presencial', 'no_requerida', 'amarillo', '{}', 'x', '$hoy 13:20:00')");
}

if ($escenario === 'uno') {
    $db->exec("UPDATE usuario_clinica SET estado = 'inactivo' WHERE id_usuario = 5 AND id_clinica = 1");
}
$ahora = new DateTimeImmutable($hoy . ' 13:40:00', new DateTimeZone('America/Bogota'));
$controlador = new PanelController($db, fn () => $ahora);
if ($modo === 'ajax') {
    $_GET = $peticion;
    $controlador->diaAdminAjax();
    return;
}
$_GET = ['action' => 'admin_panel'];
$panel = $controlador->datosAdministrador($ahora);
require __DIR__ . '/../../../views/admin/layout.php';
