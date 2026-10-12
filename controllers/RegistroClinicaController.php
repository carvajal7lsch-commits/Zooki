<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../models/RegistroClinica.php';
require_once __DIR__ . '/../helpers/Turnstile.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/../helpers/Security.php';

final class RegistroClinicaController
{
    private RegistroClinica $registro;
    private Turnstile $turnstile;

    public function __construct(?PDO $db = null, private mixed $correo = null, ?Turnstile $turnstile = null)
    {
        $this->registro = new RegistroClinica($db ?? (new Database())->getConnection());
        $this->turnstile = $turnstile ?? new Turnstile();
    }

    public function formulario(): void
    {
        // M0.1: resultado de un envío sin JavaScript (ver procesar()).
        $resultadoRegistro = $_SESSION['registro_clinica_resultado'] ?? null;
        unset($_SESSION['registro_clinica_resultado']);
        require __DIR__ . '/../views/auth/registro_clinica.php';
    }

    public function procesar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validate()) {
            $this->responder(['success' => false, 'message' => 'Recarga el formulario e inténtalo de nuevo.'], 403);
            return;
        }
        $this->responder($this->registrar($_POST, Auditoria::ipCliente()));
    }

    /**
     * El formulario envía por AJAX y recibe JSON. Sin JavaScript (M0.1) el
     * mismo POST vuelve a la página con el resultado, como el registro del
     * propietario; el servidor valida lo mismo en los dos casos.
     */
    private function responder(array $resultado, int $codigo = 200): void
    {
        $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if (!$ajax) {
            $_SESSION['registro_clinica_resultado'] = $resultado;
            header('Location: index.php?action=registro_clinica');
            return;
        }
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultado);
    }

    /** RE-0.1.4/8: verifica antes de escribir; transporte sustituible en pruebas. */
    public function registrar(array $entrada, string $ip): array
    {
        if (!$this->turnstile->validar((string) ($entrada['cf-turnstile-response'] ?? ''), $ip)) {
            return ['success' => false, 'message' => 'Completa la verificación de seguridad e inténtalo de nuevo.'];
        }
        try {
            EnlaceCuenta::base();
            $solicitud = $this->registro->registrar($entrada, $ip);
        } catch (NitDuplicado $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'reportar_nit' => true];
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('D3: no se pudo registrar la clínica.');
            return ['success' => false, 'message' => 'No se pudo completar la solicitud. Inténtalo más tarde.'];
        }
        try {
            $this->correo ??= new EmailService();
            $url = EnlaceCuenta::crear('activar_clinica', $solicitud['enlace']['id'], $solicitud['enlace']['token']);
            $asunto = 'Confirma que quieres registrar ' . $solicitud['clinica'] . ' con tu cuenta';
            $mensaje = 'Confirma solo si solicitaste registrar esta clínica. El enlace vence en 24 horas. Tu identidad, contraseña y otros roles se conservan.';
            $recordatorio = '';
            if (!$solicitud['nueva']) {
                $recuperar = htmlspecialchars(EnlaceCuenta::base() . '?action=login&recuperar=1', ENT_QUOTES, 'UTF-8');
                $recordatorio = '<p>La contraseña escrita en el formulario no se usó. Entrarás con tu contraseña de siempre y elegirás la clínica al entrar.</p><p><a href="' . $recuperar . '">¿Olvidaste tu contraseña?</a></p>';
            }
            if ($solicitud['nueva']) {
                $asunto = 'Activa tu clínica ' . $solicitud['clinica'];
                $mensaje = 'Confirma tu correo y activa la clínica con el plan gratuito. El enlace vence en 24 horas.';
            }
            $html = $this->correo->obtenerPlantillaBaseHTML($solicitud['nombre'], $asunto, '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>' . $recordatorio, 'Confirmar registro', $url);
            $this->correo->limpiarDirecciones();
            $this->correo->enviarCorreoPersonalizado($solicitud['email'], $solicitud['nombre'], $asunto, $html);
        } catch (Throwable $e) {
            // RN-G06/RE-T.13.2: el fallo de transporte tampoco revela la cuenta elegida.
            error_log('D3: no se pudo enviar el enlace de activación de clínica.');
        }
        return ['success' => true, 'message' => RegistroClinica::MENSAJE];
    }

    public function activar(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        $fila = $this->registro->leer($id, $token);
        $error = null;
        $terminado = false;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                if (!Csrf::validate()) {
                    throw new InvalidArgumentException('Recarga el enlace e inténtalo de nuevo.');
                }
                $this->registro->activar($id, $token, $_POST, Auditoria::ipCliente());
                $terminado = true;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('D3: falló la activación de clínica.');
                $error = 'No se pudo activar la clínica. Puedes volver a intentarlo con este enlace.';
            }
        }
        require __DIR__ . '/../views/auth/activar_clinica.php';
    }

    public function reportar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validate()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Recarga el formulario e inténtalo de nuevo.']);
            return;
        }
        try {
            if (!$this->turnstile->validar((string) ($_POST['cf-turnstile-response'] ?? ''), Auditoria::ipCliente())) {
                throw new InvalidArgumentException('Completa nuevamente la verificación de seguridad.');
            }
            $this->registro->reportarNit($_POST);
            echo json_encode(['success' => true, 'message' => 'El reporte quedó registrado para soporte.']);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('D3: no se pudo registrar el reporte de NIT.');
            echo json_encode(['success' => false, 'message' => 'No se pudo enviar el reporte. Inténtalo más tarde.']);
        }
    }
}
