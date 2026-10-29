<?php
// HU-5.1 / RNF-16: controlador y vistas reales, datos efímeros; sin .env ni base de trabajo.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/PortalController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
DosClinicas::crearMascotasSqlite($db);
DosClinicas::crearCatalogosSqlite($db);
DosClinicas::crearHistoriaSqlite($db);
DosClinicas::poblarAgenda($db);
DosClinicas::vincularLunaASur($db);
// RNF-16: datos suficientes para reproducir el desborde; solo SQLite efímera.
$db->exec("UPDATE usuarios SET telefono = '3001112233', tipo_documento = 'CC' WHERE id_usuario = 6");
$db->exec("UPDATE mascotas SET id_especie = 1, id_raza = 49, fecha_nacimiento = '2024-12-28', peso = 12.5, sexo = 'Macho' WHERE id_mascota = 1");
$db->exec("UPDATE mascota_clinica SET numero_historia_clinica = 'HC-000001' WHERE id_mascota = 1 AND id_clinica = 1");
$db->exec("UPDATE mascota_clinica SET numero_historia_clinica = 'SUR-000042' WHERE id_mascota = 1 AND id_clinica = 2");
$insertarMascota = $db->prepare("INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,id_especie,id_raza,fecha_nacimiento,peso,sexo,estado) VALUES (?,6,1,?,?,1,49,'2024-12-28',12.5,'Macho',1)");
foreach (['Doki', 'Kira', 'Coco', 'Simba', 'Pelusa'] as $indice => $nombre) {
    $id = $indice + 2;
    $insertarMascota->execute([$id, str_repeat((string) $id, 43), $nombre]);
    $db->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,1,'activo')")->execute([$id]);
}
$db->exec("UPDATE clinicas SET direccion = 'Calle 123 # 45-67, barrio Centro', telefono = '3001112233', email_contacto = 'contacto@zooki.test' WHERE id_clinica IN (1,2)");
$db->exec("INSERT INTO clinicas (id_clinica,nombre,nit,estado,direccion) VALUES (4,'Clínica del Parque','900111223-1','activa','Carrera 14 # 22-36, barrio El Parque'),(5,'Clínica Los Pinos','900111224-1','activa','Calle 15 # 18-20, barrio Los Pinos')");
$db->exec("INSERT OR IGNORE INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (6,2,'activo')");
$db->exec('DELETE FROM citas');
$hoy = new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
$insertarCita = $db->prepare("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,motivo,estado) VALUES (1,1,2,1,?,'09:30:00','Control',?)");
foreach ([1, 2, -1, -2, -3] as $dias) {
    $insertarCita->execute([$hoy->modify(($dias > 0 ? '+' : '') . $dias . ' days')->format('Y-m-d'), $dias > 0 ? 'confirmada' : 'atendida']);
}
$db->prepare("INSERT INTO vacunas (id_clinica,id_mascota,id_veterinario,nombre_vacuna,fecha_aplicacion,fecha_proxima_dosis) VALUES (1,1,2,'Rabia',?,?)")->execute([$hoy->modify('-27 days')->format('Y-m-d'), $hoy->modify('+338 days')->format('Y-m-d')]);
if (($argv[1] ?? '') === 'reajuste') {
    $db->exec("UPDATE citas SET estado_reajuste = 'pendiente_reprogramacion' WHERE id_cita = 1");
}
$_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO, 'usuario_nombre' => 'Fabio Propietario', 'contextos_total' => ($argv[1] ?? '') === 'uno' ? 1 : 2];
Contexto::activar(Contexto::dePropietario(), ($argv[1] ?? '') === 'uno' ? 1 : 2);
$_GET = [];
$_POST = [];
if (str_starts_with($argv[1] ?? '', 'imprimir-')) {
    // Datos clínicos de ejemplo, solo para revisar el documento real en SQLite efímera.
    $db->exec("INSERT INTO consultas (id_clinica,id_mascota,id_veterinario,fecha_hora,motivo_consulta,anamnesis,diagnostico,plan_tratamiento) VALUES (1,1,2,'2026-09-01 10:00:00','Dolor de oído','Revisión','Otitis','Control según indicación veterinaria'),(2,1,4,'2026-10-01 14:00:00','Seguimiento','Revisión de control','Evolución favorable','Seguimiento clínico')");
    $db->exec("INSERT INTO desparasitaciones (id_clinica,id_mascota,id_veterinario,tipo,producto,periodicidad,fecha_aplicacion,fecha_proxima) VALUES (1,1,2,'interna','Antiparasitario','trimestral','2026-09-01','2026-12-01')");
    $db->exec("INSERT INTO alertas_medicas (id_mascota,id_clinica,id_veterinario,tipo,descripcion) VALUES (1,1,2,'alergia','Reacción alérgica registrada por el veterinario')");
    $_GET = ['id_mascota' => 1, 'origen' => substr($argv[1], 9)];
    (new PortalController($db))->imprimirHistorial();
} elseif (($argv[1] ?? '') === 'detalle') {
    $_GET = ['id_mascota' => 1];
    (new PortalController($db))->verDetalleMascotaAjax();
} else {
    (new PortalController($db))->index();
}
