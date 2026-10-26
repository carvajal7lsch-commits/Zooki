<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/ConfiguracionWompi.php';
require_once __DIR__ . '/../helpers/FirmaWompi.php';
require_once __DIR__ . '/../models/EventoWompi.php';
require_once __DIR__ . '/../models/ConciliadorPago.php';
require_once __DIR__ . '/../helpers/ClienteWompiHttp.php';

final class WompiEventosController
{
    public function __construct(private ?PDO $db = null, private ?ConfiguracionWompi $configuracion = null, private ?ClienteWompi $cliente = null, private mixed $reloj = null)
    {
        $this->configuracion ??= new ConfiguracionWompi();
    }

    public function recibir(string $entorno, ?string $cuerpo = null): void
    {
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        try {
            $this->configuracion->validarEventos();
            if ($this->configuracion->entorno() !== $entorno) {
                http_response_code(401);
                return;
            }
        } catch (RuntimeException $error) {
            http_response_code(503);
            return;
        }
        if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > FirmaWompi::EVENTO_MAX_BYTES) {
            http_response_code(413);
            return;
        }
        if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            http_response_code(400);
            return;
        }
        $cuerpo ??= file_get_contents('php://input', false, null, 0, FirmaWompi::EVENTO_MAX_BYTES + 1);
        try {
            $evento = FirmaWompi::evento($cuerpo, (string) ($_SERVER['HTTP_X_EVENT_CHECKSUM'] ?? ''), $entorno, $this->configuracion->valor('SECRETO_EVENTOS'));
        } catch (LengthException $error) {
            http_response_code(413);
            return;
        } catch (UnexpectedValueException $error) {
            http_response_code(401);
            return;
        } catch (InvalidArgumentException $error) {
            http_response_code(400);
            return;
        }
        try {
            // Database heredada puede imprimir el error de conexión: nunca devolverlo al proveedor.
            ob_start();
            try {
                $db = $this->db ?? (new Database())->getConnection();
            } finally {
                ob_end_clean();
            }
            $nuevo = (new EventoWompi($db, $this->reloj))->recibir($evento);
            http_response_code(200);
        } catch (Throwable $error) {
            error_log('Wompi: no se confirmó el almacenamiento del evento.');
            http_response_code(503);
            return;
        }
        if (!$nuevo || $evento['tipo'] !== 'transaction.updated') {
            return;
        }
        // S2 revisión: confirmar recepción durable antes de HTTP; no tocar búferes ajenos/de pruebas.
        if (!defined('ZOOKI_PRUEBAS') && getenv('ZOOKI_ENTORNO_PRUEBAS') !== '1') {
            ignore_user_abort(true);
            header('Content-Length: 0');
            flush();
        }
        try {
            $q = $db->prepare('SELECT id_evento FROM wompi_eventos WHERE entorno=? AND clave_evento=?');
            $q->execute([$evento['entorno'], $evento['clave_evento']]);
            (new ConciliadorPago($db, $this->cliente ?? new ClienteWompiHttp($this->configuracion), $this->configuracion, $this->reloj))->procesar((int) $q->fetchColumn());
        } catch (Throwable $error) {
            // El 200 ya reconoce el inbox. Schedule recupera errores sin exigir reenvío del proveedor.
            error_log('Wompi: evento conservado para conciliación posterior.');
        }
    }
}
