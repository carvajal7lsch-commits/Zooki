<?php
// HTTP real, archivos en un temporal de la prueba y SQLite efímero. Nunca usa configuración local.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/ConfiguracionClinicaController.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$carpeta = getenv('ZOOKI_PRUEBA_LOGOS_DIR');
if (!$carpeta || !is_dir($carpeta) || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$db = DosClinicas::sqlite();
DosClinicas::crearMascotasSqlite($db);
DosClinicas::crearCatalogosSqlite($db);
DosClinicas::crearHistoriaSqlite($db);
DosClinicas::poblarAgenda($db);
$rol = ($_GET['rol'] ?? '') === 'vet' ? Roles::VETERINARIO : Roles::ADMIN;
$_SESSION = ['id_usuario' => $rol === Roles::ADMIN ? 1 : 2, 'csrf_default' => str_repeat('c', 64)];
Contexto::activar(Contexto::deClinica(1, 'Norte', $rol), 1);
$db->exec("UPDATE clinicas SET url_logo='uploads/logos/anterior.png' WHERE id_clinica=1");
if (isset($_GET['fallo_auditoria'])) {
    $db->exec("CREATE TRIGGER falla_marca BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'Fallo de prueba'); END");
}
$controlador = new ConfiguracionClinicaController($db, null, new ConfiguracionWompi([]), null, new LogoClinica($carpeta));
try {
    $controlador->guardarMarcaAjax();
} catch (AccesoDenegado $error) {
    RespuestaJson::error($error->codigo(), $error->getMessage());
}
