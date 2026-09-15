<?php
/**
 * Script de backup automático de base de datos (HU-23)
 * 
 * Uso:
 *   php scripts/backup.php
 * 
 * Cron job recomendado (diario a las 3:00 AM):
 *   0 3 * * * cd /ruta/al/proyecto && php scripts/backup.php >> logs/backup.log 2>&1
 */

// T-06 — Las credenciales salen de .env, la misma fuente que usa
// config/Database.php. Antes estaban escritas aqui (root, sin contrasena):
// quedaban versionadas en git y el respaldo fallaba en cualquier entorno donde
// la clave real no fuera vacia, que es justo el de produccion.
$envFile = __DIR__ . '/../.env';
$env = file_exists($envFile) ? parse_ini_file($envFile) : [];

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? 'zooki_db';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';

// Igual que Database.php: el host 'db' es el de Docker y no resuelve fuera.
if ($dbHost === 'db' && (PHP_OS_FAMILY === 'Windows' || gethostbyname('db') === 'db')) {
    $dbHost = '127.0.0.1';
}

// T-12 (HU-23) — El destino se configura con BACKUP_DIR para poder apuntar a
// un volumen o unidad de red fuera del servidor de aplicacion, como pide el
// criterio. El directorio dentro del proyecto queda solo como ultimo recurso.
$backupDir = $env['BACKUP_DIR'] ?? (__DIR__ . '/../backups');
$retencionDias = (int) ($env['BACKUP_RETENCION_DIAS'] ?? 7);

// Crear directorio de backups si no existe
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        error_log("[BACKUP ERROR] No se pudo crear el directorio: $backupDir");
        exit(1);
    }
}

$fecha = date('Y-m-d_H-i-s');
$archivoSql = "$backupDir/{$dbName}_{$fecha}.sql";
$archivoGz = "$archivoSql.gz";

// 1. Ejecutar mysqldump
//
// T-06 — La contrasena viaja por un fichero temporal con permisos 0600, no por
// --password=: los argumentos de un proceso son visibles para cualquier
// usuario del servidor con un simple `ps`.
$cnf = tempnam(sys_get_temp_dir(), 'zooki_bk_');
if ($cnf === false) {
    error_log('[BACKUP ERROR] No se pudo crear el archivo temporal de credenciales');
    exit(1);
}
chmod($cnf, 0600);
file_put_contents($cnf, sprintf(
    "[client]\nhost=%s\nuser=%s\npassword=%s\n",
    $dbHost,
    $dbUser,
    $dbPass
));

$command = sprintf(
    'mysqldump --defaults-extra-file=%s --single-transaction --routines --triggers %s > %s 2>&1',
    escapeshellarg($cnf),
    escapeshellarg($dbName),
    escapeshellarg($archivoSql)
);

exec($command, $output, $returnCode);

// El fichero de credenciales se borra pase lo que pase, incluso si el dump fallo.
unlink($cnf);

if ($returnCode !== 0) {
    $errorMsg = implode("\n", $output);
    error_log("[BACKUP ERROR] mysqldump falló: $errorMsg");
    if (file_exists($archivoSql)) {
        unlink($archivoSql);
    }
    exit(1);
}

// 2. Verificar que el dump no esté vacío o corrupto
$tamano = filesize($archivoSql);
if ($tamano === false || $tamano < 1024) {
    error_log("[BACKUP ERROR] El archivo SQL generado está vacío o es muy pequeño ($tamano bytes)");
    unlink($archivoSql);
    exit(1);
}

// Validar que contenga al menos una sentencia CREATE TABLE
$contenidoMuestra = file_get_contents($archivoSql, false, null, 0, 5000);
if (strpos($contenidoMuestra, 'CREATE TABLE') === false) {
    error_log("[BACKUP ERROR] El archivo SQL no contiene sentencias CREATE TABLE. Posiblemente corrupto.");
    unlink($archivoSql);
    exit(1);
}

// 3. Comprimir con gzip
$commandGz = sprintf('gzip -f %s 2>&1', escapeshellarg($archivoSql));
exec($commandGz, $outputGz, $returnCodeGz);

if ($returnCodeGz !== 0 || !file_exists($archivoGz)) {
    error_log("[BACKUP ERROR] Falló la compresión gzip. Se conserva el archivo SQL sin comprimir.");
    $archivoFinal = $archivoSql; // Conservar sin comprimir si gzip falla
    $ratio = 0.0;
} else {
    $archivoFinal = $archivoGz;
    // T-20: el ratio se calculaba y no se usaba; ahora va al log del respaldo.
    $ratio = round((1 - (filesize($archivoGz) / $tamano)) * 100, 1);
}

// 4. Rotación: eliminar backups con más de 7 días
$eliminados = 0;
foreach (glob("$backupDir/{$dbName}_*.sql*") as $archivo) {
    $edadDias = (time() - filemtime($archivo)) / 86400;
    if ($edadDias > $retencionDias) {
        if (unlink($archivo)) {
            $eliminados++;
        }
    }
}

// 5. Registrar resultado
$tamanoFinal = filesize($archivoFinal);
$msg = sprintf(
    "[BACKUP OK] %s | Destino: %s | Archivo: %s | Tamaño: %s (-%s%%) | SQL original: %s | Eliminados antiguos: %d",
    date('Y-m-d H:i:s'),
    $backupDir,
    basename($archivoFinal),
    formatoBytes($tamanoFinal),
    $ratio,
    formatoBytes($tamano),
    $eliminados
);
error_log($msg);
echo $msg . PHP_EOL;

// 6. Guardar también en un log específico de backups. El log vive siempre en
// el proyecto, aunque BACKUP_DIR apunte a un volumen externo (T-12).
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
