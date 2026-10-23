<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../models/LlegadaAgenda.php';

if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('El escritor C8 exige zooki_test_base_v2 explícita.');
}
$carpeta = $argv[1];
$nombre = $argv[2];
$comando = json_decode(file_get_contents($carpeta . '/' . $nombre . '.json'), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=zooki_test_base_v2;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
    getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Auditoria::definirProxiesConfiables([]);
$_SESSION = ['id_usuario' => 2];
Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);

function publicarC8(string $ruta, string $contenido): void
{
    file_put_contents($ruta . '.tmp', $contenido);
    rename($ruta . '.tmp', $ruta);
}

function esperarC8(string $ruta): void
{
    $limite = microtime(true) + 20;
    while (!is_file($ruta)) {
        if (microtime(true) > $limite) {
            throw new RuntimeException('No llegó la barrera C8: ' . basename($ruta));
        }
        usleep(10000);
    }
}

publicarC8($carpeta . '/' . $nombre . '.listo', (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
if ($comando['entrada_controlada'] ?? false) {
    esperarC8($carpeta . '/entrar_segundo');
}
$llegadas = new LlegadaAgenda($db, static fn () => IntervalosAgenda::fecha('2030-01-07 07:05:00'));
$asignar = static fn (): array => $llegadas->asignar((int) $comando['id_solicitud'], '08:00', hash('sha256', $nombre));
try {
    if ($nombre === 'primero') {
        // El servicio de llegadas bloquea a todo el personal vet, titulares y mascotas del día: el exterior debe cubrirlos.
        // Retiene esos recursos tras crear la cita, sin confirmar: el segundo debe esperar el candado real.
        $resultado = (new CoordinadorAgenda($db))->ejecutar(1, static fn () => ['usuarios' => [2, 5, 6, 8], 'mascotas' => [2, 3]], function () use ($asignar, $carpeta): array {
            $resultado = $asignar();
            publicarC8($carpeta . '/bloqueado', 'Cita de llegada escrita sin confirmar.');
            esperarC8($carpeta . '/liberar');
            return $resultado;
        });
    } else {
        $resultado = $asignar();
    }
    $salida = ['ok' => true, 'resultado' => $resultado];
} catch (Throwable $error) {
    $salida = ['ok' => false, 'clase' => $error::class, 'mensaje' => $error->getMessage()];
}
publicarC8($carpeta . '/' . $nombre . '.fin', json_encode($salida, JSON_THROW_ON_ERROR));
