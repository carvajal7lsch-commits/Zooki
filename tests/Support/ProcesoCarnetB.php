<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../models/CarnetPublico.php';
if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('Carnet B exige la base explícita zooki_test_base_v2.');
}
$carpeta = $argv[1];
$nombre = $argv[2];
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=zooki_test_base_v2;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)), getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$publicar = static function (string $ruta, string $contenido): void {
    file_put_contents($ruta . '.tmp', $contenido);
    rename($ruta . '.tmp', $ruta);
};
$publicar($carpeta . '/' . $nombre . '.conexion', (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
$correo = static function (array $aviso) use ($carpeta, $nombre): bool {
    file_put_contents($carpeta . '/correo-' . $nombre, $aviso['asunto']);
    return true;
};
$ahora = new DateTimeImmutable('2026-10-23 12:00:00', new DateTimeZone(ReglaAtencion::ZONA));
try {
    if ($nombre === 'primero') {
        $db->beginTransaction();
    }
    $resultado = (new CarnetPublico($db, $correo))->escanear(str_repeat('a', 43), hash('sha256', $nombre), $ahora);
    if ($nombre === 'primero') {
        $publicar($carpeta . '/bloqueado', '1');
        $limite = microtime(true) + 20;
        while (!is_file($carpeta . '/liberar')) {
            if (microtime(true) > $limite) {
                throw new RuntimeException('No se liberó el escaneo.');
            }
            usleep(10000);
        }
        $db->commit();
    }
    $publicar($carpeta . '/' . $nombre . '.fin', json_encode(['ok' => $resultado !== null]));
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $publicar($carpeta . '/' . $nombre . '.fin', json_encode(['ok' => false, 'error' => $error->getMessage()]));
    exit(1);
}
