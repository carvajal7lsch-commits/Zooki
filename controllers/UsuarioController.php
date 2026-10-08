<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/Security.php';

/**
 * HU-T.7 — Personal de la clínica activa (administradores y veterinarios).
 *
 * Todo ocurre dentro de la clínica del contexto: la persona se identifica por
 * id_usuario y su rol vive en usuario_clinica. Una persona que no es personal
 * de esta clínica es un recurso ajeno: 403 y auditoría (RN-G13). La matriz de
 * Security ya exige el rol administrador (RE-T.7.2).
 */
class UsuarioController {
    private $db;
    private Usuario $usuario;
    private Auditoria $auditoria;
    private $emailService;

    /**
     * La conexion y el servicio de correo son inyectables: el router los crea
     * de verdad y las pruebas pasan un PDO en memoria y un correo falso.
     */
    public function __construct($db = null, $emailService = null) {
        if ($db === null) {
            $database = new Database();
            $db = $database->getConnection();
        }
        $this->db = $db;
        $this->usuario = new Usuario($this->db);
        $this->auditoria = new Auditoria($this->db);
        $this->emailService = $emailService;
    }

    private function clinica(): int {
        return (int) Contexto::clinicaActiva();
    }

    private function correo() {
        return $this->emailService ??= new EmailService();
    }

    private function responder(bool $ok, string $mensaje, array $extra = []): void {
        echo json_encode(['success' => $ok, 'message' => $mensaje] + $extra);
    }

    /** Personal de la clínica activa, para la vista de administración. */
    public function listar(): array {
        return array_map(function ($persona) {
            $persona['identidad_editable'] = $this->usuario->identidadEditableEnClinica((int) $persona['id_usuario'], $this->clinica());
            return $persona;
        }, $this->usuario->personalDeClinica($this->clinica()));
    }

    /** Propietarios vinculados a la clínica activa (solo lectura en C1). */
    public function listarPropietarios(): array {
        return $this->usuario->propietariosDeClinica($this->clinica());
    }

    /**
     * La persona como personal de la clínica activa; si no lo es, es un
     * recurso ajeno y Security corta con 403 y auditoría.
     */
    private function personalDeEstaClinica($idUsuario): array {
        $id = ctype_digit((string) $idUsuario) ? (int) $idUsuario : 0;
        $fila = $id > 0 ? $this->usuario->personalEnClinica($id, $this->clinica()) : null;
        if ($fila === null) {
            Security::denegarRecursoAjeno('usuarios', $idUsuario);
        }
        return $fila;
    }

    /**
     * Valida y normaliza los datos de identidad y el rol que llegan por POST.
     *
     * T-14: se valida en el servidor aunque el formulario ya valide. RE-T.11.2
     * y B.5: el rol solo puede ser uno de clínica (1 o 2); el 3 ya no existe
     * y el 5 se marca con es_super_admin, nunca en usuario_clinica.
     */
    private function validarDatos(array $entrada, ?array &$limpios): ?string {
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
        if (!in_array($limpios['tipo_documento'], Usuario::TIPOS_DOCUMENTO, true)) {
            return 'El tipo de documento no es valido.';
        }

        // D1: la misma regla de teléfono en todo el sistema (ValidadorTelefono).
        $telefono = ValidadorTelefono::normalizar((string) ($entrada['telefono'] ?? ''));
        if ($telefono !== '' && !ValidadorTelefono::esValido($telefono)) {
            return ValidadorTelefono::MENSAJE;
        }
        $limpios['telefono'] = $telefono;

        $rol = $entrada['id_rol'] ?? null;
        if (!ctype_digit((string) $rol) || !Roles::esDeClinica((int) $rol)) {
            return 'El rol indicado no es valido.';
        }
        $limpios['id_rol'] = (int) $rol;

        $estado = (string) ($entrada['estado'] ?? '1');
        if (!in_array($estado, ['0', '1'], true)) {
            return 'El estado indicado no es valido.';
        }
        $limpios['estado'] = (int) $estado;

        return null;
    }

    /**
     * Alta de personal en la clínica activa.
     *
     * RE-T.7.5 / RN-G06: si el documento o el correo ya pertenecen a una
     * persona de la plataforma, se le asigna el rol en esta clínica en vez de
     * crear otra cuenta; sus datos no se tocan. Si el documento y el correo
     * son de personas distintas, se rechaza.
     */
    public function registrarAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Metodo no permitido.');
            return;
        }

        $error = $this->validarDatos($_POST, $datos);
        if ($error !== null) {
            $this->responder(false, $error);
            return;
        }

        $idClinica = $this->clinica();
        $porDocumento = $this->usuario->buscarPorDocumento($datos['documento']);
        $porEmail = $this->usuario->buscarPorEmail($datos['email']);

        try {
            if ($porDocumento !== null || $porEmail !== null) {
                $this->vincularExistente($porDocumento, $porEmail, $datos, $idClinica);
                return;
            }

            // HU-36 (VD-SEG-07): si el administrador escribe una contraseña
            // debe cumplir la política; si la deja vacía se genera una
            // temporal aleatoria. En ambos casos se pide cambiarla al entrar.
            $password = (string) ($_POST['password'] ?? '');
            if ($password !== '') {
                $motivo = PoliticaPassword::validar($password, [$datos['documento'], $datos['nombre_completo'], $datos['email']]);
                if ($motivo !== null) {
                    $this->responder(false, $motivo);
                    return;
                }
            } else {
                $password = PoliticaPassword::generarTemporal();
            }

            $this->db->beginTransaction();
            $idUsuario = $this->usuario->crear($datos + [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'debe_cambiar_password' => 1,
            ]);
            $this->usuario->asignarRolEnClinica($idUsuario, $idClinica, $datos['id_rol']);
            $this->db->commit();

            $this->auditoria->log(Contexto::idUsuario(), 'INSERT', 'usuario_clinica', $idUsuario, null, [
                'nombre_completo' => $datos['nombre_completo'],
                'email' => $datos['email'],
                'id_rol' => $datos['id_rol'],
            ], 'Personal creado en la clínica');

            // Se envía la misma contraseña con la que se creó el hash.
            $enviado = $this->correo()->enviarCredencialesUsuario($datos['email'], $datos['nombre_completo'], $datos['documento'], $password);
            $this->responder(true, $enviado
                ? 'Usuario creado. Se enviaron las credenciales al correo registrado.'
                : 'Usuario creado, pero no se pudo enviar el correo con las credenciales. Restablece la contraseña para enviarlas de nuevo.');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof AccesoDenegado) {
                throw $e;
            }
            // T-04: el detalle tecnico va al log del servidor, no al navegador.
            error_log('Error al crear personal: ' . $e->getMessage());
            $this->responder(false, 'No se pudo crear el usuario. Intenta nuevamente.');
        }
    }

    /** RE-T.7.5: asigna el rol a la persona que ya existe en la plataforma. */
    private function vincularExistente(?array $porDocumento, ?array $porEmail, array $datos, int $idClinica): void {
        if ($porDocumento !== null && $porEmail !== null && (int) $porDocumento['id_usuario'] !== (int) $porEmail['id_usuario']) {
            $this->responder(false, 'El documento y el correo pertenecen a cuentas distintas. Verifica los datos.');
            return;
        }

        $persona = $porDocumento ?? $porEmail;
        $idUsuario = (int) $persona['id_usuario'];

        if ($this->usuario->personalEnClinica($idUsuario, $idClinica) !== null) {
            $this->responder(false, 'Esa persona ya es parte del personal de esta clínica. Edítala desde la lista.');
            return;
        }

        try {
            $this->usuario->asignarRolEnClinica($idUsuario, $idClinica, $datos['id_rol']);
        } catch (InvalidArgumentException $e) {
            // RE-T.17.5: un super-administrador no recibe roles de clínica.
            $this->responder(false, 'A esa cuenta no se le puede asignar un rol en la clínica.');
            return;
        }

        $this->auditoria->log(Contexto::idUsuario(), 'INSERT', 'usuario_clinica', $idUsuario, null, ['id_rol' => $datos['id_rol']], 'Rol asignado a una persona ya registrada');
        $this->responder(true, 'Esa persona ya tenía cuenta en Zooki: se le asignó el rol en esta clínica. Entra con sus datos de siempre; los datos de su cuenta no se cambiaron.', ['vinculado' => true]);
    }

    /**
     * Edición de una persona del personal: identidad, rol y estado en esta
     * clínica. El documento es un dato editable con unicidad (RN-G06), no la
     * clave: la persona sigue siendo el mismo id_usuario.
     */
    public function actualizarAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Metodo no permitido.');
            return;
        }

        $actual = $this->personalDeEstaClinica($_POST['id_usuario'] ?? null);
        $idUsuario = (int) $actual['id_usuario'];
        $idClinica = $this->clinica();

        // C1.6: rechazar incluso peticiones directas antes de validar o escribir.
        if (!$this->usuario->identidadEditableEnClinica($idUsuario, $idClinica)) {
            foreach (['documento', 'tipo_documento', 'email'] as $campo) {
                if (isset($_POST[$campo]) && trim((string) $_POST[$campo]) !== (string) $actual[$campo]) {
                    $this->denegarIdentidadCompartida($idUsuario);
                }
            }
            if (trim((string) ($_POST['password'] ?? '')) !== '') {
                $this->denegarIdentidadCompartida($idUsuario);
            }
        }

        $error = $this->validarDatos($_POST, $datos);
        if ($error !== null) {
            $this->responder(false, $error);
            return;
        }

        // RE-T.11.3 / RN-G08: el único administrador activo de la clínica no
        // pierde el rol ni queda inactivo en ella.
        $quitaAdmin = $datos['id_rol'] !== Roles::ADMIN;
        $desactiva = $datos['estado'] !== 1;
        if (($quitaAdmin || $desactiva) && $this->usuario->esUltimoAdminActivo($idUsuario, $idClinica)) {
            $this->responder(false, 'No se puede quitar el rol ni desactivar al unico administrador activo de la clinica. Asigna primero otro administrador.');
            return;
        }

        if ($this->usuario->existeDocumento($datos['documento'], $idUsuario)) {
            $this->responder(false, 'El documento ya está registrado por otra persona.');
            return;
        }
        if ($this->usuario->existeEmail($datos['email'], $idUsuario)) {
            $this->responder(false, 'El correo electrónico ya está registrado por otra persona.');
            return;
        }

        try {
            $this->db->beginTransaction();
            if ($this->usuario->identidadEditableEnClinica($idUsuario, $idClinica)) {
                $this->usuario->actualizarIdentidad($idUsuario, $datos);
            } else {
                $this->usuario->actualizarDatosPersonal($idUsuario, $datos);
            }
            $this->usuario->asignarRolEnClinica($idUsuario, $idClinica, $datos['id_rol']);
            if ($datos['estado'] !== 1) {
                $this->usuario->cambiarEstadoEnClinica($idUsuario, $idClinica, false);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error al actualizar personal: ' . $e->getMessage());
            $this->responder(false, 'No se pudo actualizar el usuario. Intenta nuevamente.');
            return;
        }

        // T-11: datos anteriores y nuevos reales, para reconstruir el cambio.
        $campos = ['documento', 'tipo_documento', 'nombre_completo', 'email', 'telefono', 'id_rol'];
        $antes = array_intersect_key($actual, array_flip($campos)) + ['estado' => (int) $actual['estado_clinica']];
        $this->auditoria->log(Contexto::idUsuario(), 'UPDATE', 'usuarios', $idUsuario, $antes, $datos, 'Personal actualizado');
        $this->responder(true, 'Usuario actualizado.');
    }

    /** Datos de una persona del personal (sin contraseña, T-03). */
    public function getUsuarioAjax() {
        header('Content-Type: application/json');
        $fila = $this->personalDeEstaClinica($_GET['id_usuario'] ?? null);
        $fila['estado'] = (int) $fila['estado_clinica'];
        $fila['identidad_editable'] = $this->usuario->identidadEditableEnClinica((int) $fila['id_usuario'], $this->clinica());
        echo json_encode(['success' => true, 'usuario' => $fila]);
    }

    /**
     * Activa o inactiva a la persona en esta clínica (RN-G08): su cuenta y
     * sus roles en otras clínicas no cambian.
     */
    public function cambiarEstadoAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Metodo no permitido.');
            return;
        }

        $actual = $this->personalDeEstaClinica($_POST['id_usuario'] ?? null);
        $idUsuario = (int) $actual['id_usuario'];
        $activo = (string) ($_POST['estado'] ?? '') === '1';

        if (!$activo && $this->usuario->esUltimoAdminActivo($idUsuario, $this->clinica())) {
            $this->responder(false, 'No se puede desactivar al unico administrador activo de la clinica. Asigna primero otro administrador.');
            return;
        }

        $this->usuario->cambiarEstadoEnClinica($idUsuario, $this->clinica(), $activo);
        $this->auditoria->log(
            Contexto::idUsuario(),
            'UPDATE',
            'usuario_clinica',
            $idUsuario,
            ['estado' => (int) $actual['estado_clinica']],
            ['estado' => $activo ? 1 : 0],
            'Estado en la clínica cambiado a ' . ($activo ? 'activo' : 'inactivo')
        );
        $this->responder(true, 'Estado actualizado.');
    }

    /**
     * HU-T.14 — Restablecer la contraseña de una persona del personal: clave
     * temporal que cumple la política (RN-G10), enviada por correo, y cambio
     * obligatorio al entrar. Solo sobre personal de esta clínica.
     */
    public function resetearPasswordAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Metodo no permitido.');
            return;
        }

        $persona = $this->personalDeEstaClinica($_POST['id_usuario'] ?? null);
        $idUsuario = (int) $persona['id_usuario'];

        if (!$this->usuario->identidadEditableEnClinica($idUsuario, $this->clinica())) {
            $this->denegarIdentidadCompartida($idUsuario);
        }

        // Para la contraseña propia está el perfil, que sí pide la actual.
        if ($idUsuario === Contexto::idUsuario()) {
            $this->responder(false, 'Para cambiar tu propia contrasena usa la opcion de tu perfil.');
            return;
        }

        try {
            $temporal = PoliticaPassword::generarTemporal();
            $this->usuario->actualizarPassword($idUsuario, password_hash($temporal, PASSWORD_DEFAULT));
            $this->usuario->marcarCambioPassword($idUsuario, true);

            $this->auditoria->log(Contexto::idUsuario(), 'UPDATE', 'usuarios', $idUsuario, null, ['debe_cambiar_password' => 1], 'Contrasena restablecida por el administrador');

            $enviado = $this->correo()->enviarCredencialesUsuario($persona['email'], $persona['nombre_completo'], (string) $persona['documento'], $temporal);
            $this->responder(true, $enviado
                ? 'Contrasena restablecida. Se envio la clave temporal al correo del usuario.'
                : 'Contrasena restablecida, pero no se pudo enviar el correo. Comunicasela al usuario por otro medio.');
        } catch (Throwable $e) {
            error_log('Error al restablecer contrasena: ' . $e->getMessage());
            $this->responder(false, 'No se pudo restablecer la contrasena. Intenta nuevamente.');
        }
    }

    /**
     * Ayuda del formulario: si el documento ya existe en la plataforma y si
     * ya es personal de esta clínica. Con RE-T.7.5 el alta de un documento
     * existente vincula a esa persona, así que el formulario lo avisa.
     */
    public function verificarDocumentoAjax() {
        header('Content-Type: application/json');
        $persona = $this->usuario->buscarPorDocumento(trim((string) ($_GET['documento'] ?? '')));
        echo json_encode($this->estadoDePersona($persona, $_GET['excluir'] ?? null));
    }

    public function verificarEmailAjax() {
        header('Content-Type: application/json');
        $persona = $this->usuario->buscarPorEmail(trim((string) ($_GET['email'] ?? '')));
        echo json_encode($this->estadoDePersona($persona, $_GET['excluir'] ?? null));
    }

    private function estadoDePersona(?array $persona, $excluir): array {
        if ($persona === null || (string) $persona['id_usuario'] === (string) $excluir) {
            return ['exists' => false, 'en_clinica' => false];
        }
        return [
            'exists' => true,
            'en_clinica' => $this->usuario->personalEnClinica((int) $persona['id_usuario'], $this->clinica()) !== null,
        ];
    }

    private function denegarIdentidadCompartida(int $idUsuario): never {
        $this->auditoria->log(Contexto::idUsuario(), 'OTHER', 'usuarios', $idUsuario,
            null, null, 'Acceso denegado: modificación de identidad compartida', $this->clinica());
        throw new AccesoDenegado(403, 'La cuenta tiene otros vínculos. El titular debe corregir sus datos desde su perfil.');
    }
}
