<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../models/PropietarioClinica.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/../helpers/Csrf.php';

/**
 * Propietarios desde el personal de la clínica (HU-1.2, RN-109): alta
 * presencial, solicitud y confirmación del vínculo. El portal del
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
            $valor = trim((string) ($entrada[$campo] ?? ''));
            if ($valor === '') return $mensaje;
            $limpios[$campo] = $valor;
        }

        if (!preg_match('/^\d{5,15}$/', $limpios['documento'])) {
            return 'El documento debe tener entre 5 y 15 digitos.';
        }
        if (!filter_var($limpios['email'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo electronico no tiene un formato valido.';
        }
        if (!preg_match('/^[0-9+\s-]{7,20}$/', $limpios['telefono'])) {
            return 'El telefono no tiene un formato valido.';
        }
        if (mb_strlen($limpios['nombre_completo']) < 3 || mb_strlen($limpios['nombre_completo']) > 100) {
            return 'El nombre completo debe tener entre 3 y 100 caracteres.';
        }
        if (!in_array($limpios['tipo_documento'], ['CC', 'CE', 'TI', 'PP', 'NIT'], true)) {
            return 'El tipo de documento no es valido.';
        }

        // RE-1.2.2 / RN-G06: ni documento ni correo duplicados.
        if ($this->usuarioModel->buscarPorDocumento($limpios['documento'])) {
            return 'Ese documento ya esta registrado en el sistema.';
        }
        if ($this->usuarioModel->buscarPorEmail($limpios['email'])) {
            return 'Ese correo electronico ya esta registrado en el sistema.';
        }

        return null;
    }

    public function registrarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        try {
            $error=$this->validarDatosPropietario($_POST,$datos);
            if ($error !== null) throw new InvalidArgumentException($error);
            // RE-T.19.1: el titular acepta en esta pantalla presencial; no el empleado.
            $datos['acepta_politica']=(string)($_POST['acepta_politica'] ?? '');
            $datos['titular_presente']=(string)($_POST['titular_presente'] ?? '');
            EnlaceCuenta::base(); // Validar destino antes de crear cuenta o tokens.
            $alta=(new PropietarioClinica($this->db))->registrar($datos,self::versionPolitica(),Auditoria::ipCliente());
            $enlace=EnlaceCuenta::crear('reset_password',$alta['id_enlace'],$alta['token']);
            $enviado=$this->correo($datos['email'],$datos['nombre_completo'],'Crea tu contraseña en Zooki',
                'Tu cuenta fue registrada con tu aceptación presencial. Crea tu contraseña desde este enlace; vence en 24 horas.',$enlace);
            echo json_encode(['success'=>true,'id_usuario'=>$alta['id_usuario'],'message'=>$enviado
                ? 'Propietario registrado. Se envió el enlace para crear su contraseña.'
                : 'Propietario registrado. No se pudo enviar el enlace; el titular puede solicitarlo desde Recuperar contraseña.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Throwable $e) { error_log('Alta de propietario: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'No se pudo registrar al propietario.']); }
    }

    /** La versión identifica el contenido mostrado; la gestión de versiones llega en D. */
    public static function versionPolitica(): string
    {
        return substr(hash_file('sha256',__DIR__ . '/../views/legal/privacidad.php'),0,20);
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
            $solicitud=(new PropietarioClinica($this->db))->solicitarVinculo((string)($_POST['identificador'] ?? ''));
            $enlace=EnlaceCuenta::crear('confirmar_vinculo_propietario',$solicitud['id_enlace'],$solicitud['token']);
            $enviado=$this->correo($solicitud['email'],$solicitud['nombre'],'Confirma tu vínculo con ' . $solicitud['clinica'],
                'El personal de ' . $solicitud['clinica'] . ' solicita vincular tu cuenta. Confirma solo si elegiste atenderte allí. El enlace vence en 24 horas.',$enlace);
            // Nunca devolver el token ni el enlace al personal que hizo la solicitud.
            echo json_encode(['success'=>$enviado,'message'=>$enviado
                ? 'Se envió la solicitud al propietario. Vuelve a buscarlo cuando confirme.'
                : 'No se pudo enviar el correo. No se creó el vínculo; puedes reintentar.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Throwable $e) { error_log('Solicitud de vínculo: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'No se pudo solicitar la vinculación.']); }
    }

    public function confirmarVinculo(): void
    {
        $id=(int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $token=(string)($_POST['token'] ?? $_GET['token'] ?? '');
        $resultado=null;
        // GET no consume el enlace: los filtros de correo suelen abrirlo automáticamente.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resultado=(new PropietarioClinica($this->db))->confirmarVinculo($id,$token);
        }
        require __DIR__ . '/../views/auth/confirmar_vinculo.php';
    }
}
