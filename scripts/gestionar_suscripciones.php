<?php
/** S1–S3: cada cinco minutos; cobros solo con mandato y activación explícita. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/helpers/ZonaHoraria.php';
ZonaHoraria::aplicar();
require_once dirname(__DIR__) . '/helpers/MantenimientoSuscripciones.php';
require_once dirname(__DIR__) . '/config/Database.php';
try {
    $revisar = false;
    $solo = null;
    foreach (array_slice($argv, 1) as $argumento) {
        if ($argumento === '--revisar') {
            $revisar = true;
        } elseif (in_array($argumento, ['--solo=vencimientos', '--solo=avisos', '--solo=conciliacion', '--solo=cobros'], true)) {
            $solo = substr($argumento, 7);
        } else {
            throw new InvalidArgumentException('Uso: --revisar y --solo=vencimientos|avisos|conciliacion|cobros.');
        }
    }
    $configuracion = Database::enPruebas() ? Database::configuracionPruebas() : null;
    $db = (new Database($configuracion))->getConnection();
    require_once dirname(__DIR__) . '/helpers/CorreoSuscripcion.php';
    $resultado = (new MantenimientoSuscripciones($db))->ejecutar($revisar, $solo, $revisar ? null : [CorreoSuscripcion::class, 'enviar']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($resultado['errores'] > 0 || $resultado['inciertos'] > 0 ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo gestionar suscripciones: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
