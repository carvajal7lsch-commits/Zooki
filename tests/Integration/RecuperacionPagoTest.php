<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__ . '/../Support/FinanzasFixture.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';
require_once __DIR__ . '/../../controllers/PlataformaController.php';
require_once __DIR__ . '/../../helpers/MantenimientoSuscripciones.php';

/** S4: evento perdido y recibo; ninguna prueba contacta al proveedor ni SMTP. */
final class RecuperacionPagoTest extends TestCase
{
    private PDO $db;
    private ClienteWompiFalso $cliente;
    private ConfiguracionWompi $config;
    private DateTimeImmutable $ahora;
    private array $orden;
    private array $tx;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $this->config = ClienteWompiFalso::configuracion();
        $this->cliente = new ClienteWompiFalso();
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-26 08:00:00');
        $this->cliente->antesDeConsultar = fn () => $this->assertFalse($this->db->inTransaction(), 'HTTP fuera de transacción');
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica IN (1,2)");
        putenv('APP_URL=http://localhost/Zooki/public');
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $this->orden = (new PagoSuscripcion($this->db, 1, $this->config, fn () => $this->ahora))->reservar('mensual', 0, 1);
        $this->tx = ['id' => '01-1531231271-19365', 'reference' => $this->orden['checkout']['reference'], 'status' => 'APPROVED',
            'amount_in_cents' => 8999000, 'currency' => 'COP', 'environment' => 'test',
            'payment_method' => ['type' => 'CARD', 'extra' => ['last_four' => '1234', 'numero_completo' => 'NO_GUARDAR']]];
        $this->cliente->respuestas[$this->tx['id']] = $this->tx;
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        Security::definirFuenteDeContextos(null);
        putenv('APP_URL');
        http_response_code(200);
    }

    private function conciliador(): ConciliadorPago
    {
        return new ConciliadorPago($this->db, $this->cliente, $this->config, fn () => $this->ahora);
    }

    private function evento(): void
    {
        $inbox = new EventoWompi($this->db, fn () => $this->ahora);
        $inbox->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($this->tx, 'test', 8), '', 'test', 'test_events_falso'));
        $this->conciliador()->procesar((int) $this->db->query('SELECT MAX(id_evento) FROM wompi_eventos')->fetchColumn());
    }

    private function contar(string $tabla): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla)->fetchColumn();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRetornoRecuperaEventoPerdidoIgnoraEstadoReferenciaYClinicaDelNavegador(): void
    {
        session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
        session_start();
        $contexto = Contexto::deClinica(1, 'Norte', Roles::ADMIN);
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar($contexto, 1);
        Security::definirFuenteDeContextos(static fn () => [$contexto]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => $this->tx['id'], 'status' => 'DECLINED', 'referencia' => ['maliciosa']];
        ob_start();
        (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->retorno();
        $html = ob_get_clean();
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame('', $html, 'El retorno redirige a la sección de Ajustes.');
        parse_str(parse_url(EnlaceAjustes::suscripcion($_GET), PHP_URL_QUERY), $_GET);
        $datos = (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->datosVista();
        $this->assertSame('24/11/2026', VigenciaSuscripcion::fechaIncluida($datos['estadoSuscripcion']['periodo_fin']));
        $this->assertStringContainsString('Consulta completada', $datos['mensajePago']);
        $this->assertStringNotContainsString('prv_test_', json_encode($datos));
        $this->assertSame('transaction.consultada', $this->db->query('SELECT tipo FROM wompi_eventos')->fetchColumn());
        $this->conciliador()->recuperar($this->tx['id'], 1);
        $this->evento();
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame(1, $this->contar('avisos_suscripcion'));
        $this->assertSame(0, (int) $this->db->query('SELECT version_periodo FROM suscripciones WHERE id_clinica=2')->fetchColumn());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRetornoHttpDeOtraClinicaNoAcreditaNiRevelaReferencia(): void
    {
        session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
        session_start();
        $ajeno = Contexto::deClinica(2, 'Sur', Roles::ADMIN);
        $_SESSION = ['id_usuario' => 3];
        Contexto::activar($ajeno, 1);
        Security::definirFuenteDeContextos(static fn () => [$ajeno]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => $this->tx['id']];
        ob_start();
        (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->retorno();
        $ajenoHtml = ob_get_clean();
        $this->assertSame(0, $this->contar('suscripcion_periodos'));
        $this->assertSame(0, $this->contar('wompi_eventos'));
        $this->assertStringNotContainsString($this->orden['checkout']['reference'], $ajenoHtml);
        $datos = (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->datosVista();
        $this->assertStringNotContainsString($this->orden['checkout']['reference'], json_encode($datos));
    }

    public function testIdAjenoNoModificaNingunaClinicaNiGuardaEvidencia(): void
    {
        $this->assertFalse($this->conciliador()->recuperar($this->tx['id'], 2));
        $this->assertSame(0, $this->contar('wompi_eventos'));
        $this->assertSame(0, $this->contar('suscripcion_periodos'));
        $this->assertSame(0, $this->contar('avisos_suscripcion'));
    }

    public function testIdInventadoApiCaidaYReferenciaDesconocidaNoAcreditanNiLiberanLaOrden(): void
    {
        foreach ([new RuntimeException('404'), new RuntimeException('timeout'), array_replace($this->tx, ['reference' => 'desconocida'])] as $respuesta) {
            $this->cliente->respuestas[$this->tx['id']] = $respuesta;
            $this->assertFalse($this->conciliador()->recuperar($this->tx['id'], 1));
        }
        $this->assertSame(0, $this->contar('suscripcion_periodos'));
        $this->assertSame(0, $this->contar('wompi_eventos'));
        $otra = (new PagoSuscripcion($this->db, 1, $this->config, fn () => $this->ahora))->reservar('mensual', 0, 1);
        $this->assertSame($this->orden['checkout']['reference'], $otra['checkout']['reference']);
        $this->assertSame(1, $this->contar('intentos_pago'));
    }

    public function testFormatoInvalidoNoConsultaProveedor(): void
    {
        foreach (['', '../123', '123?x=1', str_repeat('x', 129), "123\n"] as $id) {
            try {
                $this->conciliador()->recuperar($id, 1);
                $this->fail('Debe validar antes de consultar.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame([], $this->cliente->consultas);
            }
        }
    }

    public function testPendingRecuperadoQuedaDurableYElScheduleRetomaConCobrosApagados(): void
    {
        $this->cliente->respuestas[$this->tx['id']]['status'] = 'PENDING';
        $this->assertTrue($this->conciliador()->recuperar($this->tx['id'], 1));
        $this->assertSame(0, $this->contar('suscripcion_periodos'));
        $this->assertSame(0, $this->contar('avisos_suscripcion'));
        $this->assertSame('pendiente', $this->db->query('SELECT estado FROM wompi_eventos')->fetchColumn());
        $this->ahora = $this->ahora->modify('+10 minutes');
        $this->cliente->respuestas[$this->tx['id']] = $this->tx;
        $config = ClienteWompiFalso::configuracion('test', '0');
        $worker = new MantenimientoSuscripciones($this->db, fn () => $this->ahora, $this->cliente, $config);
        $worker->ejecutar(false, 'conciliacion');
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame(1, $this->contar('avisos_suscripcion'));
        $this->assertSame([], $this->cliente->creaciones);
    }

    public function testMismosCotejosDeEventoImpidenImporteMonedaEntornoOIdDiscordantes(): void
    {
        foreach ([['amount_in_cents' => 1], ['currency' => 'USD'], ['environment' => 'prod'], ['id' => 'otro']] as $cambio) {
            $this->cliente->respuestas[$this->tx['id']] = array_replace($this->tx, $cambio);
            $this->assertFalse($this->conciliador()->recuperar($this->tx['id'], 1));
            $this->assertSame(0, $this->contar('suscripcion_periodos'));
            $this->assertSame(0, $this->contar('avisos_suscripcion'));
        }
    }

    public function testManualPorReferenciaRemotaConMotivoAuditadoYFalloDeAuditoriaSinEfectos(): void
    {
        FinanzasFixture::plataforma();
        $panel = new Plataforma($this->db);
        try {
            $panel->conciliarPago($this->tx['id'], ' ', $this->conciliador());
            $this->fail('Motivo obligatorio.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame([], $this->cliente->consultas);
        }
        $this->db->exec("CREATE TRIGGER s4_no_auditar BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'sin auditoria'); END");
        try {
            $panel->conciliarPago($this->tx['id'], 'Recuperar evento perdido', $this->conciliador());
            $this->fail('Debe detenerse si no audita.');
        } catch (RuntimeException $error) {
            $this->assertSame([], $this->cliente->consultas);
            $this->assertSame(0, $this->contar('suscripcion_periodos'));
        }
        $this->db->exec('DROP TRIGGER s4_no_auditar');
        $this->assertTrue($panel->conciliarPago($this->tx['id'], 'Recuperar evento perdido', $this->conciliador()));
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $audit = $this->db->query("SELECT * FROM auditoria_sistema WHERE descripcion='Recuperar evento perdido'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(7, (int) $audit['id_usuario']);
        $this->assertStringContainsString($this->tx['id'], $audit['datos_nuevos']);
        $this->assertSame([], $panel->finanzas(2)['pagos']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEndpointManualExigePostCsrfMotivoYNoEligeLaClinicaDelFormulario(): void
    {
        session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
        session_start();
        FinanzasFixture::plataforma();
        $_SESSION['csrf_default'] = 'csrf_s4_recuperacion';
        $controller = new PlataformaController($this->db, null, $this->conciliador());
        $_POST = ['id_transaccion' => $this->tx['id'], 'motivo' => 'Evento no recibido', 'id_clinica' => '2'];
        foreach (['GET', 'POST'] as $metodo) {
            $_SERVER['REQUEST_METHOD'] = $metodo;
            ob_start();
            $controller->modificar('plataforma_conciliar_ajax');
            ob_end_clean();
            $this->assertSame(403, http_response_code());
            $this->assertSame([], $this->cliente->consultas);
        }
        $_POST['csrf_token'] = 'csrf_s4_recuperacion';
        $_POST['motivo'] = '';
        ob_start();
        $controller->modificar('plataforma_conciliar_ajax');
        ob_end_clean();
        $this->assertSame(422, http_response_code());
        $this->assertSame([], $this->cliente->consultas);
        $_POST['motivo'] = 'Evento no recibido';
        http_response_code(200);
        ob_start();
        $controller->modificar('plataforma_conciliar_ajax');
        $json = json_decode(ob_get_clean(), true);
        $this->assertTrue($json['success']);
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame(1, (int) $this->db->query('SELECT id_clinica FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT version_periodo FROM suscripciones WHERE id_clinica=2')->fetchColumn());
    }

    public static function vias(): array
    {
        return [['boton'], ['automatico'], ['retorno'], ['manual']];
    }

    #[DataProvider('vias')]
    public function testReciboUnicoPorPagoYAdministradorTrasCommitPorTodasLasVias(string $via): void
    {
        $this->db->exec("UPDATE usuario_clinica SET id_rol=1 WHERE id_clinica=1 AND id_usuario=2");
        $this->db->exec("INSERT INTO usuario_clinica(id_usuario,id_clinica,id_rol,estado) VALUES (3,1,1,'activo')");
        $this->db->exec("UPDATE usuario_clinica SET id_rol=1,estado='inactivo' WHERE id_usuario=8 AND id_clinica=1");
        $this->db->exec("INSERT INTO verificaciones_email(id_usuario,email,proposito,token_hash,expires_at) SELECT id_usuario,email,'activacion_personal','hash','2099-01-01' FROM usuarios WHERE id_usuario=3");
        if ($via === 'automatico') {
            $this->db->exec("UPDATE intentos_pago SET origen='automatico'");
            $this->tx['payment_method'] = ['type' => 'NEQUI'];
            $this->cliente->respuestas[$this->tx['id']] = $this->tx;
        }
        if ($via === 'manual') {
            FinanzasFixture::plataforma();
            (new Plataforma($this->db))->conciliarPago($this->tx['id'], 'Evento perdido', $this->conciliador());
        } elseif ($via === 'retorno') {
            $this->conciliador()->recuperar($this->tx['id'], 1);
        } else {
            $this->evento();
        }
        $this->evento();
        $this->conciliador()->recuperar($this->tx['id'], 1);
        $this->assertSame(2, $this->contar('avisos_suscripcion'));
        $this->assertSame(0, $this->contar('notificaciones'));
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        // Otro periodo no puede volver obsoleto el recibo ni cambiar su fecha.
        $this->db->exec("UPDATE suscripciones SET periodo_fin='2027-01-25 00:00:00' WHERE id_clinica=1");
        $correos = [];
        $smtp = function (array $correo) use (&$correos): bool {
            $this->assertFalse($this->db->inTransaction());
            $correos[] = $correo;
            return true;
        };
        $worker = new MantenimientoSuscripciones($this->db, fn () => $this->ahora, $this->cliente, $this->config);
        $worker->ejecutar(false, 'avisos', $smtp);
        $worker->ejecutar(false, 'avisos', $smtp);
        $this->assertCount(2, $correos);
        foreach ($correos as $correo) {
            $this->assertSame('Recibimos tu pago', $correo['asunto']);
            $this->assertStringContainsString('$89.990 COP', $correo['mensaje']);
            $this->assertStringContainsString('Mensual', $correo['mensaje']);
            $this->assertStringContainsString('24/11/2026', $correo['mensaje']);
            $this->assertStringContainsString($this->orden['checkout']['reference'], $correo['mensaje']);
            $this->assertStringContainsString($via === 'automatico' ? 'Nequi' : 'Tarjeta ···1234', $correo['mensaje']);
            $this->assertStringNotContainsString('NO_GUARDAR', $correo['mensaje']);
        }
        $this->assertStringNotContainsString('NO_GUARDAR', json_encode($this->db->query('SELECT * FROM avisos_suscripcion')->fetchAll()));
    }

    public function testFalloAlReservarReciboReviertePeriodoYPermiteRecuperarDespues(): void
    {
        $this->db->exec("CREATE TRIGGER s4_no_recibo BEFORE INSERT ON avisos_suscripcion BEGIN SELECT RAISE(ABORT,'outbox caida'); END");
        try {
            $this->conciliador()->recuperar($this->tx['id'], 1);
            $this->fail('Sin reserva no debe confirmar el periodo.');
        } catch (PDOException $error) {
            $this->assertSame(0, $this->contar('suscripcion_periodos'));
            $this->assertSame('creado', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
        }
        $this->db->exec('DROP TRIGGER s4_no_recibo');
        $this->ahora = $this->ahora->modify('+10 minutes');
        $this->conciliador()->ejecutar();
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame(1, $this->contar('avisos_suscripcion'));
    }

    public function testCorreoInciertoNoSeDuplicaNiReviertePagoYRechazoNoRecibeRecibo(): void
    {
        $this->cliente->respuestas[$this->tx['id']]['status'] = 'DECLINED';
        $this->conciliador()->recuperar($this->tx['id'], 1);
        $this->assertSame(0, $this->contar('avisos_suscripcion'));
        $this->cliente->respuestas[$this->tx['id']] = $this->tx;
        $this->conciliador()->recuperar($this->tx['id'], 1);
        $id = (int) $this->db->query('SELECT id_aviso FROM avisos_suscripcion')->fetchColumn();
        $salidas = 0;
        $smtp = function () use (&$salidas): bool {
            $this->assertFalse($this->db->inTransaction());
            $salidas++;
            throw new RuntimeException('SMTP incierto');
        };
        $aviso = new AvisoSuscripcion($this->db, 1, fn () => $this->ahora);
        $this->assertFalse($aviso->despachar($id, $smtp));
        $this->assertFalse($aviso->despachar($id, $smtp));
        $this->assertSame(1, $salidas);
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame('confirmado', $this->db->query('SELECT estado FROM pagos_suscripcion')->fetchColumn());
        $this->assertSame('incierto', $this->db->query('SELECT estado FROM avisos_suscripcion')->fetchColumn());
    }
}
