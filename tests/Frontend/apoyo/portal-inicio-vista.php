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
$db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado) VALUES (1, 1, 2, 1, '2099-03-01', '09:00:00', 'Control', 'confirmada')");
$_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO, 'usuario_nombre' => $argv[1] ?? 'Fabio Propietario'];
Contexto::activar(Contexto::dePropietario(), 1);
$_GET = [];
$_POST = [];
(new PortalController($db))->index();
