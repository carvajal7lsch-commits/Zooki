<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../models/PagoSuscripcion.php';
require_once __DIR__ . '/../../models/ConciliadorPago.php';
require_once __DIR__ . '/../../controllers/WompiEventosController.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';

final class PagoSuscripcionTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $ahora;
    private ClienteWompiFalso $cliente;
    private ConfiguracionWompi $config;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-26 08:00:00');
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $this->cliente = new ClienteWompiFalso();
        $this->config = ClienteWompiFalso::configuracion();
        $this->cliente->antesDeConsultar = function (): void {
            $this->assertFalse($this->db->inTransaction(), 'HTTP bajo candado');
        };
        putenv('APP_URL=http://localhost/Zooki/public');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        unset($_SERVER['CONTENT_LENGTH'], $_SERVER['HTTP_X_EVENT_CHECKSUM']);
    }

    protected function tearDown(): void
    {
        putenv('APP_URL');
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        unset($_SERVER['CONTENT_LENGTH'], $_SERVER['HTTP_X_EVENT_CHECKSUM'], $_SERVER['CONTENT_TYPE']);
        Security::definirFuenteDeContextos(null);
        http_response_code(200);
    }

    private function pagos(int $clinica = 1): PagoSuscripcion
    {
        return new PagoSuscripcion($this->db, $clinica, $this->config, fn () => $this->ahora);
    }

    private function reservar(string $ciclo = 'mensual'): array
    {
        return $this->pagos()->reservar($ciclo, 0, 1);
    }

    private function remoto(array $orden, string $id = 'tx_1', string $status = 'APPROVED', array $cambios = []): array
    {
        return array_replace(['id' => $id, 'reference' => $orden['checkout']['reference'], 'status' => $status,
            'amount_in_cents' => $orden['total_centavos'], 'currency' => 'COP'], $cambios);
    }

    private function evento(array $tx, int $timestamp = 1): int
    {
        $evento = FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx, 'test', $timestamp), '', 'test', 'test_events_falso');
        (new EventoWompi($this->db, fn () => $this->ahora))->recibir($evento);
        return (int) $this->db->query('SELECT MAX(id_evento) FROM wompi_eventos')->fetchColumn();
    }

    private function conciliar(int $id): bool
    {
        return (new ConciliadorPago($this->db, $this->cliente, $this->config, fn () => $this->ahora))->procesar($id);
    }

    public function testAncla31CambioDeCicloYTarifaAnteriorSeConservan(): void
    {
        $this->ahora = VigenciaSuscripcion::fecha('2027-02-20 08:00:00');
        $this->db->exec("UPDATE suscripciones SET periodo_inicio='2027-01-31 00:00:00',periodo_fin='2027-02-28 00:00:00' WHERE id_clinica=1");
        $orden = $this->reservar();
        $this->db->exec('UPDATE planes SET precio_mensual=120000 WHERE id_plan=2');
        $tx = $this->remoto($orden);
        $this->cliente->respuestas['tx_1'] = $tx;
        $this->assertTrue($this->conciliar($this->evento($tx)));
        $this->assertSame('2027-03-31 00:00:00', $this->db->query('SELECT fin FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame(8999000, (int) $this->db->query('SELECT total_centavos FROM pagos_suscripcion')->fetchColumn());
        $this->assertSame('2027-01-31 00:00:00', $this->db->query('SELECT periodo_inicio FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $anual = $this->pagos()->reservar('anual', 1, 1);
        $tx = $this->remoto($anual, 'anual');
        $this->cliente->respuestas['anual'] = $tx;
        $this->conciliar($this->evento($tx, 2));
        $this->assertSame('2028-03-31 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame('2027-03-31 00:00:00', $this->db->query('SELECT inicio FROM suscripcion_periodos ORDER BY id_periodo DESC LIMIT 1')->fetchColumn());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testPostManipuladoNoEligeMontoBeneficiarioNiFirmaYRetornoNoAcredita(): void
    {
        session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
        session_start();
        $contexto = Contexto::deClinica(1, 'Norte', Roles::ADMIN);
        $_SESSION = ['id_usuario' => 1, 'csrf_default' => str_repeat('c', 64)];
        Contexto::activar($contexto, 1);
        Security::definirFuenteDeContextos(static fn () => [$contexto]);
        $_POST = ['csrf_token' => str_repeat('c', 64), 'version' => '0', 'ciclo' => 'anual', 'monto' => '1', 'id_plan' => '1', 'firma' => 'falsa'];
        ob_start();
        (new SuscripcionController($this->db, fn () => $this->ahora, $this->config))->pagar();
        $respuesta = json_decode(ob_get_clean(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame(89990000, $respuesta['pago']['total_centavos']);
        $this->assertSame('anual', $respuesta['pago']['ciclo']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['action' => 'suscripcion_retorno', 'referencia' => $respuesta['pago']['checkout']['reference'], 'id' => 'id_falso', 'status' => 'APPROVED'];
        ob_start();
        (new SuscripcionController($this->db, fn () => $this->ahora, $this->config, $this->cliente))->retorno();
        $html = ob_get_clean();
        $this->assertStringContainsString('El pago sigue pendiente de confirmación', $html);
        $this->assertStringContainsString('24/10/2026', $html);
        $this->assertStringContainsString('27/10/2026', $html);
        $this->assertStringNotContainsString('evento verificado, nunca por el retorno', $html);
        $this->assertStringNotContainsString('prv_test_falsa', $html);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame(['id_falso'], $this->cliente->consultas);
    }

    public function testRevisarConciliacionNoConsultaNiEscribeYComandoNormalRetoma(): void
    {
        require_once __DIR__ . '/../../helpers/MantenimientoSuscripciones.php';
        $tx = $this->remoto($this->reservar());
        $this->evento($tx);
        $this->cliente->respuestas['tx_1'] = $tx;
        $worker = new MantenimientoSuscripciones($this->db, fn () => $this->ahora, $this->cliente, $this->config);
        $r = $worker->ejecutar(true, 'conciliacion');
        $this->assertSame(1, $r['conciliacion']['pendientes']);
        $this->assertSame([], $this->cliente->consultas);
        $this->assertSame('pendiente', $this->db->query('SELECT estado FROM wompi_eventos')->fetchColumn());
        $r = $worker->ejecutar(false, 'conciliacion');
        $this->assertSame(1, $r['conciliacion']['conciliados']);
        $this->assertSame('confirmado', $this->db->query('SELECT estado FROM pagos_suscripcion')->fetchColumn());
        $q = $this->db->prepare("INSERT INTO wompi_eventos (clave_evento,entorno,tipo,id_transaccion_wompi,timestamp_proveedor,checksum,huella_datos_firmados,estado,recibido_en,proxima_revision) VALUES (?,'test','transaction.updated',?,1,?,?,'pendiente','2026-10-25 00:00:00','2026-10-27 00:00:00')");
        for ($n = 0; $n < 100; $n++) {
            $hash = hash('sha256', 'postergado_' . $n);
            $q->execute([$hash, 'postergado_' . $n, $hash, $hash]);
        }
        $siguiente = $this->pagos()->reservar('mensual', 1, 1);
        $tx2 = $this->remoto($siguiente, 'nuevo_tras_lote');
        $this->cliente->respuestas['nuevo_tras_lote'] = $tx2;
        $this->evento($tx2, 2);
        $r = $worker->ejecutar(false, 'conciliacion');
        $this->assertSame(1, $r['conciliacion']['conciliados'], 'Cien reintentos futuros no deben dejar sin acreditar otro pago.');
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM pagos_suscripcion WHERE estado='confirmado'")->fetchColumn());
    }

    public function testDosPestanasCiclosDistintosMismaOrdenImporteServidorYSinSecretos(): void
    {
        $a = $this->reservar();
        $b = $this->reservar('anual');
        $this->assertSame($a, $b);
        $this->assertSame(8999000, $a['checkout']['amountInCents']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM pagos_suscripcion')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
        $this->assertStringNotContainsString('prv_', json_encode($a));
        $this->assertStringNotContainsString('events_falso', json_encode($a));
        $this->assertStringNotContainsString('integrity_falso', json_encode($a));
        $this->ahora = $this->ahora->modify('+16 minutes');
        $this->assertFalse($this->reservar()['abrir']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
    }

    public function testReservaSinSuscripcionNuncaConcedeBeneficiosNiInventaLegado(): void
    {
        $this->db->exec('DELETE FROM suscripciones WHERE id_clinica=1');
        $this->assertSame('Gratuito', $this->pagos()->estadoLocal()['plan']);
        $reserva = $this->reservar();
        $this->assertTrue($reserva['abrir']);
        $this->assertSame('Gratuito', $this->pagos()->estadoLocal()['plan']);
        $this->assertSame('sin_vigencia', $this->pagos()->estadoLocal()['situacion']);
        $this->assertSame('gestionada', $this->db->query('SELECT gestion_cobro FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testEventoAntesDeRespuestaDuplicadosDesordenadosYPeriodoEnGracia(): void
    {
        $orden = $this->reservar();
        $tx = $this->remoto($orden);
        $id = $this->evento($tx);
        $this->cliente->respuestas['tx_1'] = $tx;
        $this->assertTrue($this->conciliar($id));
        $this->assertSame('2026-11-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertFalse($this->conciliar($id));
        $this->assertFalse((new EventoWompi($this->db))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx), '', 'test', 'test_events_falso')) && $this->conciliar($id));
        $otro = $this->evento($tx, 2);
        $this->assertTrue($this->conciliar($otro));
        $this->cliente->respuestas['tx_1']['status'] = 'PENDING';
        $this->assertTrue($this->conciliar($this->evento($tx, 3)));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos WHERE origen="wompi"')->fetchColumn());
        $this->assertSame('aprobado', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
        $respuestaTardia = $this->reservar();
        $this->assertFalse($respuestaTardia['abrir']);
        $this->assertSame('confirmado', $respuestaTardia['estado']);
    }

    public function testApiCaida404PendienteReintentoYAuditoriaFallidaRetomable(): void
    {
        $orden = $this->reservar();
        $tx = $this->remoto($orden);
        $id = $this->evento($tx);
        $this->assertFalse($this->conciliar($id));
        $this->assertSame('pendiente', $this->db->query('SELECT estado FROM wompi_eventos')->fetchColumn());
        $this->ahora = $this->ahora->modify('+6 minutes');
        $this->cliente->respuestas['tx_1'] = $tx;
        $this->db->exec("CREATE TRIGGER fallo_pago BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='suscripcion_periodos' BEGIN SELECT RAISE(ABORT,'fallo simulado'); END");
        try {
            $this->conciliar($id);
            $this->fail('Debe revertir la acreditación.');
        } catch (RuntimeException $error) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        }
        $this->db->exec('DROP TRIGGER fallo_pago');
        $this->ahora = $this->ahora->modify('+6 minutes');
        $this->assertTrue($this->conciliar($id));
        $this->assertSame(1, (int) $this->db->query('SELECT version_periodo FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame('Profesional', $this->pagos()->estadoLocal()['plan']);
    }

    public function testMontosEntornoMonedaReferenciaIdDesconocidosNuncaAcreditan(): void
    {
        $orden = $this->reservar();
        foreach ([['amount_in_cents' => 1], ['currency' => 'USD'], ['environment' => 'prod'], ['reference' => 'desconocida'], ['id' => 'otro'], ['merchant' => ['public_key' => 'pub_test_otro']], ['merchant_id' => 999]] as $n => $cambio) {
            $tx = $this->remoto($orden, 'tx_' . $n);
            $id = $this->evento($tx, $n);
            $this->cliente->respuestas['tx_' . $n] = array_replace($tx, $cambio);
            $this->assertFalse($this->conciliar($id));
        }
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame(7, (int) $this->db->query("SELECT COUNT(*) FROM wompi_eventos WHERE estado='revision'")->fetchColumn());
    }

    public function testPagoAnualTrasCongelarNoLevantaSuspensionYAnulacionConservaPeriodo(): void
    {
        $this->ahora = VigenciaSuscripcion::fecha('2026-10-29 08:00:00');
        $orden = $this->reservar('anual');
        $this->db->exec("UPDATE clinicas SET estado='suspendida' WHERE id_clinica=1");
        $tx = $this->remoto($orden);
        $this->cliente->respuestas['tx_1'] = $tx;
        $this->assertTrue($this->conciliar($this->evento($tx)));
        $this->assertSame('2027-10-29 08:00:00', $this->db->query('SELECT fin FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame('suspendida', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica=1')->fetchColumn());
        $this->assertSame('Gratuito', $this->pagos()->estadoLocal()['plan']);
        $this->cliente->respuestas['tx_1']['status'] = 'VOIDED';
        $this->assertFalse($this->conciliar($this->evento($tx, 2)));
        $this->assertSame('confirmado', $this->db->query('SELECT estado FROM pagos_suscripcion')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testCotizacionFallidaVencidaUsaNuevaTarifaPeroPendienteNunca(): void
    {
        $orden = $this->reservar();
        $tx = $this->remoto($orden, 'rechazo', 'DECLINED');
        $this->cliente->respuestas['rechazo'] = $tx;
        $this->conciliar($this->evento($tx));
        $this->ahora = $this->ahora->modify('+16 minutes');
        $this->db->exec('UPDATE planes SET precio_mensual=90000 WHERE id_plan=2');
        $nueva = $this->reservar();
        $this->assertSame(9000000, $nueva['total_centavos']);
        $this->assertNotSame($orden['orden'], $nueva['orden']);
        $this->assertSame('cancelado', $this->db->query('SELECT estado FROM pagos_suscripcion ORDER BY id_pago LIMIT 1')->fetchColumn());
        $this->assertSame($nueva, $this->reservar());
        $this->ahora = $this->ahora->modify('+16 minutes');
        $this->assertFalse($this->reservar()['abrir']);
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM pagos_suscripcion')->fetchColumn());
    }

    public function testVolverDosHorasDespuesSinReferenciaDistinguePlanAlDiaYOrdenSinConfirmar(): void
    {
        $this->db->exec("UPDATE suscripciones SET periodo_fin='2026-11-25 00:00:00' WHERE id_clinica=1");
        $orden = $this->reservar();
        $this->ahora = $this->ahora->modify('+2 hours');
        $antes = $this->db->query('SELECT * FROM intentos_pago')->fetchAll(PDO::FETCH_ASSOC);
        $consulta = $this->pagos()->estadoLocal();
        $this->assertSame('al_dia', $consulta['situacion']);
        $this->assertSame(['estado' => 'pendiente', 'intento' => 'creado', 'cotizacion_vencida' => true], $consulta['pago']);
        $this->assertSame($antes, $this->db->query('SELECT * FROM intentos_pago')->fetchAll(PDO::FETCH_ASSOC), 'Consultar no cambia el resultado financiero.');
        $this->assertSame($consulta['pago'], $this->pagos()->estadoLocal($orden['checkout']['reference'])['pago']);
        $this->assertArrayNotHasKey('pago', $this->pagos(2)->estadoLocal());
        $reapertura = $this->reservar();
        $this->assertFalse($reapertura['abrir']);
        $this->assertSame($orden['orden'], $reapertura['orden']);
        $this->assertSame('incierto', $this->pagos()->estadoLocal()['pago']['intento']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM pagos_suscripcion')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testConsultaSinReferenciaUsaUltimoIntentoYSuEntorno(): void
    {
        $orden = $this->reservar();
        $tx = $this->remoto($orden, 'rechazo_estado', 'DECLINED');
        $this->cliente->respuestas['rechazo_estado'] = $tx;
        $this->conciliar($this->evento($tx));
        $this->assertSame('declinado', $this->pagos()->estadoLocal()['pago']['intento']);
        $this->reservar();
        $this->assertSame('creado', $this->pagos()->estadoLocal()['pago']['intento']);
        $otroEntorno = new PagoSuscripcion($this->db, 1, ClienteWompiFalso::configuracion('prod'), fn () => $this->ahora);
        $this->assertArrayNotHasKey('pago', $otroEntorno->estadoLocal());
        $this->expectException(AccesoDenegado::class);
        $otroEntorno->estadoLocal($orden['checkout']['reference']);
    }

    public function testSinPasarelaConfiguradaSigueDisponibleElPlanSinExponerOrdenesDeOtroEntorno(): void
    {
        $this->reservar();
        $sinPasarela = new PagoSuscripcion($this->db, 1, new ConfiguracionWompi([]), fn () => $this->ahora);
        $estado = $sinPasarela->estadoLocal();
        $this->assertSame('Profesional', $estado['plan']);
        $this->assertArrayNotHasKey('pago', $estado);
    }

    public function testOtroCargoRealMismaReferenciaConservaEvidenciaSinSegundoPeriodo(): void
    {
        $orden = $this->reservar();
        foreach (['primero', 'segundo'] as $id) {
            $tx = $this->remoto($orden, $id);
            $this->cliente->respuestas[$id] = $tx;
            $this->conciliar($this->evento($tx));
        }
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn());
        $this->assertSame('COBRO_DUPLICADO', $this->db->query('SELECT codigo_error FROM wompi_eventos ORDER BY id_evento DESC LIMIT 1')->fetchColumn());
    }

    public function testBancoApruebaIntentoAntesRechazadoTrasPagarOtroIntento(): void
    {
        $primero = $this->reservar();
        $tx = $this->remoto($primero, 'antes_rechazado', 'DECLINED');
        $this->cliente->respuestas['antes_rechazado'] = $tx;
        $this->conciliar($this->evento($tx));
        $segundo = $this->reservar();
        $this->assertSame($primero['orden'], $segundo['orden']);
        $this->assertNotSame($primero['checkout']['reference'], $segundo['checkout']['reference']);
        $tx2 = $this->remoto($segundo, 'nuevo_intento');
        $this->cliente->respuestas['nuevo_intento'] = $tx2;
        $this->conciliar($this->evento($tx2, 2));
        $tx['status'] = 'APPROVED';
        $this->cliente->respuestas['antes_rechazado'] = $tx;
        $this->assertFalse($this->conciliar($this->evento($tx, 3)));
        $this->assertSame('COBRO_DUPLICADO', $this->db->query('SELECT codigo_error FROM wompi_eventos ORDER BY id_evento DESC LIMIT 1')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }

    public function testWebhookFirmaEntornoTamanoDurabilidadYBaseCaida(): void
    {
        $_SESSION = [];
        Security::autorizar('wompi_eventos_test');
        $tx = $this->remoto($this->reservar());
        $cuerpo = ClienteWompiFalso::cuerpo($tx);
        $c = new WompiEventosController($this->db, $this->config);
        $c->recibir('test', $cuerpo);
        $this->assertSame(200, http_response_code());
        $c->recibir('test', $cuerpo);
        $this->assertSame(200, http_response_code());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM wompi_eventos')->fetchColumn());
        $soloEventos = new ConfiguracionWompi(['ENTORNO' => 'sandbox', 'SECRETO_EVENTOS' => 'test_events_falso']);
        (new WompiEventosController($this->db, $soloEventos))->recibir('test', ClienteWompiFalso::cuerpo($tx, 'test', 2));
        $this->assertSame(200, http_response_code(), 'La falta de llave API no debe perder un evento auténtico.');
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM wompi_eventos')->fetchColumn());
        $c->recibir('prod', $cuerpo);
        $this->assertSame(401, http_response_code());
        (new WompiEventosController($this->db, ClienteWompiFalso::configuracion('prod')))->recibir('test', $cuerpo);
        $this->assertSame(401, http_response_code());
        $c->recibir('test', str_replace('APPROVED', 'DECLINED', $cuerpo));
        $this->assertSame(401, http_response_code());
        $c->recibir('test', str_repeat('x', 65537));
        $this->assertSame(413, http_response_code());
        $this->db->exec('DROP TABLE wompi_eventos');
        $c->recibir('test', $cuerpo);
        $this->assertSame(503, http_response_code());
        $this->assertSame([], $this->cliente->consultas);
    }

    public function testPermisosRolesDosClinicasCsrfRetornoFalsoYLecturasSinMutar(): void
    {
        $orden = $this->reservar();
        foreach ([2 => Roles::VETERINARIO, 6 => Roles::PROPIETARIO, 7 => Roles::SUPER_ADMIN] as $id => $rol) {
            $_SESSION = ['id_usuario' => $id];
            $contexto = $rol === Roles::SUPER_ADMIN ? Contexto::dePlataforma() : ($rol === Roles::PROPIETARIO ? Contexto::dePropietario() : Contexto::deClinica(1, 'Norte', $rol));
            Contexto::activar($contexto, 1);
            Security::definirFuenteDeContextos(static fn () => [$contexto]);
            foreach (['suscripcion_pagar_ajax', 'suscripcion_estado_ajax', 'suscripcion_historial_ajax', 'suscripcion_retorno'] as $accion) {
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
        try {
            Security::autorizar('suscripcion_pagar_ajax');
            $this->fail('Sin CSRF');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $this->assertSame([], $this->pagos(2)->historial()['filas']);
        try {
            $this->pagos(2)->estadoLocal($orden['checkout']['reference']);
            $this->fail('Referencia ajena');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $this->assertSame('pendiente', $this->pagos()->estadoLocal($orden['checkout']['reference'])['pago']['estado']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripcion_periodos')->fetchColumn());
    }
}

