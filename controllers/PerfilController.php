<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/Roles.php';

/**
 * HU-T.5 — Ver y actualizar mi perfil y datos de contacto.
 *
 * Responsabilidad unica: la cuenta de la persona que esta en sesion. No
 * gestiona cuentas ajenas; eso es de UsuarioController, que exige rol
 * administrador. Aqui el sujeto siempre es el id_usuario de la sesion y nunca
 * sale del POST, asi que no hay forma de apuntar a la cuenta de otra persona
 * (RN-G02).
 */
class PerfilController
{
    private $db;
    private $usuario;
    private $auditoria;

    /** La conexion es inyectable para poder probar el controlador. */
    public function __construct($db = null)
    {
        if ($db === null) {
            $database = new Database();
            $db = $database->getConnection();
        }
        $this->db = $db;
        $this->usuario = new Usuario($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    /**
     * HU-T.5: miembro desde, acceso anterior, intentos fallidos de los últimos
     * 30 días y actividad reciente, todo en hora de la clínica. Si la
     * auditoría falla, el perfil se muestra igual, sin esa sección.
     */
    private function resumenDeCuenta(int $idUsuario): array
    {
        require_once __DIR__ . '/../helpers/ActividadCuenta.php';
        $ahora = new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
        $resumen = ['miembro_desde' => null, 'acceso_anterior' => null, 'fallidos_30' => 0, 'actividad' => [], 'disponible' => false];

        try {
            $desfase = ActividadCuenta::normalizarDesfase(
                (string) $this->db->query("SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP())")->fetchColumn()
            );

            $registro = $this->usuario->getFechaRegistro($idUsuario);
            if ($registro) {
                $resumen['miembro_desde'] = ActividadCuenta::mesYAnio(ActividadCuenta::aHoraClinica($registro, $desfase));
            }

            $accesos = 0;
            foreach ($this->auditoria->actividadDeCuenta($idUsuario, 8) as $fila) {
                $fecha = ActividadCuenta::aHoraClinica($fila['fecha_hora'], $desfase);
                $item = ActividadCuenta::describir($fila) + [
                    'momento' => ActividadCuenta::momento($fecha, $ahora),
                    'ip' => $fila['ip_address'] ?? '',
                ];
                // El acceso más reciente es la sesión actual; el anterior es el segundo.
                if ($item['tipo'] === 'acceso' && ++$accesos === 2) {
                    $resumen['acceso_anterior'] = $item['momento'];
                }
                $resumen['actividad'][] = $item;
            }

            $desdeBd = $ahora->modify('-30 days')->setTimezone(new DateTimeZone($desfase))->format('Y-m-d H:i:s');
            $resumen['fallidos_30'] = $this->auditoria->contarAccesosFallidos($idUsuario, $desdeBd);
            $resumen['disponible'] = true;
        } catch (Throwable $e) {
            error_log('Perfil: no se pudo leer la actividad de la cuenta: ' . $e->getMessage());
        }

        return $resumen;
    }

    /**
     * Actualiza los datos de contacto propios: correo y telefono.
     *
     * El documento y el nombre no se tocan aqui a proposito: los corrige el
     * administrador de la clinica, y la correccion por el propio titular con
     * verificacion llega con RE-T.5.7 y RE-T.5.8. El rol tampoco: es del
     * contexto, no de la persona.
     */
    public function actualizarAjax(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metodo no permitido.']);
            return;
        }

        $idUsuario = Contexto::idUsuario();
        if ($idUsuario === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesion expirada.']);
            return;
        }

        try {
            $email    = trim((string) ($_POST['email'] ?? ''));
            $telefono = trim((string) ($_POST['telefono'] ?? ''));

            if ($email === '') {
                echo json_encode(['success' => false, 'message' => 'El correo electronico es obligatorio.']);
                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'El correo electronico no tiene un formato valido.']);
                return;
            }
            if ($telefono !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $telefono)) {
                echo json_encode(['success' => false, 'message' => 'El telefono no tiene un formato valido.']);
                return;
            }

            // RN-G06: el correo es unico en la plataforma (RE-T.5.3).
            if ($this->usuario->existeEmail($email, $idUsuario)) {
                echo json_encode(['success' => false, 'message' => 'Ese correo ya esta registrado por otro usuario.']);
                return;
            }

            $anterior = $this->usuario->buscarPorId($idUsuario);
            if (!$anterior) {
                echo json_encode(['success' => false, 'message' => 'No se encontro tu perfil.']);
                return;
            }

            if (!$this->usuario->actualizarContacto($idUsuario, $email, $telefono)) {
                echo json_encode(['success' => false, 'message' => 'No se pudieron guardar los cambios.']);
                return;
            }

            // RN-G05: toda edicion queda registrada con datos previos y nuevos.
            $this->auditoria->log(
                $idUsuario,
                'UPDATE',
                'usuarios',
                $idUsuario,
                ['email' => $anterior['email'], 'telefono' => $anterior['telefono']],
                ['email' => $email, 'telefono' => $telefono],
                'Datos de contacto actualizados por el propio usuario'
            );

            echo json_encode(['success' => true, 'message' => 'Datos de contacto actualizados.']);
        } catch (Exception $e) {
            error_log('Error al actualizar perfil: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudieron guardar los cambios. Intenta nuevamente.']);
        }
    }

    /**
     * Panel "Mi perfil" del personal (HU-T.5 y HU-T.2): datos de la cuenta,
     * contacto editable y cambio de contraseña. Se pinta dentro del layout del
     * rol del contexto activo; el propietario tiene su perfil en el portal.
     */
    public function mostrar(): void
    {
        $layouts = [Roles::ADMIN => 'admin', Roles::VETERINARIO => 'vet'];
        $idRol = (int) Contexto::rolActivo();
        $idUsuario = Contexto::idUsuario();

        $perfil = ($idUsuario !== null && isset($layouts[$idRol])) ? $this->usuario->buscarPorId($idUsuario) : null;
        if (!$perfil) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $rolNombre    = Roles::nombre($idRol);
        $cuentaGoogle = (int) $perfil['tiene_google'] === 1 || ($_SESSION['login_method'] ?? 'password') === 'google';
        // HU-39: sin contraseña (cuenta creada con Google, password NULL) no se pide la actual.
        $pideActual   = (int) $perfil['tiene_password'] === 1;
        $cuenta       = $this->resumenDeCuenta($idUsuario);
        $content_view = __DIR__ . '/../views/perfil/index.php';
        require __DIR__ . '/../views/' . $layouts[$idRol] . '/layout.php';
    }
}
