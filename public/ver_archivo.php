<?php
/**
 * Entrega controlada de adjuntos clínicos (HU-2.3, RE-2.3.2, RE-2.3.3).
 *
 * Es la ÚNICA vía por la que sale un archivo de uploads/clinicos/: la carpeta
 * está bloqueada por .htaccess, pero eso solo cubre Apache con AllowOverride
 * activo, así que la autorización real se hace aquí.
 *
 * - Security::autorizar() comprueba sesión, contexto activo y rol, igual que
 *   el front controller (la acción «ver_archivo» está en la matriz).
 * - ArchivoClinico::paraDescargar() aplica la regla de la historia
 *   (RN-113): en una clínica, el adjunto de una consulta visible; en el
 *   portal, solo los de las mascotas propias (RN-G02). Si no corresponde,
 *   403 auditado.
 * - El tipo servido sale de una lista cerrada (M2-06): un archivo que
 *   hubiera burlado la validación de subida nunca se sirve como HTML.
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../models/ArchivoClinico.php';

// M2-07: mismos parámetros de cookie que el front controller.
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $esHttps,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

function responderSinArchivo(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensaje);
}

try {
    Security::autorizar('ver_archivo');
} catch (AccesoDenegado $e) {
    responderSinArchivo($e->codigo(), $e->getMessage());
}

$idArchivo = $_GET['id'] ?? null;
if (!ctype_digit((string) $idArchivo) || (int) $idArchivo <= 0) {
    responderSinArchivo(400, 'Archivo no especificado.');
}

$conexion = (new Database())->getConnection();
if (!$conexion) {
    responderSinArchivo(500, 'No se pudo acceder al archivo.');
}

try {
    $archivo = (new ArchivoClinico($conexion))->paraDescargar((int) $idArchivo);
} catch (AccesoDenegado $e) {
    responderSinArchivo($e->codigo(), 'No tienes permiso para ver este archivo.');
}

// basename() por si el nombre guardado trajera separadores de ruta; el
// archivo nunca se busca fuera de la carpeta de adjuntos.
$ruta = __DIR__ . '/uploads/clinicos/' . basename($archivo['nombre_servidor']);
if (!is_file($ruta)) {
    responderSinArchivo(404, 'El archivo no existe.');
}

$tiposPermitidos = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'pdf'  => 'application/pdf',
];

$extension = strtolower($archivo['extension']);
if (!isset($tiposPermitidos[$extension])) {
    responderSinArchivo(415, 'Tipo de archivo no admitido.');
}

$nombreDescarga = preg_replace('/[^A-Za-z0-9._-]/', '_', $archivo['nombre_original']);

header('Content-Type: ' . $tiposPermitidos[$extension]);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $nombreDescarga . '"');
header('Cache-Control: private, no-store');

readfile($ruta);
exit;
