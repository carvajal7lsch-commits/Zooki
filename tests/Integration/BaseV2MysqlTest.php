<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Migrador.php';
require_once __DIR__ . '/../../helpers/CreadorSuperAdmin.php';

/**
 * Plan M0, etapa B — La base v2 limpia contra un MySQL o MariaDB real.
 *
 * El esquema usa columnas calculadas, JSON, information_schema y GET_LOCK, que
 * SQLite no tiene; por eso esta prueba necesita un servidor. Se configura solo
 * con variables de entorno (nunca con .env, para no tocar la base de la app):
 *
 *   ZOOKI_TEST_MYSQL_HOST  (obligatoria; sin ella la prueba se salta)
 *   ZOOKI_TEST_MYSQL_PORT  (3306)
 *   ZOOKI_TEST_MYSQL_USER  (root)
 *   ZOOKI_TEST_MYSQL_PASS  ('')
 *   ZOOKI_TEST_MYSQL_DB    (zooki_test_base_v2) — se BORRA y se crea en cada prueba
 *
 * En CI la provee el servicio MySQL 8 de .github/workflows/ci.yml.
 */
class BaseV2MysqlTest extends TestCase
{
    public function testPautaMigracion16DobleConservaContenido(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->db->exec('ALTER TABLE grafo_aristas DROP COLUMN pauta');
        $this->cargar('16_pautas_tratamiento.sql');
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        (new CargadorConocimiento($this->db))->cargar(__DIR__ . '/../Fixtures/conocimiento/pautas');
        $antes = $this->db->query('SELECT codigo, pauta FROM grafo_aristas ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);
        $this->cargar('16_pautas_tratamiento.sql');
        $this->assertSame($antes, $this->db->query('SELECT codigo, pauta FROM grafo_aristas ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertCount(8, $antes);
        $this->assertNotNull(array_column($antes, 'pauta', 'codigo')['trata.prueba']);
    }

    public function testCorrecciones230Migracion15DoblePreservaTiposYConfiguraSoloSuClinica(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/ConfiguracionClinica.php';
        require_once __DIR__ . '/../../models/CopiaCatalogosClinica.php';
        $this->db->exec('ALTER TABLE clinicas DROP COLUMN anticipacion_cambios_min');
        $this->db->exec('ALTER TABLE tipos_cita DROP COLUMN solo_personal');
        $this->db->exec("INSERT INTO tipos_cita (id_tipo_cita,id_clinica,nombre_tipo,duracion_minutos,margen_minutos,pausable) VALUES (99,1,'Cirugía programada',150,20,1)");
        $this->db->exec("INSERT INTO clinicas (id_clinica,nombre,nit,estado) VALUES (44,'Pendiente','900111444','pendiente_verificacion')");
        $this->cargar('15_correcciones_agenda.sql');
        $this->cargar('15_correcciones_agenda.sql');
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM tipos_cita WHERE id_clinica=1 AND nombre_tipo='Cirugía programada'")->fetchColumn());
        $this->assertSame(150, (int) $this->db->query('SELECT duracion_minutos FROM tipos_cita WHERE id_tipo_cita=99')->fetchColumn());
        $this->assertSame(['120', '15', '0', '1'], array_map('strval', array_values($this->db->query("SELECT duracion_minutos,margen_minutos,pausable,solo_personal FROM tipos_cita WHERE id_clinica=2 AND nombre_tipo='Cirugía programada'")->fetch(PDO::FETCH_ASSOC))));
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM tipos_cita WHERE id_clinica=44')->fetchColumn());
        $this->db->exec("UPDATE clinicas SET estado='activa' WHERE id_clinica=44");
        (new CopiaCatalogosClinica($this->db))->copiar(44);
        $this->assertSame(7, (int) $this->db->query('SELECT COUNT(*) FROM tipos_cita WHERE id_clinica=44')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT solo_personal FROM tipos_cita WHERE id_clinica=44 AND nombre_tipo='Cirugía programada'")->fetchColumn());
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $configuracion = new ConfiguracionClinica($this->db);
        $marca = $configuracion->leer()['marca'];
        $configuracion->guardarMarca(array_replace($marca, ['anticipacion_cambios_min' => '0']));
        $configuracion->guardarTipo(99, ['nombre_tipo' => 'Cirugía programada', 'duracion_minutos' => '150', 'margen_minutos' => '20', 'pausable' => '1', 'solo_personal' => '1']);
        $this->cargar('15_correcciones_agenda.sql');
        $this->assertSame(0, (int) $this->db->query('SELECT anticipacion_cambios_min FROM clinicas WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(30, (int) $this->db->query('SELECT anticipacion_cambios_min FROM clinicas WHERE id_clinica=2')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT solo_personal FROM tipos_cita WHERE id_tipo_cita=99')->fetchColumn());
    }

    public function testTriageProvisionalReservaYRevisionPosteriorConVacunasGlobales(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/LlegadaAgenda.php';
        DosClinicas::poblarAgenda($this->db);
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        $this->comoVeterinario(1, 2);
        $reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00');
        $agenda = new SolicitudAgenda($this->db, $reloj);
        $entrada = ['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07',
            'sintomas' => [['codigo' => 'sintoma.otro_no_se', 'tiempo' => 'no_se']]];
        foreach (['verde', 'naranja', 'rojo'] as $nivel) {
            $r = $agenda->evaluar($entrada);
            $this->assertTrue($r['reservable']);
            $hora = $agenda->horas($r['id_solicitud'], $entrada)[0];
            $guardada = $agenda->reservar($entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => $hora, 'ignore_warning' => '1']);
            $antes = (new Cita($this->db))->getById($guardada['id_cita']);
            $agenda->revisar($r['id_solicitud'], $nivel, 'Valoración posterior a reservar', $r['huella'], null);
            $cita = (new Cita($this->db))->getById($guardada['id_cita']);
            $this->assertSame($nivel, $cita['prioridad']);
            $this->assertSame('amarillo', $cita['prioridad_calculada']);
            if ($nivel === 'verde') {
                $this->assertSame($antes['hora'], $cita['hora']);
            }
        }
        $this->assertCount(1, (new UrgenciaAgenda($this->db, $reloj))->listar());
        $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='citas' AND descripcion LIKE 'Reclasificación veterinaria%'")->fetchColumn());
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $paciente = new PacienteDiagnostico($this->db);
        $this->assertNull($paciente->contexto(1, $grafo, $reloj(), [])['vacunacion']);
        $this->db->exec("INSERT INTO vacunas (id_mascota,id_clinica,nombre_vacuna,fecha_aplicacion,fecha_proxima_dosis) VALUES (1,2,'Rabia','2029-01-01','2031-01-01')");
        $this->assertSame('completa', $paciente->contexto(1, $grafo, $reloj(), [])['vacunacion']);
        $this->db->exec("UPDATE vacunas SET fecha_proxima_dosis='2029-01-02'");
        $this->assertSame('incompleta', $paciente->contexto(1, $grafo, $reloj(), [])['vacunacion']);
        $this->assertSame('completa', $paciente->contexto(1, $grafo, $reloj(), ['vacunacion' => 'completa'])['vacunacion']);
    }

    public static function carrerasCheckout(): array
    {
        return [['checkout_workers', 0, 'cancelado'], ['checkout_evento_primero', 1, 'confirmado'], ['checkout_cierre_primero', 0, 'revision']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('carrerasCheckout')]
    public function testCheckoutCierreYConciliacionCompartenCandado(string $fase, int $periodos, string $estado): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
        require_once __DIR__ . '/../../models/PagoSuscripcion.php';
        require_once __DIR__ . '/../../models/EventoWompi.php';
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        putenv('APP_URL=http://localhost/Zooki/public');
        try {
            $orden = (new PagoSuscripcion($this->db, 1, ClienteWompiFalso::configuracion(), static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00')))->reservar('mensual', 0, 1);
        } finally {
            putenv('APP_URL');
        }
        if ($fase !== 'checkout_workers') {
            $tx = ['id' => 'tx_simultanea', 'reference' => $orden['checkout']['reference'], 'status' => 'APPROVED', 'amount_in_cents' => 8999000, 'currency' => 'COP'];
            (new EventoWompi($this->db))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx), '', 'test', 'test_events_falso'));
        }
        $resultados = $this->competirS2($fase);
        $this->assertSame($periodos, $this->contar('suscripcion_periodos'));
        $this->assertSame($estado, $this->db->query('SELECT estado FROM pagos_suscripcion')->fetchColumn());
        $this->assertSame(1, $this->contar('pagos_suscripcion'));
        $this->assertSame(1, $this->contar('intentos_pago'));
        if ($fase === 'checkout_workers') {
            $this->assertSame(1, array_sum(array_column(array_column($resultados, 'resultado'), 'cerradas')));
        }
    }

    public function testS4ArranqueDockerSqlCompletoSeguidoDeMigrador(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        foreach (glob(self::DATABASE . '/*.sql') as $ruta) {
            if (preg_match('/^(\d+)_/', basename($ruta), $numero) && (int) $numero[1] >= 3) {
                $this->cargar(basename($ruta));
            }
        }
        // Docker primero ejecuta SQL sin bitácora; PHP después repite las migraciones.
        $this->migrador()->migrar();
        $this->assertSame(['ejecutadas' => [], 'semilla' => true], $this->migrador()->migrar());
        $tipo = $this->db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='tipo'")->fetchColumn();
        $this->assertStringContainsString('pago_confirmado', $tipo);
        $this->assertSame(0, $this->contar('avisos_suscripcion'));
    }

    public static function ordenesRecuperacionS4(): array
    {
        return [['s4_retorno_evento'], ['s4_evento_retorno']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ordenesRecuperacionS4')]
    public function testS4RetornoYEventoConcurrentesUnPeriodoYUnRecibo(string $fase): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
        require_once __DIR__ . '/../../models/PagoSuscripcion.php';
        require_once __DIR__ . '/../../models/EventoWompi.php';
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        putenv('APP_URL=http://localhost/Zooki/public');
        try {
            $orden = (new PagoSuscripcion($this->db, 1, ClienteWompiFalso::configuracion(), static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00')))->reservar('mensual', 0, 1);
        } finally {
            putenv('APP_URL');
        }
        $tx = ['id' => 'tx_simultanea', 'reference' => $orden['checkout']['reference'], 'status' => 'APPROVED', 'amount_in_cents' => 8999000, 'currency' => 'COP'];
        foreach ([1, 2] as $timestamp) {
            (new EventoWompi($this->db))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx, 'test', $timestamp), '', 'test', 'test_events_falso'));
        }
        $this->competirS2($fase);
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM avisos_suscripcion WHERE tipo='pago_confirmado'")->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT version_periodo FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='suscripcion_periodos' AND accion='INSERT'")->fetchColumn());
    }

    public function testS4Migracion14DobleParcialPreservaOutboxYDespachaRecibo(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../Support/FinanzasFixture.php';
        putenv('APP_URL=http://localhost/Zooki/public');
        try {
            FinanzasFixture::datos($this->db);
            $this->db->exec("DELETE FROM avisos_suscripcion WHERE tipo='pago_confirmado'");
            $this->db->exec('ALTER TABLE avisos_suscripcion DROP COLUMN medio_pago');
            $this->db->exec("ALTER TABLE avisos_suscripcion MODIFY tipo ENUM('vence_7','vence_1','congelada','cobro_fallido') NOT NULL");
            // Fallo parcial tras agregar la columna, antes de ampliar el ENUM.
            $this->db->exec('ALTER TABLE avisos_suscripcion ADD COLUMN medio_pago VARCHAR(32) DEFAULT NULL');
            $this->cargar('14_aviso_pago_confirmado.sql');
            $this->cargar('14_aviso_pago_confirmado.sql');
            $this->db->exec("INSERT INTO avisos_suscripcion(id_clinica,id_suscripcion,id_pago,id_intento,id_usuario_destinatario,tipo,medio_pago,clave_aviso,vencimiento_objetivo,programado_en) VALUES(1,1,1,1,1,'pago_confirmado','Nequi','recibo_mysql','2026-11-25 00:00:00','2026-10-26 08:00:00')");
            $id = (int) $this->db->lastInsertId();
            $enviados = 0;
            $aviso = new AvisoSuscripcion($this->db, 1, static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00'));
            $smtp = function (array $correo) use (&$enviados): bool {
                $this->assertFalse($this->db->inTransaction());
                $this->assertSame('Recibimos tu pago', $correo['asunto']);
                $this->assertStringContainsString('Nequi', $correo['mensaje']);
                $enviados++;
                return true;
            };
            $this->assertTrue($aviso->despachar($id, $smtp));
            $this->assertFalse($aviso->despachar($id, $smtp));
            $this->assertSame(1, $enviados);
            $this->assertSame(1, $this->contar('suscripcion_periodos'));
        } finally {
            putenv('APP_URL');
            $_SESSION = [];
        }
    }

    public function testS4MigracionRepetidaParcialYRevisionAuditadaConDatosReales(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../Support/FinanzasFixture.php';
        putenv('APP_URL=http://localhost/Zooki/public');
        try {
            FinanzasFixture::datos($this->db);
            FinanzasFixture::plataforma();
            $this->db->exec('ALTER TABLE wompi_eventos DROP FOREIGN KEY fk_evento_revisor');
            $this->db->exec('ALTER TABLE wompi_eventos DROP COLUMN revisado_por, DROP COLUMN revisado_en, DROP COLUMN nota_revision');
            $this->db->exec('ALTER TABLE suscripciones DROP COLUMN motivo_automatico_apagado');
            // Simula interrupción después del primer DDL, sin pérdida de pagos existentes.
            $this->db->exec('ALTER TABLE suscripciones ADD COLUMN motivo_automatico_apagado VARCHAR(40) DEFAULT NULL');
            $this->cargar('13_plataforma_finanzas.sql');
            $this->cargar('13_plataforma_finanzas.sql');
            $panel = new Plataforma($this->db);
            $id = (int) $panel->finanzas(1)['incidencias'][0]['id_evento'];
            $panel->revisarIncidencia($id, 1, 'Revisada en MySQL');
            $panel->revisarIncidencia($id, 1, 'Doble clic');
            $this->assertSame('Revisada en MySQL', $panel->finanzas(1)['incidencias'][0]['nota_revision']);
            $this->assertSame([], $panel->finanzas(2)['pagos']);
            $this->assertSame(1, $this->contar('suscripcion_periodos'));
            $this->assertSame(1, $this->contar('pagos_suscripcion'));
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion='Revisión financiera sin alterar el pago (RE-0.3.7)'")->fetchColumn());
        } finally {
            putenv('APP_URL');
            $_SESSION = [];
        }
    }

    private function prepararCobroS3(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/CobroAutomatico.php';
        require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $cliente = new ClienteWompiFalso();
        $cliente->datosComercio = ClienteWompiFalso::contratos();
        $fuente = new FuentePago($this->db, 1, $cliente, ClienteWompiFalso::configuracion('test', '1', '1'), static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00'));
        $version = AutorizacionCobro::contratos($cliente->datosComercio)['version'];
        $fuente->guardar('CARD', 'tok_test_mysql', true, true, $version, 1);
        $fuente->automatico(true, $version, 8999000, 1);
    }

    public function testS3DosTrabajadoresUnSoloPost(): void
    {
        $this->prepararCobroS3();
        $r = $this->competirS2('s3_workers');
        $this->assertSame(1, array_sum(array_column(array_column($r, 'resultado'), 'posts')));
        $this->assertSame(1, $this->contar('pagos_suscripcion'));
        $this->assertSame(1, $this->contar('intentos_pago'));
        $this->assertSame('pendiente', $this->db->query('SELECT estado FROM intentos_pago')->fetchColumn());
    }

    public function testS3BotonPrimeroExcluyeAutomatico(): void
    {
        $this->prepararCobroS3();
        $r = $this->competirS2('s3_manual');
        $this->assertSame(0, array_sum(array_column(array_column($r, 'resultado'), 'posts')));
        $this->assertSame(1, $this->contar('pagos_suscripcion'));
        $this->assertSame(1, $this->contar('intentos_pago'));
        $this->assertSame('boton', $this->db->query('SELECT origen FROM intentos_pago')->fetchColumn());
    }

    public function testS3AutomaticoPrimeroExcluyeWidget(): void
    {
        $this->prepararCobroS3();
        $r = $this->competirS2('s3_auto_manual');
        $this->assertSame(1, array_sum(array_column(array_column($r, 'resultado'), 'posts')));
        $this->assertFalse($r[1]['resultado']['operacion']['abrir']);
        $this->assertSame(1, $this->contar('pagos_suscripcion'));
        $this->assertSame(1, $this->contar('intentos_pago'));
    }

    public function testS3RetiroConcurrenteAntesDeReclamarEvitaPost(): void
    {
        $this->prepararCobroS3();
        $r = $this->competirS2('s3_retiro');
        $this->assertSame(0, array_sum(array_column(array_column($r, 'resultado'), 'posts')));
        $this->assertSame(0, $this->contar('intentos_pago'));
        $this->assertSame(0, (int) $this->db->query('SELECT cobro_automatico FROM suscripciones WHERE id_clinica=1')->fetchColumn());
    }

    public function testS3RetiroTrasReclamacionDetieneSiguientesYConservaCargo(): void
    {
        $this->prepararCobroS3();
        $r = $this->competirS2('s3_auto_retiro');
        $this->assertSame(1, array_sum(array_column(array_column($r, 'resultado'), 'posts')));
        $this->assertStringContainsString('cargo en curso', $r[1]['resultado']['operacion']['mensaje']);
        $this->assertSame('retirada', $this->db->query('SELECT estado FROM fuentes_pago')->fetchColumn());
        $this->assertSame(1, $this->contar('intentos_pago'));
    }

    public function testS3DosReceptoresAcreditanSinCliUnaVez(): void
    {
        $this->prepararCobroS3();
        $url = getenv('APP_URL');
        putenv('APP_URL=http://localhost/Zooki/public');
        try {
            (new PagoSuscripcion($this->db, 1, ClienteWompiFalso::configuracion(), static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00')))->reservar('mensual', 0, 1);
        } finally {
            putenv($url === false ? 'APP_URL' : 'APP_URL=' . $url);
        }
        $r = $this->competirS2('s3_receptor');
        $this->assertSame([200, 200], array_column(array_column($r, 'resultado'), 'codigo'));
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame(2, $this->contar('wompi_eventos'));
        $this->assertSame(1, (int) $this->db->query('SELECT version_periodo FROM suscripciones WHERE id_clinica=1')->fetchColumn());
    }

    public function testS2DosProcesosDosPestanasUnaOrdenYUnIntento(): void
    {
        $this->prepararHistoriaClinica();
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $resultados = $this->competirS2('reserva');
        $this->assertSame($resultados[0]['resultado'], $resultados[1]['resultado']);
        $this->assertSame(1, $this->contar('pagos_suscripcion'));
        $this->assertSame(1, $this->contar('intentos_pago'));
        $this->assertSame(0, $this->contar('suscripcion_periodos'));
        $this->assertSame(8999000, $resultados[0]['resultado']['total_centavos']);
    }

    public function testS2DosEventosConcurrentesAcreditanUnaVezConAuditoria(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
        require_once __DIR__ . '/../../models/PagoSuscripcion.php';
        require_once __DIR__ . '/../../models/EventoWompi.php';
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $urlAnterior = getenv('APP_URL');
        putenv('APP_URL=http://localhost/Zooki/public');
        try {
            $orden = (new PagoSuscripcion($this->db, 1, ClienteWompiFalso::configuracion(), static fn () => VigenciaSuscripcion::fecha('2026-10-26 08:00:00')))->reservar('mensual', 0, 1);
        } finally {
            putenv($urlAnterior === false ? 'APP_URL' : 'APP_URL=' . $urlAnterior);
        }
        $tx = ['id' => 'tx_simultanea', 'reference' => $orden['checkout']['reference'], 'status' => 'APPROVED', 'amount_in_cents' => 8999000, 'currency' => 'COP'];
        foreach ([1, 2] as $timestamp) {
            (new EventoWompi($this->db))->recibir(FirmaWompi::evento(ClienteWompiFalso::cuerpo($tx, 'test', $timestamp), '', 'test', 'test_events_falso'));
        }
        $this->competirS2('acreditar');
        $this->assertSame(1, $this->contar('suscripcion_periodos'));
        $this->assertSame('2026-11-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT version_periodo FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM wompi_eventos WHERE estado='procesado'")->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='suscripcion_periodos' AND accion='INSERT'")->fetchColumn());
    }

    private function competirS2(string $fase): array
    {
        $this->carpetaTemporal = sys_get_temp_dir() . '/zooki_pago_s2_' . bin2hex(random_bytes(8));
        mkdir($this->carpetaTemporal);
        $esperar = function (string $archivo): void {
            $hasta = microtime(true) + 20;
            while (!is_file($this->carpetaTemporal . '/' . $archivo)) {
                if (microtime(true) > $hasta) {
                    $this->fail('Barrera de pago no alcanzada: ' . $archivo);
                }
                usleep(10000);
            }
        };
        $procesos = [];
        $resultados = [];
        try {
            foreach (['primero' => 1, 'segundo' => 2] as $nombre => $evento) {
                $proceso = proc_open([PHP_BINARY, __DIR__ . '/../Support/ProcesoPagoS2.php', $this->carpetaTemporal, $nombre, $fase, (string) $evento], [0 => ['pipe', 'r'], 1 => ['file', $this->carpetaTemporal . '/' . $nombre . '.log', 'a'], 2 => ['file', $this->carpetaTemporal . '/' . $nombre . '.log', 'a']], $tuberias);
                $this->assertIsResource($proceso);
                fclose($tuberias[0]);
                $procesos[] = $proceso;
                $esperar($nombre === 'primero' ? 'bloqueado' : 'segundo.conexion');
            }
            $a = (int) file_get_contents($this->carpetaTemporal . '/primero.conexion');
            $b = (int) file_get_contents($this->carpetaTemporal . '/segundo.conexion');
            $maria = str_contains((string) $this->db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB');
            $sql = $maria ? 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?'
                : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?';
            $q = $this->db->prepare($sql);
            $hasta = microtime(true) + 10;
            do {
                $q->execute([$b, $a]);
                $espera = (int) $q->fetchColumn() > 0;
                $q->closeCursor();
                if (!$espera) {
                    usleep($maria ? 125000 : 20000);
                }
            } while (!$espera && microtime(true) < $hasta);
            $this->assertTrue($espera, 'Las dos peticiones deben competir por el candado real de la clínica.');
            file_put_contents($this->carpetaTemporal . '/liberar', '1');
            foreach (['primero', 'segundo'] as $nombre) {
                $esperar($nombre . '.fin');
                $r = json_decode(file_get_contents($this->carpetaTemporal . '/' . $nombre . '.fin'), true, 64, JSON_THROW_ON_ERROR);
                $this->assertTrue($r['ok'], json_encode($r));
                $resultados[] = $r;
            }
        } finally {
            file_put_contents($this->carpetaTemporal . '/liberar', '1');
            foreach ($procesos as $proceso) {
                proc_close($proceso);
            }
        }
        return $resultados;
    }

    public function testS1CliRealRevisarNoModificaNiEnviaYS3ApagadaNoCobra(): void
    {
        $this->prepararHistoriaClinica();
        $antes = $this->db->query('SELECT * FROM suscripciones ORDER BY id_suscripcion')->fetchAll(PDO::FETCH_ASSOC);
        $auditoria = $this->contar('auditoria_sistema');
        $correo = $this->contar('notificaciones');
        foreach ([['--revisar','--solo=avisos'],['--solo=cobros']] as $argumentos) {
            $proceso = proc_open(array_merge([PHP_BINARY, __DIR__ . '/../../scripts/gestionar_suscripciones.php'], $argumentos), [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $tuberias);
            $this->assertIsResource($proceso);
            fclose($tuberias[0]);
            $salida = stream_get_contents($tuberias[1]);
            $error = stream_get_contents($tuberias[2]);
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            $codigo = proc_close($proceso);
            if (in_array('--revisar', $argumentos, true)) {
                $this->assertSame(0, $codigo, $error);
                $r = json_decode($salida, true, 64, JSON_THROW_ON_ERROR);
                $this->assertSame(0, $r['enviados']);
                $this->assertSame(0, $r['transiciones']);
                $this->assertNotEmpty($r['revision']);
            } else {
                $this->assertSame(0, $codigo, $error);
                $r = json_decode($salida, true, 64, JSON_THROW_ON_ERROR);
                $this->assertFalse($r['cobros']['habilitado']);
                $this->assertSame(0, $r['cobros']['enviados']);
            }
        }
        $this->assertSame($antes, $this->db->query('SELECT * FROM suscripciones ORDER BY id_suscripcion')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame($auditoria, $this->contar('auditoria_sistema'));
        $this->assertSame($correo, $this->contar('notificaciones'));
    }

    public function testS1MigracionDesdeElEsquemaAnteriorConDatos(): void
    {
        $sql = file_get_contents(self::DATABASE . '/01_schema.sql');
        $fin = strpos($sql, '-- M0-S S1: estructuras futuras vacías.');
        $this->assertNotFalse($fin);
        $sql = substr($sql, 0, $fin);
        foreach (['suscripcion_gracia_dias','aviso_vencimiento_1_dias','aviso_vencimiento_2_dias','gestion_cobro','periodo_inicio','periodo_fin','estado_cobro','version_periodo','cobro_automatico','id_fuente_activa','autorizado_por','autorizado_en','retirado_en','cambio_ciclo_siguiente'] as $columna) {
            $sql = preg_replace('/^  `' . $columna . '`[^\r\n]*\r?\n/m', '', $sql);
        }
        $st = $this->db->query($sql);
        while ($st->nextRowset()) {
        }
        $st->closeCursor();
        $this->cargar('02_semilla.sql');
        require_once __DIR__ . '/../Support/DosClinicas.php';
        DosClinicas::poblar($this->db);
        $this->db->exec("UPDATE suscripciones SET fecha_fin='2026-10-24' WHERE id_clinica=1");
        $this->cargar('12_suscripcion_pagos.sql');
        $this->db->exec('UPDATE clinicas SET suscripcion_gracia_dias=5 WHERE id_clinica=1');
        $this->cargar('12_suscripcion_pagos.sql');
        $this->assertSame('2026-10-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertSame('legado', $this->db->query('SELECT gestion_cobro FROM suscripciones WHERE id_clinica=2')->fetchColumn());
        $this->assertSame(5, (int) $this->db->query('SELECT suscripcion_gracia_dias FROM clinicas WHERE id_clinica=1')->fetchColumn());
        $this->assertSame(0, $this->contar('pagos_suscripcion'));
    }

    public function testS1Migracion12LegadoPrecioPersonalizadoYRecuperacionParcial(): void
    {
        $this->prepararHistoriaClinica(false);
        $this->db->exec("UPDATE planes SET precio_mensual=100000,precio_anual=960000 WHERE id_plan=2");
        $this->db->exec("UPDATE suscripciones SET fecha_fin='2026-10-24' WHERE id_clinica=1");
        $this->cargar('12_suscripcion_pagos.sql');
        $this->cargar('12_suscripcion_pagos.sql');
        $this->assertSame('2026-10-25 00:00:00', $this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=1')->fetchColumn());
        $this->assertNull($this->db->query('SELECT periodo_fin FROM suscripciones WHERE id_clinica=2')->fetchColumn());
        $this->assertSame([89990,899900], array_map('intval', $this->db->query('SELECT precio_mensual,precio_anual FROM planes WHERE id_plan=2')->fetch(PDO::FETCH_NUM)));
        $this->db->exec('UPDATE planes SET precio_mensual=123456,precio_anual=1234560 WHERE id_plan=2');
        $this->db->exec('ALTER TABLE clinicas DROP COLUMN aviso_vencimiento_2_dias');
        $this->db->exec('DROP TABLE wompi_eventos');
        $this->cargar('12_suscripcion_pagos.sql');
        $this->cargar('12_suscripcion_pagos.sql');
        $this->assertSame(123456, (int) $this->db->query('SELECT precio_mensual FROM planes WHERE id_plan=2')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT aviso_vencimiento_2_dias FROM clinicas WHERE id_clinica=1')->fetchColumn());
        foreach (['pagos_suscripcion','intentos_pago','wompi_eventos','fuentes_pago','suscripcion_periodos','avisos_suscripcion'] as $tabla) {
            $this->assertSame(0, $this->contar($tabla));
        }
        $this->db->exec('ALTER TABLE pagos_suscripcion DROP INDEX uq_pago_obligacion');
        try {
            $this->cargar('12_suscripcion_pagos.sql');
            $this->fail('Una tabla incompatible no puede pasar como existente.');
        } catch (PDOException $error) {
            $this->assertStringContainsString('S1 indice incompatible', $error->getMessage());
        }
    }

    public function testS1DosTrabajadoresConcurrentesUnaTransicionYUnAviso(): void
    {
        $this->prepararHistoriaClinica();
        $this->db->exec("UPDATE suscripciones SET gestion_cobro='gestionada',periodo_inicio='2026-09-25 00:00:00',periodo_fin='2026-10-25 00:00:00' WHERE id_clinica=1");
        $this->carpetaTemporal = sys_get_temp_dir() . '/zooki_s1_' . bin2hex(random_bytes(8));
        mkdir($this->carpetaTemporal);
        $esperar = function (string $archivo): void {
            $limite = microtime(true) + 20;
            while (!is_file($this->carpetaTemporal . '/' . $archivo)) {
                if (microtime(true) > $limite) {
                    $this->fail('No se alcanzó la barrera S1: ' . $archivo);
                }
                usleep(10000);
            }
        };
        $procesos = [];
        $lanzar = function (string $nombre) {
            $proceso = proc_open([PHP_BINARY, __DIR__ . '/../Support/ProcesoSuscripcion.php', $this->carpetaTemporal, $nombre], [0 => ['pipe','r'], 1 => ['file',$this->carpetaTemporal . '/' . $nombre . '.log','a'], 2 => ['file',$this->carpetaTemporal . '/' . $nombre . '.log','a']], $tuberias);
            if (!is_resource($proceso)) {
                throw new RuntimeException('No se pudo abrir el trabajador S1.');
            }
            fclose($tuberias[0]);
            return $proceso;
        };
        try {
            $procesos[] = $lanzar('primero');
            $esperar('bloqueado');
            $procesos[] = $lanzar('segundo');
            $esperar('segundo.conexion');
            $primero = (int) file_get_contents($this->carpetaTemporal . '/primero.conexion');
            $segundo = (int) file_get_contents($this->carpetaTemporal . '/segundo.conexion');
            $maria = str_contains((string) $this->db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB');
            $sql = $maria ? 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?'
                : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?';
            $consulta = $this->db->prepare($sql);
            $limite = microtime(true) + 10;
            do {
                $consulta->execute([$segundo, $primero]);
                $espera = (int) $consulta->fetchColumn() > 0;
                $consulta->closeCursor();
                if (!$espera) {
                    usleep(125000);
                }
            } while (!$espera && microtime(true) < $limite);
            $this->assertTrue($espera, 'El segundo trabajador espera el bloqueo real de clínica.');
            file_put_contents($this->carpetaTemporal . '/liberar', '1');
            foreach (['primero','segundo'] as $nombre) {
                $esperar($nombre . '.fin');
                $r = json_decode(file_get_contents($this->carpetaTemporal . '/' . $nombre . '.fin'), true);
                $this->assertSame(0, $r['errores'] ?? $r, json_encode($r));
            }
            $this->assertSame(1, $this->contar('avisos_suscripcion'));
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='suscripciones' AND datos_nuevos LIKE '%congelada%'")->fetchColumn());
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones WHERE tipo_notificacion='suscripcion_congelada'")->fetchColumn());
            $this->assertCount(1, glob($this->carpetaTemporal . '/correo-*'));
        } finally {
            file_put_contents($this->carpetaTemporal . '/liberar', '1');
            foreach ($procesos as $proceso) {
                proc_close($proceso);
            }
        }
    }

    public function testCarnetBMigracion11SeRepiteSinPerderAccesosNiGuardarTokenOMascota(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('11_accesos_carnet.sql');
        $this->db->exec("INSERT INTO carnet_accesos (ip_hash, fecha) VALUES (REPEAT('a', 64), '2026-10-23 12:00:00')");
        $this->cargar('11_accesos_carnet.sql');
        $this->assertSame(1, $this->contar('carnet_accesos'));
        $columnas = $this->db->query('SHOW COLUMNS FROM carnet_accesos')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['id_acceso', 'ip_hash', 'fecha'], $columnas);
        $this->db->exec('DROP TABLE carnet_accesos');
        $this->cargar('11_accesos_carnet.sql');
        $this->cargar('11_accesos_carnet.sql');
        $this->assertSame(0, $this->contar('carnet_accesos'));
    }

    public function testCarnetBDosEscaneosSimultaneosGuardanDosVistasYUnSoloAviso(): void
    {
        $this->prepararHistoriaClinica();
        $this->db->prepare('UPDATE mascotas SET token_carnet = ? WHERE id_mascota = 1')->execute([str_repeat('a', 43)]);
        require_once __DIR__ . '/../../models/CarnetPublico.php';
        $publico = new CarnetPublico($this->db, static fn (): bool => true);
        $this->assertSame(1, $publico->resolver(str_repeat('a', 43)));
        $this->assertNull($publico->resolver(str_repeat('A', 43)), 'general_ci no debe hacer equivalentes dos tokens distintos.');
        $secretoAnterior = getenv('CARNET_IP_SECRETO');
        $baseAnterior = getenv('ZOOKI_TEST_MYSQL_DB');
        putenv('CARNET_IP_SECRETO=' . str_repeat('s', 64));
        putenv('ZOOKI_TEST_MYSQL_DB=zooki_test_base_v2');
        $this->carpetaTemporal = sys_get_temp_dir() . '/zooki_carnet_b_' . bin2hex(random_bytes(8));
        mkdir($this->carpetaTemporal);
        $procesos = [];
        $esperar = function (string $archivo): void {
            $limite = microtime(true) + 20;
            while (!is_file($this->carpetaTemporal . '/' . $archivo)) {
                if (microtime(true) > $limite) {
                    $this->fail('No se alcanzó la barrera del carnet: ' . $archivo);
                }
                usleep(10000);
            }
        };
        $lanzar = function (string $nombre) {
            $proceso = proc_open([PHP_BINARY, __DIR__ . '/../Support/ProcesoCarnetB.php', $this->carpetaTemporal, $nombre], [0 => ['pipe', 'r'], 1 => ['file', $this->carpetaTemporal . '/' . $nombre . '.log', 'a'], 2 => ['file', $this->carpetaTemporal . '/' . $nombre . '.log', 'a']], $tuberias);
            if (!is_resource($proceso)) {
                throw new RuntimeException('No se pudo abrir el escáner de prueba.');
            }
            fclose($tuberias[0]);
            return $proceso;
        };
        try {
            $procesos[] = $lanzar('primero');
            $esperar('bloqueado');
            $procesos[] = $lanzar('segundo');
            $esperar('segundo.conexion');
            $primero = (int) file_get_contents($this->carpetaTemporal . '/primero.conexion');
            $segundo = (int) file_get_contents($this->carpetaTemporal . '/segundo.conexion');
            $maria = str_contains((string) $this->db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB');
            $sql = $maria ? 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?'
                : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?';
            $consulta = $this->db->prepare($sql);
            $limite = microtime(true) + 10;
            do {
                $consulta->execute([$segundo, $primero]);
                $espera = (int) $consulta->fetchColumn() > 0;
                $consulta->closeCursor();
                if (!$espera) {
                    usleep($maria ? 125000 : 20000);
                }
            } while (!$espera && microtime(true) < $limite);
            $this->assertTrue($espera, 'El segundo escaneo debe esperar el bloqueo real de la mascota.');
            file_put_contents($this->carpetaTemporal . '/liberar', '1');
            foreach (['primero', 'segundo'] as $nombre) {
                $esperar($nombre . '.fin');
                $resultado = json_decode(file_get_contents($this->carpetaTemporal . '/' . $nombre . '.fin'), true);
                $this->assertTrue($resultado['ok'], json_encode($resultado));
            }
            $this->assertSame(2, $this->contar('carnet_escaneos'));
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones WHERE tipo_notificacion = 'carnet_escaneo'")->fetchColumn());
            $this->assertCount(1, glob($this->carpetaTemporal . '/correo-*'));
        } finally {
            file_put_contents($this->carpetaTemporal . '/liberar', '1');
            foreach ($procesos as $proceso) {
                proc_close($proceso);
            }
            putenv($secretoAnterior === false ? 'CARNET_IP_SECRETO' : 'CARNET_IP_SECRETO=' . $secretoAnterior);
            putenv($baseAnterior === false ? 'ZOOKI_TEST_MYSQL_DB' : 'ZOOKI_TEST_MYSQL_DB=' . $baseAnterior);
        }
    }

    public function testM2C4TriageConFichaPropiaYBusquedaSinEscrituras(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../controllers/TriageController.php';
        require_once __DIR__ . '/../../models/CargadorConocimiento.php';
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        $this->db->exec('UPDATE mascotas SET id_especie = 2, id_raza = 50 WHERE id_mascota = 1');
        $antes = $this->contar('auditoria_sistema');
        $this->comoVeterinario(1, 2);
        $fecha = new DateTimeImmutable('2026-10-15T12:00:00-05:00');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id_mascota' => 1, 'sintomas' => [], 'exposiciones' => [['codigo' => 'farmaco.paracetamol', 'tiempo' => 'no_se']]];
        foreach ([false, true] as $propietario) {
            if ($propietario) {
                $_SESSION = ['id_usuario' => 6];
                Contexto::activar(Contexto::dePropietario(), 1);
            }
            ob_start();
            (new TriageController($this->db, $fecha))->calcularAjax();
            $resultado = json_decode(ob_get_clean(), true, 64, JSON_THROW_ON_ERROR);
            $this->assertSame('rojo', $resultado['nivel']);
            $this->assertFalse($resultado['revision']);
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['q' => 'bomita'];
        ob_start();
        (new TriageController($this->db, $fecha))->buscarAjax();
        $respuesta = json_decode(ob_get_clean(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertContains('sintoma.vomito_sangre', array_column($respuesta['sugerencias'], 'codigo'));
        $this->assertSame($antes, $this->contar('auditoria_sistema'));
        foreach (['citas', 'consultas', 'cita_sintomas', 'consulta_sintomas'] as $tabla) {
            $this->assertSame(0, $this->contar($tabla));
        }
        $_GET = [];
        $_POST = [];
        http_response_code(200);
    }

    public function testC3PrescripcionSeRevalidaAlGuardarConAuditoriaYRollback(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/ValidacionPrescripcion.php';
        require_once __DIR__ . '/../../models/CargadorConocimiento.php';
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        $this->comoVeterinario(1, 2);
        $fecha = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $fila = ['farmaco' => 'farmaco.paracetamol', 'medicamento' => 'Nombre indicado', 'dosis' => 'Indicada', 'via_administracion' => 'Oral', 'duracion' => 'Según valoración', 'fecha_inicio' => $fecha->format('Y-m-d'), 'fecha_fin' => $fecha->modify('+1 day')->format('Y-m-d')];
        $entrada = ['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Prueba', 'diagnostico' => 'Decisión del veterinario'];
        $this->db->exec('UPDATE mascotas SET id_especie = 2, id_raza = 50 WHERE id_mascota = 1');
        try {
            (new Consulta($this->db))->registrar($entrada, [$fila], [], static fn (): array => []);
            $this->fail('Se guardó un fármaco bloqueado.');
        } catch (PrescripcionRechazada $error) {
            $this->assertTrue($error->publica['bloqueada']);
        }
        $this->assertSame(0, $this->contar('consultas'));
        $this->assertSame(0, $this->contar('tratamientos'));
        $this->assertSame(0, $this->contar('archivos_clinicos'));
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Prescripción rechazada (RE-2.9.2, RE-2.9.3)'")->fetchColumn());
        $this->db->exec('UPDATE mascotas SET id_especie = 1, id_raza = 49 WHERE id_mascota = 1');
        $evaluacion = (new ValidacionPrescripcion($this->db))->evaluar(1, [$fila], $fecha)[0];
        $fila += ['decision' => 'confirmar', 'huella' => $evaluacion['publica']['huella']];
        $id = (new Consulta($this->db))->registrar($entrada, [$fila], [], static fn (): array => []);
        $guardado = $this->db->query('SELECT t.fecha_fin, n.codigo FROM tratamientos t JOIN grafo_nodos n ON n.id_nodo = t.id_nodo_farmaco WHERE t.id_consulta = ' . $id)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($fila['fecha_fin'], $guardado['fecha_fin']);
        $this->assertSame($fila['farmaco'], $guardado['codigo']);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Decisión de prescripción (RE-2.9.3)'")->fetchColumn());
    }

    public function testC2RankingConFichaYConocimientoActivosSinGuardar(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/GrafoConocimiento.php';
        require_once __DIR__ . '/../../models/PacienteDiagnostico.php';
        require_once __DIR__ . '/../../helpers/MotorDiagnosticos.php';
        require_once __DIR__ . '/../../models/CargadorConocimiento.php';
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        $this->comoVeterinario(1, 2);
        $this->db->exec("UPDATE mascotas SET fecha_nacimiento = '2026-07-15', sexo = 'Macho', esterilizado = 0 WHERE id_mascota = 1");
        $lector = new GrafoConocimiento($this->db);
        $grafo = $lector->instantanea();
        $this->assertSame($grafo, $lector->instantanea());
        $fecha = new DateTimeImmutable('2026-10-15T12:00:00-05:00');
        $contexto = (new PacienteDiagnostico($this->db))->contexto(1, $grafo, $fecha, ['vacunacion' => 'incompleta']);
        $this->assertSame(3, $contexto['edad_meses']);
        $this->assertFalse($contexto['esterilizado']);
        $contexto['sintomas'] = array_fill_keys(['sintoma.vomito', 'sintoma.diarrea_sangre', 'sintoma.no_come'], ['minutos' => null]);
        $antes = $this->contar('auditoria_sistema');
        $resultado = MotorDiagnosticos::sugerir($grafo, $contexto, $fecha);
        $this->assertSame('diagnostico.parvovirosis_canina', $resultado['candidatos'][0]['codigo']);
        $this->assertSame($antes, $this->contar('auditoria_sistema'));
        $this->assertSame(0, $this->contar('consultas'));
        $this->assertSame(0, $this->contar('consulta_sintomas'));
    }

    public function testB1Migracion07ActualizaElEsquemaAnteriorDosVecesSinCambiarIds(): void
    {
        $sql = $this->db->query(file_get_contents(__DIR__ . '/../Fixtures/grafo_pre07.sql'));
        while ($sql->nextRowset()) {
        }
        $sql->closeCursor();
        $this->cargar('07_grafo_conocimiento.sql');
        $antes = $this->db->query('SELECT * FROM grafo_nodos ORDER BY id_nodo')->fetchAll(PDO::FETCH_ASSOC);
        $tablas = [];
        foreach (['grafo_nodos', 'grafo_aristas', 'grafo_sinonimos', 'grafo_reglas', 'citas', 'cita_sintomas', 'consulta_sintomas'] as $tabla) {
            $tablas[$tabla] = $this->db->query('SHOW CREATE TABLE ' . $tabla)->fetch(PDO::FETCH_NUM)[1];
        }
        $this->cargar('07_grafo_conocimiento.sql');
        $this->assertSame($antes, $this->db->query('SELECT * FROM grafo_nodos ORDER BY id_nodo')->fetchAll(PDO::FETCH_ASSOC));
        foreach ($tablas as $tabla => $ddl) {
            $this->assertSame($ddl, $this->db->query('SHOW CREATE TABLE ' . $tabla)->fetch(PDO::FETCH_NUM)[1]);
        }
        $this->assertSame('especie', $antes[0]['tipo']);
        $this->assertSame(0, (int) $antes[0]['activo']);
        $this->assertSame(2, (int) $this->db->query('SELECT id_nodo FROM consulta_sintomas')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM razas WHERE nombre_raza = 'Gran danés'")->fetchColumn());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'grafo_%' AND COLUMN_NAME IN ('validado_por','fecha_validacion')")->fetchColumn());
    }

    public function testB1CargadorRealIdempotenteConCandadoYRevisarSinEscritura(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->cargar('07_grafo_conocimiento.sql');
        $this->cargar('07_grafo_conocimiento.sql');
        $carpeta = ConocimientoPrueba::copiar();
        try {
            $cargador = new CargadorConocimiento($this->db);
            $this->assertSame(9, $cargador->cargar($carpeta, true)['altas']);
            $this->assertSame(0, $this->contar('auditoria_sistema'));
            $resultado = $cargador->cargar($carpeta);
            $antes = ConocimientoPrueba::instantanea($this->db);
            $this->assertSame(64, strlen($resultado['version']));
            $this->assertSame(0, $cargador->cargar($carpeta)['cambios']);
            $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
            $otra = $this->otraConexion();
            $otra->query("SELECT GET_LOCK('zooki_conocimiento', 0)");
            $bloqueada = $this->db->query("SELECT IS_USED_LOCK('zooki_conocimiento')")->fetchColumn();
            $this->assertNotFalse($bloqueada);
            $otra->query("SELECT RELEASE_LOCK('zooki_conocimiento')");
            $id = $this->db->query("SELECT id_nodo FROM grafo_nodos WHERE codigo = 'sustancia.prueba'")->fetchColumn();
            ConocimientoPrueba::cambiar($carpeta, 'nodos', static function (array &$nodos): void {
                $nodos[5]['etiqueta'] = 'Nueva etiqueta';
            });
            $antesRevision = ConocimientoPrueba::instantanea($this->db);
            $this->assertGreaterThan(0, $cargador->cargar($carpeta, true)['cambios']);
            $this->assertSame($antesRevision, ConocimientoPrueba::instantanea($this->db));
            $cargador->cargar($carpeta);
            $this->assertSame($id, $this->db->query("SELECT id_nodo FROM grafo_nodos WHERE codigo = 'sustancia.prueba'")->fetchColumn());
            ConocimientoPrueba::cambiar($carpeta, 'nodos', static function (array &$nodos): void {
                array_pop($nodos);
            });
            $this->assertSame(1, $cargador->cargar($carpeta)['retiros']);
            copy(__DIR__ . '/../Fixtures/conocimiento/nodos.json', $carpeta . '/nodos.json');
            $cargador->cargar($carpeta);
            $this->assertSame(1, (int) $this->db->query('SELECT activo FROM grafo_nodos WHERE id_nodo = ' . $id)->fetchColumn());
            $this->assertSame(55, $this->contar('razas'));
            foreach ([[$carpeta, 0], [$carpeta . '/ausente', 1]] as [$directorio, $esperado]) {
                $proceso = proc_open([PHP_BINARY, __DIR__ . '/../../scripts/cargar_conocimiento.php', '--revisar', '--carpeta=' . $directorio], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $salida = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame($esperado, proc_close($proceso), $salida . $error);
                $this->assertStringContainsString('[conocimiento]', $salida . $error);
            }
        } finally {
            ConocimientoPrueba::borrar($carpeta);
        }
    }

    public function testB2ConocimientoRealSeCargaDosVecesSinCambios(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->cargar('07_grafo_conocimiento.sql');
        $carpeta = self::DATABASE . '/conocimiento';
        $cargador = new CargadorConocimiento($this->db);
        $primera = $cargador->cargar($carpeta);
        $this->assertGreaterThan(0, $primera['altas']);
        $antes = ConocimientoPrueba::instantanea($this->db);
        $segunda = $cargador->cargar($carpeta);
        $this->assertSame([0, 0, 0], [$segunda['altas'], $segunda['cambios'], $segunda['retiros']]);
        $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
        // Las razas y especies del conjunto se enlazan al catálogo real, sin crear taxonomía.
        $this->assertSame(55, $this->contar('razas'));
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM grafo_nodos WHERE tipo IN ('especie', 'raza') AND id_especie IS NULL AND id_raza IS NULL")->fetchColumn());
        $this->assertSame(55, (int) $this->db->query("SELECT id_raza FROM grafo_nodos WHERE codigo = 'raza.gran_danes'")->fetchColumn());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM grafo_nodos WHERE codigo NOT LIKE 'legado.%' AND estado_validacion <> 'pendiente'")->fetchColumn());
    }

    public function testB21RetiraPartoProlongadoSinBorrarloNiCambiarSuId(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $carpeta = ConocimientoPrueba::copiar(self::DATABASE . '/conocimiento');
        try {
            // Conserva la declaración anterior para demostrar el retiro sobre una carga existente.
            $anterior = json_decode(file_get_contents(__DIR__ . '/../Fixtures/parto_regla_b2.json'), true, 64, JSON_THROW_ON_ERROR);
            ConocimientoPrueba::cambiar($carpeta, 'reglas', static function (array &$reglas) use ($anterior): void {
                $reglas[] = $anterior;
            });
            $cargador = new CargadorConocimiento($this->db);
            $cargador->cargar($carpeta);
            $partoAntes = $this->db->query("SELECT * FROM grafo_reglas WHERE codigo = 'triage.parto_prolongado'")->fetch(PDO::FETCH_ASSOC);
            $antes = ConocimientoPrueba::instantanea($this->db);
            $simulacion = $cargador->cargar(self::DATABASE . '/conocimiento', true);
            $this->assertSame(1, $simulacion['retiros']);
            $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
            $resultado = $cargador->cargar(self::DATABASE . '/conocimiento');
            $this->assertSame(1, $resultado['retiros']);
            $partoDespues = $this->db->query("SELECT * FROM grafo_reglas WHERE codigo = 'triage.parto_prolongado'")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame($partoAntes['id_regla'], $partoDespues['id_regla']);
            $this->assertSame(0, (int) $partoDespues['activo']);
            $this->assertSame($partoAntes['condiciones'], $partoDespues['condiciones']);
            $this->assertSame(1, (int) $this->db->query("SELECT alarma_universal FROM grafo_nodos WHERE codigo = 'sintoma.parto_esfuerzo_sin_cria'")->fetchColumn());
            $despues = ConocimientoPrueba::instantanea($this->db);
            $repetida = $cargador->cargar(self::DATABASE . '/conocimiento');
            $this->assertSame([0, 0, 0], [$repetida['altas'], $repetida['cambios'], $repetida['retiros']]);
            $this->assertSame($despues, ConocimientoPrueba::instantanea($this->db));
        } finally {
            ConocimientoPrueba::borrar($carpeta);
        }
    }

    public function testC1AlertasMedicasEnElEsquemaRealConNodoYAuditoria(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        require_once __DIR__ . '/../../models/AlertaMedica.php';
        $this->prepararHistoriaClinica();
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        try {
            $this->comoVeterinario(1, 2);
            $modelo = new AlertaMedica($this->db);
            $id = $modelo->registrar(['id_mascota' => 1, 'tipo' => 'alergia', 'descripcion' => 'Alergia al paracetamol', 'nodo' => 'farmaco.paracetamol']);
            $sinNodo = $modelo->registrar(['id_mascota' => 1, 'tipo' => 'otra', 'descripcion' => 'Muerde al examinarlo']);
            $nodo = (int) $this->db->query("SELECT id_nodo FROM grafo_nodos WHERE codigo = 'farmaco.paracetamol'")->fetchColumn();
            $this->assertSame($nodo, (int) $this->db->query('SELECT id_nodo FROM alertas_medicas WHERE id_alerta = ' . $id)->fetchColumn());
            $this->assertNull($this->db->query('SELECT id_nodo FROM alertas_medicas WHERE id_alerta = ' . $sinNodo)->fetchColumn());
            $this->comoVeterinario(2, 4);
            $sur = new AlertaMedica($this->db);
            $this->assertSame('Clínica Norte', $sur->deMascota(1)['activas'][1]['clinica_nombre']);
            $this->assertDenegado(fn () => $sur->desactivar($id));
            $this->comoVeterinario(1, 2);
            $modelo->desactivar($id);
            $historial = $modelo->deMascota(1)['historial'];
            $this->assertSame([$id], array_map('intval', array_column($historial, 'id_alerta')));
            $this->assertNotNull($historial[0]['desactivada_en']);
            $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM alertas_medicas")->fetchColumn(), 'Nunca se borra');
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'alertas_medicas' AND accion = 'UPDATE' AND registro_id = '$id'")->fetchColumn());
        } finally {
            $_SESSION = [];
        }
    }

    /** M2 C5a (RN-210): síntomas con FK real a consultas y grafo_nodos, en la transacción de la consulta. */
    public function testC5aSintomasDeLaConsultaEnElEsquemaRealConRollback(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        require_once __DIR__ . '/../../models/Consulta.php';
        $this->prepararHistoriaClinica();
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        try {
            $this->comoVeterinario(1, 2);
            $consultas = new Consulta($this->db);
            $sinAdjuntos = fn (int $idConsulta, array $adjunto, int $indice): array => [];
            $id = $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Vomita', 'diagnostico' => 'Gastritis', 'sintomas' => ['sintoma.vomito', 'sintoma.diarrea']], [], [], $sinAdjuntos);
            $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM consulta_sintomas WHERE id_consulta = $id")->fetchColumn());
            $this->assertSame(['Diarrea', 'Vómito'], array_column($consultas->historialDeMascota(1)[0]['sintomas'], 'etiqueta'));

            // Retirado entre la búsqueda y el guardado: 422 en el campo y nada escrito.
            $this->db->exec("UPDATE grafo_nodos SET activo = 0 WHERE codigo = 'sintoma.diarrea'");
            try {
                $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano', 'sintomas' => ['sintoma.vomito', 'sintoma.diarrea']], [], [], $sinAdjuntos);
                $this->fail('Un síntoma retirado no se guarda.');
            } catch (CampoInvalido $e) {
                $this->assertSame('sintomas', $e->campo);
            }
            $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
            $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM consulta_sintomas')->fetchColumn());
        } finally {
            $_SESSION = [];
        }
    }

    /** M2 C5b: «No se pudo pesar» guarda NULL (nunca 0) y una fila con fin anterior al inicio no deja nada. */
    public function testC5bPesoDesconocidoYFinDeLaFilaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../../models/Consulta.php';
        $this->prepararHistoriaClinica();
        try {
            $this->comoVeterinario(1, 2);
            $consultas = new Consulta($this->db);
            $sinAdjuntos = fn (int $idConsulta, array $adjunto, int $indice): array => [];
            $fila = ['medicamento' => 'Amoxicilina', 'dosis' => '1 tableta', 'via_administracion' => 'Oral', 'duracion' => '7 días', 'fecha_inicio' => '2026-10-15', 'fecha_fin' => '2026-10-21', 'observaciones' => 'Con comida'];
            $id = $consultas->registrar(['id_mascota' => 1, 'motivo' => 'Control', 'diagnostico' => 'Sano', 'peso' => '', 'peso_desconocido' => '1'], [$fila], [], $sinAdjuntos);
            $this->assertNull($this->db->query("SELECT peso FROM consultas WHERE id_consulta = $id")->fetchColumn());
            $this->assertSame('2026-10-21', $this->db->query("SELECT fecha_fin FROM tratamientos WHERE id_consulta = $id")->fetchColumn());
            try {
                $consultas->registrar(['id_mascota' => 1, 'motivo' => 'Control', 'diagnostico' => 'Sano', 'peso' => '8.5'], [$fila, array_replace($fila, ['fecha_fin' => '2026-10-01'])], [], $sinAdjuntos);
                $this->fail('Un fin anterior al inicio no se guarda.');
            } catch (CampoInvalido $e) {
                $this->assertSame('tratamientos.1.fecha_fin', $e->campo);
            }
            $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
        } finally {
            $_SESSION = [];
        }
    }

    /** M2 C7: los síntomas en la lista de la clínica y en la historia del portal, con el esquema real. */
    public function testC7SintomasEnLaListaYEnElPortalEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        require_once __DIR__ . '/../../models/Consulta.php';
        require_once __DIR__ . '/../../models/HistoriaPropietario.php';
        $this->prepararHistoriaClinica();
        (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        try {
            $this->comoVeterinario(1, 2);
            $consultas = new Consulta($this->db);
            $consultas->registrar(['id_mascota' => 1, 'peso' => '', 'peso_desconocido' => '1', 'motivo' => 'Vomita', 'diagnostico' => 'Gastritis', 'sintomas' => ['sintoma.vomito']], [], [], fn (): array => []);
            $this->assertSame(['Vómito'], array_column($consultas->listarDeLaClinica()[0]['sintomas'], 'etiqueta'));
            $_SESSION = ['id_usuario' => 6];
            Contexto::activar(Contexto::dePropietario(), 1);
            $historia = (new HistoriaPropietario($this->db))->consultas(1);
            $this->assertSame(['Vómito'], array_column($historia[0]['sintomas'], 'etiqueta'));
            $this->assertNull($historia[0]['peso']);
        } finally {
            $_SESSION = [];
        }
    }

    public function testC6MapaDelConocimientoRealSoloActivoYConReglasEnSusNodos(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        require_once __DIR__ . '/../../models/GrafoConocimiento.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->cargar('07_grafo_conocimiento.sql');
        $carga = (new CargadorConocimiento($this->db))->cargar(self::DATABASE . '/conocimiento');
        $mapa = (new GrafoConocimiento($this->db))->mapa();
        $this->assertSame($carga['version'], $mapa['version']['hash']);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM grafo_nodos WHERE activo = 1')->fetchColumn(), $mapa['conteos']['nodos']);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM grafo_aristas WHERE activo = 1')->fetchColumn(), $mapa['conteos']['aristas']);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM grafo_reglas WHERE activo = 1')->fetchColumn(), $mapa['conteos']['reglas']);
        // La regla retirada en B2.1 no forma parte del conjunto vigente; el retiro de filas se prueba en GrafoConocimientoTest.
        $this->assertNotContains('triage.parto_prolongado', array_column($mapa['reglas'], 'codigo'));
        $nodos = array_column($mapa['nodes'], null, 'codigo');
        foreach ($mapa['reglas'] as $regla) {
            $this->assertNotEmpty($regla['nodos'], $regla['codigo']);
            foreach ($regla['nodos'] as $codigo) {
                $this->assertContains($regla['codigo'], $nodos[$codigo]['reglas'], $codigo);
            }
        }
        $this->assertSame(['especie.canino'], $nodos['raza.gran_danes']['especies']);
        $this->assertSame(['especie.felino'], $nodos['raza.persa']['especies']);
        $this->assertSame('parcial', array_column($mapa['cobertura'], null, 'codigo')['especie.canino']['triage']);
        $this->assertSame('sin_cobertura', array_column($mapa['cobertura'], null, 'codigo')['especie.ave']['triage']);
        $json = json_encode($mapa);
        foreach (['id_mascota', 'id_consulta', 'id_cita', 'id_clinica', 'id_tratamiento', 'id_propietario', 'id_usuario', 'id_nodo'] as $clave) {
            $this->assertStringNotContainsString('"' . $clave . '"', $json);
        }
    }

    public function testB1TaxonomiaConFKYTiposCoherentes(): void
    {
        require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        (new CargadorConocimiento($this->db))->cargar(__DIR__ . '/../Fixtures/conocimiento');
        foreach (["id_especie = 999999", "id_raza = 55", "id_especie = NULL"] as $cambio) {
            try {
                $this->db->exec("UPDATE grafo_nodos SET $cambio WHERE codigo = 'especie.prueba'");
                $this->fail('La FK o el CHECK debió rechazar el enlace.');
            } catch (PDOException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
    }
    public function testCarnetASerializaLaRegeneracionYDescartaElDobleEnvioConUnaInstantaneaAnterior(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/CarnetPropietario.php';
        $baseAnterior = getenv('APP_URL');
        putenv('APP_URL=https://zooki.example');
        Auditoria::definirProxiesConfiables([]);
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $otra = $this->otraConexion();
        $otra->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $token = (string) $this->db->query('SELECT token_carnet FROM mascotas WHERE id_mascota = 1')->fetchColumn();
        try {
            $this->db->beginTransaction();
            $this->db->query('SELECT id_mascota FROM mascotas WHERE id_mascota = 1 FOR UPDATE')->fetchColumn();
            try {
                (new CarnetPropietario($otra))->regenerar(1, $token);
                $this->fail('Debe esperar el bloqueo de la mascota.');
            } catch (PDOException $error) {
                $this->assertSame(1205, (int) $error->errorInfo[1]);
                $this->assertFalse($otra->inTransaction());
            } finally {
                $this->db->rollBack();
            }
            $otra->beginTransaction();
            try {
                $this->assertSame($token, $otra->query('SELECT token_carnet FROM mascotas WHERE id_mascota = 1')->fetchColumn());
                $nuevo = (new CarnetPropietario($this->db))->regenerar(1, $token);
                $repetido = (new CarnetPropietario($otra))->regenerar(1, $token);
                $this->assertTrue($nuevo['regenerado']);
                $this->assertFalse($repetido['regenerado']);
                $this->assertSame($nuevo['token'], $repetido['token']);
                $this->assertNotSame($token, $nuevo['token']);
                $otra->commit();
            } finally {
                if ($otra->inTransaction()) $otra->rollBack();
            }
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE '%RE-5.10.5%'")->fetchColumn());
            $this->assertSame($nuevo['token'], $this->db->query('SELECT token_carnet FROM mascotas WHERE id_mascota = 1')->fetchColumn());
            $datos = (new CarnetMascota($this->db))->datosEmergencia(1);
            $this->assertSame('Luna', $datos['nombre']);
            $this->assertArrayNotHasKey('token_carnet', $datos);
        } finally {
            putenv($baseAnterior === false ? 'APP_URL' : 'APP_URL=' . $baseAnterior);
            $_SESSION = [];
        }
    }

    public function testM5FTopeDeActivacionSerializaLaCuentaYSeComparteEntreConexiones(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../models/ActivacionPropietario.php';
        $this->comoVeterinario(1, 2);
        $modelo = new ActivacionPropietario($this->db);
        $alta = Transaccion::ejecutar($this->db, fn () => $modelo->crear(['tipo_documento' => 'CC', 'documento' => '1000000040', 'nombre_completo' => 'Rosa Nueva', 'telefono' => '3001234567', 'email' => 'rosa@zooki.test'], 1));
        $id = $alta['id_usuario'];
        $this->db->prepare('UPDATE verificaciones_email SET created_at = ? WHERE id_usuario = ?')->execute([date('Y-m-d H:i:s', time() - 7200), $id]);
        $otra = $this->otraConexion();
        $otra->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $this->db->beginTransaction();
        $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? FOR UPDATE')->execute([$id]);
        try {
            (new ActivacionPropietario($otra))->renovarPorTitular($id);
            $this->fail('Debe esperar el bloqueo de la cuenta antes de renovar.');
        } catch (PDOException $error) {
            $this->assertSame(1205, (int) $error->errorInfo[1]);
            $this->assertFalse($otra->inTransaction(), 'La solicitud que no pudo renovar revierte su transacción.');
        } finally {
            $this->db->rollBack();
        }
        // El autorregistro ya leyó la cuenta antes de esperar: conservar esa instantánea
        // no puede ocultarle el envío que acaba de confirmar la otra conexión.
        $otra->beginTransaction();
        try {
            $this->assertTrue((new ActivacionPropietario($otra))->esPendiente($id));
            $nuevo = $modelo->renovarPorTitular($id);
            $this->assertNotNull($nuevo);
            $this->assertNull((new ActivacionPropietario($otra))->renovarPorTitular($id));
        } finally {
            $otra->rollBack();
        }
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM verificaciones_email WHERE id_usuario = ' . $id)->fetchColumn());
        $this->assertSame((int) $nuevo['id'], (int) $this->db->query('SELECT id FROM verificaciones_email WHERE id_usuario = ' . $id . ' AND used = 0')->fetchColumn());
        $_SESSION = [];
    }

    public function testE2MigracionRepetibleConservaLimitesPersonalizados(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->db->exec("UPDATE planes SET limite_mascotas = 5, limite_citas_mes = 30 WHERE nombre = 'Gratuito'");
        $this->cargar('05_limites_plan_gratuito.sql');
        $this->cargar('05_limites_plan_gratuito.sql');
        $this->assertSame([50, 100, 2], array_map('intval', $this->db->query("SELECT limite_mascotas, limite_citas_mes, limite_personal FROM planes WHERE nombre = 'Gratuito'")->fetch(PDO::FETCH_NUM)));
        $this->db->exec("UPDATE planes SET limite_mascotas = 17, limite_citas_mes = 30 WHERE nombre = 'Gratuito'");
        $this->cargar('05_limites_plan_gratuito.sql');
        $this->cargar('02_semilla.sql');
        $this->assertSame([17, 30, 2], array_map('intval', $this->db->query("SELECT limite_mascotas, limite_citas_mes, limite_personal FROM planes WHERE nombre = 'Gratuito'")->fetch(PDO::FETCH_NUM)));
        $this->assertSame(89990, (int) $this->db->query("SELECT precio_mensual FROM planes WHERE nombre = 'Profesional'")->fetchColumn());
    }

    public function testE2CuotasYBloqueoDeClinicaEnElEsquemaReal(): void
    {
        $this->prepararHistoriaClinica();
        require_once __DIR__ . '/../../helpers/LimitePlan.php';
        require_once __DIR__ . '/../../models/Cita.php';
        DosClinicas::poblarAgenda($this->db);
        $this->comoVeterinario(1, 2);
        $this->db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 1');
        $plan = new LimitePlan($this->db, 1);
        $this->assertSame(50, $plan->resumen()['mascotas']['limite']);
        // La misma fila de clínica que bloquea Plataforma serializa cualquier alta del plan.
        $otra = $this->otraConexion();
        $otra->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $this->db->beginTransaction();
        $this->db->query('SELECT id_clinica FROM clinicas WHERE id_clinica = 1 FOR UPDATE')->fetchColumn();
        try {
            (new LimitePlan($otra, 1))->consumir('mascotas', static fn () => true);
            $this->fail('Debió esperar el bloqueo de la clínica.');
        } catch (PDOException $error) {
            $this->assertSame(1205, (int) $error->errorInfo[1]);
        } finally {
            $this->db->rollBack();
        }
        $this->db->exec('UPDATE planes SET limite_citas_mes = 0 WHERE id_plan = 1');
        $datos = ['id_mascota' => 1, 'id_veterinario' => 2, 'fecha' => '2027-03-01', 'hora' => '08:00', 'motivo' => 'Urgencia', 'id_tipo_cita' => 1];
        $citas = new Cita($this->db);
        try {
            $citas->registrar($datos);
            $this->fail('Debió bloquear la cita ordinaria.');
        } catch (LimiteAlcanzado $error) {
            $this->assertSame(0, $error->limite);
        }
        $this->db->exec('UPDATE suscripciones SET al_dia = 0 WHERE id_clinica = 1');
        $id = $citas->registrarEvaluada($datos, 'rojo');
        $this->assertSame('rojo', $citas->getById($id)['prioridad']);
        $this->assertSame(1, $plan->resumen('2027-03-01')['citas']['uso']);
        $this->assertSame(0, (new LimitePlan($this->db, 2))->resumen('2027-03-01')['citas']['uso']);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Acción bloqueada%'")->fetchColumn());
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        try {
            $citas->enClinicaDelPropietario(1)->registrar($datos);
            $this->fail('Debió bloquear al propietario.');
        } catch (LimiteAlcanzado $error) {
            $this->assertSame('No es posible completar la acción ahora', $error->getMessage());
        }
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones_internas WHERE id_clinica = 1 AND tipo = 'LIMITE_PLAN'")->fetchColumn());
        $_SESSION = [];
    }

    public function testE2DatosPruebaContinuaSinCambiarClinicaAjena(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->db->exec("INSERT INTO clinicas (nombre, nit, estado) VALUES ('Clínica propia del usuario', '9012345678', 'pendiente_verificacion')");
        $antes = $this->db->query('SELECT * FROM clinicas')->fetch(PDO::FETCH_ASSOC);
        $this->db->exec("INSERT INTO clinicas (nombre, nit, estado) VALUES ('Clínica Norte (prueba)', '900123456-8', 'activa')");
        $db = $this->db;
        $argv = ['datos_prueba.php', '--si'];
        ob_start();
        try {
            include __DIR__ . '/../../scripts/dev/datos_prueba.php';
            $salida = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('9012345678', $salida);
        $this->assertStringContainsString('Datos de prueba listos', $salida);
        $this->assertSame($antes, $this->db->query('SELECT * FROM clinicas WHERE id_clinica = 1')->fetch(PDO::FETCH_ASSOC));
        $this->assertSame(3, $this->contar('clinicas'));
        $this->assertSame('9001234568', $this->db->query('SELECT nit FROM clinicas WHERE id_clinica = 2')->fetchColumn());
        // F0: las clínicas de prueba quedan en Profesional con una suscripción activa; la ajena no cambia.
        $this->assertSame([2, 2], array_map('intval', $this->db->query("SELECT id_plan FROM clinicas WHERE nit IN ('9001234568', '9006543210') ORDER BY id_clinica")->fetchAll(PDO::FETCH_COLUMN)));
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM suscripciones s JOIN planes p ON p.id_plan = s.id_plan WHERE p.nombre = 'Profesional' AND s.estado = 'activa' AND s.al_dia = 1")->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM suscripciones WHERE id_clinica = 1')->fetchColumn());
    }

    /** E1 sobre el MER real: ENUM, vínculos, suscripciones, soporte, IP y auditoría. */
    public function testE1PanelPlataformaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/PlataformaFixture.php';
        require_once __DIR__ . '/../../models/Plataforma.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        PlataformaFixture::datos($this->db);
        PlataformaFixture::plataforma();
        try {
            $modelo = new Plataforma($this->db);
            $this->assertCount(4, $modelo->clinicas());
            $modelo->cambiarSuscripcion(1, 2, 'activa', 'Cambio solicitado', '2099-12-31');
            $modelo->cambiarSuscripcion(1, 2, 'cancelada', 'Cancelación solicitada', '2099-12-31');
            $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM suscripciones')->fetchColumn());
            $this->assertSame('cancelada', $this->db->query('SELECT estado FROM suscripciones')->fetchColumn());
            $this->assertSame(2, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 1')->fetchColumn());
            $modelo->cambiarEstado(1, 'suspendida', 'Revisar titularidad');
            $this->assertSame([], (new Usuario($this->db))->contextosDe(2));
            $modelo->cambiarEstado(1, 'activa', 'Titularidad válida');
            $this->assertCount(1, (new Usuario($this->db))->contextosDe(2));
            $modelo->cerrarCaso(1, 'resuelto', 'Titular identificado');
            $modelo->cerrarCaso(2, 'descartado', 'Caso improcedente');
            $this->assertCount(2, $modelo->casos());
            $ips = $modelo->ips();
            $this->assertCount(3, $ips[0]['cuentas']);
            $envios = [];
            $modelo->avisar('192.0.2.8', '2:3', 'Aviso editado', function (array $admin, string $texto) use (&$envios): bool {
                $envios[] = [$admin['email'], $texto];
                return true;
            });
            $this->assertSame([['carla@zooki.test', 'Aviso editado']], $envios);
            $modelo->desbloquear('192.0.2.8');
            $this->assertSame([], $modelo->ips());
            $this->assertSame(9, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE accion = 'UPDATE'")->fetchColumn());
            require_once __DIR__ . '/../../models/RegistroClinica.php';
            $registro = new RegistroClinica($this->db);
            $_SERVER['REMOTE_ADDR'] = '192.0.2.50';
            Auditoria::definirProxiesConfiables([]);
            $reporte = ['nit' => '900123456-8', 'email' => 'titular@zooki.test'];
            for ($i = 0; $i < 5; $i++) {
                $registro->reportarNit($reporte);
            }
            try {
                $registro->reportarNit($reporte);
                $this->fail('El sexto reporte debe rechazarse también en MySQL.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(7, (int) $this->db->query('SELECT COUNT(*) FROM casos_soporte')->fetchColumn());
            }
            $this->db->exec("CREATE TRIGGER impedir_auditoria BEFORE INSERT ON auditoria_sistema FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sin auditoria'");
            try {
                $modelo->cambiarEstado(1, 'baja', 'No persistir');
                $this->fail('Debe revertir sin auditoría.');
            } catch (RuntimeException $e) {
                $this->assertSame('activa', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica = 1')->fetchColumn());
            }
        } finally {
            $_SESSION = [];
        }
    }

    /** D2 contra FK, ENUM, unicidad y enlaces reales; siempre en la base de pruebas por defecto. */
    public function testD2CuentasEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/CuentaTitular.php';
        require_once __DIR__ . '/../../models/IntentoLogin.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        try {
            $modelo = new CuentaTitular($this->db);
            $datos = ['documento' => '1033333333', 'tipo_documento' => 'CC', 'email' => 'laura@zooki.test', 'nombre_completo' => 'Laura Nueva', 'telefono' => '3001112233', 'id_rol' => 2];
            $alta = $modelo->crearPersonal($datos, 1);
            $usuario = new Usuario($this->db);
            $this->assertNull($usuario->contextosDe($alta['id_usuario']));
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn());
            $modelo->activar($alta['enlace']['id'], $alta['enlace']['token'], ['password' => 'Bosque#Seguro2026', 'confirm_password' => 'Bosque#Seguro2026', 'acepta_datos' => '1'], '192.0.2.10');
            $this->assertTrue($usuario->verificarPassword($alta['id_usuario'], 'Bosque#Seguro2026'));
            $this->assertSame('alta_personal', $this->db->query('SELECT medio FROM consentimientos_datos')->fetchColumn());
            $this->db->exec("UPDATE usuarios SET google_uid = 'google-fabio' WHERE id_usuario = 6");
            $correo = $modelo->solicitarCorreo(6, ['email' => 'nuevo.fabio@zooki.test', 'password_actual' => DosClinicas::PASSWORD]);
            $this->assertSame('fabio@zooki.test', $usuario->buscarPorId(6)['email']);
            $modelo->confirmarCorreo($correo['enlace']['id'], $correo['enlace']['token']);
            $this->assertSame('nuevo.fabio@zooki.test', $usuario->buscarPorId(6)['email']);
            $this->assertSame(0, (int) $usuario->buscarPorId(6)['tiene_google']);
            try {
                $modelo->cambiarDocumento(6, ['documento' => '1000000001', 'tipo_documento' => 'CC', 'password_actual' => DosClinicas::PASSWORD]);
                $this->fail('Debe abrir soporte sin aplicar documento duplicado.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM casos_soporte')->fetchColumn());
            }
            $reset = $modelo->restablecerPersonal(2, 1);
            $this->assertSame(0, (int) $usuario->buscarPorId(2)['tiene_password']);
            $tokens = new PasswordReset($this->db);
            $this->assertTrue($tokens->consumirToken($reset['enlace']['id']));
            $this->assertFalse($tokens->consumirToken($reset['enlace']['id']));
            $datos['documento'] = '1044444444';
            $datos['email'] = 'pendiente@zooki.test';
            $pendiente = $modelo->crearPersonal($datos, 1);
            (new Auditoria($this->db))->log($pendiente['id_usuario'], 'LOGIN_FAIL', 'usuarios', $pendiente['id_usuario'], null, null, 'Intento sobre alta pendiente', null);
            $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$pendiente['enlace']['id']]);
            $usuario->asignarRolEnClinica($pendiente['id_usuario'], 2, 2);
            $usuario->cambiarEstadoEnClinica($pendiente['id_usuario'], 2, false);
            $this->assertSame(0, $modelo->limpiarPendientes());
            $this->db->prepare('DELETE FROM usuario_clinica WHERE id_usuario = ? AND id_clinica = 2')->execute([$pendiente['id_usuario']]);
            $this->assertSame(1, $modelo->limpiarPendientes());
            $this->assertNull($usuario->buscarPorId($pendiente['id_usuario']));
            $intentos = new IntentoLogin($this->db);
            for ($i = 0; $i < 5; $i++) {
                $intentos->registrarFallo('cuenta:6', 5, 900, 900);
            }
            $this->assertTrue($intentos->exigeCaptcha('cuenta:6'));
        } finally {
            $_SESSION = [];
        }
    }

    /** D2.2: actualizar una base anterior conserva sus datos y se puede repetir. */
    public function testMigracionD22ActualizaUnaBaseAnteriorDosVeces(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/VerificacionEmail.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $id = (new VerificacionEmail($this->db))->crear(6, 'fabio@zooki.test', 'hash-conservado', '2030-01-01 00:00:00');
        $this->db->exec('ALTER TABLE verificaciones_email DROP FOREIGN KEY fk_verif_rol_vinculo');
        $this->db->exec('ALTER TABLE verificaciones_email DROP COLUMN id_rol_vinculo, DROP COLUMN nombre_invitado, DROP COLUMN tipo_documento_invitado, DROP COLUMN documento_invitado, DROP COLUMN email_invitado, DROP COLUMN telefono_invitado');
        $this->db->exec('ALTER TABLE usuarios DROP COLUMN version_sesion');
        $this->cargar('04_invitacion_personal_y_sesiones.sql');
        $this->cargar('04_invitacion_personal_y_sesiones.sql');
        $columnas = $this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['id_rol_vinculo', 'nombre_invitado', 'tipo_documento_invitado', 'documento_invitado', 'email_invitado', 'telefono_invitado'] as $columna) {
            $this->assertContains($columna, $columnas);
        }
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND CONSTRAINT_NAME = 'fk_verif_rol_vinculo'")->fetchColumn());
        $fila = (new VerificacionEmail($this->db))->buscarPorId($id);
        $this->assertSame('hash-conservado', $fila['token_hash']);
        $this->assertSame('fabio@zooki.test', $fila['email']);
        $this->assertNull($fila['id_rol_vinculo']);
        $this->assertSame(0, (int) $this->db->query('SELECT MAX(version_sesion) FROM usuarios')->fetchColumn());
        $this->db->exec('UPDATE usuarios SET version_sesion = 3 WHERE id_usuario = 6');
        $this->db->prepare('UPDATE verificaciones_email SET id_rol_vinculo = 2, nombre_invitado = ? WHERE id = ?')->execute(['Nombre escrito', $id]);
        $this->cargar('04_invitacion_personal_y_sesiones.sql');
        $this->assertSame(3, (int) $this->db->query('SELECT version_sesion FROM usuarios WHERE id_usuario = 6')->fetchColumn());
        $this->assertSame('Nombre escrito', (new VerificacionEmail($this->db))->buscarPorId($id)['nombre_invitado']);
    }

    /** RE-T.7.5: UNION, aislamiento, datos escritos y aceptación sin duplicar identidad. */
    public function testD22InvitacionesYVersionSesionEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/InvitacionPersonal.php';
        require_once __DIR__ . '/../../models/CuentaTitular.php';
        require_once __DIR__ . '/../../helpers/CierreSesiones.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $usuarios = new Usuario($this->db);
        $invitaciones = new InvitacionPersonal($this->db);
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN), 1);
        try {
            $datos = ['documento' => '1099999999', 'tipo_documento' => 'CC', 'nombre_completo' => 'Ana Invitada', 'email' => 'ana.invitada@zooki.test', 'telefono' => '3001112233', 'id_rol' => 2];
            $nueva = (new CuentaTitular($this->db))->crearPersonal($datos, 1);
            $escritos = ['documento' => '1088888888', 'tipo_documento' => 'CE', 'nombre_completo' => 'Zeta Escrita', 'email' => 'escrito@zooki.test', 'telefono' => '3004445566', 'id_rol' => 2];
            $existente = $invitaciones->crear($usuarios->buscarPorId(6), 1, $escritos);
            $sur = $invitaciones->crear($usuarios->buscarPorId(6), 2, $escritos);
            $cuentas = $this->contar('usuarios');
            $pendientes = $invitaciones->pendientesDeClinica(1);
            $this->assertSame(['Ana Invitada', 'Zeta Escrita'], array_column($pendientes, 'nombre_completo'));
            $this->assertSame([$nueva['enlace']['id'], $existente['id']], array_map('intval', array_column($pendientes, 'id_invitacion')));
            foreach (['documento', 'tipo_documento', 'email', 'telefono'] as $campo) {
                $this->assertSame($escritos[$campo], $pendientes[1][$campo]);
            }
            $this->assertCount(1, $invitaciones->pendientesDeClinica(2));
            $this->assertNull($invitaciones->pendienteDeClinica($sur['id'], 1));
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM usuario_clinica WHERE id_usuario = 6')->fetchColumn());
            $this->assertSame('Clínica Norte', $invitaciones->aceptar($existente['id'], $existente['token'])['clinica']);
            $this->assertSame($cuentas, $this->contar('usuarios'));
            $this->assertSame(2, (int) $this->db->query('SELECT id_rol FROM usuario_clinica WHERE id_usuario = 6 AND id_clinica = 1')->fetchColumn());
            $this->assertSame('activo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 1')->fetchColumn());
            $this->assertCount(2, $usuarios->contextosDe(6));
            $this->assertNull($invitaciones->leer($existente['id'], $existente['token']));
            $this->assertCount(1, $invitaciones->pendientesDeClinica(1));
            $this->assertCount(1, $invitaciones->pendientesDeClinica(2));
            $invitaciones->rechazar($sur['id'], $sur['token']);
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM usuario_clinica WHERE id_usuario = 6 AND id_clinica = 2')->fetchColumn());
            $_SESSION = ['id_usuario' => 6, 'version_sesion' => 0];
            CierreSesiones::cerrarOtras($usuarios, 6);
            $this->assertSame(1, $usuarios->versionSesion(6));
            $this->assertSame(1, $_SESSION['version_sesion']);
            $this->assertSame(0, $usuarios->versionSesion(1));
        } finally {
            $_SESSION = [];
        }
    }

    /** La misma hora para PHP y MySQL, incluso si arrancaron con zonas diferentes. */
    public function testD22ZonaHorariaAlineaNowYDate(): void
    {
        require_once __DIR__ . '/../../helpers/ZonaHoraria.php';
        $anterior = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Berlin');
            $this->db->exec("SET time_zone = '+02:00'");
            ZonaHoraria::aplicarEnConexion($this->db);
            $this->assertSame('America/Bogota', date_default_timezone_get());
            $this->assertSame('-05:00', $this->db->query('SELECT @@session.time_zone')->fetchColumn());
            $horaMysql = (string) $this->db->query('SELECT NOW()')->fetchColumn();
            $this->assertLessThanOrEqual(2, abs(strtotime($horaMysql) - strtotime(date('Y-m-d H:i:s'))));
        } finally {
            date_default_timezone_set($anterior);
        }
    }

    /** D3: FK y límites reales; el rollback incluye token, rol y todos los catálogos. */
    public function testD3RegistroActivacionYRollbackEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/RegistroClinicaTest.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $explicita = (new Database(Database::configuracionPruebas()))->getConnection();
        $this->assertSame('zooki_test_base_v2', $explicita->query('SELECT DATABASE()')->fetchColumn());
        unset($explicita);
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.201';
        $modelo = new RegistroClinica($this->db);
        $alta = $modelo->registrar(RegistroClinicaTest::datos(), Auditoria::ipCliente());
        $fila = $modelo->leer($alta['enlace']['id'], $alta['enlace']['token']);
        $this->assertNull((new Usuario($this->db))->contextosDe((int) $fila['id_usuario']));
        $this->db->exec("CREATE TRIGGER fallo_d3 BEFORE INSERT ON laboratorios_base FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo simulado'");
        try {
            $modelo->activar($alta['enlace']['id'], $alta['enlace']['token'], ['acepta_datos' => '1'], Auditoria::ipCliente());
            $this->fail('Debía fallar el catálogo.');
        } catch (PDOException $e) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM tipos_cita')->fetchColumn());
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM vacunas_base')->fetchColumn());
            $this->assertNotNull($modelo->leer($alta['enlace']['id'], $alta['enlace']['token']));
        }
        $this->db->exec('DROP TRIGGER fallo_d3');
        $clinica = $modelo->activar($alta['enlace']['id'], $alta['enlace']['token'], ['acepta_datos' => '1'], Auditoria::ipCliente());
        foreach (['tipos_cita' => 7, 'horarios_clinica' => 7, 'vacunas_base' => 15, 'laboratorios_base' => 11, 'productos_desparasitacion_base' => 15] as $tabla => $esperado) {
            $this->assertSame($esperado, (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla . ' WHERE id_clinica = ' . $clinica)->fetchColumn());
        }
        $persona = (new Usuario($this->db))->buscarParaLogin('fabio@zooki.test');
        $existente = $modelo->registrar(RegistroClinicaTest::datos(2, ['email' => 'fabio@zooki.test']), Auditoria::ipCliente());
        $modelo->activar($existente['enlace']['id'], $existente['enlace']['token'], ['acepta_datos' => '1'], Auditoria::ipCliente());
        $this->assertSame($persona, (new Usuario($this->db))->buscarParaLogin('fabio@zooki.test'));
        $this->assertCount(2, (new Usuario($this->db))->contextosDe(6));
        $vence = $modelo->registrar(RegistroClinicaTest::datos(3, ['documento' => '1000000006']), Auditoria::ipCliente());
        try {
            $modelo->registrar(RegistroClinicaTest::datos(4), Auditoria::ipCliente());
            $this->fail('No debe admitirse el cuarto registro.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('límite', $e->getMessage());
        }
        $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$vence['enlace']['id']]);
        $modelo->reportarNit(RegistroClinicaTest::datos(3));
        $this->assertSame(1, $modelo->limpiarPendientes());
        $this->assertNotNull((new Usuario($this->db))->buscarPorId(6));
        $_SERVER['REMOTE_ADDR'] = '192.0.2.202';
        $nueva = $modelo->registrar(RegistroClinicaTest::datos(5), Auditoria::ipCliente());
        $idNueva = (int) $modelo->leer($nueva['enlace']['id'], $nueva['enlace']['token'])['id_usuario'];
        $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$nueva['enlace']['id']]);
        $this->assertSame(1, $modelo->limpiarPendientes());
        $this->assertNull((new Usuario($this->db))->buscarPorId($idNueva));
        // E2: el NIT de datos(6) ya pertenece a Norte aunque tenga guion.
        $primera = $modelo->registrar(RegistroClinicaTest::datos(26), Auditoria::ipCliente());
        $idCompartido = (int) $modelo->leer($primera['enlace']['id'], $primera['enlace']['token'])['id_usuario'];
        $segunda = $modelo->registrar(RegistroClinicaTest::datos(7, ['email' => $primera['email']]), Auditoria::ipCliente());
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE used = 0");
        $this->assertSame(2, $modelo->limpiarPendientes());
        $this->assertNull((new Usuario($this->db))->buscarPorId($idCompartido));
        $_SERVER['REMOTE_ADDR'] = '192.0.2.203';
        $sinEnlace = $modelo->registrar(RegistroClinicaTest::datos(8, ['email' => 'fabio@zooki.test']), Auditoria::ipCliente());
        $idClinica = (int) $modelo->leer($sinEnlace['enlace']['id'], $sinEnlace['enlace']['token'])['id_clinica_vinculo'];
        $this->db->prepare("UPDATE clinicas SET fecha_registro = '2000-01-01 00:00:00' WHERE id_clinica = ?")->execute([$idClinica]);
        $this->db->prepare('DELETE FROM verificaciones_email WHERE id = ?')->execute([$sinEnlace['enlace']['id']]);
        $this->assertSame(1, $modelo->limpiarPendientes());
        $this->assertNotNull((new Usuario($this->db))->buscarPorId(6));
    }

    private const DATABASE = __DIR__ . '/../../database';

    private PDO $db;
    private string $base;
    private ?string $carpetaTemporal = null;

    protected function setUp(): void
    {
        $host = getenv('ZOOKI_TEST_MYSQL_HOST');
        if ($host === false || $host === '') {
            $this->markTestSkipped('Sin ZOOKI_TEST_MYSQL_HOST: no hay MySQL de prueba.');
        }
        $this->base = getenv('ZOOKI_TEST_MYSQL_DB') ?: 'zooki_test_base_v2';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->base)) {
            $this->fail('ZOOKI_TEST_MYSQL_DB solo admite letras, números y guion bajo.');
        }

        try {
            $this->db = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
                getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root',
                getenv('ZOOKI_TEST_MYSQL_PASS') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $this->markTestSkipped('No se pudo conectar al MySQL de prueba: ' . $e->getMessage());
        }

        $this->db->exec("DROP DATABASE IF EXISTS `{$this->base}`");
        $this->db->exec("CREATE DATABASE `{$this->base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $this->db->exec("USE `{$this->base}`");
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->exec("DROP DATABASE IF EXISTS `{$this->base}`");
        }
        if ($this->carpetaTemporal !== null) {
            array_map('unlink', glob($this->carpetaTemporal . '/*'));
            rmdir($this->carpetaTemporal);
        }
    }

    public function testC8CronRealConCorreoSimuladoYDosClinicas(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        require_once __DIR__ . '/../Support/RecordatoriosDosClinicas.php';
        DosClinicas::poblar($this->db);
        RecordatoriosDosClinicas::poblar($this->db);
        $db = $this->db;
        $emailService = new CorreoRecordatorioSimulado();
        $ahora = new DateTimeImmutable('2026-09-23 01:00:00 UTC');
        $appUrl = 'https://zooki.test/index.php';
        ob_start();
        try {
            include __DIR__ . '/../../scripts/send_reminders.php';
            $this->assertSame(7, $intentos);
            include __DIR__ . '/../../scripts/send_reminders.php';
            $this->assertSame(0, $intentos);
            $db->exec("DELETE FROM notificaciones");
            $db->exec("UPDATE mascota_clinica SET estado='inactivo' WHERE id_clinica=2");
            include __DIR__ . '/../../scripts/send_reminders.php';
            $this->assertSame(4, $intentos);
        } finally {
            ob_end_clean();
        }
        $this->assertSame([1], array_map('intval', $db->query('SELECT DISTINCT id_clinica FROM notificaciones')->fetchAll(PDO::FETCH_COLUMN)));
        $this->assertSame([6], array_map('intval', $db->query('SELECT DISTINCT id_usuario FROM notificaciones')->fetchAll(PDO::FETCH_COLUMN)));
        foreach ($emailService->envios as $envio) {
            $this->assertStringContainsString('Clínica ', $envio['asunto']);
            $this->assertStringContainsString('Luna', $envio['cuerpo']);
        }
    }

    public function testElEsquemaCreaLasCincuentaYNueveTablasEnInnoDbYUtf8mb4(): void
    {
        $this->cargar('01_schema.sql');

        $tablas = $this->db->query(
            "SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(65, $tablas);
        foreach ($tablas as $t) {
            $this->assertSame('InnoDB', $t['ENGINE']);
            $this->assertSame('utf8mb4_general_ci', $t['TABLE_COLLATION']);
        }
    }

    public function testLaSemillaCargaLosCatalogosYNoDuplicaAlRepetirse(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->cargar('02_semilla.sql');

        $this->assertSame(['roles' => 4, 'planes' => 2, 'especies' => 6, 'razas' => 55, 'colores_base' => 9, 'especialidades' => 0], $this->conteos());
        $this->assertSame(
            [1 => 'administrador', 2 => 'veterinario', 4 => 'propietario', 5 => 'super-administrador'],
            $this->db->query('SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol')->fetchAll(PDO::FETCH_KEY_PAIR)
        );
        $this->assertSame(0, $this->contar('usuarios'));
        $this->assertSame(0, $this->contar('clinicas'));
    }

    public function testElMigradorEstaAlDiaDosVecesSeguidas(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $antes = $this->conteos();

        $primera = $this->migrador()->migrar();
        $segunda = $this->migrador()->migrar();

        $this->assertSame(['ejecutadas' => ['03_confirmacion_vinculo_propietario.sql', '04_invitacion_personal_y_sesiones.sql', '05_limites_plan_gratuito.sql', '06_medio_activacion_propietario.sql', '07_grafo_conocimiento.sql', '08_agenda_grafo_ii.sql', '09_origen_interno_agenda.sql', '10_comienzos_urgencia.sql', '11_accesos_carnet.sql', '12_suscripcion_pagos.sql', '13_plataforma_finanzas.sql', '14_aviso_pago_confirmado.sql', '15_correcciones_agenda.sql', '16_pautas_tratamiento.sql'], 'semilla' => true], $primera);
        $this->assertSame(['ejecutadas' => [], 'semilla' => true], $segunda);
        $this->assertSame($antes, $this->conteos());
    }

    /** C3: aislamiento, consentimiento, confirmacion y ficha global en el esquema real. */
    public function testC3MascotasYConfirmacionEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/Mascota.php';
        require_once __DIR__ . '/../../models/PropietarioClinica.php';
        $this->cargar('01_schema.sql'); $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db); DosClinicas::completarMascota($this->db);
        $_SESSION=['id_usuario'=>4]; Contexto::activar(Contexto::deClinica(2,'Clínica Sur',Roles::VETERINARIO),1);
        try {
            $mascotas=new Mascota($this->db); $propietarios=new PropietarioClinica($this->db);
            $this->assertSame([],$mascotas->getAll());
            try { $mascotas->getById(1); $this->fail('Debió rechazar'); } catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
            $this->assertNull($propietarios->buscarExacto('fabio@'));
            $solicitud=$propietarios->solicitarVinculo('fabio@zooki.test');
            $this->assertFalse($mascotas->esPropietarioValido(6));
            $this->assertTrue($propietarios->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
            $this->assertFalse($propietarios->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
            $mascotas->vincular(1,6); $mascotas->vincular(1,6);
            $this->assertSame(1,$this->contar('mascotas')); $this->assertSame(2,$this->contar('mascota_clinica'));
            try { $mascotas->update(['id_mascota'=>1,'id_especie'=>2]); $this->fail('Debió rechazar'); } catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
            $mascotas->update(['id_mascota'=>1,'peso'=>'9.25']);
            $this->assertSame('9.25',(string)$mascotas->getById(1)['peso']);
            $this->assertSame(1,$this->contar('notificaciones'));
            $datos=['id_propietario'=>6,'nombre'=>'Nueva ficha','id_especie'=>1,'id_raza'=>null,
                'raza_indicada'=>'Raza por confirmar','fecha_nacimiento'=>'2022-01-01','peso'=>'3.20','sexo'=>'Macho','colores'=>[1,2]];
            $id=$mascotas->insert($datos); $otra=$mascotas->insert($datos);
            $this->assertSame(55, $this->contar('razas'));
            $m=$mascotas->getById($id); $this->assertNull($m['numero_historia_clinica']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D',$m['token_carnet']);
            $this->assertNotSame($m['token_carnet'],$mascotas->getById($otra)['token_carnet']);
            $this->assertSame('1,2',$m['colores_ids']);
            $cuentas=$this->contar('usuarios');
            // M5 C3 (RE-T.19.1): el alta del personal deja la cuenta pendiente, sin consentimiento, con el enlace de activación.
            $datos=['nombre_completo'=>'Titular Nuevo','tipo_documento'=>'CC','documento'=>'1000000030','telefono'=>'3001234567',
                'email'=>'nuevo@zooki.test'];
            $alta=$propietarios->registrar($datos);
            $this->assertSame($cuentas+1,$this->contar('usuarios'));
            $this->assertNull($this->db->query('SELECT password FROM usuarios WHERE id_usuario=' . $alta['id_usuario'])->fetchColumn());
            $this->assertSame(0,(int)$this->db->query('SELECT estado FROM usuarios WHERE id_usuario=' . $alta['id_usuario'])->fetchColumn());
            $this->assertSame(0,$this->contar('consentimientos_datos'));
            $this->assertSame('activacion_propietario',$this->db->query('SELECT proposito FROM verificaciones_email WHERE id=' . $alta['id'])->fetchColumn());
            try { $propietarios->registrar($datos); $this->fail('Debió rechazar el duplicado'); }
            catch (InvalidArgumentException $e) { $this->assertSame(PropietarioClinica::MENSAJE_SIN_VINCULO,$e->getMessage()); }
        } finally { $_SESSION=[]; }
    }

    public function testMigracionC3ActualizaUnaBaseAnteriorYSePuedeRepetir(): void
    {
        $this->cargar('01_schema.sql'); $this->cargar('02_semilla.sql');
        // Reproduce la forma publicada en B antes de la confirmación adelantada.
        $this->db->exec('ALTER TABLE verificaciones_email DROP FOREIGN KEY fk_verif_clinica_vinculo');
        $this->db->exec('ALTER TABLE verificaciones_email DROP COLUMN id_clinica_vinculo, DROP COLUMN proposito');
        $this->cargar('03_confirmacion_vinculo_propietario.sql');
        $this->cargar('03_confirmacion_vinculo_propietario.sql');
        $columnas=$this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='verificaciones_email'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('id_clinica_vinculo',$columnas); $this->assertContains('proposito',$columnas);
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_verif_clinica_vinculo'")->fetchColumn());
    }

    /** M5 C4b: la migración 06 agrega el medio del propietario a una base anterior y se puede repetir. */
    public function testMigracionC4bAgregaElMedioDelPropietarioYSePuedeRepetir(): void
    {
        $this->cargar('01_schema.sql'); $this->cargar('02_semilla.sql');
        // La forma publicada antes de C4b.
        $this->db->exec("ALTER TABLE consentimientos_datos MODIFY medio ENUM('formulario','google','alta_personal') NOT NULL");
        $this->cargar('06_medio_activacion_propietario.sql');
        $this->cargar('06_medio_activacion_propietario.sql');
        $tipo=$this->db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consentimientos_datos' AND COLUMN_NAME='medio'")->fetchColumn();
        $this->assertSame("enum('formulario','google','alta_personal','activacion_propietario')",$tipo);
        $this->assertSame('NO',$this->db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consentimientos_datos' AND COLUMN_NAME='medio'")->fetchColumn());
    }

    /** D-5: la semilla solo inserta lo que falta y nunca sobrescribe. */
    public function testLaSemillaNoSobrescribeNiRecreaLoQueCambioLaPlataforma(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->db->exec("UPDATE planes SET precio_mensual = 120000 WHERE id_plan = 2");
        $this->db->exec("UPDATE colores_base SET nombre_color = 'Café oscuro' WHERE id_color = 3");

        $this->migrador()->migrar();

        $this->assertSame(120000, (int) $this->db->query('SELECT precio_mensual FROM planes WHERE id_plan = 2')->fetchColumn());
        $this->assertSame('Café oscuro', $this->db->query('SELECT nombre_color FROM colores_base WHERE id_color = 3')->fetchColumn());
        $this->assertSame(9, $this->contar('colores_base'));
    }

    public function testUnaMigracionNuevaSeAplicaUnaSolaVez(): void
    {
        $this->cargar('01_schema.sql');
        $carpeta = $this->carpetaConMigracion(
            '03_prueba_migrador.sql',
            "CREATE TABLE IF NOT EXISTS prueba_migrador (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB;\n"
            . "INSERT IGNORE INTO prueba_migrador (id) VALUES (1);"
        );

        $revision = (new Migrador($this->db, $carpeta))->migrar(true);
        $primera = (new Migrador($this->db, $carpeta))->migrar();
        $segunda = (new Migrador($this->db, $carpeta))->migrar();

        $this->assertSame(['03_prueba_migrador.sql'], $revision['ejecutadas']);
        $this->assertSame(['03_prueba_migrador.sql'], $primera['ejecutadas']);
        $this->assertSame([], $segunda['ejecutadas']);
        $this->assertSame(1, $this->contar('schema_migraciones'));
        $this->assertSame(4, $this->contar('roles'), 'La semilla también se aplicó');
    }

    public function testSeNiegaSobreUnaBaseV1(): void
    {
        // La forma de la v1: usuarios con el documento como clave y sin clinicas.
        $this->db->exec("CREATE TABLE usuarios (documento VARCHAR(20) NOT NULL PRIMARY KEY, id_rol INT) ENGINE=InnoDB");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('esquema v1');
        $this->migrador()->migrar();
    }

    public function testSeNiegaSobreUnaBaseSinEsquema(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('01_schema.sql');
        $this->migrador()->migrar();
    }

    /** D-2 / RN-401: la base impide dos reservas del mismo veterinario a la misma hora. */
    public function testLaDobleReservaSeRechazaSalvoSiLaCitaEstaLibreOEsSobrecupo(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        [$clinicaA, $clinicaB, $vet, $mascota] = $this->datosDeAgenda();

        $primera = $this->reservar($clinicaA, $mascota, $vet, 0);

        // Mismo veterinario y hora, aunque sea en otra clínica: se rechaza.
        try {
            $this->reservar($clinicaB, $mascota, $vet, 0);
            $this->fail('Se aceptó una doble reserva del mismo veterinario.');
        } catch (PDOException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        // Un sobrecupo no ocupa el horario.
        $this->reservar($clinicaA, $mascota, $vet, 1);

        // Al cancelar la primera, el horario queda libre otra vez.
        $this->db->exec("UPDATE citas SET estado = 'cancelada' WHERE id_cita = {$primera}");
        $this->reservar($clinicaA, $mascota, $vet, 0);

        $this->assertSame(3, $this->contar('citas'));
    }

    public function testCreaElSuperAdministradorSinClinicaYNoRepiteElCorreo(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $creador = new CreadorSuperAdmin($this->db);

        $id = $creador->crear('operador@zooki.test', 'Operador Zooki', 'Plataforma#Vet2026');

        $fila = $this->db->query("SELECT * FROM usuarios WHERE id_usuario = {$id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $fila['es_super_admin']);
        $this->assertNull($fila['documento']);
        $this->assertTrue(password_verify('Plataforma#Vet2026', $fila['password']));
        $this->assertSame(0, $this->contar('usuario_clinica'));
        $this->assertSame(0, $this->contar('propietario_clinica'));
        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM auditoria_sistema WHERE id_usuario = {$id} AND id_clinica IS NULL AND accion = 'INSERT'"
        )->fetchColumn());

        try {
            $creador->crear('OPERADOR@zooki.test', 'Otra persona', 'Plataforma#Vet2026');
            $this->fail('Se creó una segunda cuenta con el mismo correo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('correo', $e->getMessage());
        }
        $this->assertSame(1, $this->contar('usuarios'));
    }

    /**
     * C1 — El fixture de dos clínicas que usan las pruebas en SQLite también
     * carga en el esquema real, y los contextos salen iguales: si 01_schema.sql
     * cambia una columna que el fixture usa, esta prueba lo detecta.
     */
    public function testElFixtureDeDosClinicasCargaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/Usuario.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');

        DosClinicas::poblar($this->db);
        $usuario = new Usuario($this->db);

        $this->assertSame(['clinica:1:2', 'clinica:2:1', 'propietario'], array_column($usuario->contextosDe(DosClinicas::DOBLE), 'clave'));
        $this->assertSame(['plataforma'], array_column($usuario->contextosDe(DosClinicas::SUPER_ADMIN), 'clave'));
        $this->assertSame([1, 2, 5, 8], array_map('intval', array_column($usuario->personalDeClinica(DosClinicas::NORTE), 'id_usuario')));
        $this->assertSame(1, (int) $usuario->propietariosDeClinica(DosClinicas::NORTE)[1]['num_mascotas']);
    }

    /** M4 C1, P01/P19: mismos escritores y rollback sobre el esquema y candados reales. */
    public function testM4C1MarcaTiposYAuditoriaAtomicaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/ConfiguracionClinica.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        Auditoria::definirProxiesConfiables([]);
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $modelo = new ConfiguracionClinica($this->db);
        $marca = ['nombre' => 'Norte C1', 'direccion' => 'Calle 1', 'telefono' => '3001234567',
            'telefono_urgencias' => '3007654321', 'email_contacto' => 'norte@example.com', 'url_logo' => 'https://example.com/norte.png'];
        $this->assertSame('Norte C1', $modelo->guardarMarca($marca)['nombre']);
        $tipo = $modelo->guardarTipo(null, ['nombre_tipo' => 'Cirugía C1', 'duracion_minutos' => '45', 'margen_minutos' => '0', 'pausable' => '0']);
        $this->assertSame(0, (int) $tipo['pausable']);
        $this->assertSame(1, (int) $tipo['activo']);
        $this->assertSame(0, (int) $modelo->cambiarEstado((int) $tipo['id_tipo_cita'], ['activo' => '0'])['activo']);
        $this->assertNotSame('Norte C1', $this->db->query('SELECT nombre FROM clinicas WHERE id_clinica=2')->fetchColumn());
        $antes = $modelo->leer();
        $auditorias = $this->contar('auditoria_sistema');
        $this->db->exec("CREATE TRIGGER bloquear_auditoria_c1 BEFORE INSERT ON auditoria_sistema FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria C1 simulada'");
        foreach ([fn () => $modelo->guardarMarca(array_replace($marca, ['nombre' => 'No confirmar'])),
            fn () => $modelo->cambiarEstado((int) $tipo['id_tipo_cita'], ['activo' => '1'])] as $operacion) {
            try {
                $operacion();
                $this->fail('Confirmó la configuración sin auditoría.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('auditar', $error->getMessage());
            }
            $this->assertSame($antes, $modelo->leer());
            $this->assertSame($auditorias, $this->contar('auditoria_sistema'));
        }
        $this->db->exec('DROP TRIGGER bloquear_auditoria_c1');
        $_SESSION = [];
    }

    /** C2: copia repetible, relaciones reales y restauración aislada en MySQL/MariaDB. */
    public function testConfiguracionDeDosClinicasEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../helpers/InicializadorClinica.php';
        require_once __DIR__ . '/../../models/CatalogoClinica.php';
        require_once __DIR__ . '/../../models/HorarioClinica.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $inicializador = new InicializadorClinica($this->db);
        $inicializador->copiar(1);
        $inicializador->copiar(2);
        $inicializador->copiar(1);
        $inicializador->copiar(2);
        foreach (['tipos_cita'=>14,'horarios_clinica'=>14,'vacunas_base'=>30,'especie_vacunas'=>42,
                  'laboratorios_base'=>22,'productos_desparasitacion_base'=>30] as $tabla=>$cantidad) {
            $this->assertSame($cantidad, $this->contar($tabla));
        }
        $_SESSION = ['id_usuario'=>1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $catalogo = new CatalogoClinica($this->db);
        $tipos = $catalogo->tiposCita();
        $this->assertCount(7, $tipos);
        $this->assertCount(12, $catalogo->vacunasPorEspecie(1));
        $horario = new HorarioClinica($this->db);
        $horario->guardar([[1,1,1,0,'09:00:00','11:00:00',null,null]]);
        $horario->restaurar();
        $this->assertSame('08:00:00', $horario->dia(1)['bloque_morning_inicio']);
        Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 1);
        $this->assertNull($catalogo->tipoCita((int) $tipos[0]['id_tipo_cita']));
        $this->assertSame('08:00:00', $horario->dia(1)['bloque_morning_inicio']);
        $this->db->beginTransaction();
        $inicializador->copiar(3);
        $this->db->rollBack();
        $this->assertSame(30, $this->contar('vacunas_base'));
        $_SESSION = [];
    }

    /**
     * C4 en el esquema real: registro atómico con clínica y veterinario,
     * número de historia por clínica, RN-113 con y sin autorización y 403
     * al modificar lo de otra clínica.
     */
    public function testC4HistoriaClinicaEnElEsquemaReal(): void
    {
        $this->prepararHistoriaClinica();
        try {
            $this->comoVeterinario(1, 2);
            $tratamiento = ['medicamento' => 'Amoxicilina', 'dosis' => '1 ml', 'via_administracion' => 'Oral', 'duracion' => '5 días', 'fecha_inicio' => '2026-10-07'];
            $guardar = fn (int $idConsulta, array $adjunto, int $indice): array => $this->metadatosDeAdjunto();
            $consultas = new Consulta($this->db);
            $idConsulta = $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Oído', 'diagnostico' => 'Otitis'], [$tratamiento], [['nombre' => 'rx.jpg']], $guardar);
            $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], $guardar);
            $idVacuna = (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01']);
            $idArchivo = (int) $this->db->query('SELECT id_archivo FROM archivos_clinicos')->fetchColumn();

            $fila = $this->db->query("SELECT id_clinica, id_veterinario FROM consultas WHERE id_consulta = $idConsulta")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame([1, 2], [(int) $fila['id_clinica'], (int) $fila['id_veterinario']]);
            $this->assertSame('HC-000001', $this->numeroHistoria(1, 1));

            // Sin fecha de inicio en un tratamiento no queda nada (RE-2.6.1).
            unset($tratamiento['fecha_inicio']);
            try {
                $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'X', 'diagnostico' => 'Y'], [$tratamiento], [], $guardar);
                $this->fail('Debió rechazar el tratamiento sin fecha de inicio.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('fecha de inicio', $e->getMessage());
            }
            $this->assertSame(2, $this->contar('consultas'));

            $this->comoVeterinario(2, 4);
            $this->assertSame([], $consultas->historialDeMascota(1));
            $this->assertSame('Clínica Norte', (new Vacuna($this->db))->findByMascota(1)[0]['clinica_nombre']);
            $this->assertDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($idArchivo));

            $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 1 WHERE id_propietario = 6 AND id_clinica = 2');
            $historial = $consultas->historialDeMascota(1);
            $this->assertCount(2, $historial);
            $this->assertCount(1, array_merge(...array_column($historial, 'tratamientos')));
            $this->assertSame('CLI_real.jpg', (new ArchivoClinico($this->db))->paraDescargar($idArchivo)['nombre_servidor']);
            $this->assertDenegado(fn () => $consultas->paraModificar($idConsulta));
            $this->assertDenegado(fn () => (new Vacuna($this->db))->paraModificar($idVacuna));

            $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 0 WHERE id_propietario = 6 AND id_clinica = 2');
            $this->assertSame([], $consultas->historialDeMascota(1));

            $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], $guardar);
            $this->assertSame('HC-000001', $this->numeroHistoria(1, 2));
            $this->assertSame('HC-000001', $this->numeroHistoria(1, 1));
            $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND accion = 'OTHER'")->fetchColumn());
        } finally {
            $_SESSION = [];
        }
    }

    /**
     * RF-2.4: mientras otra sesión asigna un número en la misma clínica, una
     * primera consulta espera el candado; si no lo obtiene no guarda nada, y
     * al soltarlo toma el número siguiente sin repetir.
     */
    public function testC4ElNumeroDeHistoriaSeAsignaBajoCandadoSinRepetirse(): void
    {
        $this->prepararHistoriaClinica();
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (2, 6, 1, '" . str_repeat('b', 43) . "', 'Kira', 1, 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 1, 'activo')");
        $otraSesion = $this->otraConexion();
        try {
            $this->comoVeterinario(1, 2);
            $sinAdjuntos = fn (): array => [];
            $consultas = new ConsultaConEsperaCorta($this->db);
            $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);

            $this->db->exec('SET SESSION innodb_lock_wait_timeout=1');
            $otraSesion->beginTransaction();
            $otraSesion->query('SELECT id_clinica FROM clinicas WHERE id_clinica=1 FOR UPDATE')->closeCursor();
            try {
                $consultas->registrar(['id_mascota' => 2, 'peso' => '8.5', 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);
                $this->fail('Debió esperar el candado común de clínica.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('agenda cambió', $e->getMessage());
            }
            $this->assertSame(1, $this->contar('consultas'));
            $this->assertNull($this->numeroHistoria(2, 1));
            $otraSesion->rollBack();
            $consultas->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);
            $consultas->registrar(['id_mascota' => 2, 'peso' => '8.5', 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);
            $this->assertSame('HC-000002', $this->numeroHistoria(2, 1));
            $this->assertFalse($this->db->inTransaction());
        } finally {
            if ($otraSesion->inTransaction()) {
                $otraSesion->rollBack();
            }
            $this->db->exec('SET SESSION innodb_lock_wait_timeout=50');
            $_SESSION = [];
        }
    }

    /**
     * C5 en el esquema real: duración y margen copiados, aislamiento, doble
     * reserva entre clínicas por la aplicación y por el índice único (error
     * 1062 convertido en mensaje) y RN-409 con NotificacionInterna v2.
     */
    public function testC5AgendaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../Support/CitaSinValidacionPrevia.php';
        require_once __DIR__ . '/../../helpers/VigilanteAtenciones.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::completarMascota($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (2, 2, 2, 'activo')");
        // Estado histórico deliberadamente incompatible para probar ocupación interclínica.
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin) VALUES (2,2,1,'08:00:00','12:00:00')");
        $reserva = ['id_mascota' => 1, 'id_veterinario' => 2, 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'Control', 'id_tipo_cita' => 1];
        try {
            $_SESSION = ['id_usuario' => 2];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
            $citas = new Cita($this->db);
            $id = $citas->registrar($reserva);
            $fila = $this->db->query("SELECT id_clinica, duracion_minutos, margen_minutos, hora_fin, prioridad, es_sobrecupo, ocupa_horario FROM citas WHERE id_cita = $id")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame(['1', '30', '10', '09:30:00', 'verde', '0', '1'], array_map('strval', array_values($fila)));

            $_SESSION = ['id_usuario' => 3];
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 1);
            try {
                $citas->getById($id);
                $this->fail('Debió responder 403.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
            $this->assertSame([], $citas->listarRango('2030-01-07', '2030-01-07'));

            $enSur = ['id_tipo_cita' => 2] + $reserva;
            foreach ([$citas, new CitaSinValidacionPrevia($this->db)] as $modelo) {
                try {
                    $modelo->registrar($enSur);
                    $this->fail('Debió rechazar la doble reserva entre clínicas.');
                } catch (HorarioOcupado $e) {
                    $this->assertNotSame('', $e->getMessage());
                }
            }
            $this->assertSame(1, $this->contar('citas'));

            $_SESSION = ['id_usuario' => 2];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
            $this->assertTrue($citas->cancelar($citas->getById($id)));
            $_SESSION = ['id_usuario' => 3];
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 1);
            $deSur = $citas->registrar($enSur);
            $this->assertSame(20, (int) $this->db->query("SELECT duracion_minutos FROM citas WHERE id_cita = $deSur")->fetchColumn());

            $this->db->exec("UPDATE citas SET estado = 'en_curso' WHERE id_cita = $deSur");
            $vigilante = new VigilanteAtenciones($this->db, function (): void {
            });
            $zona = new DateTimeZone(ReglaAtencion::ZONA);
            $this->assertSame(1, $vigilante->revisar(new DateTimeImmutable('2030-01-07 09:31', $zona))['avisadas']);
            $this->assertSame(0, $vigilante->revisar(new DateTimeImmutable('2030-01-07 09:45', $zona))['avisadas']);
            $aviso = $this->db->query("SELECT id_clinica, id_usuario FROM notificaciones_internas WHERE tipo = 'ATENCION_ABIERTA'")->fetchAll(PDO::FETCH_NUM);
            $this->assertSame([['2', '2']], array_map(fn ($f) => array_map('strval', $f), $aviso));
        } finally {
            $_SESSION = [];
        }
    }

    /**
     * C6 en el esquema real: el propietario se vincula a Sur, agenda allí
     * (la mascota se vincula en la misma transacción), edita la ficha con
     * auditoría sin clínica y, al desvincularse, Sur queda en solo lectura.
     */
    public function testC6PortalEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../controllers/CitaController.php';
        require_once __DIR__ . '/../../models/VinculosPropietario.php';
        require_once __DIR__ . '/../../models/MascotaPropietario.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::completarMascota($this->db);
        DosClinicas::poblarAgenda($this->db);
        try {
            $_SESSION = ['id_usuario' => 6];
            Contexto::activar(Contexto::dePropietario(), 1);
            $vinculos = new VinculosPropietario($this->db);
            $vinculos->vincular(2);

            $_POST = ['id_clinica' => '2', 'id_mascota' => '1', 'id_veterinario' => '4', 'id_tipo_cita' => '2', 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'Control'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $reloj = fn (): DateTimeImmutable => new DateTimeImmutable('2030-01-07 07:00', new DateTimeZone(ReglaAtencion::ZONA));
            (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
            $_POST = AgendaC4Prueba::capturar($this->db, $_POST, $reloj);
            ob_start();
            (new CitaController($this->db, $reloj))->agendarDesdePortalAjax();
            $respuesta = json_decode(ob_get_clean(), true);
            $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
            $this->assertSame('activo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());

            $cambios = (new MascotaPropietario($this->db))->actualizar(1, ['sexo' => 'Macho', 'peso' => '8.50']);
            $this->assertSame(['sexo'], $cambios);
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica IS NULL AND tabla_afectada = 'mascotas' AND accion = 'UPDATE'")->fetchColumn());

            try {
                $vinculos->desvincular(2);
                $this->fail('Con una cita sin resolver no se desvincula.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('sin resolver', $e->getMessage());
            }
            $cita = (new Cita($this->db))->paraPropietario((int) $respuesta['id_cita'], 6);
            // C4: el portal cancela con el modelo acotado a la clínica de la cita, como el controlador.
            $this->assertTrue((new Cita($this->db))->enClinicaDelPropietario(2)->cancelar($cita));
            $vinculos->desvincular(2);
            $this->assertSame('inactivo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());

            $_SESSION = ['id_usuario' => 4];
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 1);
            try {
                (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'X', 'fecha_aplicacion' => '2026-10-01']);
                $this->fail('Tras desvincularse, Sur no registra nada nuevo.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
            $this->assertSame([], (new Vacuna($this->db))->findByMascota(1));
        } finally {
            $_SESSION = [];
            $_POST = [];
        }
    }

    /**
     * C7 en el esquema real: el panel de cada clínica cuenta solo lo suyo,
     * la carga sale de usuario_clinica y una mascota desvinculada no es
     * paciente activo. Elena ve su agenda en Norte y la clínica en Sur.
     */
    public function testC7PanelesEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../controllers/PanelController.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, estado)
            VALUES (2, 9, 2, '" . str_repeat('b', 43) . "', 'Toby', 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 2, 'inactivo')");
        $cita = $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado)
            VALUES (?, 1, ?, ?, '2030-01-07', ?, 'Control', ?)");
        $cita->execute([1, 2, 1, '09:00:00', 'completada']);
        $cita->execute([1, 5, 1, '10:00:00', 'confirmada']);
        $cita->execute([2, 4, 2, '09:00:00', 'pendiente']);
        $ahora = new DateTimeImmutable('2030-01-07 08:00', new DateTimeZone(ReglaAtencion::ZONA));
        try {
            $_SESSION = ['id_usuario' => 5];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 2);
            $vet = (new PanelController($this->db))->datosVeterinario(5, $ahora);
            $this->assertSame(['10:00:00'], array_column($vet['agenda'], 'hora'));

            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 2);
            $sur = (new PanelController($this->db))->datosAdministrador($ahora);
            $this->assertSame([4], array_map('intval', array_column($sur['citas'], 'id_veterinario')));
            $this->assertSame(['Diego Sur'], array_column($sur['carga'], 'veterinario'));
            $this->assertSame(1, $sur['pacientes_activos'], 'Toby está desvinculado de Sur.');
            $this->assertSame(2, $sur['propietarios']);

            $_SESSION = ['id_usuario' => 1];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
            $norte = (new PanelController($this->db))->datosAdministrador($ahora);
            $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($norte['carga'], 'veterinario'));
            $this->assertEquals([1, 1], array_column($norte['carga'], 'total'));
            $this->assertSame(1, $norte['pacientes_activos']);
        } finally {
            $_SESSION = [];
        }
    }

    // -----------------------------------------------------------------------

    /**
     * D1: registro con formulario y con Google, prueba del consentimiento
     * (ENUM `medio` real), verificación con vínculo, re-aceptación de la
     * política y el bloqueo de RN-109, sobre el esquema real.
     */
    public function testD1RegistroPoliticaYVinculoEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../../models/RegistroPropietario.php';
        require_once __DIR__ . '/../../models/PropietarioClinica.php';
        require_once __DIR__ . '/../../helpers/InicioSesion.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $registro = new RegistroPropietario($this->db);
        try {
            $r = $registro->conFormulario([
                'id_clinica' => '2', 'tipo_documento' => 'CC', 'documento' => '1000000099', 'nombre_completo' => 'Olga Nueva',
                'telefono' => '+57 300 555 1234', 'email' => 'olga@zooki.test', 'password' => 'Huellita#2026',
                'confirm_password' => 'Huellita#2026', 'acepta_datos' => '1',
            ], '10.0.0.1');
            $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = {$r['id_usuario']}")->fetchColumn());
            $this->assertSame('Clínica Sur', $registro->verificarRegistro($r['enlace']['id'], $r['enlace']['token'])['clinica']);

            $google = $registro->conGoogle(['email' => 'pia@gmail.test', 'nombre_completo' => 'Pía Google', 'google_uid' => 'sub-pia'], ['id_clinica' => '1', 'acepta_datos' => '1'], '10.0.0.2');
            $medios = $this->db->query('SELECT medio FROM consentimientos_datos ORDER BY id_consentimiento')->fetchAll(PDO::FETCH_COLUMN);
            $this->assertSame(['formulario', 'google'], $medios);
            $this->assertSame([PoliticaDatos::VERSION], array_values(array_unique($this->db->query('SELECT version_politica FROM consentimientos_datos')->fetchAll(PDO::FETCH_COLUMN))));

            $usuario = (new Usuario($this->db))->buscarPorId($google);
            $this->assertSame('portal_propietario', (new InicioSesion($this->db))->abrir($usuario, 'google'));
            $this->assertSame(1, $_SESSION['perfil_incompleto']);
            $ana = (new Usuario($this->db))->buscarPorId(1);
            $this->assertSame('aceptar_politica', (new InicioSesion($this->db))->abrir($ana, 'password'));

            $_SESSION = ['id_usuario' => 1];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
            $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 1");
            try {
                (new PropietarioClinica($this->db))->actualizar(6, ['nombre_completo' => 'Fabio Dueño', 'telefono' => '3001234567', 'estado' => '1']);
                $this->fail('RN-109: la clínica reactivó el vínculo sin la confirmación del titular.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('inactivo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 1')->fetchColumn());
            }
        } finally {
            $_SESSION = [];
        }
    }

    private function prepararHistoriaClinica(bool $aplicarMigracionesPosteriores = true): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
        require_once __DIR__ . '/../Support/ConsultaConEsperaCorta.php';
        require_once __DIR__ . '/../../models/Consulta.php';
        require_once __DIR__ . '/../../models/Vacuna.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        if ($aplicarMigracionesPosteriores) {
            $this->cargar('14_aviso_pago_confirmado.sql');
        }
        DosClinicas::poblar($this->db);
        DosClinicas::completarMascota($this->db);
        DosClinicas::vincularLunaASur($this->db);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function metadatosDeAdjunto(): array
    {
        return [
            'nombre_original' => 'rx.jpg',
            'nombre_servidor' => 'CLI_real.jpg',
            'ruta_archivo' => 'uploads/clinicos/CLI_real.jpg',
            'tipo_archivo' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano_bytes' => 2048,
        ];
    }

    private function numeroHistoria(int $mascota, int $clinica): ?string
    {
        $numero = $this->db->query("SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota = $mascota AND id_clinica = $clinica")->fetchColumn();
        return $numero === false ? null : $numero;
    }

    private function assertDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió responder 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    /** Segunda sesión sobre la misma base, para simular otra petición en curso. */
    private function otraConexion(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('ZOOKI_TEST_MYSQL_HOST'),
            (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306),
            $this->base
        );
        $usuario = getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root';
        $clave = getenv('ZOOKI_TEST_MYSQL_PASS') ?: '';
        return new PDO($dsn, $usuario, $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function migrador(): Migrador
    {
        return new Migrador($this->db, self::DATABASE);
    }

    /** Ejecuta un archivo de varias sentencias, como lo hace el migrador. */
    private function cargar(string $archivo): void
    {
        $st = $this->db->query(file_get_contents(self::DATABASE . '/' . $archivo));
        while ($st->nextRowset()) {
        }
        $st->closeCursor();
    }

    /** Copia 02_semilla.sql y una migración de prueba a una carpeta temporal. */
    private function carpetaConMigracion(string $nombre, string $sql): string
    {
        $this->carpetaTemporal = sys_get_temp_dir() . '/zooki_migrador_' . bin2hex(random_bytes(4));
        mkdir($this->carpetaTemporal);
        copy(self::DATABASE . '/02_semilla.sql', $this->carpetaTemporal . '/02_semilla.sql');
        file_put_contents($this->carpetaTemporal . '/' . $nombre, $sql);
        return $this->carpetaTemporal;
    }

    private function contar(string $tabla): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
    }

    /** @return array<string, int> */
    private function conteos(): array
    {
        $r = [];
        foreach (['roles', 'planes', 'especies', 'razas', 'colores_base', 'especialidades'] as $t) {
            $r[$t] = $this->contar($t);
        }
        return $r;
    }

    /** @return int[] [clínica A, clínica B, veterinario, mascota] */
    private function datosDeAgenda(): array
    {
        $this->db->exec("INSERT INTO clinicas (nombre, nit, id_plan, estado) VALUES ('Clínica A', '900000001', 1, 'activa'), ('Clínica B', '900000002', 1, 'activa')");
        $this->db->exec("INSERT INTO usuarios (nombre_completo, email) VALUES ('Vet', 'vet@zooki.test'), ('Dueña', 'duena@zooki.test')");
        $this->db->exec("INSERT INTO mascotas (id_propietario, token_carnet, id_especie, id_raza, nombre) VALUES (2, '" . str_repeat('a', 43) . "', 1, 49, 'Luna')");
        return [1, 2, 1, 1];
    }

    private function reservar(int $clinica, int $mascota, int $vet, int $sobrecupo): int
    {
        $this->db->prepare(
            "INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, es_sobrecupo)
             VALUES (?, ?, ?, '2026-11-02', '09:00:00', 'Control', ?)"
        )->execute([$clinica, $mascota, $vet, $sobrecupo]);
        return (int) $this->db->lastInsertId();
    }
}
