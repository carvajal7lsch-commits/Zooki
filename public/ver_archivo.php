<?php
/**
 * Entrega controlada de adjuntos clínicos (HU-06 / RN-204).
 *
 * Es la ÚNICA vía por la que debe salir un archivo de uploads/clinicos/: la
 * carpeta está bloqueada por .htaccess, pero eso solo cubre Apache con
 * AllowOverride activo, así que la autorización real se hace aquí.
 *
 * Qué estaba mal antes:
 *
 * - M2-03: el script leía `$_GET['file']` y el enlace del área del veterinario
 *   mandaba `?id=`. El parámetro nunca llegaba, así que **ningún adjunto se
 *   podía abrir**: siempre respondía "Archivo no especificado".
 * - M2-04: aceptaba un nombre de archivo suelto y lo servía a cualquier sesión
 *   válida. Un propietario podía leer adjuntos de pacientes ajenos, y los
 *   nombres eran adivinables (CLI_{id_consulta}_{timestamp}_{i}.{ext}).
 * - M2-06: el tipo salía de mime_content_type() sobre un archivo cuyo formato
 *   solo se había validado por la extensión del nombre que envió el cliente.
 *   Un HTML subido como .jpg se servía `inline` y ejecutaba en el navegador.
 * - M2-07: session_start() desnudo, sin los flags de cookie endurecidos.
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Consulta.php';
require_once __DIR__ . '/../helpers/Security.php';

// M2-07: mismos parámetros de cookie que el front controller. Este archivo es
// un punto de entrada aparte y sin esto abría la sesión sin HttpOnly/SameSite.
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

function denegar(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensaje);
}

if (empty($_SESSION['usuario_doc'])) {
    denegar(403, 'Acceso denegado. Debe iniciar sesión.');
}

$idArchivo = $_GET['id'] ?? null;
if (!ctype_digit((string) $idArchivo) || (int) $idArchivo <= 0) {
    denegar(400, 'Archivo no especificado.');
}

$database = new Database();
$conexion = $database->getConnection();
if (!$conexion) {
    denegar(500, 'No se pudo acceder al archivo.');
}

$archivo = (new Consulta($conexion))->getArchivoConDueno((int) $idArchivo);
if ($archivo === null) {
    denegar(404, 'El archivo no existe.');
}

// M2-04 — Quién puede ver un adjunto clínico:
//   · Veterinario y administrador: cualquiera (RN-208, la atención no está
//     restringida al veterinario asignado, y el administrador audita).
//   · Propietario: solo los de sus propias mascotas (RN-G02).
//   · Recepción: no. La matriz de autorización tampoco le da el historial.
$rol = (int) ($_SESSION['usuario_id_rol'] ?? 0);
$documento = (string) $_SESSION['usuario_doc'];

$autorizado = match ($rol) {
    Security::ROL_ADMIN, Security::ROL_VETERINARIO => true,
    Security::ROL_PROPIETARIO => $archivo['doc_propietario'] === $documento,
    default => false,
};

if (!$autorizado) {
    error_log(sprintf(
        'Adjunto clinico denegado: archivo %d, rol %d, documento %s',
        $archivo['id_archivo'],
        $rol,
        $documento
    ));
    denegar(403, 'No tienes permiso para ver este archivo.');
}

// basename() por si el nombre guardado en base de datos trajera separadores de
// ruta; el archivo nunca se busca fuera de la carpeta de adjuntos.
$ruta = __DIR__ . '/uploads/clinicos/' . basename($archivo['nombre_servidor']);
if (!is_file($ruta)) {
    denegar(404, 'El archivo no existe.');
}

// M2-06 — El tipo servido sale de una lista cerrada indexada por la extensión
// que el servidor guardó, no de inspeccionar el contenido. Así un archivo que
// hubiera burlado la validación de subida nunca puede servirse como HTML.
$tiposPermitidos = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'pdf'  => 'application/pdf',
];

$extension = strtolower($archivo['extension']);
if (!isset($tiposPermitidos[$extension])) {
    denegar(415, 'Tipo de archivo no admitido.');
}

$nombreDescarga = preg_replace('/[^A-Za-z0-9._-]/', '_', $archivo['nombre_original']);

header('Content-Type: ' . $tiposPermitidos[$extension]);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $nombreDescarga . '"');
header('Cache-Control: private, no-store');

readfile($ruta);
exit;
