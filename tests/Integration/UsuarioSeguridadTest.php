<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../models/Auditoria.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';

/**
 * HU-T.7 y HU-T.11 en la v2: el personal vive en usuario_clinica, por clínica.
 *
 *   VD-SEG-01  solo se asignan roles de clínica (1 y 2): ni el 3 ni el 5 (B.5, RE-T.11.2)
 *   VD-SEG-02  el último administrador activo se cuenta por clínica (RE-T.11.3, RN-G08)
 *   RE-T.17.5  un super-administrador no recibe roles de clínica
 *   RE-T.7.5   el alta de una persona que ya existe la invita; el rol llega solo si acepta (D2.2)
 */
class UsuarioSeguridadTest extends TestCase
{
    private PDO $db;
    private Usuario $usuario;
    private object $correo;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $this->usuario = new Usuario($this->db);
        Security::definirAuditoria(new Auditoria($this->db));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Security::definirAuditoria(false);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    /** Sesión de administrador en la clínica indicada. */
    private function comoAdmin(int $idUsuario, int $idClinica): UsuarioController
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($idClinica, 'Clínica', Roles::ADMIN), 1);

        $correo = new class {
            public array $enviados = [];
            public function limpiarDirecciones(): void {}
            public function enviarInvitacionPersonal(...$datos) { $this->enviados[] = $datos; return true; }
            public function enviarRestablecimientoPorAdministrador(...$datos) { $this->enviados[] = $datos; return true; }
            public function enviarInvitacionClinica(...$datos) { $this->enviados[] = $datos; return true; }
        };
        $this->correo = $correo;
        return new UsuarioController($this->db, $correo);
    }

    /** Ejecuta una acción del controlador y devuelve su JSON. */
    private function json(callable $accion): array
    {
        ob_start();
        $accion();
        return json_decode((string) ob_get_clean(), true) ?? [];
    }

    private function datosDePersonal(array $cambios = []): array
    {
        return $cambios + [
            'tipo_documento' => 'CC', 'documento' => '1000000077', 'nombre_completo' => 'Nueva Persona',
            'email' => 'nueva@zooki.test', 'telefono' => '3001112233', 'id_rol' => '2', 'estado' => '1', 'password' => '',
        ];
    }

    private function rolEn(int $idUsuario, int $idClinica): ?array
    {
        $stmt = $this->db->prepare('SELECT id_rol, estado FROM usuario_clinica WHERE id_usuario = ? AND id_clinica = ?');
        $stmt->execute([$idUsuario, $idClinica]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** C1.6: vínculo de propietario incluso inactivo impide controlar la cuenta. */
    public function testIdentidadCompartidaRechazaCambiosYRestablecimiento(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        foreach (['otra_clinica', 'propietario_activo', 'propietario_inactivo'] as $vinculo) {
            $this->db->exec('DELETE FROM usuario_clinica WHERE id_usuario = 2 AND id_clinica = 2');
            $this->db->exec('DELETE FROM propietario_clinica WHERE id_propietario = 2');
            if ($vinculo === 'otra_clinica') {
                $this->usuario->asignarRolEnClinica(2, 2, Roles::VETERINARIO);
            } else {
                $estado = $vinculo === 'propietario_activo' ? 'activo' : 'inactivo';
                $this->db->prepare('INSERT INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (2,1,?)')->execute([$estado]);
            }
            $antes = $this->usuario->buscarPorId(2);
            foreach (['documento' => '1000000999', 'email' => 'cambio@zooki.test', 'tipo_documento' => 'CE', 'password' => 'Cambio#2026'] as $campo => $valor) {
                $_POST = $this->datosDePersonal($antes + ['id_rol' => 2]);
                $_POST[$campo] = $valor;
                $this->assertDenegado(fn () => $controlador->actualizarAjax());
                $this->assertSame($antes, $this->usuario->buscarPorId(2));
            }
            $_POST = ['id_usuario' => '2'];
            $this->assertDenegado(fn () => $controlador->resetearPasswordAjax());
            $this->assertTrue($this->usuario->verificarPassword(2, DosClinicas::PASSWORD));
            $_GET = ['id_usuario' => '2'];
            $this->assertFalse($this->json(fn () => $controlador->getUsuarioAjax())['usuario']['identidad_editable']);
        }
        $this->assertSame(15, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Acceso denegado: modificación de identidad compartida'")->fetchColumn());
    }

    private function assertDenegado(callable $accion): void
    {
        ob_start();
        try {
            $accion();
            $this->fail('Debió rechazar la modificación de la cuenta con 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        } finally {
            ob_end_clean();
        }
    }

    public function testCuentaExclusivaPermiteCorreoDocumentoYRestablecimiento(): void
    {
        $controlador = $this->comoAdmin(1, 1);
        $_POST = $this->datosDePersonal(['id_usuario' => '2', 'documento' => '1000000999', 'email' => 'nuevo@zooki.test']);
        $this->assertTrue($this->json(fn () => $controlador->actualizarAjax())['success']);
        $_POST = ['id_usuario' => '2'];
        $this->assertTrue($this->json(fn () => $controlador->resetearPasswordAjax())['success']);
        $this->assertFalse($this->usuario->verificarPassword(2, DosClinicas::PASSWORD));
        $this->assertSame(1, (int) $this->usuario->buscarPorId(2)['debe_cambiar_password']);
    }

    /** RE-T.7.4: editar una identidad mantiene la unicidad, aunque el alta permita invitar. */
    public function testEditarElCorreoRechazaElDeOtraCuentaSinModificarIdentidades(): void
    {
        $controlador = $this->comoAdmin(1, 1);
        $original = $this->usuario->buscarPorId(2);
        $otra = $this->usuario->buscarPorId(6);
        $_POST = $this->datosDePersonal($original);
        $_POST['id_usuario'] = '2';
        $_POST['email'] = $otra['email'];
        $respuesta = $this->json(fn () => $controlador->actualizarAjax());
        $this->assertFalse($respuesta['success']);
        $this->assertSame($original, $this->usuario->buscarPorId(2));
        $this->assertSame($otra, $this->usuario->buscarPorId(6));
    }

    public function testPersonaCompartidaPermiteNombreTelefonoRolYEstadoLocal(): void
    {
        $controlador = $this->comoAdmin(1, 1);
        $actual = $this->usuario->buscarPorId(5);
        $_POST = $this->datosDePersonal($actual);
        $_POST['nombre_completo'] = 'Nombre corregido';
        $_POST['telefono'] = '3001234567';
        $_POST['id_rol'] = '1';
        $_POST['estado'] = '0';
        $this->assertTrue($this->json(fn () => $controlador->actualizarAjax())['success']);
        $this->assertSame('Nombre corregido', $this->usuario->buscarPorId(5)['nombre_completo']);
        $this->assertSame('inactivo', $this->rolEn(5, 1)['estado']);
        $this->assertSame('activo', $this->rolEn(5, 2)['estado']);
    }

    public function testOtroVinculoPersonalInactivoBloqueaIdentidad(): void
    {
        $this->usuario->asignarRolEnClinica(2, 2, Roles::VETERINARIO);
        $this->usuario->cambiarEstadoEnClinica(2, 2, false);
        $this->assertFalse($this->usuario->identidadEditableEnClinica(2, 1));
        $controlador = $this->comoAdmin(1, 1);
        $antes = $this->usuario->buscarPorId(2);
        foreach (['documento' => '1000000999', 'email' => 'cambio@zooki.test', 'tipo_documento' => 'CE', 'password' => 'Cambio#2026'] as $campo => $valor) {
            $_POST = $this->datosDePersonal($antes + ['id_rol' => 2]);
            $_POST[$campo] = $valor;
            $this->assertDenegado(fn () => $controlador->actualizarAjax());
            $this->assertSame($antes, $this->usuario->buscarPorId(2));
        }
        $_POST = ['id_usuario' => '2'];
        $this->assertDenegado(fn () => $controlador->resetearPasswordAjax());
        $this->assertTrue($this->usuario->verificarPassword(2, DosClinicas::PASSWORD));
        $_GET = ['id_usuario' => '2'];
        $this->assertFalse($this->json(fn () => $controlador->getUsuarioAjax())['usuario']['identidad_editable']);
        $this->assertSame(5, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Acceso denegado: modificación de identidad compartida'")->fetchColumn());
    }

    // ── VD-SEG-01 / B.5 / RE-T.17.5: roles asignables ───────────────────

    public function testSoloSeAsignanLosRolesDeClinica(): void
    {
        foreach ([3, 4, 5, 0, 99] as $rol) {
            try {
                $this->usuario->asignarRolEnClinica(DosClinicas::PROPIETARIO, DosClinicas::NORTE, $rol);
                $this->fail("El rol $rol no debía poder asignarse en una clínica");
            } catch (InvalidArgumentException $e) {
                $this->assertNull($this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::NORTE));
            }
        }

        $this->usuario->asignarRolEnClinica(DosClinicas::PROPIETARIO, DosClinicas::NORTE, Roles::VETERINARIO);
        $this->assertSame(['id_rol' => 2, 'estado' => 'activo'], array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::NORTE)));
    }

    public function testElControladorRechazaElRol5YElRol3(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);

        foreach (['5', '3', 'administrador', ''] as $rol) {
            $_POST = $this->datosDePersonal(['id_rol' => $rol]);
            $r = $this->json(fn () => $controlador->registrarAjax());
            $this->assertFalse($r['success'], "El rol '$rol' no debía aceptarse");
        }
        $this->assertSame(10, (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(), 'No se creó ninguna cuenta');
    }

    /** RE-T.17.5: a un super-administrador no se le asigna rol de clínica, ni por el modelo ni por el alta. */
    public function testUnSuperAdministradorNoRecibeRolesDeClinica(): void
    {
        try {
            $this->usuario->asignarRolEnClinica(DosClinicas::SUPER_ADMIN, DosClinicas::NORTE, Roles::ADMIN);
            $this->fail('Debió rechazarse');
        } catch (InvalidArgumentException $e) {
            $this->assertNull($this->rolEn(DosClinicas::SUPER_ADMIN, DosClinicas::NORTE));
        }

        // D2.2: la misma respuesta que con cualquier correo, pero sin correo ni rol.
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = $this->datosDePersonal(['email' => 'gina@zooki.test', 'documento' => '1000000123']);
        $r = $this->json(fn () => $controlador->registrarAjax());
        $this->assertTrue($r['success']);
        $this->assertSame('Invitación enviada. La persona tiene 72 horas para aceptarla.', $r['message']);
        $this->assertSame([], $this->correo->enviados);
        $this->assertNull($this->rolEn(DosClinicas::SUPER_ADMIN, DosClinicas::NORTE));
    }

    // ── Alta de personal (RE-T.7.1, RE-T.7.5) ───────────────────────────

    public function testElAltaCreaLaPersonaConSuRolEnLaClinicaActiva(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = $this->datosDePersonal();

        $r = $this->json(fn () => $controlador->registrarAjax());

        $this->assertTrue($r['success'], $r['message'] ?? '');
        $nueva = $this->usuario->buscarPorDocumento('1000000077');
        $this->assertSame(0, (int) $nueva['tiene_password']);
        $this->assertSame(0, (int) $nueva['estado']);
        $this->assertNull($this->usuario->contextosDe((int) $nueva['id_usuario']));
        $this->assertSame(['id_rol' => 2, 'estado' => 'activo'], $this->rolEn((int) $nueva['id_usuario'], 1));
    }

    /**
     * RE-T.7.5 (D2.2): un propietario que ya tiene cuenta no queda vinculado
     * al darlo de alta: recibe la invitación y nada cambia hasta que acepta.
     */
    public function testElAltaDeUnaPersonaExistenteLaInvitaSinVincularNiCrearOtraCuenta(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_SUR, DosClinicas::SUR);
        $_POST = $this->datosDePersonal(['documento' => '1000000006', 'email' => 'otro@zooki.test', 'nombre_completo' => 'Otro Nombre']);

        $r = $this->json(fn () => $controlador->registrarAjax());

        $this->assertTrue($r['success']);
        $this->assertArrayNotHasKey('vinculado', $r);
        $this->assertSame(10, (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(), 'No se creó otra cuenta');
        $this->assertNull($this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::SUR), 'Sin aceptar no hay rol');
        $this->assertSame(['propietario'], array_column($this->usuario->contextosDe(DosClinicas::PROPIETARIO), 'clave'));
        $this->assertSame('fabio@zooki.test', $this->correo->enviados[0][0], 'La invitación va al correo de la cuenta del documento');
    }

    /** D2.2: documento de una persona y correo de otra: manda el correo, con la misma respuesta. */
    public function testDocumentoYCorreoDeCuentasDistintasInvitanAlDuenoDelCorreo(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = $this->datosDePersonal(['documento' => '1000000003', 'email' => 'diego@zooki.test']);

        $r = $this->json(fn () => $controlador->registrarAjax());

        $this->assertTrue($r['success']);
        $this->assertSame('Invitación enviada. La persona tiene 72 horas para aceptarla.', $r['message']);
        $this->assertSame('diego@zooki.test', $this->correo->enviados[0][0]);
        $this->assertNull($this->rolEn(DosClinicas::ADMIN_SUR, DosClinicas::NORTE));
        $this->assertNull($this->rolEn(DosClinicas::VET_SUR, DosClinicas::NORTE));
    }

    // ── VD-SEG-02: último administrador, por clínica ────────────────────

    public function testElUnicoAdministradorActivoDeLaClinicaEstaProtegido(): void
    {
        $this->assertTrue($this->usuario->esUltimoAdminActivo(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE));
        $this->assertSame(1, $this->usuario->contarAdminsActivos(DosClinicas::NORTE));
    }

    /** Los administradores de otra clínica no sirven de respaldo. */
    public function testSeCuentaPorClinica(): void
    {
        // Elena es administradora de Sur: no cuenta para Norte.
        $this->assertTrue($this->usuario->esUltimoAdminActivo(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE));

        $this->usuario->asignarRolEnClinica(DosClinicas::VET_NORTE, DosClinicas::NORTE, Roles::ADMIN);
        $this->assertFalse($this->usuario->esUltimoAdminActivo(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE));
        $this->assertSame(2, $this->usuario->contarAdminsActivos(DosClinicas::NORTE));
    }

    public function testUnAdministradorConLaCuentaInactivaNoCuentaComoRespaldo(): void
    {
        $this->usuario->asignarRolEnClinica(DosClinicas::INACTIVO, DosClinicas::NORTE, Roles::ADMIN);

        $this->assertSame(1, $this->usuario->contarAdminsActivos(DosClinicas::NORTE));
        $this->assertTrue($this->usuario->esUltimoAdminActivo(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE));
    }

    public function testQuienNoEsAdministradorNuncaEsElUltimo(): void
    {
        $this->assertFalse($this->usuario->esUltimoAdminActivo(DosClinicas::VET_NORTE, DosClinicas::NORTE));
        $this->assertFalse($this->usuario->esUltimoAdminActivo(999, DosClinicas::NORTE));
    }

    public function testElControladorNoDesactivaAlUltimoAdministrador(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = ['id_usuario' => (string) DosClinicas::ADMIN_NORTE, 'estado' => '0'];

        $this->assertFalse($this->json(fn () => $controlador->cambiarEstadoAjax())['success']);
        $this->assertSame('activo', $this->rolEn(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE)['estado']);
    }

    /** RN-G08: inactivar en una clínica no toca la cuenta ni el rol en la otra. */
    public function testInactivarEnUnaClinicaNoAfectaLaOtra(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = ['id_usuario' => (string) DosClinicas::DOBLE, 'estado' => '0'];

        $this->assertTrue($this->json(fn () => $controlador->cambiarEstadoAjax())['success']);
        $this->assertSame('inactivo', $this->rolEn(DosClinicas::DOBLE, DosClinicas::NORTE)['estado']);
        $this->assertSame('activo', $this->rolEn(DosClinicas::DOBLE, DosClinicas::SUR)['estado']);
        $this->assertSame(1, (int) $this->usuario->buscarPorId(DosClinicas::DOBLE)['estado']);
    }

    /** El documento es un dato editable con unicidad, no la clave: la persona sigue siendo el mismo id. */
    public function testEditarElDocumentoConservaLaPersona(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = $this->datosDePersonal([
            'id_usuario' => (string) DosClinicas::VET_NORTE, 'documento' => '1000000222',
            'email' => 'beto@zooki.test', 'nombre_completo' => 'Beto Norte',
        ]);
        $this->assertTrue($this->json(fn () => $controlador->actualizarAjax())['success']);
        $this->assertSame(DosClinicas::VET_NORTE, (int) $this->usuario->buscarPorDocumento('1000000222')['id_usuario']);

        // Un documento que ya es de otra persona no se aplica.
        $_POST['documento'] = '1000000003';
        $this->assertFalse($this->json(fn () => $controlador->actualizarAjax())['success']);
    }
}
