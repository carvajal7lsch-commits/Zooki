<?php
require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/PagoSuscripcion.php';
require_once __DIR__ . '/../../models/ConciliadorPago.php';
require_once __DIR__ . '/ClienteWompiFalso.php';
require_once __DIR__ . '/../../models/CobroAutomatico.php';
require_once __DIR__ . '/../../controllers/WompiEventosController.php';

/** La barrera observa el candado real del servicio, sin cambiar su código. */
final class BarreraPagoS2 extends PDOStatement
{
    protected function __construct(private mixed $despues)
    {
    }

    public function execute(?array $params = null): bool
    {
        $resultado = parent::execute($params);
        if (str_starts_with($this->queryString, 'SELECT id_clinica FROM clinicas WHERE id_clinica=?')) {
            ($this->despues)();
        }
        return $resultado;
    }
}

[$script, $carpeta, $nombre, $fase, $evento] = array_pad($argv, 5, '0');
putenv('APP_URL=http://localhost/Zooki/public');
$db = (new Database(Database::configuracionPruebas()))->getConnection();
file_put_contents($carpeta . '/' . $nombre . '.conexion', (string) $db->query('SELECT CONNECTION_ID()')->fetchColumn());
$retenido = false;
$db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BarreraPagoS2::class, [function () use ($carpeta, $nombre, &$retenido): void {
    if ($nombre !== 'primero' || $retenido) {
        return;
    }
    $retenido = true;
    file_put_contents($carpeta . '/bloqueado', '1');
    $hasta = microtime(true) + 25;
    while (!is_file($carpeta . '/liberar')) {
        if (microtime(true) > $hasta) {
            throw new RuntimeException('Barrera de pago agotada.');
        }
        usleep(10000);
    }
}]]);
$reloj = static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00');
$config = ClienteWompiFalso::configuracion('test', '1', str_starts_with($fase, 's3_') ? '1' : '0');
try {
    if (in_array($fase, ['s3_workers', 's3_manual', 's3_auto_manual', 's3_retiro', 's3_auto_retiro'], true)) {
        $cliente = new ClienteWompiFalso();
        $cliente->datosComercio = ClienteWompiFalso::contratos();
        $cliente->antesDeConsultar = static function () use ($db): void {
            if ($db->inTransaction()) {
                throw new RuntimeException('HTTP dentro del candado.');
            }
        };
        $manual = ($fase === 's3_manual' && $nombre === 'primero') || ($fase === 's3_auto_manual' && $nombre === 'segundo');
        $retiro = ($fase === 's3_retiro' && $nombre === 'primero') || ($fase === 's3_auto_retiro' && $nombre === 'segundo');
        if ($manual) {
            $r = (new PagoSuscripcion($db, 1, $config, $reloj))->reservar('mensual', 0, 1);
        } elseif ($retiro) {
            $r = (new FuentePago($db, 1, $cliente, $config, $reloj))->quitar(1);
        } else {
            $r = ['enviado' => (new CobroAutomatico($db, $cliente, $config, $reloj))->procesar(1)];
        }
        $resultado = ['operacion' => $r, 'posts' => count($cliente->creaciones)];
    } elseif ($fase === 'reserva') {
        $resultado = (new PagoSuscripcion($db, 1, $config, $reloj))->reservar($nombre === 'primero' ? 'mensual' : 'anual', 0, 1);
    } else {
        $cliente = new ClienteWompiFalso();
        $referencia = $db->query('SELECT referencia FROM intentos_pago ORDER BY id_intento LIMIT 1')->fetchColumn();
        $cliente->respuestas['tx_simultanea'] = ['id' => 'tx_simultanea', 'status' => 'APPROVED', 'reference' => $referencia, 'currency' => 'COP', 'amount_in_cents' => 8999000];
        $cliente->antesDeConsultar = static function () use ($db): void {
            if ($db->inTransaction()) {
                throw new RuntimeException('GET con transacción abierta.');
            }
        };
        if ($fase === 's3_receptor') {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            (new WompiEventosController($db, $config, $cliente, $reloj))->recibir('test', ClienteWompiFalso::cuerpo($cliente->respuestas['tx_simultanea'], 'test', (int) $evento));
            $resultado = ['codigo' => http_response_code()];
        } else {
            $resultado = ['acreditado' => (new ConciliadorPago($db, $cliente, $config, $reloj))->procesar((int) $evento)];
        }
    }
    file_put_contents($carpeta . '/' . $nombre . '.fin', json_encode(['ok' => true, 'resultado' => $resultado], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $error) {
    file_put_contents($carpeta . '/' . $nombre . '.fin', json_encode(['ok' => false, 'error' => $error->getMessage()]));
    exit(1);
}
