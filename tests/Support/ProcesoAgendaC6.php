<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../models/AusenciaAgenda.php';
require_once __DIR__ . '/../../models/Cita.php';

if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('El escritor C6 exige zooki_test_base_v2 explícita.');
}
$carpeta = $argv[1];
$nombre = $argv[2];
$comando = json_decode(file_get_contents($carpeta . '/' . $nombre . '.json'), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=zooki_test_base_v2;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
    getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Auditoria::definirProxiesConfiables([]);
$_SESSION = ['id_usuario' => ($comando['accion'] === 'aprobar' ? 1 : 2)];
Contexto::activar(Contexto::deClinica(1, 'Norte', $comando['accion'] === 'aprobar' ? Roles::ADMIN : Roles::VETERINARIO), 1);

function publicarC6(string $ruta, string $contenido): void
{
    file_put_contents($ruta . '.tmp', $contenido);
    rename($ruta . '.tmp', $ruta);
}

function esperarC6(string $ruta): void
{
    $limite = microtime(true) + 20;
    while (!is_file($ruta)) {
        if (microtime(true) > $limite) {
            throw new RuntimeException('No llegó la barrera C6: ' . basename($ruta));
        }
        usleep(10000);
    }
}

publicarC6($carpeta . '/' . $nombre . '.listo', (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
if ($comando['entrada_controlada'] ?? false) {
    esperarC6($carpeta . '/entrar_segundo');
}
$reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00');
$agenda = new CascadaAgenda($db,$reloj);
$reservar = static function () use ($comando,$db,$reloj,$nombre): array {
    if ($comando['accion'] === 'aprobar') {
        return (new AusenciaAgenda($db,$reloj))->resolver($comando['ausencia'],true,'',hash('sha256',$nombre));
    }
    return ['id_cita' => (new Cita($db,$reloj))->registrar(['id_mascota'=>2,'id_veterinario'=>2,'id_tipo_cita'=>1,'fecha'=>'2030-01-07','hora'=>'09:00:00','motivo'=>'Carrera C6','clave_operacion'=>hash('sha256',$nombre)])];
};
try {
    if ($nombre === 'primero') {
        // Retiene vet y mascota tras escribir, sin confirmar: el segundo debe esperar el candado real.
        $resultado = (new CoordinadorAgenda($db))->ejecutar(1, function () use ($agenda): array {
            $recursos = $agenda->recursos('2030-01-07');
            $recursos['mascotas'][] = 2;
            return $recursos;
        }, function () use ($reservar, $carpeta): array {
            $resultado = $reservar();
            publicarC6($carpeta . '/bloqueado', 'Respuesta escrita sin confirmar.');
            esperarC6($carpeta . '/liberar');
            return $resultado;
        });
    } else {
        $resultado = $reservar();
    }
    $salida = ['ok' => true, 'resultado' => $resultado];
} catch (Throwable $error) {
    $salida = ['ok' => false, 'clase' => $error::class, 'mensaje' => $error->getMessage()];
}
publicarC6($carpeta . '/' . $nombre . '.fin', json_encode($salida, JSON_THROW_ON_ERROR));
