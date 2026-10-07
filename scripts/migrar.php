<?php
/**
 * Aplica las migraciones de database/ que falten y la semilla
 * (helpers/Migrador.php).
 *
 * En Docker lo corre docker/iniciar.sh cada vez que arranca el contenedor web,
 * es decir, en cada despliegue de Dokploy. A mano:
 *
 *   php scripts/migrar.php            aplica las que falten y la semilla
 *   php scripts/migrar.php --revisar  solo dice qué aplicaría
 *
 * Sale con código 1 si no hay conexión, si la base no tiene el esquema v2 o
 * si una migración falla.
 */

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/helpers/Migrador.php';

$registrar = static function (string $mensaje): void {
    echo '[migraciones] ' . $mensaje . PHP_EOL;
};
$soloRevisar = in_array('--revisar', $argv ?? [], true);

// En Docker la base arranca al mismo tiempo que la web: se espera hasta un minuto.
$db = null;
for ($intento = 1; $intento <= 30 && !$db; $intento++) {
    ob_start(); // Database imprime el error de conexión; aquí se reporta aparte.
    $db = (new Database())->getConnection();
    $error = trim((string) ob_get_clean());
    if (!$db) {
        $registrar("La base de datos aún no responde (intento $intento de 30).");
        sleep(2);
    }
}
if (!$db) {
    $registrar('No se pudo conectar a la base de datos: ' . $error);
    exit(1);
}

try {
    $hecho = (new Migrador($db, dirname(__DIR__) . '/database', $registrar))->migrar($soloRevisar);
} catch (Throwable $e) {
    $registrar('ERROR: ' . $e->getMessage());
    exit(1);
}

$registrar($hecho['ejecutadas']
    ? ($soloRevisar ? 'Se aplicarían: ' : 'Aplicadas: ') . implode(', ', $hecho['ejecutadas'])
    : 'La base está al día.');
if ($hecho['semilla']) {
    $registrar(($soloRevisar ? 'Se aplicaría ' : 'Aplicada ') . Migrador::SEMILLA . ' (solo agrega las filas que falten).');
}
