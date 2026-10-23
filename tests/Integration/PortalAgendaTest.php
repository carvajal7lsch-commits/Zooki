<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/AgendaC4Prueba.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/**
 * HU-5.3 / RE-5.3.5 / RE-5.9.3 — Agendar desde el portal (C6).
 *
 * El propietario elige una de SUS clínicas con vínculo activo; el modelo lo
 * valida aunque la petición mande otro id (403 auditado). Si la mascota aún
 * no está vinculada a esa clínica, se vincula al agendar, en la misma
 * transacción (RN-110). Mismas validaciones de la agenda (C5); prioridad
 * verde y sin sobrecupo.
 */
final class PortalAgendaTest extends TestCase
{
    private const FECHA = '2030-01-07';

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        ConocimientoRealPrueba::taxonomia($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        Contexto::activar(Contexto::dePropietario(), 1);
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    public function testAgendarEnUnaClinicaNoVinculadaSeRechazaAunqueMandeElId(): void
    {
        $this->assertAccesoDenegado(fn () => $this->agendar(DosClinicas::SUR, DosClinicas::VET_SUR, 2));
        $_GET = ['id_clinica' => (string) DosClinicas::SUR];
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->portalTiposCitaAjax()));
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->portalVeterinariosAjax()));

        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_usuario = 6 AND tabla_afectada = 'clinicas'")->fetchColumn());
    }

    /** RN-110 / RE-5.3.5: Fabio se vinculó a Sur; Luna todavía no. Al agendar allí, Luna queda vinculada. */
    public function testAgendarConLaMascotaSinVincularLaVincula(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'activo')");

        $respuesta = $this->agendar(DosClinicas::SUR, DosClinicas::VET_SUR, 2);

        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $this->assertSame('activo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());
        $cita = $this->db->query('SELECT id_clinica, id_veterinario, duracion_minutos, prioridad, es_sobrecupo FROM citas')->fetch(PDO::FETCH_NUM);
        $this->assertSame(['2', '4', '20', 'verde', '0'], array_map('strval', $cita));
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_mascotas WHERE id_clinica = 2 AND campo_modificado = 'vinculo_clinica' AND id_usuario = 6")->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND tabla_afectada = 'citas' AND accion = 'INSERT'")->fetchColumn());
    }

    /** Si la reserva falla, la mascota no queda vinculada a medias. */
    public function testSiLaReservaFallaLaMascotaNoQuedaVinculada(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'activo')");
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, hora_fin, motivo, estado)
            VALUES (1, 1, 4, '" . self::FECHA . "', '09:00:00', '09:30:00', 'Ocupa', 'confirmada')");

        $respuesta = $this->agendar(DosClinicas::SUR, DosClinicas::VET_SUR, 2);

        $this->assertFalse($respuesta['success']);
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('No hay disponibilidad', $respuesta['message']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());
    }

    /** RE-5.3.2/3: tipos, veterinarios y horarios son los de la clínica elegida. */
    public function testLosCatalogosSonDeLaClinicaElegida(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'activo')");

        $_GET = ['id_clinica' => (string) DosClinicas::NORTE];
        $this->assertSame(['Consulta Norte'], array_column($this->ejecutar(fn (CitaController $c) => $c->portalTiposCitaAjax())['tipos'], 'nombre_tipo'));
        $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($this->ejecutar(fn (CitaController $c) => $c->portalVeterinariosAjax()), 'nombre_completo'));

        $_GET = ['id_clinica' => (string) DosClinicas::SUR, 'fecha' => self::FECHA, 'id_veterinario' => '4', 'id_mascota' => '1', 'id_tipo_cita' => '2'];
        $_GET = AgendaC4Prueba::capturar($this->db, $_GET, static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00'));
        $horas = $this->ejecutar(fn (CitaController $c) => $c->portalHorasAjax())['horas'];
        $this->assertSame('08:00', $horas[0]);

        $_GET = [];
        $todos = $this->ejecutar(fn (CitaController $c) => $c->portalVeterinariosAjax());
        $this->assertSame(['Clínica Norte', 'Clínica Norte', 'Clínica Sur'], array_column($todos, 'clinica_nombre'));
    }

    public function testNoAgendaParaUnaMascotaAjenaNiConUnVeterinarioDeOtraClinica(): void
    {
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (2, 9, 1, '" . str_repeat('m', 43) . "', 'Milo', 2, 1)");

        $this->assertAccesoDenegado(fn () => $this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1, 2));

        $this->assertAccesoDenegado(fn () => $this->agendar(DosClinicas::NORTE, DosClinicas::VET_SUR, 1));
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    /** Mismas validaciones de la agenda: horario de la clínica y doble reserva. */
    public function testRespetaElHorarioYLaDobleReserva(): void
    {
        $this->assertStringContainsString('No hay disponibilidad', $this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1, 1, '13:00')['message']);

        $this->assertTrue($this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1)['success']);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (3, 6, 1, '" . str_repeat('t', 43) . "', 'Toby', 1, 1)");
        $segunda = $this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1, 3);

        $this->assertStringContainsString('No hay disponibilidad', $segunda['message']);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    public function testE2PortalBloqueadoDaMensajeNeutralAuditaYAvisaSinVincular(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica) VALUES (6, 2)");
        $this->db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 2');
        $this->db->exec('UPDATE planes SET limite_citas_mes = 0 WHERE id_plan = 1');
        $respuesta = $this->agendar(2, 4, 2);
        $this->assertFalse($respuesta['success']);
        $this->assertSame('No es posible completar la acción ahora', $respuesta['message']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica WHERE id_clinica = 2')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND descripcion LIKE 'Acción bloqueada%'")->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones_internas WHERE id_clinica = 2 AND tipo = 'LIMITE_PLAN'")->fetchColumn());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones_internas WHERE id_clinica = 1 AND tipo = 'LIMITE_PLAN'")->fetchColumn());
    }

    public function testE2PortalCuentaVincularMascotaExistenteComoCupo(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica) VALUES (6, 2)");
        $this->db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 2');
        $this->db->exec('UPDATE planes SET limite_mascotas = 0 WHERE id_plan = 1');
        $respuesta = $this->agendar(2, 4, 2);
        $this->assertSame('No es posible completar la acción ahora', $respuesta['message']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica WHERE id_clinica = 2')->fetchColumn());
    }

    private function agendar(int $clinica, int $veterinario, int $tipo, int $mascota = 1, string $hora = '09:00'): array
    {
        $_POST = [
            'id_clinica' => (string) $clinica,
            'id_mascota' => (string) $mascota,
            'id_veterinario' => (string) $veterinario,
            'id_tipo_cita' => (string) $tipo,
            'fecha' => self::FECHA,
            'hora' => $hora,
            'motivo' => 'Control',
        ];
        return $this->ejecutar(fn (CitaController $c) => $c->agendarDesdePortalAjax());
    }

    public function testC3ReplayHttpConservaResultadoSinAdvertenciaNiDuplicarAuditoriaOVinculo(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica) VALUES (6,2)");
        $_POST = ['id_clinica'=>'2','id_mascota'=>'1','id_veterinario'=>'4','id_tipo_cita'=>'2','fecha'=>self::FECHA,
            'hora'=>'09:00','motivo'=>'Control','clave_operacion'=>str_repeat('c',64)];
        $primera = $this->ejecutar(fn ($c) => $c->agendarDesdePortalAjax());
        $this->assertTrue($primera['success']);
        $auditorias = $this->db->query('SELECT * FROM auditoria_sistema')->fetchAll(PDO::FETCH_ASSOC);
        $avisos = $this->db->query('SELECT * FROM notificaciones_internas')->fetchAll(PDO::FETCH_ASSOC);
        $segunda = $this->ejecutar(fn ($c) => $c->agendarDesdePortalAjax());
        $this->assertSame($primera, $segunda);
        $this->assertSame($auditorias, $this->db->query('SELECT * FROM auditoria_sistema')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame($avisos, $this->db->query('SELECT * FROM notificaciones_internas')->fetchAll(PDO::FETCH_ASSOC));
        $tardia = $this->ejecutar(fn ($c) => $c->agendarDesdePortalAjax(), new DateTimeImmutable('2030-01-08 08:00', new DateTimeZone(ReglaAtencion::ZONA)));
        $this->assertSame($primera, $tardia);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(3, (int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        $_POST['hora'] = '10:00';
        $this->assertFalse($this->ejecutar(fn ($c) => $c->agendarDesdePortalAjax())['success']);
    }

    public function testC3FalloAuditoriaRevierteTambienVinculoOperacionYCuota(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica) VALUES (6,2)");
        $this->db->exec("CREATE TRIGGER falla_cita_c3 BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='citas' BEGIN SELECT RAISE(ABORT,'Fallo C3'); END");
        $respuesta = $this->agendar(2, 4, 2);
        $this->assertFalse($respuesta['success']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn(), 'Solo queda la evaluación anterior; la confirmación fallida revierte.');
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica WHERE id_clinica=2')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_mascotas WHERE id_clinica=2')->fetchColumn());
    }

    private function ejecutar(callable $accion, ?DateTimeImmutable $corte = null): array
    {
        $reloj = fn (): DateTimeImmutable => $corte ?? new DateTimeImmutable('2030-01-07 07:00', new DateTimeZone(ReglaAtencion::ZONA));
        if (isset($_POST['id_mascota']) && !isset($_POST['id_solicitud'])) {
            $_POST = AgendaC4Prueba::capturar($this->db, $_POST, $reloj);
        }
        http_response_code(200);
        ob_start();
        try {
            $accion(new CitaController($this->db, $reloj));
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true) ?? [];
    }

    private function assertAccesoDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió responder 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }
}
