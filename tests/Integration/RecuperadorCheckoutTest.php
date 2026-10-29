<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../models/PagoSuscripcion.php';
require_once __DIR__ . '/../../models/RecuperadorCheckout.php';
require_once __DIR__ . '/../../helpers/MantenimientoSuscripciones.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';
require_once __DIR__ . '/../../helpers/Csrf.php';

final class RecuperadorCheckoutTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $ahora;
    private ClienteWompiFalso $cliente;
    private ConfiguracionWompi $config;
    private array $orden;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-26 08:00:00');
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $this->config = ClienteWompiFalso::configuracion();
        $this->cliente = new ClienteWompiFalso();
        $this->cliente->antesDeConsultar = fn () => $this->assertFalse($this->db->inTransaction(), 'HTTP bajo candado');
        putenv('APP_URL=http://localhost/Zooki/public');
        $this->orden = $this->pagos()->reservar('mensual', 0, 1);
        $this->cliente->porReferencia[$this->referencia()] = [];
    }

    protected function tearDown(): void
    {
        putenv('APP_URL');
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        Security::definirFuenteDeContextos(null);
        http_response_code(200);
    }

    private function referencia(): string
    {
        return $this->orden['checkout']['reference'];
    }

    private function pagos(): PagoSuscripcion
    {
        return new PagoSuscripcion($this->db, 1, $this->config, fn () => $this->ahora);
    }

    private function recuperar(?int $clinica = 1, bool $revisar = false): array
    {
        return (new RecuperadorCheckout($this->db, $this->cliente, $this->config, fn () => $this->ahora))->ejecutar($revisar, $clinica);
    }

    private function estado(): string
    {
        return $this->db->query('SELECT estado FROM pagos_suscripcion ORDER BY id_pago LIMIT 1')->fetchColumn();
    }

    private function transaccion(string $estado = 'PENDING'): array
    {
        return ['id' => 'tx_recuperada', 'reference' => $this->referencia(), 'status' => $estado, 'amount_in_cents' => $this->orden['total_centavos'], 'currency' => 'COP'];
    }

    public function testAbandonoDosHorasCierraConAuditoriaSinCambiarVigenciaYPermitePagar(): void
    {
        $contrato = $this->db->query('SELECT * FROM suscripciones')->fetchAll();
        $this->assertSame(0, $this->recuperar()['cerradas']);
        $this->assertSame([], $this->cliente->referenciasConsultadas);
        $this->ahora = $this->ahora->modify('+2 hours');
        $this->assertSame(1, $this->recuperar()['cerradas']);
        $this->assertSame('cancelado', $this->estado());
        $this->assertSame($contrato, $this->db->query('SELECT * FROM suscripciones')->fetchAll());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertStringContainsString('lista completa', $this->db->query('SELECT datos_nuevos FROM auditoria_sistema ORDER BY id_auditoria DESC LIMIT 1')->fetchColumn());
        $nuevo = $this->pagos()->reservar('anual', 0, 1);
        $this->assertTrue($nuevo['abrir']);
        $this->assertNotSame($this->orden['orden'], $nuevo['orden']);
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
        $this->assertSame([], $this->cliente->creaciones);
    }

    public function testCaidaO404NoLiberaNiDuplica(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        foreach (['404', 'Timeout'] as $fallo) {
            $this->cliente->porReferencia[$this->referencia()] = new RuntimeException($fallo);
            $this->assertSame(1, $this->recuperar()['errores']);
            $this->assertSame('pendiente', $this->estado());
            $reintento = $this->pagos()->reservar('mensual', 0, 1);
            $this->assertFalse($reintento['abrir']);
            $this->assertSame($this->orden['orden'], $reintento['orden']);
        }
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
    }

    public function testEncuentraPendienteOAprobadoSinEmitirCargo(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        foreach (['PENDING', 'APPROVED'] as $estado) {
            $tx = $this->transaccion($estado);
            $this->cliente->porReferencia[$this->referencia()] = [$tx];
            $this->cliente->respuestas[$tx['id']] = $tx;
            $this->assertSame(0, $this->recuperar()['cerradas']);
            $this->assertSame($estado === 'PENDING' ? 'pendiente' : 'confirmado', $this->estado());
        }
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame([], $this->cliente->creaciones);
        $this->assertSame(0, $this->recuperar()['cerradas']);
    }

    public function testRechazoEncontradoConciliaYPermiteCotizarDeNuevo(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $tx = $this->transaccion('DECLINED');
        $this->cliente->porReferencia[$this->referencia()] = [$tx];
        $this->cliente->respuestas[$tx['id']] = $tx;
        $this->recuperar();
        $nuevo = $this->pagos()->reservar('mensual', 0, 1);
        $this->assertTrue($nuevo['abrir']);
        $this->assertNotSame($this->orden['orden'], $nuevo['orden']);
    }

    public function testRespuestaDiscordanteOConsultaPorIdFallidaNoLiberan(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $tx = $this->transaccion();
        $tx['reference'] = 'otra_referencia';
        $this->cliente->porReferencia[$this->referencia()] = [$tx];
        $this->assertSame(1, $this->recuperar()['errores']);
        $this->assertSame('pendiente', $this->estado());
        $tx['reference'] = $this->referencia();
        $this->cliente->porReferencia[$this->referencia()] = [$tx];
        $this->recuperar();
        $this->assertSame('pendiente', $this->estado());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame([], $this->cliente->creaciones);
    }

    public function testNoTocaOtraClinicaNiEntornoNiAutomatico(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $this->assertSame(0, $this->recuperar(2)['pendientes']);
        $this->db->exec("UPDATE intentos_pago SET entorno='prod'");
        $this->assertSame(0, $this->recuperar()['pendientes']);
        $this->db->exec("UPDATE intentos_pago SET entorno='test',origen='automatico',estado='incierto'");
        $this->assertSame(0, $this->recuperar()['pendientes']);
        $this->assertSame([], $this->cliente->referenciasConsultadas);
        $this->assertSame('pendiente', $this->estado());
    }

    public function testNoCierraSiOtroIntentoAnteriorSigueIncierto(): void
    {
        $this->db->exec("UPDATE intentos_pago SET estado='declinado'");
        $segunda = $this->pagos()->reservar('mensual', 0, 1);
        $this->cliente->porReferencia[$segunda['checkout']['reference']] = [];
        $this->db->exec("UPDATE intentos_pago SET estado='incierto' WHERE numero=1");
        $this->ahora = $this->ahora->modify('+2 hours');
        $this->assertSame(0, $this->recuperar()['cerradas']);
        $this->assertSame([], $this->cliente->referenciasConsultadas);
        $this->db->exec("UPDATE intentos_pago SET estado='declinado' WHERE numero=1");
        $this->assertSame(1, $this->recuperar()['cerradas']);
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
    }

    public function testCambioConcurrenteInvalidaLaAusenciaRemota(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $this->cliente->antesDeConsultar = function (): void {
            $this->assertFalse($this->db->inTransaction());
            $this->db->exec("UPDATE intentos_pago SET id_transaccion_wompi='tx_concurrente',estado='pendiente'");
        };
        $this->assertSame(0, $this->recuperar()['cerradas']);
        $this->assertSame('pendiente', $this->estado());
        $this->assertFalse($this->pagos()->reservar('mensual', 0, 1)['abrir']);
    }

    public function testFalloDeAuditoriaRevierteCierreYClaves(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $antes = $this->db->query('SELECT * FROM pagos_suscripcion')->fetchAll();
        $this->db->exec("CREATE TRIGGER auditoria_falla BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'Fallo simulado'); END");
        $this->assertSame(1, $this->recuperar()['errores']);
        $this->assertSame($antes, $this->db->query('SELECT * FROM pagos_suscripcion')->fetchAll());
    }

    public function testAprobadoTardioTrasCerrarConservaEvidenciaSinConcederPeriodo(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $this->recuperar();
        $tx = $this->transaccion('APPROVED');
        $this->cliente->respuestas[$tx['id']] = $tx;
        $this->assertFalse((new ConciliadorPago($this->db, $this->cliente, $this->config, fn () => $this->ahora))->recuperar($tx['id'], 1));
        $this->assertSame('revision', $this->estado());
        $this->assertSame('PAGO_TRAS_CIERRE', $this->db->query('SELECT codigo_error FROM wompi_eventos')->fetchColumn());
        $this->assertSame('aprobado', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testScheduleRecuperaAunqueNoHayaWebhookYRevisarNoEscribeNiConsulta(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $tarea = new MantenimientoSuscripciones($this->db, fn () => $this->ahora, $this->cliente, $this->config);
        $antes = $this->db->query('SELECT * FROM pagos_suscripcion')->fetchAll();
        $this->assertSame(1, $tarea->ejecutar(true, 'conciliacion')['checkout']['pendientes']);
        $this->assertSame([], $this->cliente->referenciasConsultadas);
        $this->assertSame($antes, $this->db->query('SELECT * FROM pagos_suscripcion')->fetchAll());
        $this->assertSame(1, $tarea->ejecutar(false, 'conciliacion')['checkout']['cerradas']);
        $this->assertSame(0, $tarea->ejecutar(false, 'conciliacion')['checkout']['cerradas']);
    }

    public function testIncidenciaIntermediaNoBorraLaMarcaDeOrdenCerrada(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $this->recuperar();
        $conciliador = new ConciliadorPago($this->db, $this->cliente, $this->config, fn () => $this->ahora);
        $tx = $this->transaccion('VOIDED');
        $this->cliente->respuestas[$tx['id']] = $tx;
        $conciliador->recuperar($tx['id'], 1);
        $this->assertSame('revision', $this->estado());
        $tx['status'] = 'APPROVED';
        $this->cliente->respuestas[$tx['id']] = $tx;
        $this->assertFalse($conciliador->recuperar($tx['id'], 1));
        $this->assertSame('PAGO_TRAS_CIERRE', $this->db->query('SELECT codigo_error FROM wompi_eventos ORDER BY id_evento DESC LIMIT 1')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testPagarDelAdministradorRecuperaAntesDeReservar(): void
    {
        $this->ahora = $this->ahora->modify('+2 hours');
        $token = Csrf::token();
        $_SESSION = ['id_usuario' => 1, 'csrf_default' => $token];
        $contexto = Contexto::deClinica(1, 'Norte', Roles::ADMIN);
        Contexto::activar($contexto, 1);
        Security::definirFuenteDeContextos(static fn () => [$contexto]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf_default'];
        $_POST = ['version' => '0', 'ciclo' => 'mensual', 'csrf_token' => $_SESSION['csrf_default']];
        ob_start();
        try {
            (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->pagar();
            $respuesta = json_decode(ob_get_contents(), true, 32, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        $this->assertTrue($respuesta['ok']);
        $this->assertTrue($respuesta['pago']['abrir']);
        $this->assertSame('cancelado', $this->estado());
        $this->assertSame([], $this->cliente->creaciones);
    }
}
