<?php
/** M2 B1: proceso de despliegue, sin ruta web ni acceso a pacientes (RN-212). */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CargadorConocimiento.php';

try {
    $carpeta = __DIR__ . '/../database/conocimiento';
    $soloRevisar = false;
    foreach (array_slice($argv, 1) as $argumento) {
        if ($argumento === '--revisar') {
            $soloRevisar = true;
        } elseif (str_starts_with($argumento, '--carpeta=')) {
            $carpeta = substr($argumento, strlen('--carpeta='));
        } else {
            throw new InvalidArgumentException('Argumento desconocido: ' . $argumento);
        }
    }
    // Fallar por archivos antes de abrir la conexión; las pruebas nunca leen .env.
    ArchivosConocimiento::leer($carpeta);
    $configuracion = Database::enPruebas() ? Database::configuracionPruebas() : null;
    $db = (new Database($configuracion))->getConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('No se pudo conectar a la base.');
    }
    $resumen = (new CargadorConocimiento($db))->cargar($carpeta, $soloRevisar);
    echo '[conocimiento] ' . ($soloRevisar ? 'Revisión sin escritura: ' : 'Carga terminada: ') . json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '[conocimiento] ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
