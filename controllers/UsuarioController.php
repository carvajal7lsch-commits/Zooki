<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';

class UsuarioController {
    private $db;
    private $usuario;
    private $auditoria;

    /**
     * La conexion es opcional: el router lo instancia sin argumentos y se
     * crea la conexion real de siempre. En pruebas se puede inyectar un PDO
     * propio (SQLite en memoria) para ejercitar los endpoints sin tocar la
     * base de datos real.
     */
    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $db = $database->getConnection();
        }
        $this->db = $db;
        $this->usuario = new Usuario($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    /**
     * Listar todos los usuarios para la vista de admin.
     *
     * T-17: el control de rol ya lo aplico Security::validateRole() sobre la
     * matriz de autorizacion, con el id numerico. La comprobacion que habia
     * aqui lo repetia comparando la cadena 'administrador', que ademas fallaba
     * en las sesiones de Google donde ese campo no siempre se guardaba.
     */
    public function listar() {
        return $this->usuario->getAll();
    }

    /**
     * Valida y normaliza los datos de un usuario que llegan por POST.
     *
     * T-14 — Antes los campos se leian directo de $_POST sin comprobar que
     * existieran ni que tuvieran forma valida: solo validaba el formulario, es
     * decir la unica capa que un atacante no ejecuta. Devuelve el mensaje de
     * error, o null si todo esta correcto, y deja los valores ya limpios en
     * $limpios.
     */
    private function validarDatosUsuario(array $entrada, ?array &$limpios): ?string {
        $limpios = [];

        $requeridos = [
            'documento'       => 'El documento es obligatorio.',
            'tipo_documento'  => 'El tipo de documento es obligatorio.',
            'nombre_completo' => 'El nombre completo es obligatorio.',
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
        if (mb_strlen($limpios['nombre_completo']) < 3 || mb_strlen($limpios['nombre_completo']) > 100) {
            return 'El nombre completo debe tener entre 3 y 100 caracteres.';
        }
        if (!in_array($limpios['tipo_documento'], ['CC', 'CE', 'TI', 'PP', 'NIT'], true)) {
            return 'El tipo de documento no es valido.';
        }

        $telefono = trim((string) ($entrada['telefono'] ?? ''));
        if ($telefono !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $telefono)) {
            return 'El telefono no tiene un formato valido.';
        }
        $limpios['telefono'] = $telefono;

        if (!$this->usuario->esRolValido($entrada['id_rol'] ?? null)) {
            return 'El rol indicado no es valido.';
        }
        $limpios['id_rol'] = (int) $entrada['id_rol'];

        $estado = $entrada['estado'] ?? 1;
        if (!in_array((int) $estado, [0, 1], true)) {
            return 'El estado indicado no es valido.';
        }
        $limpios['estado'] = (int) $estado;

        return null;
    }

    // Obtener roles para el formulario
    public function getRoles() {
        return $this->usuario->getRoles();
    }

    // Registrar un nuevo usuario (AJAX)
    public function registrarAjax() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // T-14 / VD-SEG-01: obligatoriedad, formato y rol validos antes
                // de tocar la base de datos.
                $error = $this->validarDatosUsuario($_POST, $datos);
                if ($error !== null) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }

                $documento = $datos['documento'];
                $email = $datos['email'];

                // Verificar si el documento ya existe
                $existingDoc = $this->usuario->getById($documento);
                if ($existingDoc) {
                    echo json_encode(['success' => false, 'message' => 'El documento ya está registrado en el sistema.']);
                    exit;
                }

                // Verificar si el email ya existe
                $existingEmail = $this->usuario->getUserByEmail($email);
                if ($existingEmail) {
                    echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado en el sistema.']);
                    exit;
                }

                // HU-36 (VD-SEG-07). Si el administrador escribe una contraseña
                // debe cumplir la política; si la deja vacía se genera una
                // temporal aleatoria. Antes el valor por defecto era '12345'
                // fijo: cualquiera que conociera el patrón podía entrar como el
                // usuario recién creado antes de que este iniciara sesión.
                if (isset($_POST['password']) && $_POST['password'] !== '') {
                    $motivo = PoliticaPassword::validar($_POST['password'], [
                        $documento,
                        $datos['nombre_completo'],
                        $email,
                    ]);
                    if ($motivo !== null) {
                        echo json_encode(['success' => false, 'message' => $motivo]);
                        exit;
                    }
                    $password = $_POST['password'];
                } else {
                    $password = self::generarPasswordTemporal();
                }

                $data = $datos + [
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'debe_cambiar_password' => 1
                ];

                if ($this->usuario->create($data)) {
                    // Auditoría: usuario creado
                    $adminDoc = $_SESSION['usuario_doc'] ?? 'sistema';
                    $this->auditoria->log($adminDoc, 'INSERT', 'usuarios', $documento, null, [
                        'nombre_completo' => $datos['nombre_completo'],
                        'email' => $email,
                        'id_rol' => $datos['id_rol']
                    ], 'Usuario creado');

                    // Enviar correo con credenciales. Se reutiliza la misma
                    // variable con la que se creó el hash, para que nunca se
                    // envíe una contraseña distinta de la que quedó guardada.
                    $emailService = new EmailService();
                    $enviado = $emailService->enviarCredencialesUsuario($email, $datos['nombre_completo'], $documento, $password);
                    
                    if ($enviado) {
                        echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente. Se han enviado las credenciales al correo registrado.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente. No se pudo enviar el correo con las credenciales, pero el usuario fue creado correctamente.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al crear el usuario.']);
                }
            }
        } catch (Exception $e) {
            // T-04: el detalle tecnico va al log del servidor, no al navegador.
            error_log('Error al crear usuario: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo crear el usuario. Intenta nuevamente.']);
        }
    }

    // Actualizar usuario (AJAX)
    public function actualizarAjax() {
        try {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $original_doc = trim((string) ($_POST['original_doc'] ?? ''));
                if ($original_doc === '') {
                    echo json_encode(['success' => false, 'message' => 'Falta el usuario a modificar.']);
                    exit;
                }

                // Si el documento está vacío (porque el input está deshabilitado), usar el original
                $entrada = $_POST;
                if (trim((string) ($entrada['documento'] ?? '')) === '') {
                    $entrada['documento'] = $original_doc;
                }

                // T-14 / VD-SEG-01: obligatoriedad, formato y rol validos.
                $error = $this->validarDatosUsuario($entrada, $datos);
                if ($error !== null) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }

                $documento = $datos['documento'];
                $email = $datos['email'];

                // VD-SEG-02: no permitir que el ultimo admin activo pierda el
                // rol o quede inactivo; dejaria el sistema sin administracion.
                $quitaAdmin = $datos['id_rol'] !== 1;
                $desactiva  = $datos['estado'] !== 1;
                if (($quitaAdmin || $desactiva) && $this->usuario->esUltimoAdminActivo($original_doc)) {
                    echo json_encode(['success' => false, 'message' => 'No se puede quitar el rol ni desactivar al unico administrador activo. Asigna primero otro administrador.']);
                    exit;
                }

                // Verificar si el email ya existe (excluyendo el usuario actual)
                $existingEmail = $this->usuario->getUserByEmailExcluding($email, $original_doc);
                if ($existingEmail) {
                    echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado en el sistema.']);
                    exit;
                }

                // T-11: se lee el estado previo para que la auditoria registre
                // datos anteriores reales y el cambio sea reconstruible.
                $anterior = $this->usuario->getById($original_doc);

                $data = $datos + ['original_doc' => $original_doc];

                if ($this->usuario->update($data)) {
                    // Auditoría: usuario actualizado
                    $adminDoc = $_SESSION['usuario_doc'] ?? 'sistema';
                    $this->auditoria->log($adminDoc, 'UPDATE', 'usuarios', $documento, $anterior ?: ['original_doc' => $original_doc], [
                        'documento' => $documento,
                        'nombre_completo' => $datos['nombre_completo'],
                        'email' => $email,
                        'id_rol' => $datos['id_rol'],
                        'estado' => $datos['estado']
                    ], 'Usuario actualizado');
                    echo json_encode(['success' => true, 'message' => 'Usuario actualizado.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar.']);
                }
            }
        } catch (PDOException $e) {
            // T-04: antes esta rama devolvia al navegador el mensaje SQL crudo
            // y una cadena "Debug:" con los documentos implicados.
            error_log('Error al actualizar usuario: ' . $e->getMessage());
            if (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
                echo json_encode(['success' => false, 'message' => 'No se puede cambiar el documento: el usuario ya tiene registros asociados en el sistema.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el usuario. Intenta nuevamente.']);
            }
        } catch (Exception $e) {
            error_log('Error al actualizar usuario: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el usuario. Intenta nuevamente.']);
        }
    }

    /**
     * Obtener un usuario por documento (AJAX).
     *
     * T-03/T-19: getById() ya no arrastra la columna `password`, y se quitaron
     * los error_log de depuracion que volcaban el registro completo del usuario
     * (hash incluido) al log del servidor.
     */
    public function getUsuarioAjax() {
        try {
            $documento = trim((string) ($_GET['documento'] ?? ''));
            if ($documento === '') {
                echo json_encode(['success' => false, 'message' => 'Documento no indicado.']);
                return;
            }

            $u = $this->usuario->getById($documento);
            if (!$u) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
                return;
            }

            echo json_encode($u);
        } catch (Exception $e) {
            // T-04: mensaje generico al cliente, detalle al log.
            error_log('Error al consultar usuario: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo consultar el usuario.']);
        }
    }

    // Cambiar estado del usuario (AJAX)
    public function cambiarEstadoAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['documento']) && isset($_POST['estado'])) {
                $doc = $_POST['documento'];
                $est = $_POST['estado'];

                // VD-SEG-02: desactivar al unico administrador activo dejaria
                // el sistema sin ningun acceso administrativo.
                if ((int) $est !== 1 && $this->usuario->esUltimoAdminActivo($doc)) {
                    echo json_encode(['success' => false, 'message' => 'No se puede desactivar al unico administrador activo. Asigna primero otro administrador.']);
                    exit;
                }

                // T-11: el estado previo se consulta antes de escribir, en vez
                // de registrar la cadena 'desconocido' que dejaba el log de
                // auditoria inservible para reconstruir el cambio (HU-24).
                $anterior = $this->usuario->getById($doc);
                if (!$anterior) {
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
                    exit;
                }

                if ($this->usuario->updateStatus($doc, $est)) {
                    // Auditoría: cambio de estado
                    $adminDoc = $_SESSION['usuario_doc'] ?? 'sistema';
                    $this->auditoria->log(
                        $adminDoc,
                        'UPDATE',
                        'usuarios',
                        $doc,
                        ['estado' => (int) $anterior['estado']],
                        ['estado' => (int) $est],
                        'Estado de usuario cambiado a ' . ((int) $est === 1 ? 'activo' : 'inactivo')
                    );
                    echo json_encode(['success' => true, 'message' => 'Estado actualizado exitosamente.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado.']);
                }
            }
        }
    }

    /**
     * HU-54 — Restablecer la contrasena de un usuario desde el panel.
     *
     * Genera una contrasena temporal que cumple la politica (RN-G10), la envia
     * al correo del usuario y marca `debe_cambiar_password`, de modo que
     * Security::validatePasswordTemporal() lo obliga a cambiarla en su
     * siguiente ingreso antes de poder usar el sistema (criterio "El usuario
     * debe cambiarla en su proximo ingreso").
     *
     * El acceso queda restringido al administrador por la matriz de
     * autorizacion, y la accion se registra en auditoria (RN-G05).
     */
    public function resetearPasswordAjax() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metodo no permitido.']);
            return;
        }

        try {
            $documento = trim((string) ($_POST['documento'] ?? ''));
            if ($documento === '') {
                echo json_encode(['success' => false, 'message' => 'Documento no indicado.']);
                return;
            }

            $usuario = $this->usuario->getById($documento);
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
                return;
            }

            // Un administrador no se restablece a si mismo por esta via: para
            // eso esta el cambio de contrasena de su propio perfil, que si pide
            // la contrasena actual.
            if ($documento === ($_SESSION['usuario_doc'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Para cambiar tu propia contrasena usa la opcion de tu perfil.']);
                return;
            }

            $temporal = self::generarPasswordTemporal();

            if (!$this->usuario->updatePassword($documento, password_hash($temporal, PASSWORD_DEFAULT))) {
                echo json_encode(['success' => false, 'message' => 'No se pudo restablecer la contrasena.']);
                return;
            }

            $this->usuario->updateDebeCambiarPassword($documento, 1);

            $this->auditoria->log(
                $_SESSION['usuario_doc'] ?? 'sistema',
                'UPDATE',
                'usuarios',
                $documento,
                null,
                ['debe_cambiar_password' => 1],
                'Contrasena restablecida por el administrador'
            );

            $emailService = new EmailService();
            $enviado = $emailService->enviarCredencialesUsuario(
                $usuario['email'],
                $usuario['nombre_completo'],
                $documento,
                $temporal
            );

            echo json_encode([
                'success' => true,
                'message' => $enviado
                    ? 'Contrasena restablecida. Se envio la clave temporal al correo del usuario.'
                    : 'Contrasena restablecida, pero no se pudo enviar el correo. Comunicasela al usuario por otro medio.',
            ]);
        } catch (Exception $e) {
            // T-04: detalle al log, mensaje generico al cliente.
            error_log('Error al restablecer contrasena: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudo restablecer la contrasena. Intenta nuevamente.']);
        }
    }

    // Verificar si documento ya existe (AJAX)
    public function verificarDocumentoAjax() {
        $documento = $_GET['documento'] ?? '';
        $exclude_doc = $_GET['exclude_doc'] ?? '';
        
        if ($exclude_doc && $documento == $exclude_doc) {
            // Es el mismo documento, no está duplicado
            echo json_encode(['exists' => false]);
            return;
        }
        
        $existing = $this->usuario->getById($documento);
        echo json_encode(['exists' => $existing !== false]);
    }

    // Verificar si email ya existe (AJAX)
    public function verificarEmailAjax() {
        $email = $_GET['email'] ?? '';
        $exclude_doc = $_GET['exclude_doc'] ?? '';
        
        if ($exclude_doc) {
            $existing = $this->usuario->getUserByEmailExcluding($email, $exclude_doc);
        } else {
            $existing = $this->usuario->getUserByEmail($email);
        }
        
        echo json_encode(['exists' => $existing !== false]);
    }

    /**
     * Contrasena temporal aleatoria. Delega en PoliticaPassword, que es donde
     * vive ahora (la necesitan tambien el alta de propietarios). Se conserva
     * el nombre para no tocar los llamadores.
     */
    private static function generarPasswordTemporal(): string
    {
        return PoliticaPassword::generarTemporal();
    }

}
