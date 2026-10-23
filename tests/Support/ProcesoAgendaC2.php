<?php
/** Escritor real para las barreras C2; solo la base automática, nunca configuración local. */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/HorarioClinica.php';

if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('El proceso C2 solo admite zooki_test_base_v2.');
}
$carpeta = $argv[1];
$nombre = $argv[2];
$comando = json_decode(file_get_contents($carpeta . '/' . $nombre . '.json'), true, 512, JSON_THROW_ON_ERROR);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=zooki_test_base_v2;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
    getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Auditoria::definirProxiesConfiables([]);
$_SESSION = ['id_usuario' => $comando['accion'] === 'reservar' ? 2 : 1];
Contexto::activar(Contexto::deClinica(1, 'Norte', $comando['accion'] === 'reservar' ? Roles::VETERINARIO : Roles::ADMIN), 1);
$idConexion = (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn();
function publicarBarreraC2(string $ruta, string $contenido): void
{
    // El lector solo ve archivos completos, nunca un ID aún vacío al crearse.
    file_put_contents($ruta . '.tmp', $contenido);
    rename($ruta . '.tmp', $ruta);
}
publicarBarreraC2($carpeta . '/' . $nombre . '.listo', (string) $idConexion);
if ($comando['entrada_controlada'] ?? false) {
    $limiteEntrada = microtime(true) + 20;
    while (!is_file($carpeta . '/entrar_segundo')) {
        if (microtime(true) > $limiteEntrada) {
            throw new RuntimeException('No se autorizó la entrada del segundo escritor.');
        }
        usleep(10000);
    }
}

$accion = function () use ($comando, $db): array {
    $m = new CoordinacionHorarios($db);
    $franjas = [['dia_semana'=>1,'hora_inicio'=>'10:00','hora_fin'=>'12:00']];
    $clave = hash('sha256', $comando['accion'] . $comando['caso']);
    return match ($comando['accion']) {
        'reservar' => ['id_cita' => (new Cita($db))->registrar(['id_mascota'=>1,'id_veterinario'=>2,'id_tipo_cita'=>1,
            'fecha'=>'2030-01-07','hora'=>$comando['hora'],'motivo'=>'Carrera C2','clave_operacion'=>$clave])],
        'vet' => $m->guardarVeterinario(2,$franjas,$clave),
        'clinica' => (new HorarioClinica($db))->guardar([[1,1,1,0,'10:00:00','12:00:00',null,null]],$clave),
        'restaurar' => (new HorarioClinica($db))->restaurar($clave),
        'aprobar' => $m->resolver($comando['id_propuesta'],true,'',$clave),
        'cerrar' => $m->cerrar('2030-01-07T09:00','2030-01-07T10:00','Cierre de carrera',$clave),
        default => throw new RuntimeException('Comando de prueba desconocido.'),
    };
};

try {
    if ($nombre === 'primero') {
        $resultado = (new CoordinadorAgenda($db))->ejecutar(1, static fn () => ['usuarios'=>range(1,10),'mascotas'=>[1]], function () use ($accion, $carpeta, $db) {
            $variable = str_contains((string) $db->getAttribute(PDO::ATTR_SERVER_VERSION), 'MariaDB') ? '@@tx_isolation' : '@@transaction_isolation';
            $retenido = $db->query('SELECT DATABASE() base,@@autocommit autocommit,' . $variable . ' aislamiento_sesion')->fetch(PDO::FETCH_ASSOC);
            $retenido['transaccion'] = $db->inTransaction();
            publicarBarreraC2($carpeta . '/bloqueado', json_encode($retenido, JSON_THROW_ON_ERROR));
            $limite = microtime(true) + 20;
            while (!is_file($carpeta . '/liberar')) {
                if (microtime(true) > $limite) {
                    throw new RuntimeException('La barrera no fue liberada.');
                }
                usleep(10000);
            }
            return $accion();
        });
    } else {
        $resultado = $accion();
    }
    $salida = ['ok'=>true,'resultado'=>$resultado];
} catch (Throwable $error) {
    $salida = ['ok'=>false,'clase'=>$error::class,'mensaje'=>$error->getMessage()];
}
publicarBarreraC2($carpeta . '/' . $nombre . '.fin', json_encode($salida, JSON_THROW_ON_ERROR));
