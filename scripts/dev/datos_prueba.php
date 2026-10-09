<?php
/**
 * Datos de prueba locales para revisar a mano las subetapas de C (plan M0).
 *
 *   php scripts/dev/datos_prueba.php                  muestra la base de destino y qué haría
 *   php scripts/dev/datos_prueba.php --si             los crea o los actualiza
 *   php scripts/dev/datos_prueba.php --si --clave=X   misma clave para todos (D2.1)
 *
 * Crea dos clínicas activas (Norte y Sur), un administrador y un veterinario
 * en cada una, una persona con rol en las dos clínicas que además es
 * propietaria, un propietario de Norte y un super-administrador. Las
 * contraseñas se generan en cada corrida y se imprimen en pantalla: nunca
 * quedan en el código. Con --clave=X todos usan esa clave, si cumple la
 * política; si no, se niega con el motivo. Todos salvo el super-administrador
 * quedan con la política vigente aceptada (D2.1).
 *
 * Es repetible: si una cuenta ya existe (por su correo) le pone la contraseña
 * nueva y la reactiva, sin duplicar nada.
 *
 * Se niega a correr fuera de la consola, dentro de Docker, contra una base
 * que no sea local, sobre una base v1 o si la base tiene clínicas que no son
 * las de prueba.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/config/Database.php';
require_once $raiz . '/models/Usuario.php';
require_once $raiz . '/helpers/PoliticaPassword.php';
require_once $raiz . '/helpers/Roles.php';
require_once $raiz . '/helpers/InicializadorClinica.php';
require_once $raiz . '/helpers/EntornoLocal.php';
require_once __DIR__ . '/DatosPrueba.php';

function salir(string $mensaje, int $codigo = 1): void
{
    fwrite($codigo === 0 ? STDOUT : STDERR, $mensaje . PHP_EOL);
    exit($codigo);
}

const CLINICAS = [
    'norte' => ['nombre' => 'Clínica Norte (prueba)', 'nit' => '900123456-8'],
    'sur'   => ['nombre' => 'Clínica Sur (prueba)', 'nit' => '900654321-0'],
];

// [nombre, documento, correo, roles de clínica [clínica => rol], propietario en [clínicas], super-administrador]
const PERSONAS = [
    ['Ana Norte', '1000000001', 'ana.norte@zooki.test', ['norte' => Roles::ADMIN], [], false],
    ['Beto Norte', '1000000002', 'beto.norte@zooki.test', ['norte' => Roles::VETERINARIO], [], false],
    ['Carla Sur', '1000000003', 'carla.sur@zooki.test', ['sur' => Roles::ADMIN], [], false],
    ['Diego Sur', '1000000004', 'diego.sur@zooki.test', ['sur' => Roles::VETERINARIO], [], false],
    ['Elena Doble', '1000000005', 'elena.doble@zooki.test', ['norte' => Roles::VETERINARIO, 'sur' => Roles::ADMIN], ['norte'], false],
    ['Fabio Propietario', '1000000006', 'fabio.propietario@zooki.test', [], ['norte'], false],
    ['Gina Plataforma', null, 'gina.plataforma@zooki.test', [], [], true],
];

// ── Guardas ───────────────────────────────────────────────────────────────
// D2.1: la clave común se revisa antes de tocar nada.
$claveComun = DatosPrueba::claveDeArgumentos($argv ?? []);
if ($claveComun !== null) {
    $motivo = DatosPrueba::motivoClaveInvalida($claveComun, PERSONAS);
    if ($motivo !== null) {
        salir("La clave de --clave {$motivo}. Elige otra.");
    }
}

ob_start(); // Database imprime el error de conexión; se reporta aparte.
$db = (new Database())->getConnection();
$error = trim((string) ob_get_clean());
if (!$db) {
    salir('No se pudo conectar a la base de datos: ' . $error);
}

$conexion = (string) $db->getAttribute(PDO::ATTR_CONNECTION_STATUS);
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();
// La misma comprobación que usa MAIL_MODO=archivo (helpers/EntornoLocal.php).
$motivoNoLocal = EntornoLocal::motivoNoLocal($conexion);
if ($motivoNoLocal !== null) {
    salir("No se crean datos de prueba: {$motivoNoLocal}. Este script solo escribe en una base de tu equipo.");
}

$tabla = static function (string $nombre) use ($db): bool {
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$nombre]);
    return (int) $st->fetchColumn() > 0;
};
if (!$tabla('clinicas') || !$tabla('usuario_clinica')) {
    salir("La base «{$base}» no tiene el esquema v2. Carga database/01_schema.sql y database/02_semilla.sql.");
}

$nits = array_column(CLINICAS, 'nit');
$ajenas = $db->query('SELECT nit FROM clinicas')->fetchAll(PDO::FETCH_COLUMN);
$ajenas = array_diff($ajenas, $nits);
if ($ajenas) {
    salir("La base «{$base}» ya tiene clínicas que no son de prueba (" . implode(', ', $ajenas) . '). No se mezclan datos.');
}

echo "Base de destino: {$base} ({$conexion})" . PHP_EOL;
if (!in_array('--si', $argv ?? [], true)) {
    salir('Se crearían 2 clínicas y ' . count(PERSONAS) . ' personas de prueba. Vuelve a correrlo con --si para hacerlo.', 0);
}

// ── Datos ─────────────────────────────────────────────────────────────────
$usuario = new Usuario($db);
$credenciales = [];

$db->beginTransaction();
try {
    $idClinica = [];
    foreach (CLINICAS as $clave => $c) {
        $st = $db->prepare('SELECT id_clinica FROM clinicas WHERE nit = ?');
        $st->execute([$c['nit']]);
        $id = $st->fetchColumn();
        if ($id === false) {
            $db->prepare("INSERT INTO clinicas (nombre, nit, id_plan, estado) VALUES (?, ?, 1, 'activa')")->execute([$c['nombre'], $c['nit']]);
            $id = $db->lastInsertId();
        } else {
            $db->prepare("UPDATE clinicas SET estado = 'activa' WHERE id_clinica = ?")->execute([$id]);
        }
        $idClinica[$clave] = (int) $id;
        (new InicializadorClinica($db))->copiar((int) $id);
    }

    foreach (PERSONAS as [$nombre, $documento, $correo, $roles, $propietarioEn, $esSuperAdmin]) {
        $password = $claveComun ?? PoliticaPassword::generarTemporal();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $existente = $usuario->buscarPorEmail($correo);
        if ($existente === null) {
            $id = $usuario->crear([
                'documento' => $documento,
                'tipo_documento' => $documento === null ? null : 'CC',
                'nombre_completo' => $nombre,
                'telefono' => '3000000000',
                'email' => $correo,
                'password' => $hash,
            ]);
        } else {
            $id = (int) $existente['id_usuario'];
            $usuario->actualizarPassword($id, $hash);
            $db->prepare('UPDATE usuarios SET estado = 1 WHERE id_usuario = ?')->execute([$id]);
        }
        $usuario->marcarCambioPassword($id, false);

        if ($esSuperAdmin) {
            $db->prepare('UPDATE usuarios SET es_super_admin = 1 WHERE id_usuario = ?')->execute([$id]);
        } else {
            // El super-administrador está exento (B.5); los demás no ven la pantalla de aceptación.
            DatosPrueba::aceptarPolitica($db, $id);
        }
        foreach ($roles as $clinica => $rol) {
            $usuario->asignarRolEnClinica($id, $idClinica[$clinica], $rol);
        }
        foreach ($propietarioEn as $clinica) {
            $st = $db->prepare('SELECT 1 FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
            $st->execute([$id, $idClinica[$clinica]]);
            if ($st->fetchColumn()) {
                $db->prepare("UPDATE propietario_clinica SET estado = 'activo' WHERE id_propietario = ? AND id_clinica = ?")->execute([$id, $idClinica[$clinica]]);
            } else {
                $db->prepare("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (?, ?, 'activo')")->execute([$id, $idClinica[$clinica]]);
            }
        }

        $contextos = array_map(static fn ($c) => $c['tipo'] === 'clinica' ? $c['clinica'] . ' · ' . $c['rol'] : $c['rol'], $usuario->contextosDe($id) ?? []);
        $credenciales[] = [$nombre, $documento ?? '—', $correo, $password, implode(' | ', $contextos)];
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    salir('No se pudieron crear los datos: ' . $e->getMessage());
}

echo PHP_EOL . 'Datos de prueba listos. Se entra con el documento o con el correo.' . PHP_EOL . PHP_EOL;
foreach ($credenciales as [$nombre, $documento, $correo, $password, $contextos]) {
    echo "  {$nombre}" . PHP_EOL;
    echo "    documento: {$documento}   correo: {$correo}" . PHP_EOL;
    echo "    contraseña: {$password}" . PHP_EOL;
    echo "    contextos: {$contextos}" . PHP_EOL . PHP_EOL;
}
