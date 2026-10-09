<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../models/CuentaTitular.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../helpers/ValidadorCuenta.php';

final class IdentidadController
{
    private PDO $db;
    private CuentaTitular $cuenta;

    public function __construct(?PDO $db = null, private mixed $correo = null, ?callable $google = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->cuenta = new CuentaTitular($this->db, $google);
    }

    private function enviar(string $email, string $nombre, string $asunto, string $mensaje, ?string $enlace = null): bool
    {
        $this->correo ??= new EmailService();
        $this->correo->limpiarDirecciones();
        $html = $this->correo->obtenerPlantillaBaseHTML($nombre, $asunto, '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>', $enlace !== null ? 'Confirmar' : null, $enlace);
        return $this->correo->enviarCorreoPersonalizado($email, $nombre, $asunto, $html);
    }

    public function solicitarCorreo(): void
    {
        $this->json(function (): array {
            EnlaceCuenta::base();
            $solicitud = $this->cuenta->solicitarCorreo((int) Contexto::idUsuario(), $_POST);
            $url = EnlaceCuenta::crear('confirmar_cambio_correo', $solicitud['enlace']['id'], $solicitud['enlace']['token']);
            $enviado = $this->enviar($solicitud['email'], $solicitud['nombre'], 'Confirma tu nuevo correo', 'El correo anterior sigue vigente hasta confirmar este cambio. El enlace vence en 24 horas.', $url);
            return ['success' => $enviado, 'message' => $enviado ? 'Revisa el correo nuevo para confirmar el cambio.' : 'No se pudo enviar el correo. Tu dirección anterior sigue vigente; puedes reintentar.'];
        });
    }

    public function cambiarDocumento(): void
    {
        $this->json(function (): array {
            $usuario = $this->cuenta->cambiarDocumento((int) Contexto::idUsuario(), $_POST);
            $enviado = $this->enviar($usuario['email'], $usuario['nombre_completo'], 'Tu documento fue corregido', 'Se corrigió el documento de tu cuenta. Si no hiciste el cambio, contacta a soporte.');
            return ['success' => true, 'documento' => $_POST['documento'], 'message' => $enviado ? 'Documento actualizado.' : 'Documento actualizado. No pudimos enviar el aviso por correo.'];
        });
    }

    public function enlace(string $proposito): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        $error = null;
        $terminado = false;
        $fila = $this->cuenta->leerEnlace($id, $token, $proposito);
        $persona = $fila !== null ? (new Usuario($this->db))->buscarPorId((int) $fila['id_usuario']) : null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                if ($proposito === 'activacion_personal') {
                    $this->cuenta->activar($id, $token, $_POST, Auditoria::ipCliente());
                } else {
                    $resultado = $this->cuenta->confirmarCorreo($id, $token);
                    $enviado = $this->enviar($resultado['anterior'], $resultado['nombre'], 'Se cambió el correo de tu cuenta', 'Se cambió el correo de tu cuenta Zooki y se retiró la vinculación anterior con Google. Si no lo solicitaste, contacta a soporte.');
                    if (!$enviado) {
                        $error = 'El cambio se aplicó, pero no pudimos enviar el aviso al correo anterior.';
                    }
                }
                $terminado = true;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('D2 enlace de cuenta: ' . $e->getMessage());
                $error = 'No se pudo completar la operación. Inténtalo nuevamente.';
            }
        }
        require __DIR__ . '/../views/auth/enlace_identidad.php';
    }

    public function validar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validarToken((string) ($_POST['cuenta_csrf'] ?? ''), 'cuenta_validacion')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Recarga el formulario para validarlo.']);
            return;
        }
        $usuarios = new Usuario($this->db);
        $campo = (string) ($_POST['campo'] ?? '');
        $valor = (string) ($_POST['valor'] ?? '');
        $id = Contexto::idUsuario();
        $persona = $id !== null ? $usuarios->buscarPorId($id) : null;
        // Al editar personal, solo se excluye de unicidad una persona de la clínica activa.
        $editado = (int) ($_POST['id_usuario'] ?? 0);
        if ($editado > 0 && Contexto::rolActivo() === Roles::ADMIN && Contexto::clinicaActiva() !== null) {
            $personal = $usuarios->personalEnClinica($editado, (int) Contexto::clinicaActiva());
            if ($personal !== null) {
                $id = $editado;
                $persona = $personal;
            }
        }
        if (!empty($_POST['id_enlace'])) {
            $fila = (new VerificacionEmail($this->db))->buscarPorId((int) $_POST['id_enlace']);
            if ($fila !== null && (int) $fila['used'] === 0 && strtotime($fila['expires_at']) > time()
                && password_verify((string) ($_POST['token_enlace'] ?? ''), $fila['token_hash'])) {
                $persona = $usuarios->buscarPorId((int) $fila['id_usuario']);
            }
        }
        if (!empty($_POST['token_id'])) {
            $fila = (new PasswordReset($this->db))->findById((int) $_POST['token_id']);
            if ($fila !== null && (int) $fila['used'] === 0 && strtotime($fila['expires_at']) > time()
                && password_verify((string) ($_POST['token'] ?? ''), $fila['token_hash'])) {
                $persona = $usuarios->buscarPorId((int) $fila['id_usuario']);
            }
        }
        $datosPersonales = [$persona['documento'] ?? '', $persona['nombre_completo'] ?? '', $persona['email'] ?? '',
            (string) ($_POST['documento'] ?? ''), (string) ($_POST['nombre_completo'] ?? ''), (string) ($_POST['email'] ?? '')];
        $error = match ($campo) {
            'documento' => ValidadorCuenta::documento($valor),
            'email' => ValidadorCuenta::correo($valor),
            'telefono' => ValidadorCuenta::telefono($valor),
            'nombre_completo' => ValidadorCuenta::nombre($valor),
            'password' => PoliticaPassword::validar($valor, $datosPersonales),
            default => 'Campo no válido.',
        };
        $existe = false;
        if ($error === null && in_array($campo, ['documento', 'email'], true)) {
            if (!Security::checkVerificationLimit()) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Espera un momento antes de seguir comprobando.']);
                return;
            }
            $existe = $campo === 'email' ? $usuarios->existeEmail(trim($valor), $id) : $usuarios->existeDocumento(trim($valor), $id);
        }
        echo json_encode(['success' => true, 'error' => $error, 'exists' => $existe]);
    }

    private function json(callable $operacion): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            echo json_encode($operacion());
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('D2 identidad: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo completar la operación.']);
        }
    }
}
