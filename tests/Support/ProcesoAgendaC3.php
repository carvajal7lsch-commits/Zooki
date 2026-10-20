<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../models/Cita.php';

if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('El escritor C3 exige zooki_test_base_v2 explícita.');
}
$carpeta = $argv[1];
$nombre = $argv[2];
$comando = json_decode(file_get_contents($carpeta . '/' . $nombre . '.json'), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=zooki_test_base_v2;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
    getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Auditoria::definirProxiesConfiables([]);
$_SESSION = ['id_usuario' => $comando['actor']];
Contexto::activar(Contexto::deClinica($comando['clinica'], 'Prueba C3', Roles::ADMIN), 1);

function publicarC3(string $ruta, string $contenido): void
{
    file_put_contents($ruta . '.tmp', $contenido);
    rename($ruta . '.tmp', $ruta);
}

function esperarC3(string $ruta): void
{
    $limite = microtime(true) + 20;
    while (!is_file($ruta)) {
        if (microtime(true) > $limite) {
            throw new RuntimeException('No llegó la barrera C3: ' . basename($ruta));
        }
        usleep(10000);
    }
}

publicarC3($carpeta . '/' . $nombre . '.listo', (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
if ($comando['entrada_controlada'] ?? false) {
    esperarC3($carpeta . '/entrar_segundo');
}
$accion = function () use ($db, $comando): array {
    $citas = new Cita($db);
    $clave = hash('sha256', json_encode($comando, JSON_THROW_ON_ERROR));
    if ($comando['accion'] === 'reprogramar') {
        $citas->reprogramar($citas->getById($comando['id_cita']), $comando['vet'], '2030-01-07', $comando['hora'], $clave);
        return ['id_cita' => $comando['id_cita']];
    }
    return ['id_cita' => $citas->registrar(['id_mascota' => $comando['mascota'], 'id_veterinario' => $comando['vet'], 'id_tipo_cita' => $comando['tipo'],
        'fecha' => '2030-01-07', 'hora' => $comando['hora'], 'motivo' => 'Carrera C3', 'clave_operacion' => $clave])];
};
try {
    if ($nombre === 'primero') {
        $resultado = (new CoordinadorAgenda($db))->ejecutar($comando['clinica'], static fn () => ['usuarios' => [$comando['vet']], 'mascotas' => [$comando['mascota']]], function () use ($accion, $carpeta, $comando): array {
            $resultado = $accion();
            publicarC3($carpeta . '/bloqueado', 'Escritura sin confirmar; recursos estables retenidos.');
            esperarC3($carpeta . '/liberar');
            if ($comando['rollback'] ?? false) {
                throw new RuntimeException('Rollback controlado después de escribir cita, operación y auditoría.');
            }
            return $resultado;
        });
    } else {
        $resultado = $accion();
    }
    $salida = ['ok' => true, 'resultado' => $resultado];
} catch (Throwable $error) {
    $salida = ['ok' => false, 'clase' => $error::class, 'mensaje' => $error->getMessage()];
}
publicarC3($carpeta . '/' . $nombre . '.fin', json_encode($salida, JSON_THROW_ON_ERROR));
