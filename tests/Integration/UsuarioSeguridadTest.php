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
 *   RE-T.7.5   el alta de una persona que ya existe le asigna el rol, sin otra cuenta
 */
class UsuarioSeguridadTest extends TestCase
{
    private PDO $db;
    private Usuario $usuario;

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
            public function enviarCredencialesUsuario(...$datos) { $this->enviados[] = $datos; return true; }
        };
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

        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = $this->datosDePersonal(['email' => 'gina@zooki.test', 'documento' => '1000000123']);
        $r = $this->json(fn () => $controlador->registrarAjax());
        $this->assertFalse($r['success']);
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
        $this->assertSame(1, (int) $nueva['debe_cambiar_password'], 'Debe cambiar la contraseña al entrar');
        $this->assertSame(['clinica:1:2'], array_column($this->usuario->contextosDe((int) $nueva['id_usuario']), 'clave'));
    }

    /** RE-T.7.5: un propietario que pasa a ser veterinario conserva una sola cuenta con los dos roles. */
    public function testElAltaDeUnaPersonaExistenteLeAsignaElRolSinOtraCuenta(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_SUR, DosClinicas::SUR);
        $_POST = $this->datosDePersonal(['documento' => '1000000006', 'email' => 'otro@zooki.test', 'nombre_completo' => 'Otro Nombre']);

        $r = $this->json(fn () => $controlador->registrarAjax());

        $this->assertTrue($r['success']);
        $this->assertTrue($r['vinculado']);
        $this->assertSame(10, (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(), 'No se creó otra cuenta');
        $this->assertSame('fabio@zooki.test', $this->usuario->buscarPorId(DosClinicas::PROPIETARIO)['email'], 'Sus datos no se tocan');
        $this->assertSame(['clinica:2:2', 'propietario'], array_column($this->usuario->contextosDe(DosClinicas::PROPIETARIO), 'clave'));
    }

    public function testDocumentoYCorreoDeCuentasDistintasSeRechazan(): void
    {
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);
        $_POST = $this->datosDePersonal(['documento' => '1000000003', 'email' => 'diego@zooki.test']);

        $this->assertFalse($this->json(fn () => $controlador->registrarAjax())['success']);
        $this->assertNull($this->rolEn(DosClinicas::ADMIN_SUR, DosClinicas::NORTE));
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
