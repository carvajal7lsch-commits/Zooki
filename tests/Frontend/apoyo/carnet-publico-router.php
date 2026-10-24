<?php
/** Servidor exclusivo de pruebas HTTP: SQLite en un temporal; sin .env ni SMTP. */
define('ZOOKI_PRUEBAS', true);
putenv('ZOOKI_ENTORNO_PRUEBAS=1');
putenv('APP_URL=http://' . $_SERVER['HTTP_HOST']);
require_once __DIR__ . '/../../Support/CarnetPrueba.php';
require_once __DIR__ . '/../../../controllers/CarnetPublicoController.php';
require_once __DIR__ . '/../../../controllers/CarnetController.php';
require_once __DIR__ . '/../../../helpers/Security.php';
Auditoria::definirProxiesConfiables([]);
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($ruta !== '/index.php') return false;
$archivo = getenv('ZOOKI_CARNET_B_SQLITE');
if (!$archivo || !str_starts_with(realpath(dirname($archivo)), realpath(sys_get_temp_dir()))) {
    throw new RuntimeException('El servidor del carnet exige un archivo temporal propio.');
}
if (!is_file($archivo)) {
    $memoria = CarnetPrueba::crear();
    $memoria->exec("INSERT INTO mascotas (id_mascota, id_propietario, token_carnet, nombre, estado, carnet_activo) VALUES
        (3, 6, '" . str_repeat('c', 43) . "', 'Inactiva', 0, 1),
        (4, NULL, '" . str_repeat('p', 43) . "', 'Provisional', 1, 0)");
    $memoria->prepare('VACUUM INTO ?')->execute([$archivo]);
}
$db = new PDO('sqlite:' . $archivo, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$accion = $_GET['action'] ?? '';
if ($accion === '__reiniciar_accesos') {
    $db->exec('DELETE FROM carnet_accesos');
    echo 'listo';
    return true;
}
$_SESSION = [];
Security::autorizar($accion);
$controlador = new CarnetPublicoController($db, static fn (): bool => true);
if ($accion === 'carnet') $controlador->mostrar();
elseif ($accion === 'carnet_ubicacion') $controlador->ubicacion();
elseif ($accion === 'carnet_foto') $controlador->foto();
else http_response_code(404);
return true;
