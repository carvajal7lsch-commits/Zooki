<?php
/**
 * Horario de prueba para los veterinarios de una base local (pruebas de usuario, 2026-10-21).
 *
 *   php scripts/dev/horarios_veterinarios.php        muestra qué haría, sin escribir
 *   php scripts/dev/horarios_veterinarios.php --si   lo hace
 *
 * RN-415: la agenda solo ofrece horas dentro del horario de la clínica y del horario del
 * veterinario. Las bases que pasaron a M4 tienen a sus veterinarios sin franjas, y así no
 * aparece ninguna hora. Este script le da a cada veterinario activo una franja de todo el
 * día (00:00 a 23:59), de lunes a domingo, en cada clínica donde trabaja; las horas reales
 * las sigue recortando el horario de la clínica.
 *
 * Es repetible: solo agrega el día que el veterinario no tenga ya configurado en esa clínica,
 * y nunca toca ni borra franjas existentes. Se niega fuera de la consola, dentro de Docker o
 * contra una base que no sea local.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/helpers/ZonaHoraria.php';
ZonaHoraria::aplicar();
require_once $raiz . '/config/Database.php';
require_once $raiz . '/helpers/Roles.php';
require_once $raiz . '/helpers/EntornoLocal.php';

function salir(string $mensaje, int $codigo = 1): void
{
    fwrite($codigo === 0 ? STDOUT : STDERR, $mensaje . PHP_EOL);
    exit($codigo);
}

ob_start(); // Database imprime el error de conexión; se reporta aparte.
$db = (new Database())->getConnection();
$error = trim((string) ob_get_clean());
if (!$db) {
    salir('No se pudo conectar a la base de datos: ' . $error);
}
$motivoNoLocal = EntornoLocal::motivoNoLocal((string) $db->getAttribute(PDO::ATTR_CONNECTION_STATUS));
if ($motivoNoLocal !== null) {
    salir("No se cargan horarios de prueba: {$motivoNoLocal}. Este script solo escribe en una base de tu equipo.");
}

$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$veterinarios = $db->prepare("SELECT uc.id_clinica, uc.id_usuario, u.nombre_completo, c.nombre AS clinica
    FROM usuario_clinica uc
    JOIN usuarios u ON u.id_usuario = uc.id_usuario
    JOIN clinicas c ON c.id_clinica = uc.id_clinica
    WHERE uc.id_rol = ? AND uc.estado = 'activo' AND u.estado = 1
    ORDER BY c.nombre, u.nombre_completo");
$veterinarios->execute([Roles::VETERINARIO]);
$lista = $veterinarios->fetchAll(PDO::FETCH_ASSOC);
if ($lista === []) {
    salir("La base {$base} no tiene veterinarios activos.", 0);
}

$existe = $db->prepare('SELECT 1 FROM horarios_veterinario WHERE id_clinica = ? AND id_veterinario = ? AND dia_semana = ? AND activo = 1 LIMIT 1');
$insertar = $db->prepare("INSERT INTO horarios_veterinario (id_clinica, id_veterinario, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, ?, '00:00:00', '23:59:00', 1)");
$escribir = in_array('--si', $argv ?? [], true);
$total = 0;

if ($escribir) {
    $db->beginTransaction();
}
try {
    foreach ($lista as $vet) {
        $dias = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            $existe->execute([$vet['id_clinica'], $vet['id_usuario'], $dia]);
            if ($existe->fetchColumn()) {
                continue;
            }
            $dias[] = $dia;
            if ($escribir) {
                $insertar->execute([$vet['id_clinica'], $vet['id_usuario'], $dia]);
            }
        }
        $total += count($dias);
        $estado = $dias === [] ? 'ya tenía horario todos los días' : count($dias) . ' día(s) ' . ($escribir ? 'agregados' : 'por agregar');
        echo "- {$vet['nombre_completo']} en {$vet['clinica']}: {$estado}" . PHP_EOL;
    }
    if ($escribir) {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    salir('No se guardó nada: ' . $e->getMessage());
}

if (!$escribir) {
    salir("Base {$base}: se agregarían {$total} franja(s) de 00:00 a 23:59. Vuelve a correrlo con --si para hacerlo.", 0);
}
salir("Listo: {$total} franja(s) agregadas en {$base}. La agenda ofrece horas dentro del horario de la clínica.", 0);
