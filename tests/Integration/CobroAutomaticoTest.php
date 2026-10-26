<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../models/CobroAutomatico.php';
require_once __DIR__ . '/../../controllers/WompiEventosController.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';
require_once __DIR__ . '/../../helpers/MantenimientoSuscripciones.php';

final class CobroAutomaticoTest extends TestCase
{
    private PDO $db;
    private ClienteWompiFalso $cliente;
    private ConfiguracionWompi $config;
    private DateTimeImmutable $ahora;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-25 00:00:00');
        $this->cliente = new ClienteWompiFalso();
        $this->cliente->datosComercio = ClienteWompiFalso::contratos();
        $this->cliente->antesDeConsultar = function (): void {
            $this->assertFalse($this->db->inTransaction(), 'HTTP dentro de transacción');
        };
        $this->config = ClienteWompiFalso::configuracion('test', '1', '1');
        putenv('APP_URL=http://localhost/Zooki/public');
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        unset($_SERVER['CONTENT_LENGTH'], $_SERVER['HTTP_X_EVENT_CHECKSUM']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        putenv('APP_URL');
        http_response_code(200);
        Security::definirFuenteDeContextos(null);
    }

    private function fuente(int $clinica = 1): FuentePago
    {
        return new FuentePago($this->db, $clinica, $this->cliente, $this->config, fn () => $this->ahora);
    }

    private function version(): string
    {
        return AutorizacionCobro::contratos($this->cliente->datosComercio)['version'];
    }

    private function guardar(): void
    {
        $this->fuente()->guardar('CARD', 'tok_test_sintetico', true, true, $this->version(), 1);
    }

    private function autorizar(): void
    {
        $this->guardar();
        $this->fuente()->automatico(true, $this->version(), 8999000, 1);
    }

    private function trabajador(): CobroAutomatico
    {
        return new CobroAutomatico($this->db, $this->cliente, $this->config, fn () => $this->ahora);
    }

    private function posts(): array
    {
        return array_values(array_filter($this->cliente->creaciones, static fn ($c) => isset($c['transaccion'])));
    }

    private function confirmar(string $status, int $marca = 1): array
    {
        $intento = $this->db->query('SELECT * FROM intentos_pago ORDER BY id_intento DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $tx = ['id' => $intento['id_transaccion_wompi'] ?? 'tx_corte', 'reference' => $intento['referencia'], 'amount_in_cents' => 8999000, 'currency' => 'COP', 'status' => $status];
        $this->cliente->respuestas[$tx['id']] = $tx;
        $this->receptor(ClienteWompiFalso::cuerpo($tx, 'test', $marca));
        return $tx;
    }

    private function receptor(string $cuerpo): void
    {
        $nivel = ob_get_level();
        ob_start();
        echo 'búfer del llamante';
        (new WompiEventosController($this->db, $this->config, $this->cliente, fn () => $this->ahora))->recibir('test', $cuerpo);
        $this->assertSame($nivel + 1, ob_get_level());
        $this->assertSame('búfer del llamante', ob_get_clean());
        $this->assertSame(200, http_response_code());
    }

    public function testFuenteMinimaSinMandatoNiDatosDelMedio(): void
    {
        $this->cliente->fuente['public_data']['phone_number'] = '3001234567';
        $this->cliente->fuente['token'] = 'nunca_persistir';
        $this->guardar();
        $this->assertSame(0, (int) $this->fuente()->actual()['cobro_automatico']);
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame([], $this->posts());
        $persistido = json_encode([$this->db->query('SELECT * FROM fuentes_pago')->fetchAll(), $this->db->query('SELECT * FROM auditoria_sistema')->fetchAll()]);
        foreach (['3001234567', 'tok_test_sintetico', 'nunca_persistir', '.falso', 'prv_test_'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $persistido);
        }
        $this->assertSame('4242', $this->fuente()->actual()['ultimos_cuatro']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM pagos_suscripcion')->fetchColumn());
    }

    public function testNequiPendienteNoCreaFuenteHastaAprobar(): void
    {
        $this->cliente->estadoNequi = 'PENDING';
        $r = $this->fuente()->guardar('NEQUI', 'nequi_test_efimero', true, true, $this->version(), 1);
        $this->assertTrue($r['pendiente']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM fuentes_pago')->fetchColumn());
        $this->assertSame([], $this->cliente->creaciones);
        $this->cliente->estadoNequi = 'APPROVED';
        $this->cliente->fuente = ['id' => 99, 'type' => 'NEQUI', 'status' => 'AVAILABLE', 'public_data' => ['phone_number' => '3001234567']];
        $this->assertFalse($this->fuente()->guardar('NEQUI', 'nequi_test_efimero', true, true, $this->version(), 1)['pendiente']);
        $this->assertSame('NEQUI', $this->fuente()->actual()['tipo']);
        $this->assertNull($this->fuente()->actual()['ultimos_cuatro']);
    }

    public function testSinAceptacionesNiTokenDelEntornoNoLlamaAlProveedor(): void
    {
        foreach ([['tok_prod_ajeno', true, true], ['tok_test_valido', false, true], ['tok_test_valido', true, false]] as [$token, $politica, $datos]) {
            try {
                $this->fuente()->guardar('CARD', $token, $politica, $datos, $this->version(), 1);
                $this->fail('Debe rechazar antes del POST.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame([], $this->cliente->creaciones);
            }
        }
    }

    public function testFuenteAjenaYEntornoRemotoNoSeReasignan(): void
    {
        $this->guardar();
        try {
            $this->fuente(2)->guardar('CARD', 'tok_test_otro', true, true, $this->version(), 3);
            $this->fail('No debe apropiarse de una fuente.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
            $this->assertNull($this->fuente(2)->actual());
        }
        $this->cliente->fuente['environment'] = 'prod';
        $this->expectException(RuntimeException::class);
        $this->fuente()->guardar('CARD', 'tok_test_otro', true, true, $this->version(), 1);
    }

    public function testAutorizacionSeparadaRetiroYAnualSinAutomatico(): void
    {
        $this->autorizar();
        $this->assertSame(1, (int) $this->fuente()->actual()['cobro_automatico']);
        $this->fuente()->automatico(false, '', 0, 1);
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame('2026-10-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->db->exec("UPDATE suscripciones SET ciclo='anual' WHERE id_clinica=1");
        try {
            $this->fuente()->automatico(true, $this->version(), 8999000, 1);
            $this->fail('Anual sin automático.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(0, (int) $this->fuente()->actual()['cobro_automatico']);
        }
        $this->db->exec('UPDATE suscripciones SET cobro_automatico=1 WHERE id_clinica=1');
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->fuente()->quitar(1);
        $this->assertNull($this->fuente()->actual());
        $this->assertSame('retirada', $this->db->query('SELECT estado FROM fuentes_pago')->fetchColumn());
    }

    public function testCambioDeImporteOCondicionesDetieneHastaNuevaAceptacion(): void
    {
        $this->autorizar();
        $this->db->exec('UPDATE planes SET precio_mensual=95000 WHERE id_plan=2');
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame(0, (int) $this->fuente()->actual()['cobro_automatico']);
        $this->fuente()->automatico(true, $this->version(), 9500000, 1);
        $this->cliente->datosComercio = ClienteWompiFalso::contratos('nuevas');
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame([], $this->posts());
        $this->assertStringContainsString('9500000', (string) $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE tabla_afectada='fuentes_pago' ORDER BY id_auditoria DESC LIMIT 1")->fetchColumn());
    }

    public function testTresIntentosTerminalesUnCorreoPorFalloYSinCuarto(): void
    {
        $this->autorizar();
        $tarea = $this->trabajador();
        $enviados = 0;
        $smtp = function () use (&$enviados): bool {
            $this->assertFalse($this->db->inTransaction());
            $enviados++;
            return true;
        };
        foreach ([0, 24, 48] as $numero => $horas) {
            $this->ahora = VigenciaSuscripcion::fecha('2026-10-25 00:00:00')->modify('+' . $horas . ' hours');
            $this->assertTrue($tarea->procesar(1));
            $this->assertFalse($tarea->procesar(1), 'Pendiente no permite POST');
            $tx = $this->confirmar('DECLINED', $numero + 1);
            $this->receptor(ClienteWompiFalso::cuerpo($tx, 'test', 100 + $numero));
            foreach ($this->db->query("SELECT id_aviso FROM avisos_suscripcion WHERE estado='pendiente'")->fetchAll(PDO::FETCH_COLUMN) as $aviso) {
                (new AvisoSuscripcion($this->db, 1, fn () => $this->ahora))->despachar((int) $aviso, $smtp);
            }
            $this->assertSame($numero + 1, $enviados);
            $this->assertFalse($tarea->procesar(1), 'Reintento antes del turno o cuarto intento');
        }
        $this->ahora = $this->ahora->modify('+23 hours');
        $this->assertFalse($tarea->procesar(1));
        $this->assertCount(3, $this->posts());
        $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM avisos_suscripcion WHERE tipo='cobro_fallido'")->fetchColumn());
        foreach ($this->posts() as $post) {
            $this->assertSame(1, $post['transaccion']['payment_method']['installments']);
            $this->assertSame(8999000, $post['transaccion']['amount_in_cents']);
        }
    }

    public function testEventoAcreditaInmediatamenteSinCliYDuplicadoNoRepite(): void
    {
        $orden = (new PagoSuscripcion($this->db, 1, $this->config, fn () => $this->ahora))->reservar('mensual', 0, 1);
        $tx = ['id' => 'tx_boton', 'reference' => $orden['checkout']['reference'], 'amount_in_cents' => 8999000, 'currency' => 'COP', 'status' => 'APPROVED'];
        $this->cliente->respuestas['tx_boton'] = $tx;
        $this->receptor(ClienteWompiFalso::cuerpo($tx));
        $this->receptor(ClienteWompiFalso::cuerpo($tx));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame('2026-11-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
    }

    public function testApiCaidaDejaInboxDurableYConciliacionLoRetoma(): void
    {
        $this->autorizar();
        $this->trabajador()->procesar(1);
        $intento = $this->db->query('SELECT * FROM intentos_pago')->fetch(PDO::FETCH_ASSOC);
        $tx = ['id' => $intento['id_transaccion_wompi'], 'reference' => $intento['referencia'], 'amount_in_cents' => 8999000, 'currency' => 'COP', 'status' => 'APPROVED'];
        $this->receptor(ClienteWompiFalso::cuerpo($tx));
        $this->assertSame('pendiente', $this->db->query('SELECT estado FROM wompi_eventos')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->cliente->respuestas[$tx['id']] = $tx;
        $this->ahora = $this->ahora->modify('+5 minutes');
        (new MantenimientoSuscripciones($this->db, fn () => $this->ahora, $this->cliente, $this->config))->ejecutar(false, 'conciliacion');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testRetiroAntesDelCandadoYDespuesDelEnvioNoReduceVigencia(): void
    {
        $this->autorizar();
        $this->cliente->antesDeConsultar = function (): void {
            $this->cliente->antesDeConsultar = null;
            $this->fuente()->automatico(false, '', 0, 1);
        };
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->fuente()->automatico(true, $this->version(), 8999000, 1);
        $this->cliente->alCrear = function ($datos): array {
            $r = $this->fuente()->quitar(1);
            $this->assertStringContainsString('cargo en curso', $r['mensaje']);
            return ['id' => 'tx_retirada', 'reference' => $datos['reference'], 'amount_in_cents' => 8999000, 'currency' => 'COP'];
        };
        $this->trabajador()->procesar(1);
        $this->confirmar('APPROVED');
        $this->assertNull($this->fuente()->actual());
        $this->assertSame('2026-11-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertCount(1, $this->posts());
    }

    public function testCortesAntesYDespuesDelPostNuncaRepiten(): void
    {
        $this->autorizar();
        $this->db->exec("CREATE TRIGGER falla_reserva BEFORE INSERT ON pagos_suscripcion BEGIN SELECT RAISE(ABORT,'fallo antes de POST'); END");
        try {
            $this->trabajador()->procesar(1);
            $this->fail('Debe revertir reserva.');
        } catch (PDOException $error) {
            $this->assertSame([], $this->posts());
        }
        $this->db->exec('DROP TRIGGER falla_reserva');
        $this->cliente->alCrear = static function (): array {
            throw new RuntimeException('Corte después de aceptar el POST');
        };
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame('incierto', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
        $this->ahora = $this->ahora->modify('+24 hours');
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertCount(1, $this->posts());
        $this->confirmar('APPROVED');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testEventoAntesDeRespuestaDelPostNoSeDegrada(): void
    {
        $this->autorizar();
        $this->cliente->alCrear = function ($datos): array {
            $tx = ['id' => 'tx_adelantada', 'reference' => $datos['reference'], 'amount_in_cents' => 8999000, 'currency' => 'COP', 'status' => 'APPROVED'];
            $this->cliente->respuestas['tx_adelantada'] = $tx;
            $this->receptor(ClienteWompiFalso::cuerpo($tx));
            return array_replace($tx, ['status' => 'PENDING']);
        };
        $this->trabajador()->procesar(1);
        $this->assertSame('aprobado', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testCorteDeAuditoriaTrasPostDejaEnvioDurableSinRepetir(): void
    {
        $this->autorizar();
        $this->cliente->alCrear = function ($datos): array {
            $this->db->exec("CREATE TRIGGER corta_respuesta BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'corte despues de POST'); END");
            return ['id' => 'tx_corte', 'reference' => $datos['reference'], 'amount_in_cents' => 8999000, 'currency' => 'COP'];
        };
        try {
            $this->trabajador()->procesar(1);
            $this->fail('Sin auditoría se revierte la respuesta local.');
        } catch (RuntimeException $error) {
            $this->assertSame('enviando', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
            $this->assertNull($this->db->query('SELECT id_transaccion_wompi FROM intentos_pago')->fetchColumn());
        }
        $this->db->exec('DROP TRIGGER corta_respuesta');
        $this->ahora = $this->ahora->modify('+6 minutes');
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame('incierto', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
        $this->assertCount(1, $this->posts());
        $this->confirmar('APPROVED');
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testCotizacionAnteriorNoCargaUnImporteMayorAlRecienAutorizado(): void
    {
        $orden = (new PagoSuscripcion($this->db, 1, $this->config, fn () => $this->ahora))->reservar('mensual', 0, 1);
        $tx = ['id' => 'tx_vieja', 'reference' => $orden['checkout']['reference'], 'amount_in_cents' => 8999000, 'currency' => 'COP', 'status' => 'DECLINED'];
        $this->cliente->respuestas['tx_vieja'] = $tx;
        $this->receptor(ClienteWompiFalso::cuerpo($tx));
        $this->guardar();
        $this->db->exec('UPDATE planes SET precio_mensual=50000 WHERE id_plan=2');
        $this->fuente()->automatico(true, $this->version(), 5000000, 1);
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame([], $this->posts());
        $this->ahora = $this->ahora->modify('+16 minutes');
        $this->assertTrue($this->trabajador()->procesar(1));
        $this->assertSame(5000000, $this->posts()[0]['transaccion']['amount_in_cents']);
    }

    public function testPagoAnualRetiraMandatoMensualYFuenteNoSeLeeDeContratoAntiguo(): void
    {
        $this->autorizar();
        $orden = (new PagoSuscripcion($this->db, 1, $this->config, fn () => $this->ahora))->reservar('anual', 0, 1);
        $tx = ['id' => 'tx_anual', 'reference' => $orden['checkout']['reference'], 'amount_in_cents' => 89990000, 'currency' => 'COP', 'status' => 'APPROVED'];
        $this->cliente->respuestas['tx_anual'] = $tx;
        $this->receptor(ClienteWompiFalso::cuerpo($tx));
        $this->assertSame(0, (int) $this->fuente()->actual()['cobro_automatico']);
        $this->assertSame('anual', $this->db->query('SELECT ciclo FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->db->exec("INSERT INTO suscripciones (id_clinica,id_plan,fecha_inicio) VALUES (1,2,'2026-10-25')");
        $this->assertNull($this->fuente()->actual(), 'Solo se lee la suscripción canónica, incluso sin fuente.');
    }

    public function testApagadoRevisarYFueraDeGraciaNoHacenPost(): void
    {
        $this->autorizar();
        $antes = $this->db->query('SELECT * FROM auditoria_sistema')->fetchAll();
        $this->trabajador()->ejecutar(true);
        $this->assertSame($antes, $this->db->query('SELECT * FROM auditoria_sistema')->fetchAll());
        $this->assertSame([], $this->posts());
        $apagado = new CobroAutomatico($this->db, $this->cliente, ClienteWompiFalso::configuracion(), fn () => $this->ahora);
        $this->assertFalse($apagado->ejecutar()['habilitado']);
        $this->ahora = $this->ahora->modify('+3 days');
        $this->assertFalse($this->trabajador()->procesar(1));
        $this->assertSame([], $this->posts());
    }

    public function testAuditoriaFallidaRevierteFuenteYMandato(): void
    {
        $this->db->exec("CREATE TRIGGER sin_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'fallo'); END");
        try {
            $this->guardar();
            $this->fail('No guardar sin auditoría.');
        } catch (RuntimeException $error) {
            $this->assertNull($this->fuente()->actual());
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM fuentes_pago')->fetchColumn());
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testRolesCsrfClinicaAjenaYNoSeAceptaUnMandatoDesdeGuardar(): void
    {
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        Security::definirAuditoria(new Auditoria($this->db));
        session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
        session_start();
        require_once __DIR__ . '/../../models/Usuario.php';
        Security::definirFuenteDeContextos(fn ($id) => (new Usuario($this->db))->contextosDe($id));
        $acciones = ['suscripcion_fuente_preparar_ajax', 'suscripcion_fuente_ajax', 'suscripcion_automatico_ajax', 'suscripcion_retirar_fuente_ajax'];
        foreach ([[2, Roles::VETERINARIO], [6, Roles::PROPIETARIO], [7, Roles::SUPER_ADMIN]] as [$id, $rol]) {
            $_SESSION = ['id_usuario' => $id];
            Contexto::activar($rol === Roles::SUPER_ADMIN ? Contexto::dePlataforma() : ($rol === Roles::PROPIETARIO ? Contexto::dePropietario() : Contexto::deClinica(1, 'Norte', $rol)), 1);
            foreach ($acciones as $accion) {
                try {
                    Security::autorizar($accion);
                    $this->fail('Rol no autorizado.');
                } catch (AccesoDenegado $error) {
                    $this->assertSame(403, $error->codigo());
                }
            }
        }
        $_SESSION = ['id_usuario' => 1];
        $contexto = Contexto::deClinica(1, 'Norte', Roles::ADMIN);
        Contexto::activar($contexto, 1);
        Security::definirFuenteDeContextos(static fn () => [$contexto]);
        foreach (array_slice($acciones, 1) as $accion) {
            try {
                Security::autorizar($accion);
                $this->fail('CSRF requerido.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $_SESSION['csrf_default'] = str_repeat('a', 64);
        $_POST['csrf_token'] = $_SESSION['csrf_default'];
        $_POST['id_clinica'] = '2';
        try {
            Security::autorizar('suscripcion_fuente_ajax');
            $this->fail('No se admite otra clínica.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $_POST = ['csrf_token' => $_SESSION['csrf_default'], 'autorizar' => '1'];
        ob_start();
        (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->automatico();
        $r = json_decode(ob_get_clean(), true);
        $this->assertFalse($r['ok']);
        $this->assertSame([], $this->posts());
        $this->assertSame(0, (int) $this->db->query('SELECT cobro_automatico FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente);
        ob_start();
        $controller->prepararFuente();
        $preparada = json_decode(ob_get_clean(), true)['resultado'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => $_SESSION['csrf_default'], 'nonce' => $preparada['nonce'], 'acepta_politica' => '1', 'acepta_datos' => '1',
            'tipo' => 'CARD', 'token' => 'tok_test_controlador', 'autorizar' => '1', 'importe' => '1', 'id_fuente' => '999'];
        ob_start();
        $controller->guardarFuente();
        $guardada = json_decode(ob_get_clean(), true);
        $this->assertTrue($guardada['ok']);
        $this->assertSame(0, (int) $guardada['resultado']['fuente']['cobro_automatico']);
        $this->assertStringNotContainsString('prv_test_', json_encode($guardada));
        $_POST['acepta_mensual'] = '1';
        ob_start();
        $controller->automatico();
        $autorizada = json_decode(ob_get_clean(), true);
        $this->assertTrue($autorizada['ok']);
        $this->assertSame(8999000, (int) $autorizada['resultado']['fuente']['importe_mensual_autorizado_centavos']);
    }
}
