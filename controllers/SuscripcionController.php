<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../models/Suscripcion.php';
require_once __DIR__ . '/../models/PagoSuscripcion.php';
require_once __DIR__ . '/../models/FuentePago.php';
require_once __DIR__ . '/../models/ConciliadorPago.php';
require_once __DIR__ . '/../models/RecuperadorCheckout.php';
require_once __DIR__ . '/../helpers/EnlaceAjustes.php';

final class SuscripcionController
{
    private ?string $mensajeRetorno = null;

    public function __construct(private PDO $db, private mixed $reloj = null, private ?ConfiguracionWompi $configuracion = null, private ?ClienteWompi $cliente = null)
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
        // Los enlaces antiguos conservan su entrada, pero la pantalla vive dentro de Ajustes.
        header('Location: ' . EnlaceAjustes::suscripcion($_GET));
    }

    /** Datos de presentación compartidos por Ajustes; sin enviar correos ni consultar la pasarela. */
    public function datosVista(): array
    {
        Security::autorizar('admin_suscripcion');
        $servicio = new Suscripcion($this->db, (int) Contexto::clinicaActiva(), $this->reloj);
        $estadoSuscripcion = $servicio->estado();
        $profesionalSuscripcion = $servicio->profesional();
        $pagoDisponible = false;
        $mensajePago = '';
        try {
            $this->configuracion->validar();
            EnlaceCuenta::base();
            $pagoDisponible = !$estadoSuscripcion['legado_sin_fecha'];
            $mensajePago = $pagoDisponible ? 'Tu plan se activa en cuanto Wompi confirme el pago; puede tardar unos minutos.' : 'Primero solicita la fecha de fin al super-administrador.';
        } catch (RuntimeException $error) {
            $mensajePago = $error->getMessage();
        }
        if (isset($this->mensajeRetorno)) {
            $mensajePago = $this->mensajeRetorno;
        }
        $avisoRetorno = $_SESSION['suscripcion_aviso_retorno'] ?? null;
        unset($_SESSION['suscripcion_aviso_retorno']);
        if (is_array($avisoRetorno) && ($avisoRetorno['clinica'] ?? null) === Contexto::clinicaActiva() && is_string($avisoRetorno['mensaje'] ?? null)) {
            $mensajePago = $avisoRetorno['mensaje'];
        }
        $historialPagos = $this->pagos()->historial($this->pagina());
        $fuentePago = $this->fuentes()->actual();
        $automaticoDisponible = false;
        $mensajeAutomatico = '';
        try {
            $this->configuracion->validarAutomaticos();
            $automaticoDisponible = $estadoSuscripcion['ciclo'] === 'mensual' && !$estadoSuscripcion['legado_sin_fecha'] && $estadoSuscripcion['periodo_fin'] !== null
                && (int) $estadoSuscripcion['plan_contratado']['id_plan'] !== (int) $estadoSuscripcion['plan_gratuito']['id_plan'];
            $mensajeAutomatico = $automaticoDisponible ? 'Guardar el medio no autoriza cargos. Autoriza aparte cada mes por el importe indicado.' : 'El automático requiere un plan profesional mensual con vencimiento. El anual se renueva con el botón.';
        } catch (RuntimeException $error) {
            $mensajeAutomatico = $error->getMessage();
        }
        $importeMensual = TarifaSuscripcion::calcular($profesionalSuscripcion, 'mensual')['total_centavos'];
        $requiereAceptacion = $fuentePago && (int) $fuentePago['cobro_automatico'] === 1 && (int) $fuentePago['importe_mensual_autorizado_centavos'] !== $importeMensual;
        $retornoPago = null;
        if ($this->mensajeRetorno === null && isset($_GET['referencia'])) {
            $retornoPago = $this->pagos()->estadoLocal($this->referencia());
        }
        $origenSuscripcion = ($_GET['desde'] ?? '') === 'inicio' ? 'inicio' : 'configuracion';
        return compact('estadoSuscripcion', 'profesionalSuscripcion', 'pagoDisponible', 'mensajePago', 'historialPagos',
            'fuentePago', 'automaticoDisponible', 'mensajeAutomatico', 'requiereAceptacion', 'retornoPago', 'origenSuscripcion');
    }

    private function pagos(): PagoSuscripcion
    {
        return new PagoSuscripcion($this->db, (int) Contexto::clinicaActiva(), $this->configuracion, $this->reloj);
    }

    private function fuentes(): FuentePago
    {
        return new FuentePago($this->db, (int) Contexto::clinicaActiva(), $this->cliente ?? new ClienteWompiHttp($this->configuracion), $this->configuracion, $this->reloj);
    }

    public function prepararFuente(): void
    {
        Security::autorizar('suscripcion_fuente_preparar_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            return;
        }
        $this->operacionFuente(function (): array {
            $datos = $this->fuentes()->preparar((int) Contexto::idUsuario());
            $nonce = bin2hex(random_bytes(32));
            $_SESSION['suscripcion_aceptacion'] = ['nonce' => $nonce, 'clinica' => Contexto::clinicaActiva(), 'actor' => Contexto::idUsuario(),
                'version' => $datos['version'], 'importe' => $datos['importe'], 'hasta' => time() + PoliticaSuscripcion::COTIZACION_MINUTOS * 60];
            return $datos + ['nonce' => $nonce];
        });
    }

    private function aceptacion(bool $mensual = false): array
    {
        $prueba = $_SESSION['suscripcion_aceptacion'] ?? [];
        $nonce = $_POST['nonce'] ?? '';
        if (!is_string($nonce) || !isset($prueba['nonce']) || !hash_equals($prueba['nonce'], $nonce) || $prueba['hasta'] <= time()
            || $prueba['clinica'] !== Contexto::clinicaActiva() || $prueba['actor'] !== Contexto::idUsuario()
            || ($_POST['acepta_politica'] ?? '') !== '1' || ($_POST['acepta_datos'] ?? '') !== '1'
            || ($mensual && ($_POST['acepta_mensual'] ?? '') !== '1')) {
            throw new InvalidArgumentException('Consulta y acepta las condiciones antes de continuar.');
        }
        return $prueba;
    }

    public function guardarFuente(): void
    {
        Security::autorizar('suscripcion_fuente_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        $this->operacionFuente(function (): array {
            $prueba = $this->aceptacion();
            $tipo = $_POST['tipo'] ?? null;
            $token = $_POST['token'] ?? null;
            if (!is_string($tipo) || !is_string($token)) {
                throw new InvalidArgumentException('Completa el medio de pago en Wompi.');
            }
            return $this->fuentes()->guardar($tipo, $token, true, true, $prueba['version'], (int) Contexto::idUsuario());
        });
    }

    public function automatico(): void
    {
        Security::autorizar('suscripcion_automatico_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        $this->operacionFuente(function (): array {
            if (!in_array($_POST['autorizar'] ?? null, ['0', '1'], true)) {
                throw new InvalidArgumentException('Elige autorizar o retirar el automático.');
            }
            $autorizar = $_POST['autorizar'] === '1';
            $prueba = $autorizar ? $this->aceptacion(true) : [];
            return $this->fuentes()->automatico($autorizar, $prueba['version'] ?? '', (int) ($prueba['importe'] ?? 0), (int) Contexto::idUsuario());
        });
    }

    public function quitarFuente(): void
    {
        Security::autorizar('suscripcion_retirar_fuente_ajax');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        $this->operacionFuente(fn (): array => $this->fuentes()->quitar((int) Contexto::idUsuario()));
    }

    private function operacionFuente(callable $operacion): void
    {
        try {
            $resultado = $operacion();
            $this->json(['ok' => true, 'resultado' => $resultado + ['fuente' => $this->fuentes()->actual()]]);
        } catch (Throwable $error) {
            if ($error instanceof AccesoDenegado) {
                throw $error;
            }
            http_response_code(422);
            $mensaje = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo completar la solicitud. Consulta el estado antes de reintentar.';
            $this->json(['ok' => false, 'mensaje' => $mensaje]);
        }
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
            if ($version === false || !in_array($ciclo, ['mensual', 'anual'], true)) {
                throw new InvalidArgumentException('Revisa el ciclo y la vigencia.');
            }
            $this->configuracion->validar();
            (new RecuperadorCheckout($this->db, $this->cliente ?? new ClienteWompiHttp($this->configuracion), $this->configuracion, $this->reloj))
                ->ejecutar(false, (int) Contexto::clinicaActiva(), (int) Contexto::idUsuario());
            $pago = $this->pagos()->reservar($ciclo, $version, (int) Contexto::idUsuario());
            if (isset($pago['checkout']) && ($_POST['desde'] ?? null) === 'inicio') {
                $pago['checkout']['redirectUrl'] .= '&desde=inicio';
            }
            $this->json(['ok' => true, 'pago' => $pago]);
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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            return;
        }
        $this->mensajeRetorno = 'El pago sigue pendiente de confirmación. Puedes consultar su estado.';
        $id = $_GET['id'] ?? null;
        if (FirmaWompi::idValido($id)) {
            try {
                $conciliador = new ConciliadorPago($this->db, $this->cliente ?? new ClienteWompiHttp($this->configuracion), $this->configuracion, $this->reloj);
                $resuelto = $conciliador->recuperar($id, (int) Contexto::clinicaActiva());
                $this->mensajeRetorno = $resuelto ? 'Consulta completada. Revisa el estado de tu plan y el historial de pagos.' : 'El pago sigue pendiente de confirmación o requiere revisión. Puedes consultar su estado.';
            } catch (Throwable $error) {
                // No mostrar errores del proveedor ni datos de una referencia ajena.
                $this->mensajeRetorno = 'No se pudo confirmar el pago. Sigue pendiente; vuelve a consultar más tarde.';
            }
        }
        $_SESSION['suscripcion_aviso_retorno'] = ['clinica' => Contexto::clinicaActiva(), 'mensaje' => $this->mensajeRetorno];
        $this->vista();
    }

    private function json(array $datos): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
