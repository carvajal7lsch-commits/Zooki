<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/MantenimientoSuscripciones.php';
require_once __DIR__ . '/../../helpers/LimitePlan.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';
require_once __DIR__ . '/../../controllers/PanelController.php';

final class SuscripcionTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $ahora;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-24 07:00:00');
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00',fecha_fin='2026-10-24' WHERE id_clinica=1");
        Security::definirFuenteDeContextos(fn (int $id): ?array => (new Usuario($this->db))->contextosDe($id));
        Security::definirAlmacenDeIntentos(null);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        Security::definirAlmacenDeIntentos(null);
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        http_response_code(200);
    }

    private function servicio(int $clinica = 1): Suscripcion
    {
        return new Suscripcion($this->db, $clinica, fn () => $this->ahora);
    }

    private function contexto(int $usuario, int $rol, int $clinica = 1): void
    {
        $_SESSION = ['id_usuario' => $usuario];
        Contexto::activar($rol === Roles::SUPER_ADMIN ? Contexto::dePlataforma() : ($rol === Roles::PROPIETARIO ? Contexto::dePropietario() : Contexto::deClinica($clinica, 'Clínica', $rol)), 1);
    }

    public function testPrecioPersonalizadoNoCambiaBeneficiosLegadosNiSeSobrescribe(): void
    {
        $this->db->exec('UPDATE planes SET precio_mensual=0,precio_anual=0 WHERE id_plan=2');
        $estado = $this->servicio(2)->estado();
        $this->assertTrue($estado['legado_sin_fecha']);
        $this->assertSame('Profesional', $estado['plan_efectivo']['nombre']);
        $this->assertNull((new LimitePlan($this->db, 2))->resumen()['mascotas']['limite']);
        $r = (new MantenimientoSuscripciones($this->db, fn () => $this->ahora))->ejecutar(true);
        $this->assertSame([2], $r['tarifas_personalizadas']);
        $this->assertSame(0, (int) $this->db->query('SELECT precio_mensual FROM planes WHERE id_plan=2')->fetchColumn());
    }

    public function testCronRetrasadoInicioYOperacionesCoincidenConRojoExento(): void
    {
        $this->contexto(1, Roles::ADMIN);
        foreach (['2026-10-26 12:00:00' => null, '2026-10-28 00:00:00' => 50] as $fecha => $limite) {
            $this->ahora = VigenciaSuscripcion::fecha($fecha);
            $cuotas = new LimitePlan($this->db, 1, fn () => $this->ahora);
            $inicio = (new PanelController($this->db, fn () => $this->ahora))->datosAdministrador();
            $this->assertSame($cuotas->nombrePlan(), $inicio['plan']['nombre']);
            $this->assertSame($limite, $cuotas->resumen()['mascotas']['limite']);
        }
        $this->db->exec('UPDATE planes SET limite_mascotas=0 WHERE id_plan=1');
        $cuotas = new LimitePlan($this->db, 1, fn () => $this->ahora);
        $this->assertSame('rojo atendido', $cuotas->consumir('mascotas', static fn () => 'rojo atendido', null, 'rojo'));
        $this->expectException(LimiteAlcanzado::class);
        $cuotas->consumir('mascotas', static fn () => 'no debe llegar');
    }

    public function testRevisarNoEscribeNiEnviaYTareaRepetidaNoDuplica(): void
    {
        $tarea = new MantenimientoSuscripciones($this->db, fn () => $this->ahora);
        $antes = $this->db->query('SELECT * FROM suscripciones')->fetchAll();
        $llamadas = 0;
        $transporte = function () use (&$llamadas): bool {
            $this->assertFalse($this->db->inTransaction());
            $llamadas++;
            return true;
        };
        $tarea->ejecutar(true, null, $transporte);
        $this->assertSame($antes, $this->db->query('SELECT * FROM suscripciones')->fetchAll());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM avisos_suscripcion')->fetchColumn());
        $this->assertSame(0, $llamadas);
        $tarea->ejecutar(false, null, $transporte);
        $tarea->ejecutar(false, null, $transporte);
        $this->assertSame(1, $llamadas);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM avisos_suscripcion')->fetchColumn());
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-28 00:00:00');
        $tarea->ejecutar(false, null, $transporte);
        $tarea->ejecutar(false, null, $transporte);
        $this->assertSame(2, $llamadas);
        $this->assertSame('activa', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='suscripciones' AND datos_nuevos LIKE '%congelada%'")->fetchColumn());
    }

    public function testTodosLosAdministradoresVerificadosHoraYAvisosObsoletos(): void
    {
        $this->db->exec("UPDATE usuario_clinica SET id_rol=1 WHERE id_clinica=1 AND id_usuario IN (2,5,8)");
        $this->db->exec("INSERT INTO verificaciones_email (id_usuario,email,proposito,token_hash,expires_at) VALUES (2,'beto@zooki.test','activacion_personal','x','2099-01-01')");
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-18 06:59:59');
        $tarea = new MantenimientoSuscripciones($this->db, fn () => $this->ahora);
        $tarea->ejecutar();
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM avisos_suscripcion')->fetchColumn());
        $this->ahora = $this->ahora->modify('+1 second');
        $tarea->ejecutar();
        $this->assertSame([1,5], array_map('intval', $this->db->query('SELECT id_usuario_destinatario FROM avisos_suscripcion ORDER BY id_usuario_destinatario')->fetchAll(PDO::FETCH_COLUMN)));
        $this->db->exec("UPDATE suscripciones SET periodo_fin='2026-11-25 00:00:00' WHERE id_clinica=1");
        $transporte = static function (): bool { throw new RuntimeException('Un aviso obsoleto no llega al transporte.'); };
        $tarea->ejecutar(false, 'avisos', $transporte);
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM avisos_suscripcion WHERE estado='obsoleto'")->fetchColumn());
        // D7: la suspensión operativa no elimina destinatarios financieros activos.
        $this->db->exec("UPDATE clinicas SET estado='suspendida' WHERE id_clinica=1");
        $this->db->exec("UPDATE suscripciones SET periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-24 07:00:00');
        $r = $tarea->ejecutar(false, 'avisos', static fn (): bool => true);
        $this->assertSame(2, $r['enviados']);
        $this->assertSame('suspendida', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica=1')->fetchColumn());
    }

    public function testFalloOLeaseExpiradoEsInciertoYNuncaReenvia(): void
    {
        $tarea = new MantenimientoSuscripciones($this->db, fn () => $this->ahora);
        $veces = 0;
        $smtp = function () use (&$veces): bool {
            $veces++;
            return false;
        };
        $resultado = $tarea->ejecutar(false, null, $smtp);
        $this->assertSame(1, $resultado['inciertos']);
        $tarea->ejecutar(false, null, $smtp);
        $this->assertSame(1, $veces);
        $this->assertSame('incierto', $this->db->query('SELECT estado FROM avisos_suscripcion')->fetchColumn());
        $this->db->exec("UPDATE avisos_suscripcion SET estado='reclamado',lease_hasta='2026-01-01 00:00:00'");
        $tarea->ejecutar(false, 'avisos', $smtp);
        $this->assertSame(1, $veces);
        $this->assertSame('incierto', $this->db->query('SELECT estado FROM avisos_suscripcion')->fetchColumn());
    }

    public function testRegularizarLegadoExigeFechaMotivoNoCreaPagosYNuncaReactivaClinica(): void
    {
        $this->contexto(7, Roles::SUPER_ADMIN);
        $servicio = $this->servicio(2);
        $this->assertTrue($servicio->estado()['legado_sin_fecha']);
        try {
            $servicio->ajustar(2, 'activa', 'Regularización', null);
            $this->fail('Debe exigir fecha.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue($servicio->estado()['legado_sin_fecha']);
        }
        $this->db->exec("UPDATE clinicas SET estado='suspendida' WHERE id_clinica=2");
        $servicio->ajustar(2, 'activa', 'Regularización sin pago', '2026-11-30', 'anual');
        $this->assertFalse($servicio->estado()['legado_sin_fecha']);
        $this->assertSame('suspendida', $servicio->estado()['clinica']['estado']);
        $this->assertSame('Gratuito', $servicio->estado()['plan_efectivo']['nombre']);
        $this->assertSame('2026-10-25 00:00:00', $this->servicio()->estado()['periodo_fin']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM pagos_suscripcion')->fetchColumn());
        $this->db->exec("CREATE TRIGGER sin_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'fallo'); END");
        try {
            $servicio->ajustar(2, 'activa', 'Debe revertir', '2026-12-31');
            $this->fail('Debe revertir sin auditoría.');
        } catch (RuntimeException $e) {
            $this->assertSame('2026-12-01 00:00:00', $servicio->estado()['periodo_fin']);
        }
    }

    public function testPermisosDosClinicasYCsrfDeAjuste(): void
    {
        foreach ([[1,Roles::ADMIN],[2,Roles::VETERINARIO],[6,Roles::PROPIETARIO],[7,Roles::SUPER_ADMIN]] as [$id,$rol]) {
            $this->contexto($id, $rol);
            try {
                Security::autorizar('admin_suscripcion');
                $this->assertSame(Roles::ADMIN, $rol);
            } catch (AccesoDenegado $e) {
                $this->assertNotSame(Roles::ADMIN, $rol);
                $this->assertSame(403, $e->codigo());
            }
        }
        $this->contexto(1, Roles::ADMIN);
        $_GET = ['id_clinica' => 2];
        try {
            Security::autorizar('admin_suscripcion');
            $this->fail('No debe ver otra clínica.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
        $_GET = [];
        $this->contexto(7, Roles::SUPER_ADMIN);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->expectException(AccesoDenegado::class);
        Security::autorizar('plataforma_suscripcion_ajax');
    }
}
