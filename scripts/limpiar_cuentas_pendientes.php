<?php
/** D2: proceso del sistema, exclusivamente altas de personal pendientes vencidas. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
// Revisión de D2.1: la zona horaria del sistema (helpers/ZonaHoraria.php).
require_once __DIR__ . '/../helpers/ZonaHoraria.php';
ZonaHoraria::aplicar();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CuentaTitular.php';
try {
    $db = (new Database())->getConnection();
    $total = (new CuentaTitular($db))->limpiarPendientes();
    echo "Altas pendientes vencidas eliminadas: $total\n";
} catch (Throwable $e) {
    error_log('D2 limpieza de cuentas: ' . $e->getMessage());
    exit(1);
}
