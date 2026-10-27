<?php
// Ajustes del administrador (rediseño 2026-10-26) con el layout completo, sobre SQLite.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/ConfiguracionClinicaController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
DosClinicas::crearMascotasSqlite($db);
DosClinicas::crearCatalogosSqlite($db);
DosClinicas::crearHistoriaSqlite($db);
DosClinicas::poblarAgenda($db);
$_SESSION = ['id_usuario' => 1, 'usuario_nombre' => 'Ana Norte'];
$contexto = Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN);
Contexto::activar($contexto, 1);
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'admin_configuracion'];
(new ConfiguracionClinicaController($db))->vista();
