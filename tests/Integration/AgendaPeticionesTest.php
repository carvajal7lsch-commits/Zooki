<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/**
 * Módulo 4 — Peticiones de la agenda (C5), por el controlador.
 *
 * Roles y contexto: el veterinario agenda y atiende lo suyo; el
 * administrador gestiona y reasigna en su clínica; B no ve ni modifica
 * citas de A (403 auditado); el propietario solo cancela citas de sus
 * mascotas en clínicas con vínculo activo. La doble reserva responde 422
 * con mensaje, no 500.
 */
final class AgendaPeticionesTest extends TestCase
{
    private const FECHA = '2030-01-07';
    private const AHORA = '2030-01-07 08:00:00';

    private PDO $db;
    private string $ahora = self::AHORA;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE, Roles::VETERINARIO);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    // ── HU-4.1 ──────────────────────────────────────────────────────────

    public function testElVeterinarioAgendaParaSiMismoYNoParaOtro(): void
    {
        $otro = $this->agendar(['id_veterinario' => (string) DosClinicas::DOBLE]);
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('otros veterinarios', $otro['message']);

        $propia = $this->agendar();
        $this->assertTrue($propia['success'], $propia['message'] ?? '');
        $this->assertSame(DosClinicas::VET_NORTE, (int) $this->db->query('SELECT id_veterinario FROM citas')->fetchColumn());
    }

    /** RN-401: la segunda reserva del mismo horario responde 422 con mensaje, no un 500. */
    public function testLaSegundaReservaDelMismoHorarioRespondeConMensaje(): void
    {
        $this->agendar();
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (2, 6, 1, '" . str_repeat('k', 43) . "', 'Kira', 1, 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 1, 'activo')");

        $respuesta = $this->agendar(['id_mascota' => '2']);

        $this->assertFalse($respuesta['success']);
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('no está disponible', $respuesta['message']);
    }

    /** RN-402 / RE-4.1.3 y fechas pasadas: se validan en el servidor. */
    public function testFueraDelHorarioOEnElPasadoSeRechaza(): void
    {
        $this->assertStringContainsString('fuera del horario', $this->agendar(['hora' => '12:30'])['message']);
        $this->assertStringContainsString('ya pasaron', $this->agendar(['fecha' => '2030-01-06'])['message']);
        $this->assertStringContainsString('ya pasaron', $this->agendar(['hora' => '07:30'])['message']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    public function testOtraCitaDeLaMascotaEseDiaPideConfirmar(): void
    {
        $this->agendar();

        $aviso = $this->agendar(['hora' => '10:00']);
        $this->assertFalse($aviso['success']);
        $this->assertTrue($aviso['has_warning']);

        $confirmada = $this->agendar(['hora' => '10:00', 'ignore_warning' => '1']);
        $this->assertTrue($confirmada['success']);
    }

    // ── RN-G13: aislamiento por petición directa ────────────────────────

    public function testDesdeBNingunaPeticionVeNiModificaUnaCitaDeA(): void
    {
        $this->agendar();
        $id = (int) $this->db->query('SELECT id_cita FROM citas')->fetchColumn();

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $_GET = ['id' => (string) $id];
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->getCitaAjax()));
        foreach (['reprogramarCitaAjax', 'cancelarAjax', 'confirmarAjax'] as $accion) {
            $_POST = ['id_cita' => (string) $id, 'fecha' => self::FECHA, 'hora' => '11:00'];
            $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->$accion()));
        }
        $this->como(DosClinicas::SUR, DosClinicas::VET_SUR, Roles::VETERINARIO);
        foreach (['iniciarAtencionAjax', 'marcarNoAsistioAjax', 'completarAtencionAjax'] as $accion) {
            $_POST = ['id_cita' => (string) $id];
            $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->$accion()));
        }

        $this->assertSame(['confirmada', '09:00:00'], $this->db->query("SELECT estado, hora FROM citas WHERE id_cita = $id")->fetch(PDO::FETCH_NUM));
        $this->assertSame(7, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND tabla_afectada = 'citas'")->fetchColumn());
    }

    public function testLosVeterinariosYElCalendarioSonDeLaClinicaActiva(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->agendar();
        $this->db->exec("INSERT INTO vacunas (id_clinica, id_mascota, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis) VALUES
            (1, 1, 'Rabia Norte', '2029-01-07', '2030-01-08'), (2, 1, 'Moquillo Sur', '2029-01-07', '2030-01-08')");

        $_GET = ['inicio' => self::FECHA, 'fin' => '2030-01-10'];
        $eventos = $this->ejecutar(fn (CitaController $c) => $c->listarSemanaAjax());
        $this->assertSame(['cita', 'vacunacion'], array_column($eventos, 'tipo'));
        $this->assertSame('Rabia Norte', $eventos[1]['motivo']);
        $this->assertSame(DosClinicas::VET_NORTE, $eventos[0]['id_veterinario']);
        $this->assertArrayNotHasKey('email', $eventos[0], 'El calendario no recibe correos');

        $veterinarios = $this->ejecutar(fn (CitaController $c) => $c->listarVeterinariosAjax());
        $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($veterinarios, 'nombre_completo'));

        $this->como(DosClinicas::SUR, DosClinicas::VET_SUR, Roles::VETERINARIO);
        $eventosSur = $this->ejecutar(fn (CitaController $c) => $c->listarSemanaAjax());
        $this->assertSame(['Moquillo Sur'], array_column($eventosSur, 'motivo'));
        $this->assertSame(['Diego Sur'], array_column($this->ejecutar(fn (CitaController $c) => $c->listarVeterinariosAjax()), 'nombre_completo'));
    }

    // ── HU-4.2: roles dentro de la clínica ──────────────────────────────

    public function testElAdministradorReprogramaYReasignaElVeterinario(): void
    {
        $this->agendar();
        $id = (int) $this->db->query('SELECT id_cita FROM citas')->fetchColumn();

        $this->como(DosClinicas::NORTE, DosClinicas::DOBLE, Roles::VETERINARIO);
        $_POST = ['id_cita' => (string) $id, 'fecha' => self::FECHA, 'hora' => '10:00'];
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->reprogramarCitaAjax()));

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $_POST = ['id_cita' => (string) $id, 'fecha' => self::FECHA, 'hora' => '10:00', 'id_veterinario' => (string) DosClinicas::DOBLE];
        $respuesta = $this->ejecutar(fn (CitaController $c) => $c->reprogramarCitaAjax());

        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $this->assertSame([DosClinicas::DOBLE, '10:00:00'], array_map(
            fn ($valor) => is_numeric($valor) && !str_contains((string) $valor, ':') ? (int) $valor : $valor,
            $this->db->query("SELECT id_veterinario, hora FROM citas WHERE id_cita = $id")->fetch(PDO::FETCH_NUM)
        ));
        $avisoAlVeterinario = "SELECT COUNT(*) FROM notificaciones_internas WHERE id_clinica = 1 AND id_usuario = 5 AND tipo = 'CITA_REPROGRAMADA'";
        $this->assertSame(1, (int) $this->db->query($avisoAlVeterinario)->fetchColumn());
    }

    /** RN-407 / RE-4.3.4: la inicia el veterinario asignado, el día de la cita, desde 15 minutos antes. */
    public function testIniciarAtencionRespetaVeterinarioDiaYHora(): void
    {
        $this->agendar();
        $id = (int) $this->db->query('SELECT id_cita FROM citas')->fetchColumn();
        $_POST = ['id_cita' => (string) $id];

        $temprano = $this->ejecutar(fn (CitaController $c) => $c->iniciarAtencionAjax());
        $this->assertStringContainsString('desde las 8:45', $temprano['message']);

        $this->como(DosClinicas::NORTE, DosClinicas::DOBLE, Roles::VETERINARIO);
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->iniciarAtencionAjax()));

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE, Roles::VETERINARIO);
        $this->ahora = self::FECHA . ' 08:46:00';
        $iniciada = $this->ejecutar(fn (CitaController $c) => $c->iniciarAtencionAjax());
        $this->assertTrue($iniciada['success']);
        $this->assertStringContainsString('vet_atencion', $iniciada['redirect_url']);
        $this->assertSame('en_curso', $this->db->query("SELECT estado FROM citas WHERE id_cita = $id")->fetchColumn());
    }

    // ── RN-G02: el propietario ──────────────────────────────────────────

    public function testElPropietarioSoloCancelaCitasDeSusMascotasConVinculoActivo(): void
    {
        $this->agendar();
        $id = (int) $this->db->query('SELECT id_cita FROM citas')->fetchColumn();
        $_POST = ['id_cita' => (string) $id];

        $this->comoPropietario(DosClinicas::GOOGLE);
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->cancelarAjax()));

        $this->comoPropietario(DosClinicas::PROPIETARIO);
        $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 1");
        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (CitaController $c) => $c->cancelarAjax()));
        $this->assertSame('confirmada', $this->db->query("SELECT estado FROM citas WHERE id_cita = $id")->fetchColumn());

        $this->db->exec("UPDATE propietario_clinica SET estado = 'activo' WHERE id_propietario = 6 AND id_clinica = 1");
        $cancelada = $this->ejecutar(fn (CitaController $c) => $c->cancelarAjax());

        $this->assertTrue($cancelada['success']);
        $this->assertSame('cancelada', $this->db->query("SELECT estado FROM citas WHERE id_cita = $id")->fetchColumn());
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica IS NULL AND tabla_afectada = 'citas'")->fetchColumn());
        $avisos = $this->db->query("SELECT id_clinica, id_usuario, id_rol_destino FROM notificaciones_internas WHERE tipo = 'CITA_CANCELADA' ORDER BY id")->fetchAll(PDO::FETCH_NUM);
        $this->assertSame([[1, 2, null], [1, null, 1]], array_map(fn ($fila) => array_map(fn ($v) => $v === null ? null : (int) $v, $fila), $avisos));
    }

    /** Agendar una cita nueva desde el portal es de C6: hoy falla cerrado. */
    public function testElPortalTodaviaNoAgenda(): void
    {
        $this->comoPropietario(DosClinicas::PROPIETARIO);

        $this->assertAccesoDenegado(fn () => $this->agendar());
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function agendar(array $cambios = []): array
    {
        $_POST = array_replace([
            'id_mascota' => '1',
            'fecha' => self::FECHA,
            'hora' => '09:00',
            'motivo' => 'Control',
            'id_tipo_cita' => '1',
        ], $cambios);
        return $this->ejecutar(fn (CitaController $c) => $c->registrarAjax());
    }

    private function ejecutar(callable $accion): array
    {
        $reloj = fn (): DateTimeImmutable => new DateTimeImmutable($this->ahora, new DateTimeZone(ReglaAtencion::ZONA));
        $controlador = new CitaController($this->db, $reloj);
        http_response_code(200);
        ob_start();
        try {
            $accion($controlador);
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true) ?? [];
    }

    private function como(int $clinica, int $idUsuario, int $rol): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', $rol), 1);
    }

    private function comoPropietario(int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::dePropietario(), 1);
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
