<?php
/**
 * Crea la cuenta del super-administrador (plan M0, §4.5). Solo por consola:
 *
 *   php scripts/crear_superadmin.php
 *   docker exec -it zooki_web php scripts/crear_superadmin.php   (producción)
 *
 * Pide correo, nombre y contraseña (sin mostrarla) y valida la contraseña con
 * la política de RN-G10. Se niega si ya existe una cuenta con ese correo: el
 * super-administrador es una cuenta nueva y aparte, sin rol en ninguna clínica.
 * Ninguna credencial queda en el código ni en el SQL.
 *
 * Sale con código 1 si algo falla.
 */

// La carpeta scripts/ no debe ser alcanzable por web; por si lo fuera, este
// archivo no hace nada fuera de la consola.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/helpers/CreadorSuperAdmin.php';

// Se averigua antes de leer: stream_isatty() sobre STDIN después de un fgets
// descarta lo que ya estaba en el búfer.
define('ENTRADA_ES_TERMINAL', stream_isatty(STDIN));

function preguntar(string $texto): string
{
    echo $texto;
    $linea = fgets(STDIN);
    return $linea === false ? '' : rtrim($linea, "\r\n");
}

/** Lee una línea sin mostrarla en la terminal. */
function preguntarOculto(string $texto): string
{
    echo $texto;

    // Entrada redirigida (sin terminal): no hay eco que ocultar.
    if (!ENTRADA_ES_TERMINAL) {
        $linea = fgets(STDIN);
        return $linea === false ? '' : rtrim($linea, "\r\n");
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $comando = 'powershell -NoProfile -Command "$s = Read-Host -AsSecureString; '
            . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); '
            . '[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)"';
        $valor = shell_exec($comando);
        return $valor === null || $valor === false ? '' : rtrim($valor, "\r\n");
    }

    system('stty -echo');
    try {
        $linea = fgets(STDIN);
    } finally {
        system('stty echo');
        echo PHP_EOL;
    }
    return $linea === false ? '' : rtrim($linea, "\r\n");
}

function terminar(string $mensaje, int $codigo): void
{
    fwrite($codigo === 0 ? STDOUT : STDERR, $mensaje . PHP_EOL);
    exit($codigo);
}

echo 'Crear el super-administrador de Zooki' . PHP_EOL;

$email = trim(preguntar('Correo: '));
$nombre = trim(preguntar('Nombre completo: '));
$password = preguntarOculto('Contraseña: ');
$confirmacion = preguntarOculto('Repite la contraseña: ');

if (!hash_equals($password, $confirmacion)) {
    terminar('Las contraseñas no coinciden. No se creó la cuenta.', 1);
}
$motivo = CreadorSuperAdmin::validar($email, $nombre, $password);
if ($motivo !== null) {
    terminar($motivo . ' No se creó la cuenta.', 1);
}

ob_start(); // Database imprime el error de conexión; aquí se reporta aparte.
$db = (new Database())->getConnection();
$error = trim((string) ob_get_clean());
if (!$db) {
    terminar('No se pudo conectar a la base de datos: ' . $error, 1);
}

try {
    $id = (new CreadorSuperAdmin($db))->crear($email, $nombre, $password);
} catch (Throwable $e) {
    terminar($e->getMessage() . ' No se creó la cuenta.', 1);
}

terminar("Super-administrador creado (id_usuario {$id}).", 0);
