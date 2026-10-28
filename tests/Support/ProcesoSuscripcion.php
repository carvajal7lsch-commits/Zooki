<?php
require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../helpers/MantenimientoSuscripciones.php';

[$script, $carpeta, $nombre] = $argv;
$config = Database::configuracionPruebas();
$db = (new Database($config))->getConnection();
file_put_contents($carpeta . '/' . $nombre . '.conexion', (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
$detenido = false;
// El primero se detiene en la primera lectura del reloj DENTRO de la transacción
// (con el candado de la clínica tomado). Desde la v2 de suscripción, cobros y
// checkout leen el reloj antes, fuera del candado; detenerse ahí dejaba pasar al segundo.
$reloj = function () use ($nombre, $carpeta, &$detenido, &$db): DateTimeImmutable {
    if ($nombre === 'primero' && !$detenido && $db->inTransaction()) {
        $detenido = true;
        file_put_contents($carpeta . '/bloqueado', '1');
        $limite = microtime(true) + 20;
        while (!is_file($carpeta . '/liberar')) {
            if (microtime(true) > $limite) {
                throw new RuntimeException('Barrera de suscripción agotada.');
            }
            usleep(10000);
        }
    }
    return VigenciaSuscripcion::fecha('2026-10-28 00:00:00');
};
// Publica el resultado de golpe: el padre lo lee en cuanto existe el archivo.
$publicar = function (string $ruta, string $contenido): void {
    file_put_contents($ruta . '.tmp', $contenido);
    rename($ruta . '.tmp', $ruta);
};
try {
    $resultado = (new MantenimientoSuscripciones($db, $reloj))->ejecutar(false, null, function (array $aviso) use ($db, $carpeta, $nombre): bool {
        if ($db->inTransaction()) {
            throw new RuntimeException('SMTP dentro de transacción.');
        }
        file_put_contents($carpeta . '/correo-' . $nombre . '-' . $aviso['id_usuario'], 'simulado');
        return true;
    });
    $publicar($carpeta . '/' . $nombre . '.fin', json_encode($resultado, JSON_THROW_ON_ERROR));
    exit($resultado['errores'] > 0 ? 1 : 0);
} catch (Throwable $error) {
    $publicar($carpeta . '/' . $nombre . '.fin', json_encode(['error' => $error->getMessage()]));
    exit(1);
}
