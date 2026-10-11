<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/PlataformaFixture.php';
require_once __DIR__ . '/../../models/Plataforma.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../models/RegistroPropietario.php';
require_once __DIR__ . '/../../controllers/PlataformaController.php';

final class PlataformaTest extends TestCase
{
    private PDO $db;
    private Plataforma $panel;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = PlataformaFixture::sqlite();
        PlataformaFixture::datos($this->db);
        PlataformaFixture::plataforma();
        $this->panel = new Plataforma($this->db);
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.8';
        Auditoria::definirProxiesConfiables([]);
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirFuenteDeVersionSesion(null);
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        Security::definirAlmacenDeIntentos(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    public function testListaClinicasActivaPendienteSuspendidaYFiltrosSinDatosClinicos(): void
    {
        $filas = $this->panel->clinicas();
        $this->assertCount(4, $filas);
        $this->assertContains('pendiente_verificacion', array_column($filas, 'estado'));
        $this->assertContains('suspendida', array_column($filas, 'estado'));
        $norte = $this->panel->clinicas('Norte', 'activa');
        $this->assertCount(1, $norte);
        $this->assertSame('Gratuito', $norte[0]['plan']);
        $this->assertSame('ana@zooki.test', $norte[0]['administrador']);
        $this->assertCount(0, $this->panel->clinicas('%'));
        foreach ($filas as $fila) {
            $this->assertArrayNotHasKey('mascotas', $fila);
            $this->assertArrayNotHasKey('consultas', $fila);
        }
    }

    public function testPlanSuscripcionMotivoYAuditoriaAtomica(): void
    {
        $this->panel->cambiarSuscripcion(1, 2, 'suspendida', '  Cambio   solicitado ');
        $this->assertSame(2, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 1')->fetchColumn());
        $this->assertSame('suspendida', $this->db->query('SELECT estado FROM suscripciones')->fetchColumn());
        $this->assertSame('Cambio solicitado', $this->db->query("SELECT descripcion FROM auditoria_sistema WHERE tabla_afectada = 'clinicas'")->fetchColumn());
        $this->panel->cambiarSuscripcion(1, 1, 'activa', 'Revisión');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripciones')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 2')->fetchColumn());
        try {
            $this->panel->cambiarSuscripcion(1, 2, 'activa', ' ');
            $this->fail('Debe exigir motivo.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(1, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 1')->fetchColumn());
        }
        $this->db->exec("CREATE TRIGGER rechazar_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'auditoria no disponible'); END");
        try {
            $this->panel->cambiarSuscripcion(1, 2, 'cancelada', 'No debe persistir');
            $this->fail('Sin auditoría debe revertir.');
        } catch (RuntimeException $e) {
            $this->assertSame(1, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 1')->fetchColumn());
            $this->assertSame('activa', $this->db->query('SELECT estado FROM suscripciones')->fetchColumn());
        }
    }

    public function testSuspenderRetiraContextoYReactivarPermiteElegirloSinBorrarDatos(): void
    {
        $this->panel->cambiarEstado(1, 'suspendida', 'Reporte por revisar');
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', 2), 1);
        try {
            Security::autorizar('vet_pacientes');
            $this->fail('El contexto suspendido no debe pasar.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
            $this->assertNull(Contexto::actual());
        }
        $this->assertNotContains(1, array_map('intval', array_column((new RegistroPropietario($this->db))->clinicasDisponibles(), 'id_clinica')));
        PlataformaFixture::plataforma();
        $this->panel->cambiarEstado(1, 'activa', 'Titularidad comprobada');
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', 2), 1);
        Security::autorizar('vet_pacientes');
        $this->assertSame(1, Contexto::clinicaActiva());
        PlataformaFixture::plataforma();
        $this->panel->cambiarEstado(1, 'baja', 'Baja solicitada');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
        $this->expectException(InvalidArgumentException::class);
        $this->panel->cambiarEstado(1, 'activa', 'No permitida');
    }

    public function testRolesYPostCsrfEnTodasLasAccionesDelPanel(): void
    {
        foreach ([1 => Contexto::deClinica(1, 'Norte', 1), 2 => Contexto::deClinica(1, 'Norte', 2), 6 => Contexto::dePropietario()] as $id => $contexto) {
            $_SESSION = ['id_usuario' => $id];
            Contexto::activar($contexto, 1);
            foreach (array_merge(['plataforma_inicio'], Security::ACCIONES_PLATAFORMA) as $accion) {
                try {
                    Security::autorizar($accion);
                    $this->fail('Otro rol accedió a ' . $accion);
                } catch (AccesoDenegado $e) {
                    $this->assertSame(403, $e->codigo());
                }
            }
        }
        PlataformaFixture::plataforma();
        foreach (['vet_pacientes', 'listar_historial_ajax', 'admin_panel', 'portal_propietario'] as $accion) {
            try {
                Security::autorizar($accion);
                $this->fail('Plataforma accedió a ' . $accion);
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
        }
        foreach (Security::ACCIONES_PLATAFORMA as $accion) {
            foreach (['GET', 'POST'] as $metodo) {
                $_SERVER['REQUEST_METHOD'] = $metodo;
                $_POST = [];
                try {
                    Security::autorizar($accion);
                    $this->fail('Petición sin POST/CSRF pasó.');
                } catch (AccesoDenegado $e) {
                    $this->assertSame(403, $e->codigo());
                }
            }
            $_SESSION['csrf_default'] = 'seguro';
            $_POST['csrf_token'] = 'seguro';
            Security::autorizar($accion);
        }
    }

    public function testCasosSeResuelvenODescartanConNotaAuditada(): void
    {
        $this->panel->cerrarCaso(1, 'resuelto', 'Titular identificado');
        $this->panel->cerrarCaso(2, 'descartado', 'Solicitud improcedente');
        $this->assertSame(['resuelto', 'descartado'], array_column(array_reverse($this->panel->casos()), 'estado'));
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'casos_soporte'")->fetchColumn());
        $this->expectException(InvalidArgumentException::class);
        $this->panel->cerrarCaso(1, 'resuelto', ' ');
    }

    public function testIpMuestraCuentasClinicasYSoloEnviaAlAdministradorElegido(): void
    {
        $avisos = [];
        $ips = $this->panel->ips();
        $this->assertCount(1, $ips);
        $this->assertCount(3, $ips[0]['cuentas']);
        $this->assertArrayHasKey('1:1', $ips[0]['administradores']);
        $this->assertArrayHasKey('2:3', $ips[0]['administradores']);
        $this->assertArrayNotHasKey('3:10', $ips[0]['administradores']);
        $this->assertSame([], $avisos, 'La lectura nunca envía avisos.');
        $enviar = function (array $admin, string $texto) use (&$avisos): bool {
            $avisos[] = [$admin['email'], $texto];
            return true;
        };
        $this->assertTrue($this->panel->avisar('192.0.2.8', '2:3', 'Texto editado', $enviar));
        $this->assertSame([['carla@zooki.test', 'Texto editado']], $avisos);
        try {
            $this->panel->avisar('192.0.2.8', '3:10', 'No enviar', $enviar);
            $this->fail('Destinatario ajeno admitido.');
        } catch (InvalidArgumentException $e) {
            $this->assertCount(1, $avisos);
        }
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'intentos_login'")->fetchColumn());
    }

    public function testDesbloquearValeConLaMismaCookieYConservaContadorDeCuenta(): void
    {
        $_SESSION['rate_limit_192.0.2.8'] = ['count' => 20, 'time' => time()];
        $this->db->prepare('INSERT INTO intentos_login (identificador, intentos, primer_intento, ultimo_intento) VALUES (?, 5, ?, ?)')->execute(['cuenta:2', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $this->assertFalse(Security::checkRateLimit('2'));
        $this->panel->desbloquear('192.0.2.8');
        $this->assertTrue(Security::checkRateLimit('2'));
        $this->assertTrue(Security::exigeCaptcha('2'));
        $this->assertSame([], $this->panel->ips());
        $this->assertSame('192.0.2.8', $this->db->query("SELECT registro_id FROM auditoria_sistema WHERE tabla_afectada = 'intentos_login'")->fetchColumn());
    }

    public function testElModeloRechazaContextoClinicoAunqueSeLlameDirecto(): void
    {
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', 1), 1);
        $this->expectException(AccesoDenegado::class);
        $this->panel->clinicas();
    }

    public function testSinMotivoNiNotaNoCambiaEstadoOCasoYFalloDeAuditoriaNoDesbloquea(): void
    {
        foreach ([fn () => $this->panel->cambiarEstado(1, 'suspendida', ''), fn () => $this->panel->cerrarCaso(1, 'resuelto', '')] as $accion) {
            try {
                $accion();
                $this->fail('Debe exigir motivo o nota.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('activa', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica = 1')->fetchColumn());
                $this->assertSame('abierto', $this->db->query('SELECT estado FROM casos_soporte WHERE id_caso = 1')->fetchColumn());
            }
        }
        $this->db->exec("CREATE TRIGGER rechazar_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'sin auditoria'); END");
        foreach ([fn () => $this->panel->cambiarEstado(1, 'suspendida', 'Revisar'), fn () => $this->panel->cerrarCaso(1, 'descartado', 'Descartar'), fn () => $this->panel->desbloquear('192.0.2.8')] as $accion) {
            try {
                $accion();
                $this->fail('Sin auditoría debe revertir.');
            } catch (RuntimeException $e) {
                $this->assertSame('activa', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica = 1')->fetchColumn());
                $this->assertSame('abierto', $this->db->query('SELECT estado FROM casos_soporte WHERE id_caso = 1')->fetchColumn());
                $this->assertCount(1, $this->panel->ips());
            }
        }
    }

    public function testAvisoFallidoSeAuditaYNuncaSeEnviaSinAuditoria(): void
    {
        $envios = 0;
        $correo = function () use (&$envios): bool {
            $envios++;
            return false;
        };
        $this->assertFalse($this->panel->avisar('192.0.2.8', '1:1', 'Aviso de prueba', $correo));
        $this->assertSame(1, $envios);
        $nuevo = json_decode($this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE tabla_afectada = 'intentos_login' ORDER BY id_auditoria DESC LIMIT 1")->fetchColumn(), true);
        $this->assertFalse($nuevo['enviado']);
        $this->db->exec("CREATE TRIGGER rechazar_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'sin auditoria'); END");
        try {
            $this->panel->avisar('192.0.2.8', '1:1', 'No enviar', $correo);
            $this->fail('Sin auditoría no debe enviar.');
        } catch (RuntimeException $e) {
            $this->assertSame(1, $envios);
        }
    }

    public function testVistaEscapaDatosYNoOfreceEnlacesClinicos(): void
    {
        $this->db->exec("UPDATE clinicas SET nombre = '<script>prueba</script>' WHERE id_clinica = 1");
        ob_start();
        (new PlataformaController($this->db))->inicio();
        $html = ob_get_clean();
        $this->assertStringContainsString('&lt;script&gt;prueba&lt;/script&gt;', $html);
        $this->assertStringContainsString('Pendiente de verificación', $html);
        $this->assertStringContainsString('data-lista-vista="tarjetas"', $html);
        $this->assertDoesNotMatchRegularExpression('/style=|onclick=|<script(?![^>]*src=)/', $html);
        $this->assertDoesNotMatchRegularExpression('/action=(vet_pacientes|ver_consulta|portal_propietario|admin_citas)/', $html);
    }
}
