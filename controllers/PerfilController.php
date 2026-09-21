<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';

/**
 * HU-42 — Ver y actualizar mi perfil y datos de contacto.
 *
 * Responsabilidad unica: la cuenta del usuario que esta en sesion. No gestiona
 * usuarios ajenos; eso es de UsuarioController, que exige rol administrador.
 * Aqui el sujeto siempre sale de $_SESSION y nunca del POST, asi que no hay
 * forma de apuntar a la cuenta de otra persona (RN-G02).
 *
 * Sirve a los cuatro roles: hasta ahora solo el propietario podia editar sus
 * datos de contacto, desde su portal, y administrador, veterinario y
 * recepcionista no tenian ninguna vista de perfil.
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
     * HU-42: miembro desde, acceso anterior, intentos fallidos de los últimos
     * 30 días y actividad reciente, todo en hora de la clínica. Si la
     * auditoría falla, el perfil se muestra igual, sin esa sección.
     */
    private function resumenDeCuenta(string $documento): array
    {
        require_once __DIR__ . '/../helpers/ActividadCuenta.php';
        $ahora = new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
        $resumen = ['miembro_desde' => null, 'acceso_anterior' => null, 'fallidos_30' => 0, 'actividad' => [], 'disponible' => false];

        try {
            $desfase = ActividadCuenta::normalizarDesfase(
                (string) $this->db->query("SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP())")->fetchColumn()
            );

            $registro = $this->usuario->getFechaRegistro($documento);
            if ($registro) {
                $resumen['miembro_desde'] = ActividadCuenta::mesYAnio(ActividadCuenta::aHoraClinica($registro, $desfase));
            }

            $accesos = 0;
            foreach ($this->auditoria->actividadDeCuenta($documento, 8) as $fila) {
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
            $resumen['fallidos_30'] = $this->auditoria->contarAccesosFallidos($documento, $desdeBd);
            $resumen['disponible'] = true;
        } catch (Throwable $e) {
            error_log('Perfil: no se pudo leer la actividad de la cuenta: ' . $e->getMessage());
        }

        return $resumen;
    }

    /** Documento del usuario en sesion, o null si no hay sesion. */
    private function documentoEnSesion(): ?string
    {
        $doc = $_SESSION['usuario_doc'] ?? '';

        return $doc !== '' ? (string) $doc : null;
    }

    /**
     * Actualiza los datos de contacto propios: correo y telefono.
     *
     * El documento, el nombre y el rol no se tocan aqui a proposito. El rol lo
     * asigna el administrador (RN-501) y dejarlo editable seria justo la
     * escalada de privilegios que corrigio HU-33; el documento identifica al
     * usuario en toda la base y el nombre es un dato de la clinica.
     */
    public function actualizarAjax(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metodo no permitido.']);
            return;
        }

        $documento = $this->documentoEnSesion();
        if ($documento === null) {
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

            // RN-G06: el correo es unico en el sistema.
            if ($this->usuario->getUserByEmailExcluding($email, $documento)) {
                echo json_encode(['success' => false, 'message' => 'Ese correo ya esta registrado por otro usuario.']);
                return;
            }

            $anterior = $this->usuario->getById($documento);
            if (!$anterior) {
                echo json_encode(['success' => false, 'message' => 'No se encontro tu perfil.']);
                return;
            }

            $actualizado = $this->usuario->update([
                'documento'       => $documento,
                'original_doc'    => $documento,
                'tipo_documento'  => $anterior['tipo_documento'],
                'nombre_completo' => $anterior['nombre_completo'],
                'telefono'        => $telefono,
                'email'           => $email,
                'id_rol'          => $anterior['id_rol'],
                'estado'          => $anterior['estado'],
            ]);

            if (!$actualizado) {
                echo json_encode(['success' => false, 'message' => 'No se pudieron guardar los cambios.']);
                return;
            }

            // RN-G05: toda edicion queda registrada con datos previos y nuevos.
            $this->auditoria->log(
                $documento,
                'UPDATE',
                'usuarios',
                $documento,
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
     * Panel "Mi perfil" del personal (HU-42 y HU-39): datos de la cuenta,
     * contacto editable y cambio de contraseña. Se pinta dentro del layout del
     * rol; el propietario tiene su propio perfil en el portal.
     */
    public function mostrar(): void
    {
        $layouts = [1 => 'admin', 2 => 'vet', 3 => 'reception'];
        $roles   = [1 => 'Administrador', 2 => 'Veterinario', 3 => 'Recepcionista'];
        $idRol   = (int) ($_SESSION['usuario_id_rol'] ?? 0);
        $documento = $this->documentoEnSesion();

        $perfil = ($documento !== null && isset($layouts[$idRol])) ? $this->usuario->getById($documento) : null;
        if (!$perfil) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $rolNombre    = $roles[$idRol];
        $cuentaGoogle = ($_SESSION['login_method'] ?? 'password') === 'google';
        // HU-39: sin contraseña conocida (cuenta creada con Google) no se pide la actual.
        $pideActual   = (int) ($perfil['password_definida'] ?? 1) === 1;
        $cuenta       = $this->resumenDeCuenta($documento);
        $content_view = __DIR__ . '/../views/perfil/index.php';
        require __DIR__ . '/../views/' . $layouts[$idRol] . '/layout.php';
    }
}
