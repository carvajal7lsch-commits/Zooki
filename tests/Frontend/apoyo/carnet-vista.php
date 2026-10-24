<?php
/** Render real del portal con propietario y SQLite aislada; nunca lee .env. */
define('ZOOKI_PRUEBAS', true);
putenv('ZOOKI_ENTORNO_PRUEBAS=1');
putenv('APP_URL=https://zooki.example');
session_save_path(sys_get_temp_dir());
session_start();
require_once __DIR__ . '/../../Support/CarnetPrueba.php';
require_once __DIR__ . '/../../../controllers/CarnetController.php';
require_once __DIR__ . '/../../../controllers/PortalController.php';
Auditoria::definirProxiesConfiables([]);
$db = CarnetPrueba::crear();
$insertarEscaneo = $db->prepare('INSERT INTO carnet_escaneos (id_mascota, fecha, ip_hash, latitud, longitud, notificado) VALUES (1, ?, ?, ?, ?, 1)');
for ($indiceEscaneo = 0; $indiceEscaneo < 12; $indiceEscaneo++) {
    $insertarEscaneo->execute(['2026-10-23 12:' . str_pad((string) $indiceEscaneo, 2, '0', STR_PAD_LEFT) . ':00', str_repeat('e', 64), $indiceEscaneo === 11 ? 2.123456 : null, $indiceEscaneo === 11 ? -75.987654 : null]);
}
DosClinicas::crearCatalogosSqlite($db);
DosClinicas::poblarAgenda($db);
$_SESSION = ['id_usuario' => 6, 'usuario_nombre' => 'Fabio'];
Contexto::activar(Contexto::dePropietario(), 1);
$_GET = ['id_mascota' => '1'];
$capturar = static function (callable $accion): string {
    ob_start();
    $accion();
    return ob_get_clean();
};
$controlador = new CarnetController($db);
$portal = $capturar(fn () => (new PortalController($db))->index());
$tarjeta = json_decode($capturar(fn () => $controlador->vistaAjax()), true)['html'];
$impresion = $capturar(fn () => $controlador->imprimir());
$svg = $capturar(fn () => $controlador->svg());
echo json_encode(compact('portal', 'tarjeta', 'impresion', 'svg'), JSON_UNESCAPED_UNICODE);
session_destroy();
