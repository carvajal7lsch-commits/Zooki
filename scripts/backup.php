<?php
/**
 * Respaldo automático de la base de datos (HU-23, RN-504).
 *
 * Uso:
 *   php scripts/backup.php
 *
 * Corre una vez al día: en producción con un Schedule de Dokploy sobre el
 * servicio web, en un servidor propio con scripts/zooki.cron.
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Respaldo.php';

// T-06 — Las credenciales salen de .env, la misma fuente que usa
// config/Database.php, que es quien abre la conexión.
$envFile = __DIR__ . '/../.env';
$env = file_exists($envFile) ? parse_ini_file($envFile) : [];

$dbName = $env['DB_NAME'] ?? 'zooki_db';

// T-12 (HU-23) — El destino se configura con BACKUP_DIR para poder apuntar a
// un volumen o unidad de red fuera del contenedor de la aplicación. El
// directorio dentro del proyecto queda solo como último recurso.
$backupDir = $env['BACKUP_DIR'] ?? (__DIR__ . '/../backups');
$retencionDias = (int) ($env['BACKUP_RETENCION_DIAS'] ?? 7);

if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        error_log("[BACKUP ERROR] No se pudo crear el directorio: $backupDir");
        exit(1);
    }
}

$fecha = date('Y-m-d_H-i-s');
$archivoGz = "$backupDir/{$dbName}_{$fecha}.sql.gz";

// 1. Volcar la base de datos
$db = (new Database())->getConnection();
if (!$db) {
    error_log('[BACKUP ERROR] No se pudo conectar a la base de datos');
    exit(1);
}

try {
    $tablas = (new Respaldo($db))->volcar($archivoGz);
} catch (Throwable $e) {
    error_log('[BACKUP ERROR] Falló el volcado: ' . $e->getMessage());
    if (file_exists($archivoGz)) {
        unlink($archivoGz);
    }
    exit(1);
}

// 2. Verificar que el archivo se pueda leer y traiga el esquema
$muestra = '';
$gz = gzopen($archivoGz, 'rb');
if ($gz !== false) {
    $muestra = gzread($gz, 20000);
    gzclose($gz);
}
if ($tablas === 0 || strpos($muestra, 'CREATE TABLE') === false) {
    error_log("[BACKUP ERROR] El respaldo generado está vacío o corrupto ($tablas tablas)");
    unlink($archivoGz);
    exit(1);
}

// 3. Rotación: eliminar respaldos más viejos que la retención
$eliminados = 0;
foreach (glob("$backupDir/{$dbName}_*.sql*") as $archivo) {
    $edadDias = (time() - filemtime($archivo)) / 86400;
    if ($edadDias > $retencionDias) {
        if (unlink($archivo)) {
            $eliminados++;
        }
    }
}

// 4. Registrar resultado
$msg = sprintf(
    "[BACKUP OK] %s | Destino: %s | Archivo: %s | Tablas: %d | Tamaño: %s | Eliminados antiguos: %d",
    date('Y-m-d H:i:s'),
    $backupDir,
    basename($archivoGz),
    $tablas,
    formatoBytes(filesize($archivoGz)),
    $eliminados
);
error_log($msg);
echo $msg . PHP_EOL;

// El log vive siempre en el proyecto, aunque BACKUP_DIR apunte a un volumen
// externo (T-12).
$logFile = __DIR__ . '/../logs/backup.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logFile, $msg . PHP_EOL, FILE_APPEND | LOCK_EX);

exit(0);

function formatoBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= 1024 ** $pow;
    return round($bytes, $precision) . ' ' . $units[$pow];
}
