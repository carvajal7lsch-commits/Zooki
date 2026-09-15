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

    /** Documento del usuario en sesion, o null si no hay sesion. */
    private function documentoEnSesion(): ?string
    {
        $doc = $_SESSION['usuario_doc'] ?? '';

        return $doc !== '' ? (string) $doc : null;
    }

    /** Devuelve los datos de la cuenta propia. */
    public function verAjax(): void
    {
        header('Content-Type: application/json');

        $documento = $this->documentoEnSesion();
        if ($documento === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesion expirada.']);
            return;
        }

        try {
            $datos = $this->usuario->getById($documento);
            if (!$datos) {
                echo json_encode(['success' => false, 'message' => 'No se encontro tu perfil.']);
                return;
            }

            echo json_encode([
                'success' => true,
                'perfil' => [
                    'documento'       => $datos['documento'],
                    'tipo_documento'  => $datos['tipo_documento'],
                    'nombre_completo' => $datos['nombre_completo'],
                    'email'           => $datos['email'],
                    'telefono'        => $datos['telefono'],
                    'rol'             => $_SESSION['usuario_rol'] ?? '',
                ],
            ]);
        } catch (Exception $e) {
            error_log('Error al consultar perfil: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo cargar tu perfil.']);
        }
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
        $content_view = __DIR__ . '/../views/perfil/index.php';
        require __DIR__ . '/../views/' . $layouts[$idRol] . '/layout.php';
    }
}
