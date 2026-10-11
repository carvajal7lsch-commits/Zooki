<?php
/** D3: proceso del sistema para altas de personal y clínicas pendientes vencidas. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
// Revisión de D2.1: la zona horaria del sistema (helpers/ZonaHoraria.php).
require_once __DIR__ . '/../helpers/ZonaHoraria.php';
ZonaHoraria::aplicar();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CuentaTitular.php';
require_once __DIR__ . '/../models/RegistroClinica.php';
try {
    $db = (new Database())->getConnection();
    $total = (new CuentaTitular($db))->limpiarPendientes();
    $clinicas = (new RegistroClinica($db))->limpiarPendientes();
    echo "Clínicas pendientes vencidas eliminadas: $clinicas\n";
    echo "Altas pendientes vencidas eliminadas: $total\n";
} catch (Throwable $e) {
    error_log('D2 limpieza de cuentas: ' . $e->getMessage());
    exit(1);
}
