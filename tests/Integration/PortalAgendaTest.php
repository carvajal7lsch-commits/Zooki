<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
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
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
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
        $this->assertStringContainsString('no está disponible', $respuesta['message']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());
    }

    /** RE-5.3.2/3: tipos, veterinarios y horarios son los de la clínica elegida. */
    public function testLosCatalogosSonDeLaClinicaElegida(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'activo')");

        $_GET = ['id_clinica' => (string) DosClinicas::NORTE];
        $this->assertSame(['Consulta Norte'], array_column($this->ejecutar(fn (CitaController $c) => $c->portalTiposCitaAjax())['tipos'], 'nombre_tipo'));
        $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($this->ejecutar(fn (CitaController $c) => $c->portalVeterinariosAjax()), 'nombre_completo'));

        $_GET = ['id_clinica' => (string) DosClinicas::SUR, 'fecha' => self::FECHA, 'intervalo' => '20'];
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

        $otroVeterinario = $this->agendar(DosClinicas::NORTE, DosClinicas::VET_SUR, 1);
        $this->assertStringContainsString('veterinarios de la clínica', $otroVeterinario['message']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    /** Mismas validaciones de la agenda: horario de la clínica y doble reserva. */
    public function testRespetaElHorarioYLaDobleReserva(): void
    {
        $this->assertStringContainsString('fuera del horario', $this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1, 1, '13:00')['message']);

        $this->assertTrue($this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1)['success']);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (3, 6, 1, '" . str_repeat('t', 43) . "', 'Toby', 1, 1)");
        $segunda = $this->agendar(DosClinicas::NORTE, DosClinicas::VET_NORTE, 1, 3);

        $this->assertStringContainsString('no está disponible', $segunda['message']);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

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

    private function ejecutar(callable $accion): array
    {
        $reloj = fn (): DateTimeImmutable => new DateTimeImmutable('2030-01-07 07:00', new DateTimeZone(ReglaAtencion::ZONA));
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
