<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../models/Suscripcion.php';
require_once __DIR__ . '/../models/PagoSuscripcion.php';

final class SuscripcionController
{
    public function __construct(private PDO $db, private mixed $reloj = null, private ?ConfiguracionWompi $configuracion = null)
    {
        $this->configuracion ??= new ConfiguracionWompi();
    }

    public function vista(): void
    {
        Security::autorizar('admin_suscripcion');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            return;
        }
        $servicio = new Suscripcion($this->db, (int) Contexto::clinicaActiva(), $this->reloj);
        $estadoSuscripcion = $servicio->estado();
        $profesionalSuscripcion = $servicio->profesional();
        $pagoDisponible = false;
        $mensajePago = '';
        try {
            $this->configuracion->validar();
            EnlaceCuenta::base();
            $pagoDisponible = !$estadoSuscripcion['legado_sin_fecha'];
            $mensajePago = $pagoDisponible ? 'El pago se confirma mediante un evento verificado, nunca por el retorno.' : 'Primero solicita la fecha de fin al super-administrador.';
        } catch (RuntimeException $error) {
            $mensajePago = $error->getMessage();
        }
        $historialPagos = $this->pagos()->historial($this->pagina());
        $retornoPago = null;
        if (isset($_GET['referencia'])) {
            $retornoPago = $this->pagos()->estadoLocal($this->referencia());
        }
        $content_view = __DIR__ . '/../views/admin/suscripcion.php';
        require __DIR__ . '/../views/admin/layout.php';
    }

    private function pagos(): PagoSuscripcion
    {
        return new PagoSuscripcion($this->db, (int) Contexto::clinicaActiva(), $this->configuracion, $this->reloj);
    }

    private function pagina(): int
    {
        return filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
    }

    private function referencia(): string
    {
        $valor = $_GET['referencia'] ?? '';
        if (!is_string($valor) || !preg_match('/^zk_[a-f0-9]{48}$/D', $valor)) {
            throw new InvalidArgumentException('Referencia inválida.');
        }
        return $valor;
    }

    public function pagar(): void
    {
        Security::autorizar('suscripcion_pagar_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        try {
            $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $ciclo = $_POST['ciclo'] ?? null;
            if ($version === false || !is_string($ciclo)) {
                throw new InvalidArgumentException('Revisa el ciclo y la vigencia.');
            }
            $this->json(['ok' => true, 'pago' => $this->pagos()->reservar($ciclo, $version, (int) Contexto::idUsuario())]);
        } catch (InvalidArgumentException | RuntimeException $error) {
            if ($error instanceof AccesoDenegado) {
                throw $error;
            }
            http_response_code(422);
            $this->json(['ok' => false, 'mensaje' => $error instanceof PDOException ? 'No fue posible reservar el pago. Intenta consultar su estado.' : $error->getMessage()]);
        }
    }

    public function estado(): void
    {
        Security::autorizar('suscripcion_estado_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            return;
        }
        $this->json(['ok' => true, 'estado' => $this->pagos()->estadoLocal(isset($_GET['referencia']) ? $this->referencia() : null)]);
    }

    public function historial(): void
    {
        Security::autorizar('suscripcion_historial_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            return;
        }
        $this->json(['ok' => true, 'historial' => $this->pagos()->historial($this->pagina())]);
    }

    public function retorno(): void
    {
        Security::autorizar('suscripcion_retorno');
        $this->vista();
    }

    private function json(array $datos): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
