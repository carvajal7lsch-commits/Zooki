<?php
require_once __DIR__ . '/../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../models/CuentaTitular.php';
require_once __DIR__ . '/../models/InvitacionPersonal.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';

/**
 * HU-T.7 — Personal de la clínica activa (administradores y veterinarios).
 *
 * Todo ocurre dentro de la clínica del contexto: la persona se identifica por
 * id_usuario y su rol vive en usuario_clinica. Una persona que no es personal
 * de esta clínica es un recurso ajeno: 403 y auditoría (RN-G13). La matriz de
 * Security ya exige el rol administrador (RE-T.7.2).
 */
class UsuarioController {
    // D2.2 (RE-T.7.5): las mismas respuestas exista o no la cuenta.
    private const INVITACION_ENVIADA = 'Invitación enviada. La persona tiene 72 horas para aceptarla.';
    private const INVITACION_SIN_CORREO = 'La invitación quedó registrada, pero no se pudo enviar el correo. Usa «Reenviar invitación» en la lista.';
    private const INVITACION_PENDIENTE = 'Ya hay una invitación pendiente con ese documento o correo. Reenvíala desde la lista.';

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

    /** Nombre de la clínica activa, para los correos al personal. */
    private function nombreClinica(): string {
        return (string) (Contexto::actual()['clinica'] ?? 'tu clínica');
    }

    private function correo() {
        return $this->emailService ??= new EmailService();
    }

    private function responder(bool $ok, string $mensaje, array $extra = []): void {
        echo json_encode(['success' => $ok, 'message' => $mensaje] + $extra);
    }

    /** Personal de la clínica activa, para la vista de administración (sin las altas pendientes). */
    public function listar(): array {
        return array_map(function ($persona) {
            $persona['identidad_editable'] = $this->usuario->identidadEditableEnClinica((int) $persona['id_usuario'], $this->clinica());
            return $persona;
        }, $this->usuario->personalDeClinica($this->clinica()));
    }

    /**
     * D2.2: invitaciones pendientes, de cuentas nuevas y existentes, con los
     * datos que la clínica puede ver. Se identifican por el id de la invitación.
     */
    public function listarInvitaciones(): array {
        return (new InvitacionPersonal($this->db))->pendientesDeClinica($this->clinica());
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

        if (ValidadorCuenta::documento($limpios['documento']) !== null) {
            return 'El documento debe tener entre 5 y 15 digitos.';
        }
        if (ValidadorCuenta::correo($limpios['email']) !== null) {
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
     * Alta de personal en la clínica activa (RE-T.7.1, RE-T.7.5, RN-705).
     *
     * D2.2 (decisión del usuario, 2026-10-09): nadie queda vinculado sin
     * aceptar. Si el documento y el correo son nuevos, se crea la cuenta
     * pendiente de D2; si alguno ya pertenece a una persona, esa persona
     * recibe una invitación que acepta o rechaza en 72 horas. Manda el
     * correo: si es de alguien, a él va la invitación; si no, al dueño del
     * documento. El administrador recibe la misma respuesta en todos los
     * casos y, mientras tanto, solo ve lo que él escribió.
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
        $invitaciones = new InvitacionPersonal($this->db);
        $porDocumento = $this->usuario->buscarPorDocumento($datos['documento']);
        $porEmail = $this->usuario->buscarPorEmail($datos['email']);

        $aviso = $this->yaEstaEnLaClinica($invitaciones, [$porEmail, $porDocumento], $datos, $idClinica);
        if ($aviso !== null) {
            $this->responder(false, $aviso);
            return;
        }

        try {
            EnlaceCuenta::base();
            $persona = $porEmail ?? $porDocumento;
            $rol = Roles::nombre((int) $datos['id_rol']);
            $this->correo()->limpiarDirecciones();
            if ($persona === null) {
                $alta = (new CuentaTitular($this->db))->crearPersonal($datos, $idClinica);
                $idInvitacion = $alta['enlace']['id'];
                $enlace = EnlaceCuenta::crear('activar_personal', $idInvitacion, $alta['enlace']['token']);
                // D2.1: correo propio de invitación (la persona no se registró sola).
                $enviado = $this->correo()->enviarInvitacionPersonal($datos['email'], $datos['nombre_completo'], $this->nombreClinica(), $rol, $enlace, CuentaTitular::ACTIVACION_HORAS);
            } else {
                $invitacion = $invitaciones->crear($persona, $idClinica, $datos);
                $idInvitacion = $invitacion['id'];
                $enviado = $this->enviarInvitacionClinica($persona, $invitacion, (int) $datos['id_rol']);
            }
            $invitaciones->auditar('enviada', $idInvitacion, $idClinica, Contexto::idUsuario());
            $this->responder(true, $enviado ? self::INVITACION_ENVIADA : self::INVITACION_SIN_CORREO);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof AccesoDenegado) {
                throw $e;
            }
            // T-04: el detalle tecnico va al log del servidor, no al navegador.
            error_log('Error al invitar personal: ' . $e->getMessage());
            $this->responder(false, 'No se pudo enviar la invitación. Intenta nuevamente.');
        }
    }

    /**
     * Lo único que se le dice al administrador antes de invitar, y solo con
     * datos que ya ve en su lista: la persona ya es de su personal o ya tiene
     * una invitación pendiente con lo que escribió.
     */
    private function yaEstaEnLaClinica(InvitacionPersonal $invitaciones, array $personas, array $datos, int $idClinica): ?string {
        foreach ($personas as $persona) {
            if ($persona === null) {
                continue;
            }
            $idUsuario = (int) $persona['id_usuario'];
            if ($this->usuario->personalEnClinica($idUsuario, $idClinica) === null) {
                continue;
            }
            if ($invitaciones->esAltaPendiente($idUsuario, $idClinica)) {
                return self::INVITACION_PENDIENTE;
            }
            return 'Esa persona ya es parte del personal de esta clínica. Edítala desde la lista.';
        }
        if ($invitaciones->hayPendienteCon($idClinica, $datos['documento'], $datos['email'])) {
            return self::INVITACION_PENDIENTE;
        }
        return null;
    }

    /**
     * Correo de invitación a una cuenta existente. A una cuenta que no puede
     * aceptar (pendiente, inactiva o super-administrador) no se le envía nada,
     * pero la respuesta al administrador es la misma (RE-T.7.5).
     */
    private function enviarInvitacionClinica(array $persona, array $invitacion, int $idRol): bool {
        if (!InvitacionPersonal::puedeAceptar($persona)) {
            return true;
        }
        $enlace = EnlaceCuenta::crear('invitacion_personal', $invitacion['id'], $invitacion['token']);
        return $this->correo()->enviarInvitacionClinica($persona['email'], $persona['nombre_completo'], $this->nombreClinica(), Roles::nombre($idRol), $enlace, InvitacionPersonal::HORAS);
    }

    /** D2.2: reenvía cualquier invitación pendiente de esta clínica, con un enlace nuevo de 72 horas. */
    public function reenviarInvitacionAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Metodo no permitido.');
            return;
        }
        $invitaciones = new InvitacionPersonal($this->db);
        $fila = $this->invitacionDeEstaClinica($invitaciones, $_POST['id_invitacion'] ?? null);
        try {
            EnlaceCuenta::base();
            $this->correo()->limpiarDirecciones();
            if ($fila['proposito'] === InvitacionPersonal::ACTIVACION) {
                $persona = $this->usuario->personalEnClinica((int) $fila['id_usuario'], $this->clinica());
                $solicitud = (new CuentaTitular($this->db))->restablecerPersonal((int) $fila['id_usuario'], $this->clinica());
                $idNueva = $solicitud['enlace']['id'];
                $enlace = EnlaceCuenta::crear('activar_personal', $idNueva, $solicitud['enlace']['token']);
                $enviado = $this->correo()->enviarInvitacionPersonal($persona['email'], $persona['nombre_completo'], $this->nombreClinica(), Roles::nombre((int) $persona['id_rol']), $enlace, $solicitud['horas']);
            } else {
                $nueva = $invitaciones->reenviar((int) $fila['id'], $this->clinica());
                $idNueva = $nueva['id'];
                $enviado = $this->enviarInvitacionClinica($nueva['persona'], $nueva, $nueva['id_rol']);
            }
            $invitaciones->auditar('reenviada', $idNueva, $this->clinica(), Contexto::idUsuario());
            $this->responder(true, $enviado ? 'Se reenvió la invitación.' : self::INVITACION_SIN_CORREO);
        } catch (Throwable $e) {
            error_log('Error al reenviar la invitación: ' . $e->getMessage());
            $this->responder(false, 'No se pudo reenviar la invitación. Intenta nuevamente.');
        }
    }

    /** D2.2: retira una invitación pendiente; una cuenta nueva sin aceptar se elimina. */
    public function cancelarInvitacionAjax() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Metodo no permitido.');
            return;
        }
        $invitaciones = new InvitacionPersonal($this->db);
        $fila = $this->invitacionDeEstaClinica($invitaciones, $_POST['id_invitacion'] ?? null);
        try {
            if ($fila['proposito'] === InvitacionPersonal::ACTIVACION) {
                (new CuentaTitular($this->db))->cancelarAlta((int) $fila['id'], $this->clinica());
            } else {
                $invitaciones->cancelar((int) $fila['id'], $this->clinica());
            }
            $invitaciones->auditar('cancelada', (int) $fila['id'], $this->clinica(), Contexto::idUsuario());
            $this->responder(true, 'Invitación cancelada.');
        } catch (Throwable $e) {
            error_log('Error al cancelar la invitación: ' . $e->getMessage());
            $this->responder(false, 'No se pudo cancelar la invitación. Intenta nuevamente.');
        }
    }

    /** La invitación pendiente de esta clínica; si no lo es, recurso ajeno (403 y auditoría). */
    private function invitacionDeEstaClinica(InvitacionPersonal $invitaciones, $id): array {
        $numero = ctype_digit((string) $id) ? (int) $id : 0;
        $fila = $numero > 0 ? $invitaciones->pendienteDeClinica($numero, $this->clinica()) : null;
        if ($fila === null) {
            Security::denegarRecursoAjeno('verificaciones_email', $id);
        }
        return $fila;
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
     * HU-T.14 — Restablecer la contraseña de una persona del personal: enlace para elegir una nueva clave (RN-G10), con la anterior invalidada. Solo sobre personal de esta clínica.
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
            EnlaceCuenta::base();
            $solicitud = (new CuentaTitular($this->db))->restablecerPersonal($idUsuario, $this->clinica());
            $enlace = EnlaceCuenta::crear($solicitud['accion'], $solicitud['enlace']['id'], $solicitud['enlace']['token']);
            $this->correo()->limpiarDirecciones();
            // D2.1: una invitación pendiente se reenvía como invitación; si no, aviso de restablecimiento.
            $enviado = $solicitud['accion'] === 'activar_personal'
                ? $this->correo()->enviarInvitacionPersonal($persona['email'], $persona['nombre_completo'], $this->nombreClinica(), Roles::nombre((int) $persona['id_rol']), $enlace, $solicitud['horas'])
                : $this->correo()->enviarRestablecimientoPorAdministrador($persona['email'], $persona['nombre_completo'], $this->nombreClinica(), $enlace, $solicitud['horas']);
            // Revisión de D2.1: con la invitación pendiente se dice lo que pasó.
            $reenvio = $solicitud['accion'] === 'activar_personal';
            if ($enviado) {
                $this->responder(true, $reenvio ? 'Se reenvió la invitación.' : 'Se envió un enlace al titular para que cree su contraseña.');
                return;
            }
            $this->responder(true, $reenvio ? self::INVITACION_SIN_CORREO : 'No se pudo enviar el enlace. La contraseña anterior está invalidada; reintenta el envío.');
        } catch (Throwable $e) {
            error_log('Error al restablecer contrasena: ' . $e->getMessage());
            $this->responder(false, 'No se pudo restablecer la contrasena. Intenta nuevamente.');
        }
    }

    private function denegarIdentidadCompartida(int $idUsuario): never {
        $this->auditoria->log(Contexto::idUsuario(), 'OTHER', 'usuarios', $idUsuario,
            null, null, 'Acceso denegado: modificación de identidad compartida', $this->clinica());
        throw new AccesoDenegado(403, 'La cuenta tiene otros vínculos. El titular debe corregir sus datos desde su perfil.');
    }
}
