<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/VinculosPropietario.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/RegistroPropietario.php';
require_once __DIR__ . '/../../controllers/MascotaController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

/**
 * M5 C1 — Huecos funcionales del Anexo A, con las decisiones del usuario de
 * «A — Revisión» (2026-10-12).
 *
 * - Hueco 1 (RE-5.12.3, RN-115): con el vínculo inactivo la clínica ve solo
 *   el nombre y la marca «Desvinculado», en todas sus lecturas.
 * - Hueco 2 (RE-1.5.1, RN-109): la búsqueda exacta sin vínculo activo no
 *   revela nombre, contacto ni mascotas.
 * - Hueco 4 (RN-G21, Modelos §11 GG4): «Registrarme con Google» desde una
 *   clínica liga a ella una cuenta ya verificada.
 * - Hueco 5 (RE-T.18.3, Modelos §13.1 D2): documento ocupado al completar el
 *   perfil abre el caso de soporte.
 */
final class M5C1HuecosTest extends TestCase
{
    private const TELEFONO_NUEVO = '3009998877';
    private const CORREO_NUEVO = 'fabio.nuevo@zooki.test';

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $this->db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto')");
        $this->db->exec("UPDATE usuarios SET telefono = '3001234567' WHERE id_usuario = 6");
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    private function comoAdministrador(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::ADMIN), 1);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function comoPropietario(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    /** El propietario cambia su contacto después de que su vínculo con Norte quedó inactivo. */
    private function cambiaSuContacto(): void
    {
        $this->db->prepare('UPDATE usuarios SET telefono = ?, email = ? WHERE id_usuario = 6')->execute([self::TELEFONO_NUEVO, self::CORREO_NUEVO]);
    }

    private function filaDeFabio(array $filas): array
    {
        foreach ($filas as $fila) {
            if ((int) $fila['id_usuario'] === DosClinicas::PROPIETARIO) {
                return $fila;
            }
        }
        $this->fail('Fabio no aparece en la lista de la clínica.');
    }

    private function assertSoloNombreYMarca(array $fila): void
    {
        $this->assertSame('Fabio Dueño', $fila['nombre_completo']);
        $this->assertSame(1, (int) $fila['desvinculado']);
        foreach (['tipo_documento', 'documento', 'email', 'telefono'] as $campo) {
            $this->assertNull($fila[$campo], "RN-115: la clínica no recibe $campo.");
        }
    }

    private function getPropietarioAjax(): string
    {
        $_GET = ['id_usuario' => (string) DosClinicas::PROPIETARIO];
        ob_start();
        (new MascotaController($this->db))->getPropietarioAjax();
        return (string) ob_get_clean();
    }

    // ── Hueco 1: RN-115 ─────────────────────────────────────────────────

    public function testConElVinculoActivoLaClinicaVeElContacto(): void
    {
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);

        $fila = $this->filaDeFabio((new PropietarioClinica($this->db))->listar());

        $this->assertSame('1000000006', $fila['documento']);
        $this->assertSame('3001234567', $fila['telefono']);
        $this->assertSame(0, (int) $fila['desvinculado']);
    }

    public function testSiElPropietarioSeDesvinculaLaClinicaNoRecibeSuTelefonoNuevo(): void
    {
        $this->comoPropietario();
        (new VinculosPropietario($this->db))->desvincular(DosClinicas::NORTE);
        $this->cambiaSuContacto();
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $modelo = new PropietarioClinica($this->db);

        $this->assertSoloNombreYMarca($this->filaDeFabio($modelo->listar()));
        $this->assertSoloNombreYMarca($modelo->obtener(DosClinicas::PROPIETARIO));
        $this->assertSoloNombreYMarca($this->filaDeFabio((new Usuario($this->db))->propietariosDeClinica(DosClinicas::NORTE)));
        $respuesta = $this->getPropietarioAjax();
        $this->assertStringNotContainsString(self::TELEFONO_NUEVO, $respuesta);
        $this->assertStringNotContainsString('fabio.nuevo', $respuesta);
        $this->assertStringNotContainsString('1000000006', $respuesta);
    }

    public function testSiLaClinicaDesactivaElVinculoTampocoVeElContactoNiEnLaFichaNiEnLasCitas(): void
    {
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado) VALUES (1, 1, 2, '2026-10-01', '09:00:00', 'Control', 'completada')");
        $idCita = (int) $this->db->lastInsertId();
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $this->assertSame('3001234567', (new Cita($this->db))->getById($idCita)['propietario_telefono'], 'Con el vínculo activo sí lo ve.');

        (new PropietarioClinica($this->db))->actualizar(DosClinicas::PROPIETARIO, ['nombre_completo' => 'Fabio Dueño', 'telefono' => '3001234567', 'estado' => '0']);
        $this->cambiaSuContacto();

        $this->assertSoloNombreYMarca($this->filaDeFabio((new PropietarioClinica($this->db))->listar()));
        // C1.1: sus mascotas quedan inactivas en Norte; la ficha de solo lectura no trae al propietario.
        $mascotas = new Mascota($this->db);
        $this->assertSame([], $mascotas->search('Luna'));
        $this->assertSame([], $mascotas->search('1000000006'));
        $historial = $mascotas->getParaHistorial(1);
        $this->assertTrue($historial['solo_lectura']);
        $this->assertArrayNotHasKey('propietario_documento', $historial);
        $cita = (new Cita($this->db))->getById($idCita);
        $this->assertSame('Fabio Dueño', $cita['propietario_nombre']);
        $this->assertNull($cita['email']);
        $this->assertNull($cita['propietario_telefono']);
    }

    /** Datos anteriores a C1.1 (vínculo inactivo con la mascota aún activa): la ficha tampoco muestra el documento. */
    public function testLaFichaNoMuestraElDocumentoSiSoloElVinculoDelPropietarioEstaInactivo(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 1");
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $mascotas = new Mascota($this->db);

        $luna = $mascotas->getById(1);

        $this->assertSame('Fabio Dueño', $luna['propietario_nombre']);
        $this->assertNull($luna['propietario_documento']);
        $this->assertSame([], $mascotas->search('1000000006'), 'No se busca por el documento de un cliente desvinculado.');
        $this->assertCount(1, $mascotas->search('Luna'));
    }

    public function testUnClienteDesvinculadoNoSeEditaYSeReactivaPidiendoLaConfirmacionPorSuId(): void
    {
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $modelo = new PropietarioClinica($this->db);
        $modelo->actualizar(DosClinicas::PROPIETARIO, ['nombre_completo' => 'Fabio Dueño', 'telefono' => '3001234567', 'estado' => '0']);

        $solicitud = $modelo->solicitarVinculoDe(DosClinicas::PROPIETARIO);

        $this->assertSame('fabio@zooki.test', $solicitud['email'], 'El correo lo usa el servidor para enviar; no viaja al navegador.');
        $this->assertTrue($modelo->confirmarVinculo($solicitud['id_enlace'], $solicitud['token']));
        $this->assertSame('1000000006', $modelo->obtener(DosClinicas::PROPIETARIO)['documento'], 'Confirmado el vínculo, vuelve a verlo.');
    }

    public function testNoSePideConfirmacionPorIdAQuienNuncaFueClienteDeLaClinica(): void
    {
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);

        try {
            (new PropietarioClinica($this->db))->solicitarVinculoDe(DosClinicas::GOOGLE);
            $this->fail('Norte pidió el vínculo de una persona que nunca fue su cliente.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM verificaciones_email WHERE proposito = 'vinculo_clinica'")->fetchColumn());
    }

    // ── Hueco 2 y aislamiento entre clínicas ────────────────────────────

    public function testSurNoVeAlPropietarioDeNorteNiPorListaNiPorBusqueda(): void
    {
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $propietarios = new PropietarioClinica($this->db);

        $this->assertNotContains(DosClinicas::PROPIETARIO, array_map('intval', array_column($propietarios->listar(), 'id_usuario')));
        $this->assertNotContains(DosClinicas::PROPIETARIO, array_map('intval', array_column((new Usuario($this->db))->propietariosDeClinica(DosClinicas::SUR), 'id_usuario')));
        $this->assertSame([], (new Mascota($this->db))->search('1000000006'));
        $respuesta = $propietarios->buscarExacto('fabio@zooki.test');
        $this->assertSame(['vinculado' => false, 'mensaje' => PropietarioClinica::MENSAJE_SIN_VINCULO], $respuesta);
    }

    public function testLaBusquedaNoDistingueNuncaVinculadoDeDesvinculado(): void
    {
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $propietarios = new PropietarioClinica($this->db);
        $nuncaVinculado = $propietarios->buscarExacto('1000000006');

        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'inactivo')");

        $this->assertSame($nuncaVinculado, $propietarios->buscarExacto('1000000006'));
        $this->assertSame($nuncaVinculado, $propietarios->buscarExacto('ana@zooki.test'), 'Tampoco distingue personal de propietario.');
    }

    // ── Hueco 4: Google desde una clínica ───────────────────────────────

    private function vincularGoogleFabio(?int $clinica): void
    {
        $fabio = (new Usuario($this->db))->buscarPorId(DosClinicas::PROPIETARIO);
        (new RegistroPropietario($this->db))->vincularGoogle($fabio, 'google-sub-fabio', $clinica);
    }

    private function vinculo(int $idUsuario, int $clinica): ?string
    {
        $stmt = $this->db->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $stmt->execute([$idUsuario, $clinica]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    public function testRegistrarmeConGoogleDesdeSurLigaLaCuentaVerificadaConAuditoria(): void
    {
        $hash = $this->db->query('SELECT password FROM usuarios WHERE id_usuario = 6')->fetchColumn();

        $this->vincularGoogleFabio(DosClinicas::SUR);

        $this->assertSame('activo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::SUR));
        $this->assertSame($hash, $this->db->query('SELECT password FROM usuarios WHERE id_usuario = 6')->fetchColumn(), 'Una cuenta verificada conserva su contraseña.');
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND tabla_afectada = 'propietario_clinica' AND descripcion LIKE '%registrarse con Google%'")->fetchColumn());
    }

    public function testUnVinculoInactivoSeReactivaComoDesdeElPortal(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado, autoriza_historia_compartida) VALUES (6, 2, 'inactivo', 0)");

        $this->vincularGoogleFabio(DosClinicas::SUR);

        $this->assertSame('activo', $this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::SUR));
        $this->assertSame(0, (int) $this->db->query('SELECT autoriza_historia_compartida FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2')->fetchColumn());
    }

    public function testEntrarConGoogleSinClinicaYUnVinculoYaActivoNoCambianNada(): void
    {
        $this->vincularGoogleFabio(null);
        $this->vincularGoogleFabio(DosClinicas::NORTE);

        $this->assertNull($this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::SUR));
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'propietario_clinica'")->fetchColumn());
    }

    public function testNoLigaAlSuperAdministradorNiAUnaClinicaSuspendida(): void
    {
        $gina = (new Usuario($this->db))->buscarPorId(DosClinicas::SUPER_ADMIN);
        (new RegistroPropietario($this->db))->vincularGoogle($gina, 'google-sub-gina', DosClinicas::SUR);
        $this->vincularGoogleFabio(DosClinicas::SUSPENDIDA);

        $this->assertNull($this->vinculo(DosClinicas::SUPER_ADMIN, DosClinicas::SUR), 'RN-G13: el super-administrador no tiene roles de clínica.');
        $this->assertNull($this->vinculo(DosClinicas::PROPIETARIO, DosClinicas::SUSPENDIDA));
    }

    public function testElControladorSoloTomaLaClinicaDelRegistro(): void
    {
        $metodo = new ReflectionMethod(AuthController::class, 'clinicaDelRegistroGoogle');
        $controlador = (new ReflectionClass(AuthController::class))->newInstanceWithoutConstructor();
        $casos = ['' => null, '0' => null, 'dos' => null, '-2' => null, '2' => 2];
        foreach ($casos as $enviado => $esperado) {
            $_POST = ['id_clinica' => (string) $enviado];
            $this->assertSame($esperado, $metodo->invoke($controlador), "id_clinica «{$enviado}»");
        }
    }

    // ── Hueco 5: completar el perfil con un documento ocupado ───────────

    public function testDocumentoDeOtraCuentaAbreUnSoloCasoSinDecirDeQuienEs(): void
    {
        $this->db->exec('UPDATE usuarios SET perfil_completo = 0 WHERE id_usuario = 9');
        $registro = new RegistroPropietario($this->db);
        $entrada = ['tipo_documento' => 'CC', 'documento' => '1000000006', 'telefono' => '3001112233'];

        foreach ([1, 2] as $intento) {
            try {
                $registro->completarPerfil(DosClinicas::GOOGLE, $entrada);
                $this->fail('Completó el perfil con el documento de otra cuenta.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(RegistroPropietario::MENSAJE_PERFIL_NO_COMPLETADO, $e->getMessage());
            }
        }

        $casos = $this->db->query('SELECT tipo, id_usuario, descripcion FROM casos_soporte')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $casos, 'El segundo intento no repite el caso abierto.');
        $this->assertSame('documento_duplicado', $casos[0]['tipo']);
        $this->assertSame(DosClinicas::GOOGLE, (int) $casos[0]['id_usuario']);
        $this->assertStringContainsString('1000000006', $casos[0]['descripcion']);
        $this->assertStringContainsString('RE-T.18.3', $casos[0]['descripcion']);
        $this->assertSame(0, (int) $this->db->query('SELECT perfil_completo FROM usuarios WHERE id_usuario = 9')->fetchColumn());
    }
}
