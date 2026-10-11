<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/MascotaPropietario.php';
require_once __DIR__ . '/../../models/HistoriaPropietario.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../controllers/PortalController.php';

/**
 * Módulo 5 — Portal del propietario (C6), con el fixture de dos clínicas.
 *
 * Fabio (6) es dueño de Luna (1), vinculada a Norte y a Sur. Iris (9) es
 * dueña de Milo (2), solo de Sur. El propietario ve todas sus mascotas y su
 * historia en todas las clínicas (RN-110, RN-114); nada de otro
 * propietario (RN-G02, RE-5.1.4: 403 auditado). Edita también especie,
 * raza, sexo y nacimiento (RN-110), con auditoría.
 */
final class PortalPropietarioTest extends TestCase
{
    private const LUNA = 1;
    private const MILO = 2;

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (2, 9, 2, '" . str_repeat('m', 43) . "', 'Milo', 2, 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 2, 'activo')");
        $this->registrarHistoria();
        $this->comoPropietario(DosClinicas::PROPIETARIO);
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    // ── RE-5.1.2, RE-5.1.4, RN-G02 ──────────────────────────────────────

    public function testElPropietarioNoVeNiTocaNadaDeOtroPropietario(): void
    {
        $mascotas = new MascotaPropietario($this->db);
        $historia = new HistoriaPropietario($this->db);
        $citaDeMilo = $this->citaDeMilo();

        $this->assertSame(['Luna'], array_column($mascotas->listar(), 'nombre'));
        $this->assertAccesoDenegado(fn () => $mascotas->ficha(self::MILO));
        $this->assertAccesoDenegado(fn () => $mascotas->actualizar(self::MILO, ['nombre' => 'Robado']));
        $this->assertAccesoDenegado(fn () => $historia->consultas(self::MILO));
        $this->assertAccesoDenegado(fn () => $historia->vacunas(self::MILO));
        $this->assertAccesoDenegado(fn () => $historia->citas(self::MILO));
        $this->assertAccesoDenegado(fn () => $historia->detalleCita($citaDeMilo));
        $this->assertAccesoDenegado(fn () => (new Cita($this->db))->paraPropietario($citaDeMilo, DosClinicas::PROPIETARIO));

        $this->assertSame('Milo', $this->db->query('SELECT nombre FROM mascotas WHERE id_mascota = 2')->fetchColumn());
        $this->assertSame(7, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_usuario = 6 AND id_clinica IS NULL AND accion = 'OTHER'")->fetchColumn());
    }

    /** Por petición directa: la ficha de otra mascota da 403, no un 200 con error. */
    public function testLaFichaDeOtraMascotaPorPeticionDirectaDa403(): void
    {
        $_GET = ['id_mascota' => (string) self::MILO];

        $this->assertAccesoDenegado(fn () => $this->ejecutar(fn (PortalController $c) => $c->verDetalleMascotaAjax()));
    }

    /** El personal no usa los modelos del portal: son del contexto propietario. */
    public function testLosModelosDelPortalSonSoloDelContextoPropietario(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Norte', Roles::VETERINARIO), 1);

        $this->assertAccesoDenegado(fn () => (new MascotaPropietario($this->db))->listar());
        $this->assertAccesoDenegado(fn () => (new HistoriaPropietario($this->db))->consultas(self::LUNA));
    }

    // ── RN-114, RE-5.9.2, RE-5.1.3 ──────────────────────────────────────

    public function testVeLaHistoriaDeSuMascotaEnTodasLasClinicasConSuClinica(): void
    {
        $_GET = ['id_mascota' => (string) self::LUNA];

        $detalle = $this->ejecutar(fn (PortalController $c) => $c->verDetalleMascotaAjax());

        $this->assertTrue($detalle['success']);
        $this->assertSame(['Clínica Sur', 'Clínica Norte'], array_column($detalle['historial'], 'clinica_nombre'));
        $this->assertCount(1, $detalle['historial'][1]['archivos']);
        $this->assertCount(1, $detalle['historial'][1]['tratamientos']);
        $this->assertSame(['Clínica Sur', 'Clínica Norte'], array_column($detalle['vacunas'], 'clinica_nombre'));
        $this->assertArrayNotHasKey('token_carnet', $detalle['mascota'], 'El token del carnet no va a la pantalla');
    }

    /** RN-114: aunque se desvincule de una clínica, sigue viendo lo que esa clínica registró. */
    public function testDesvinculadoDeUnaClinicaSigueViendoSuHistoria(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 1");
        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 1 AND id_clinica = 1");

        $consultas = (new HistoriaPropietario($this->db))->consultas(self::LUNA);

        $this->assertContains('Clínica Norte', array_column($consultas, 'clinica_nombre'));
    }

    // ── RE-5.4.1/2, RN-110: edición ─────────────────────────────────────

    public function testEditaEspecieRazaSexoYNacimientoConAuditoria(): void
    {
        $cambios = (new MascotaPropietario($this->db))->actualizar(self::LUNA, [
            'nombre' => 'Luna',
            'id_especie' => 2,
            'id_raza' => 50,
            'sexo' => 'Macho',
            'fecha_nacimiento' => '2021-05-01',
            'peso' => '8.50',
        ]);

        sort($cambios);
        $this->assertSame(['fecha_nacimiento', 'id_especie', 'id_raza', 'sexo'], $cambios, 'El mismo peso con otro formato no es un cambio');
        $fila = $this->db->query('SELECT id_especie, id_raza, sexo, fecha_nacimiento FROM mascotas WHERE id_mascota = 1')->fetch(PDO::FETCH_NUM);
        $this->assertSame(['2', '50', 'Macho', '2021-05-01'], array_map('strval', $fila));

        $auditados = $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE tabla_afectada = 'mascotas' AND accion = 'UPDATE' AND id_usuario = 6 AND id_clinica IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(4, $auditados);
        $this->assertStringContainsString('id_especie', implode(' ', $auditados));
    }

    /** RE-5.1.9 por el controlador: la raza tiene que ser de la especie. */
    public function testElControladorValidaLaRazaDeLaEspecie(): void
    {
        $_POST = ['id_mascota' => '1', 'nombre' => 'Luna', 'especie' => '2', 'raza' => '49', 'sexo' => 'Hembra', 'peso' => '8'];

        $respuesta = $this->ejecutar(fn (PortalController $c) => $c->actualizarMascotaAjax());

        $this->assertFalse($respuesta['success']);
        $this->assertSame(422, http_response_code());
        $this->assertSame('1', (string) $this->db->query('SELECT id_especie FROM mascotas WHERE id_mascota = 1')->fetchColumn());
    }

    /** Alta desde el portal: ficha global, sin clínica, hasta agendar o ser atendida (RE-5.13.2). */
    public function testRegistrarMascotaLaDejaSinClinica(): void
    {
        $id = (new MascotaPropietario($this->db))->registrar([
            'nombre' => 'Toby', 'id_especie' => 1, 'id_raza' => 49, 'raza_indicada' => null,
            'sexo' => 'Macho', 'fecha_nacimiento' => '2024-01-01', 'peso' => 4.2,
        ]);

        $fila = $this->db->query("SELECT id_propietario, id_clinica_registro, token_carnet FROM mascotas WHERE id_mascota = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(6, (int) $fila['id_propietario']);
        $this->assertNull($fila['id_clinica_registro']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $fila['token_carnet']);
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM mascota_clinica WHERE id_mascota = $id")->fetchColumn());
    }

    // ── Decisión C6: contacto ───────────────────────────────────────────

    public function testElPropietarioCambiaSuTelefonoPeroNoSuCorreo(): void
    {
        $_POST = ['telefono' => '300 123 4567', 'email' => 'otro@zooki.test'];

        $respuesta = $this->ejecutar(fn (PortalController $c) => $c->actualizarDatosContactoAjax());

        $this->assertTrue($respuesta['success']);
        $fila = $this->db->query('SELECT email, telefono FROM usuarios WHERE id_usuario = 6')->fetch(PDO::FETCH_NUM);
        $this->assertSame(['fabio@zooki.test', '300 123 4567'], $fila);

        $_POST = ['telefono' => 'abc'];
        $this->assertSame(422, $this->codigoDe(fn (PortalController $c) => $c->actualizarDatosContactoAjax()));
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function registrarHistoria(): void
    {
        $sinAdjuntos = fn (): array => [];
        $conAdjunto = fn (int $idConsulta): array => [
            'nombre_original' => 'rx.jpg', 'nombre_servidor' => 'CLI_N.jpg', 'ruta_archivo' => 'uploads/clinicos/CLI_N.jpg',
            'tipo_archivo' => 'image/jpeg', 'extension' => 'jpg', 'tamano_bytes' => 1024,
        ];
        $tratamiento = ['medicamento' => 'Amoxicilina', 'dosis' => '1 ml', 'via_administracion' => 'Oral', 'duracion' => '5 días', 'fecha_inicio' => '2026-10-01'];

        $this->comoPersonal(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $norte = (new Consulta($this->db))->registrar(['id_mascota' => 1, 'motivo' => 'Oído', 'diagnostico' => 'Otitis'], [$tratamiento], [['nombre' => 'rx']], $conAdjunto);
        $this->db->exec("UPDATE consultas SET fecha_hora = '2026-09-01 10:00:00' WHERE id_consulta = $norte");
        (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-09-01']);

        $this->comoPersonal(DosClinicas::SUR, DosClinicas::VET_SUR);
        (new Consulta($this->db))->registrar(['id_mascota' => 1, 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], $sinAdjuntos);
        (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Moquillo', 'fecha_aplicacion' => '2026-10-01']);
    }

    private function citaDeMilo(): int
    {
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado)
            VALUES (2, 2, 4, '2030-01-07', '09:00:00', 'Control', 'confirmada')");
        return (int) $this->db->lastInsertId();
    }

    private function comoPropietario(int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    private function comoPersonal(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function ejecutar(callable $accion): array
    {
        http_response_code(200);
        ob_start();
        try {
            $accion(new PortalController($this->db));
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true) ?? [];
    }

    private function codigoDe(callable $accion): int
    {
        $this->ejecutar($accion);
        return http_response_code();
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
