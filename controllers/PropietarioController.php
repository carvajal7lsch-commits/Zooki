<?php
require_once __DIR__ . '/../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../models/PropietarioClinica.php';
require_once __DIR__ . '/../models/VerificacionEmail.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/**
 * Propietarios desde el personal de la clínica (HU-1.2, RN-109): alta con
 * activación por enlace (M5 C3), solicitud y confirmación del vínculo. El portal del
 * propietario está en PortalController (C6).
 */
class PropietarioController {
    private $db;
    private $usuarioModel;

    public function __construct(?PDO $db = null, private $enviarCorreo = null) {
        $this->db = $db ?? (new Database())->getConnection();
        $this->usuarioModel = new Usuario($this->db);
    }

    /**
     * Valida los datos de alta de un propietario (HU-1.2).
     *
     * M1-03 — Antes no se comprobaba nada: los campos se leian directo de
     * $_POST y el documento o el correo duplicados llegaban hasta la base de
     * datos, donde la restriccion de unicidad lanzaba una PDOException sin
     * capturar. El resultado era una respuesta vacia que rompia el `.json()`
     * del navegador, sin decirle al usuario que el propietario ya existia.
     *
     * Devuelve el mensaje de error, o null si todo esta correcto.
     */
    private function validarDatosPropietario(array $entrada, ?array &$limpios): ?string {
        $limpios = [];

        $requeridos = [
            'documento'       => 'El documento es obligatorio.',
            'tipo_documento'  => 'El tipo de documento es obligatorio.',
            'nombre_completo' => 'El nombre completo es obligatorio.',
            'telefono'        => 'El telefono es obligatorio.',
            'email'           => 'El correo electronico es obligatorio.',
        ];

        foreach ($requeridos as $campo => $mensaje) {
            $valor = ValidadorCuenta::normalizar($campo, (string) ($entrada[$campo] ?? ''));
            if ($valor === '') return $mensaje;
            $limpios[$campo] = $valor;
        }

        if (ValidadorCuenta::documento($limpios['documento']) !== null) {
            return 'El documento debe tener entre 5 y 15 digitos.';
        }
        // M5 C4b: el aviso dice qué le falta o que es un correo temporal (RE-0.1.5).
        $motivoCorreo = ValidadorCuenta::correoDeCuenta($limpios['email']);
        if ($motivoCorreo !== null) {
            return $motivoCorreo;
        }
        // D1: la misma regla de teléfono en todo el sistema (ValidadorTelefono).
        $limpios['telefono'] = ValidadorTelefono::normalizar($limpios['telefono']);
        if (!ValidadorTelefono::esValido($limpios['telefono'])) {
            return ValidadorTelefono::MENSAJE;
        }
        if (ValidadorCuenta::nombre($limpios['nombre_completo']) !== null) {
            return ValidadorCuenta::nombre($limpios['nombre_completo']);
        }
        if (!in_array($limpios['tipo_documento'], ['CC', 'CE', 'TI', 'PP', 'NIT'], true)) {
            return 'El tipo de documento no es valido.';
        }

        return null;
    }

    /**
     * HU-1.2 y RE-T.19.1 (decisión del usuario, M5 C3): la clínica registra al
     * propietario sin aceptación presencial; el titular recibe el enlace de
     * 72 horas para aceptar la política y crear su contraseña. Si el documento
     * o el correo ya tienen cuenta, no se duplica: la respuesta es la de la
     * búsqueda exacta y la clínica pide la confirmación del vínculo (RN-109).
     */
    public function registrarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        try {
            $error = $this->validarDatosPropietario($_POST, $datos);
            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }
            EnlaceCuenta::base(); // Validar destino antes de crear cuenta o tokens.
            $modelo = new PropietarioClinica($this->db);
            $existente = $modelo->existente($datos['documento'], $datos['email']);
            if ($existente !== null) {
                http_response_code(409);
                echo json_encode(['success' => false, 'existe' => true] + $existente + ['message' => $existente['vinculado']
                    ? PropietarioClinica::MENSAJE_YA_CLIENTE
                    : PropietarioClinica::MENSAJE_SIN_VINCULO]);
                return;
            }
            $alta = $modelo->registrar($datos);
            $enviado = $this->enviarActivacion($datos['email'], $datos['nombre_completo'], (string) Contexto::actual()['clinica'], $alta);
            echo json_encode(['success' => true, 'id_usuario' => $alta['id_usuario'], 'message' => $enviado
                ? 'Propietario registrado. Le enviamos el enlace para activar su cuenta (72 horas).'
                : 'Propietario registrado. No se pudo enviar el enlace; reenvíalo desde su ficha.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        catch (Throwable $e) { error_log('Alta de propietario: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'message' => 'No se pudo registrar al propietario.']); }
    }

    /** M5 C3: reenviar el enlace de activación; el anterior deja de servir. */
    public function reenviarActivacionAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        try {
            EnlaceCuenta::base();
            $idPersona = ValidadorClinico::id($_POST['id_usuario'] ?? null);
            if ($idPersona === null) {
                throw new InvalidArgumentException('Cliente no válido.');
            }
            $enlace = (new PropietarioClinica($this->db))->reenviarActivacion($idPersona);
            $enviado = $this->enviarActivacion($enlace['email'], $enlace['nombre'], (string) $enlace['clinica'], $enlace);
            echo json_encode(['success' => $enviado, 'message' => $enviado
                ? 'Le enviamos un enlace nuevo; vence en 72 horas.'
                : 'No se pudo enviar el correo. Inténtalo de nuevo.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        catch (Throwable $e) { error_log('Reenvío de activación: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'message' => 'No se pudo reenviar el enlace.']); }
    }

    /** El único correo que recibe un propietario mientras no active su cuenta (el mismo texto en ActivacionPropietario). */
    private function enviarActivacion(string $email, string $nombre, string $clinica, array $enlace): bool
    {
        $url = EnlaceCuenta::crear('activar_propietario', (int) $enlace['id'], (string) $enlace['token']);
        $html = ActivacionPropietario::correoHtml($clinica, $url);
        if ($this->enviarCorreo !== null) return (bool) ($this->enviarCorreo)($email, $nombre, ActivacionPropietario::ASUNTO, $html);
        return (new EmailService())->enviarCorreoPersonalizado($email, $nombre, ActivacionPropietario::ASUNTO, $html);
    }

    private function correo(string $email,string $nombre,string $asunto,string $mensaje,string $enlace): bool
    {
        $html='<p>' . htmlspecialchars($mensaje,ENT_QUOTES,'UTF-8') . '</p><p><a href="' .
            htmlspecialchars($enlace,ENT_QUOTES,'UTF-8') . '">Continuar</a></p>';
        if ($this->enviarCorreo !== null) return (bool)($this->enviarCorreo)($email,$nombre,$asunto,$html);
        return (new EmailService())->enviarCorreoPersonalizado($email,$nombre,$asunto,$html);
    }

    public function solicitarVinculoAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        try {
            EnlaceCuenta::base();
            $modelo = new PropietarioClinica($this->db);
            // RN-115: desde Clientes se reactiva por el id, porque la clínica ya no ve el correo.
            $idPersona = ValidadorClinico::id($_POST['id_usuario'] ?? null);
            $solicitud = $idPersona !== null
                ? $modelo->solicitarVinculoDe($idPersona)
                : $modelo->solicitarVinculo((string) ($_POST['identificador'] ?? ''));
            // RN-G13: sin solicitud (cuenta de plataforma) se responde igual, sin enviar nada.
            $enviado = $solicitud === null || $this->enviarSolicitud($solicitud);
            // Nunca devolver el token ni el enlace al personal que hizo la solicitud.
            echo json_encode(['success'=>$enviado,'message'=>$enviado
                ? 'Se envió la solicitud al propietario. Vuelve a buscarlo cuando confirme.'
                : 'No se pudo enviar el correo. No se creó el vínculo; puedes reintentar.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Throwable $e) { error_log('Solicitud de vínculo: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'No se pudo solicitar la vinculación.']); }
    }

    private function enviarSolicitud(array $solicitud): bool
    {
        $enlace = EnlaceCuenta::crear('confirmar_vinculo_propietario', $solicitud['id_enlace'], $solicitud['token']);
        $asunto = 'Confirma tu vínculo con ' . $solicitud['clinica'];
        $texto = 'El personal de ' . $solicitud['clinica'] . ' solicita vincular tu cuenta. Confirma solo si elegiste atenderte allí. El enlace vence en 24 horas.';
        return $this->correo($solicitud['email'], $solicitud['nombre'], $asunto, $texto, $enlace);
    }

    public function confirmarVinculo(): void
    {
        $id=(int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $token=(string)($_POST['token'] ?? $_GET['token'] ?? '');
        $resultado=null;
        // GET no consume el enlace: los filtros de correo suelen abrirlo automáticamente.
        $pendiente=false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resultado=(new PropietarioClinica($this->db))->confirmarVinculo($id,$token);
            // M5 C3: si su cuenta aún no se activa, la página se lo recuerda.
            $fila=$resultado ? (new VerificacionEmail($this->db))->buscarPorId($id) : null;
            $pendiente=$fila !== null && (new ActivacionPropietario($this->db))->esPendiente((int) $fila['id_usuario']);
        }
        require __DIR__ . '/../views/auth/confirmar_vinculo.php';
    }
}
