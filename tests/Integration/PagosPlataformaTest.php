<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
require_once __DIR__ . '/../Support/FinanzasFixture.php';
require_once __DIR__ . '/../../controllers/PlataformaController.php';
require_once __DIR__ . '/../../models/CobroAutomatico.php';

final class PagosPlataformaTest extends TestCase
{
    private PDO $db;
    private Plataforma $panel;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        putenv('APP_URL=http://localhost/Zooki/public');
        FinanzasFixture::datos($this->db);
        FinanzasFixture::plataforma();
        $this->panel = new Plataforma($this->db);
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        putenv('APP_URL');
        Security::definirFuenteDeContextos(null);
        http_response_code(200);
    }

    public function testDosClinicasSinLecturasClinicasNiFuentesYReferenciaDesconocidaSinAsignar(): void
    {
        $norte = $this->panel->finanzas(1);
        $sur = $this->panel->finanzas(2);
        $this->assertCount(1, $norte['pagos']);
        $this->assertCount(1, $norte['incidencias']);
        $this->assertSame('TRANSACCION_ANULADA', $norte['incidencias'][0]['codigo_error']);
        $this->assertSame([], $sur['pagos']);
        $this->assertSame([], $sur['incidencias']);
        $this->assertSame('legado_sin_fecha', $sur['resumen']['situacion']);
        $this->assertSame('Sin fecha', $sur['resumen']['fin']);
        $this->assertSame('condiciones', $norte['resumen']['motivo_apagado']);
        $sinClinica = $this->panel->finanzas(0);
        $this->assertCount(1, $sinClinica['incidencias']);
        $this->assertNull($sinClinica['incidencias'][0]['referencia']);
        foreach (['mascotas', 'consultas', 'email', 'telefono', 'documento', 'id_fuente', 'id_fuente_wompi', 'contratos_json', 'checksum', 'datos_minimos'] as $campo) {
            $this->assertStringNotContainsString('"' . $campo . '"', json_encode([$norte, $sur, $sinClinica]));
        }
        $this->assertNotNull($norte['pagos'][0]['fin'], 'La anulación conserva el periodo acreditado.');
    }

    public function testRevisarConNotaEsAtomicoIdempotenteYNoAlteraElPago(): void
    {
        $id = (int) $this->panel->finanzas(1)['incidencias'][0]['id_evento'];
        $antes = $this->db->query('SELECT * FROM suscripcion_periodos')->fetchAll(PDO::FETCH_ASSOC);
        foreach (['', '   '] as $nota) {
            try {
                $this->panel->revisarIncidencia($id, 1, $nota);
                $this->fail('Nota obligatoria.');
            } catch (InvalidArgumentException $error) {
                $this->assertNull($this->panel->finanzas(1)['incidencias'][0]['revisado_en']);
            }
        }
        $this->db->exec("CREATE TRIGGER s4_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'auditoria no disponible'); END");
        try {
            $this->panel->revisarIncidencia($id, 1, 'Revisión que debe revertir');
            $this->fail('No debe guardar sin auditoría.');
        } catch (RuntimeException $error) {
            $this->assertNull($this->panel->finanzas(1)['incidencias'][0]['revisado_en']);
        }
        $this->db->exec('DROP TRIGGER s4_auditoria');
        $this->panel->revisarIncidencia($id, 1, '  Gestionado   en Wompi <nota> ');
        $this->panel->revisarIncidencia($id, 1, 'Segundo clic no reemplaza');
        $fila = $this->panel->finanzas(1)['incidencias'][0];
        $this->assertSame('Gestionado en Wompi <nota>', $fila['nota_revision']);
        $this->assertSame(DosClinicas::SUPER_ADMIN, (int) $fila['revisado_por']);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion='Revisión financiera sin alterar el pago (RE-0.3.7)'")->fetchColumn());
        $this->assertSame($antes, $this->db->query('SELECT * FROM suscripcion_periodos')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame('revision', $this->db->query("SELECT estado FROM wompi_eventos WHERE id_evento=$id")->fetchColumn());
        $this->expectException(InvalidArgumentException::class);
        $this->panel->revisarIncidencia($id, 2, 'No pertenece a Sur');
    }

    public function testLegadoYConcesionNoSePresentanComoPago(): void
    {
        $this->panel->cambiarSuscripcion(2, 2, 'activa', 'Regularización del legado', '2099-12-31');
        $sur = $this->panel->finanzas(2);
        $this->assertSame([], $sur['pagos']);
        $this->assertCount(1, $sur['ajustes']);
        $this->assertSame('Regularización del legado', $sur['ajustes'][0]['motivo']);
        $this->assertSame('administrativo', $sur['ajustes'][0]['origen']);
        $this->assertNotSame('legado_sin_fecha', $sur['resumen']['situacion']);
    }

    public function testPaginacionEstableYEstadoCalculadoAunqueElCronSeRetrase(): void
    {
        $q = $this->db->prepare("INSERT INTO intentos_pago(id_pago,numero,entorno,referencia,origen,estado,creado_en) VALUES (1,?,'test',?,'boton','declinado','2026-10-26 08:00:00')");
        for ($numero = 2; $numero <= 23; $numero++) {
            $q->execute([$numero, 's4_ref_' . $numero]);
        }
        $primera = $this->panel->finanzas(1);
        $segunda = $this->panel->finanzas(1, 2);
        $this->assertCount(20, $primera['pagos']);
        $this->assertCount(3, $segunda['pagos']);
        $this->assertTrue($primera['hay_mas']);
        $this->assertFalse($segunda['hay_mas']);
        $this->assertSame([], array_intersect(array_column($primera['pagos'], 'referencia'), array_column($segunda['pagos'], 'referencia')));
        $this->db->exec("UPDATE suscripciones SET periodo_inicio='2025-01-01 00:00:00',periodo_fin='2025-02-01 00:00:00',estado_cobro='al_dia' WHERE id_clinica=1");
        $congelada = $this->panel->finanzas(1)['resumen'];
        $this->assertSame('congelada', $congelada['situacion']);
        $this->assertSame('Gratuito', $congelada['efectivo']);
    }

    public function testApagadoDistinguePrecioCondicionesYAmbosYRetiroLimpiaMotivo(): void
    {
        $config = ClienteWompiFalso::configuracion('test', '1', '1');
        $reloj = static fn () => VigenciaSuscripcion::fecha('2026-12-25 08:00:00');
        foreach (['precio', 'condiciones', 'precio_y_condiciones'] as $motivo) {
            $cliente = new ClienteWompiFalso();
            $cliente->datosComercio = ClienteWompiFalso::contratos();
            $version = AutorizacionCobro::contratos($cliente->datosComercio)['version'];
            $this->db->exec("UPDATE suscripciones SET periodo_fin='2026-12-25 00:00:00' WHERE id_clinica=1");
            $this->db->exec('UPDATE planes SET precio_mensual=89990 WHERE id_plan=2');
            $fuente = new FuentePago($this->db, 1, $cliente, $config, $reloj);
            $fuente->guardar('CARD', 'tok_test_s4', true, true, $version, 1);
            $fuente->automatico(true, $version, 8999000, 1);
            if ($motivo !== 'condiciones') {
                $this->db->exec('UPDATE planes SET precio_mensual=99990 WHERE id_plan=2');
            }
            if ($motivo !== 'precio') {
                $cliente->datosComercio = ClienteWompiFalso::contratos('otro_contrato');
            }
            $this->assertFalse((new CobroAutomatico($this->db, $cliente, $config, $reloj))->procesar(1));
            $this->assertSame($motivo, $this->panel->finanzas(1)['resumen']['motivo_apagado']);
            $fuente->automatico(false, '', 0, 1);
            $this->assertNull($this->panel->finanzas(1)['resumen']['motivo_apagado']);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPermisosRutasCsrfYSalidaEscapada(): void
    {
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
        session_start();
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn ($id) => $usuarios->contextosDe($id));
        foreach ([1 => Contexto::deClinica(1, 'Norte', 1), 2 => Contexto::deClinica(1, 'Norte', 2), 6 => Contexto::dePropietario()] as $id => $contexto) {
            $_SESSION = ['id_usuario' => $id];
            Contexto::activar($contexto, 1);
            foreach (['plataforma_pagos_ajax', 'plataforma_incidencia_ajax', 'plataforma_conciliar_ajax'] as $accion) {
                try {
                    Security::autorizar($accion);
                    $this->fail('Otro rol accedió.');
                } catch (AccesoDenegado $error) {
                    $this->assertSame(403, $error->codigo());
                }
            }
            try {
                $this->panel->finanzas(1);
                $this->fail('El modelo también protege la lectura.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        FinanzasFixture::plataforma();
        $controller = new PlataformaController($this->db);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        $controller->modificar('plataforma_incidencia_ajax');
        ob_end_clean();
        $this->assertSame(403, http_response_code());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id_clinica' => 1];
        $id = (int) $this->panel->finanzas(1)['incidencias'][0]['id_evento'];
        $this->panel->revisarIncidencia($id, 1, '<script>nota escapada</script>');
        http_response_code(200);
        ob_start();
        $controller->pagos();
        $html = ob_get_clean();
        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('&lt;script&gt;nota escapada&lt;/script&gt;', $html);
        $this->assertStringContainsString('Cambio de condiciones de Wompi', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('00:00', $html);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf_default'] = 'csrf_s4';
        $_POST = ['id_clinica' => 0, 'id_evento' => 3, 'motivo' => 'Sin clínica; revisado con proveedor', 'csrf_token' => 'csrf_s4'];
        ob_start();
        $controller->modificar('plataforma_incidencia_ajax');
        $json = json_decode(ob_get_clean(), true);
        $this->assertTrue($json['success']);
        $this->assertNotNull($this->panel->finanzas(0)['incidencias'][0]['revisado_en']);
    }
}
