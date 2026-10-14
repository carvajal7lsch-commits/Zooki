<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/PortalController.php';

/** Cuenta las consultas que pasan por la conexión. */
final class PdoContador extends PDO
{
    public int $consultas = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->consultas++;
        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->consultas++;
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

/**
 * M5 C2 — Portal del propietario: Inicio · Citas · Clínicas · Perfil.
 * Solo lo del propietario (RN-G02), la clínica de cada registro (RN-114) y
 * un número fijo de consultas sin importar cuántas mascotas tenga.
 */
final class M5C2PortalTest extends TestCase
{
    private PdoContador $db;

    protected function setUp(): void
    {
        $this->db = new PdoContador('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        DosClinicas::crearEsquemaSqlite($this->db);
        DosClinicas::poblar($this->db);
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO vacunas (id_clinica, id_mascota, id_veterinario, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis) VALUES (2, 1, 4, 'Rabia', '2026-01-10', '2099-01-10')");
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado) VALUES (1, 1, 2, 1, '2099-03-01', '09:00:00', 'Control', 'confirmada')");
        // Otro propietario con su mascota y su cita: no debe aparecer en el portal de Fabio.
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, estado) VALUES (50, 9, 2, '" . str_repeat('z', 43) . "', 'Milo', 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (50, 2, 'activo')");
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado) VALUES (2, 50, 4, '2099-02-01', '10:00:00', 'Vacuna de Milo', 'pendiente')");
        // Csrf::token abre la sesión de PHP si no lo está y reemplazaría $_SESSION (como en M01AccesoTest).
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO, 'usuario_nombre' => 'Fabio Dueño'];
        Contexto::activar(Contexto::dePropietario(), 1);
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    private function portal(): string
    {
        ob_start();
        try {
            (new PortalController($this->db))->index();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    private function agregarMascotas(int $cuantas): void
    {
        for ($n = 0; $n < $cuantas; $n++) {
            $id = 100 + $n;
            $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, id_raza, estado)
                VALUES ($id, 6, 1, '" . str_pad((string) $id, 43, 'x') . "', 'Mascota $n', 1, 49, 1)");
            $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES ($id, 1, 'activo')");
            $this->db->exec("INSERT INTO vacunas (id_clinica, id_mascota, id_veterinario, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis) VALUES (1, $id, 2, 'Rabia', '2026-01-10', '2099-01-10')");
            $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado) VALUES (1, $id, 2, '2099-04-0" . ($n + 1) . "', '09:00:00', 'Control', 'pendiente')");
        }
    }

    public function testIndexHaceElMismoNumeroDeConsultasConUnaOConSeisMascotas(): void
    {
        $this->db->consultas = 0;
        $this->portal();
        $conUna = $this->db->consultas;

        $this->agregarMascotas(5);
        $this->db->consultas = 0;
        $html = $this->portal();

        $this->assertSame($conUna, $this->db->consultas, 'Las consultas no crecen con las mascotas.');
        $this->assertSame(6, substr_count($html, 'data-ui-accion="detalle-mascota"'));
    }

    public function testElPortalSoloMuestraLoDelPropietario(): void
    {
        $html = $this->portal();

        $this->assertStringContainsString('Luna', $html);
        $this->assertStringNotContainsString('Milo', $html, 'RN-G02: la mascota de otro propietario no aparece.');
        $this->assertStringNotContainsString('Vacuna de Milo', $html);
        $this->assertStringNotContainsString('data-ui-id="50"', $html);
    }

    public function testInicioMuestraLasClinicasDeLaMascotaYSuProximaDosis(): void
    {
        $html = $this->portal();
        // M5 C4a: la tarjeta pequeña del portal anterior, con las clínicas y la próxima dosis.
        $tarjeta = substr($html, strpos($html, 'class="mascota-mini portal-mascota"'));
        $tarjeta = substr($tarjeta, 0, strpos($tarjeta, '</article>'));

        $this->assertStringContainsString('Clínica Norte', $tarjeta);
        $this->assertStringContainsString('Clínica Sur', $tarjeta);
        $this->assertStringContainsString('Próxima dosis: Rabia · 10 ene 2099', $tarjeta);
    }

    public function testLaNavegacionTieneInicioCitasClinicasYPerfilSinServiciosNiAlertas(): void
    {
        $html = $this->portal();

        preg_match_all('/class="portal-rail__item[^"]*" data-nav="([a-z]+)"/', $html, $menu);
        $this->assertSame(['home', 'citas', 'clinicas', 'account'], $menu[1]);
        foreach (['screen-home', 'screen-citas', 'screen-clinicas', 'screen-account', 'screen-pet-detail'] as $pantalla) {
            $this->assertStringContainsString('id="' . $pantalla . '"', $html);
        }
        $this->assertStringNotContainsString('screen-explore', $html);
        $this->assertStringNotContainsString('screen-notifications', $html);
        $this->assertStringNotContainsString('font-awesome', $html, 'Una sola biblioteca de iconos.');
        $this->assertDoesNotMatchRegularExpression('/\?v=[^"]*-(c91|d2)"/', $html, 'Sin sufijos de versión a mano.');
    }

    public function testCitasSeparaProximasDePasadasYCancelaSoloEnClinicaVinculada(): void
    {
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado) VALUES (1, 1, 2, '2020-05-01', '09:00:00', 'Vieja', 'completada')");

        $html = $this->portal();
        $proximas = substr($html, strpos($html, 'id="citas-proximas"'), strpos($html, 'id="citas-pasadas"') - strpos($html, 'id="citas-proximas"'));
        $pasadas = substr($html, strpos($html, 'id="citas-pasadas"'), strpos($html, 'id="agenda-salud"') - strpos($html, 'id="citas-pasadas"'));

        $this->assertStringContainsString('data-ui-accion="cancelar-cita-portal"', $proximas);
        $this->assertStringContainsString('Clínica Norte', $proximas);
        $this->assertStringContainsString('Completada', $pasadas);
        $this->assertStringNotContainsString('cancelar-cita-portal', $pasadas);
    }

    /** M5 C4a (punto 4): «Próximo en salud» trae citas y dosis de sus mascotas, por fecha, nunca las de otro propietario. */
    public function testProximoEnSaludSoloTraeLoDelPropietarioYPorFecha(): void
    {
        $html = $this->portal();
        $salud = substr($html, strpos($html, 'data-proximo-salud'));
        $salud = substr($salud, 0, strpos($salud, '</section>'));

        $this->assertStringContainsString('Rabia · Luna', $salud, 'La dosis de su mascota en otra clínica (RN-114).');
        $this->assertStringContainsString('Consulta Norte · Luna', $salud, 'Su cita próxima.');
        $this->assertLessThan(strpos($salud, 'Consulta Norte · Luna'), strpos($salud, 'Rabia · Luna'), 'Por fecha: la dosis de enero va antes que la cita de marzo.');
        $this->assertStringNotContainsString('Milo', $salud, 'RN-G02: lo de otro propietario no se cuela.');
        $this->assertStringNotContainsString('data-ui-id="50"', $salud);
    }

    /** M5 C4a (punto 6): el horario de la semana en una tabla corta, con los días iguales juntos. */
    public function testElHorarioDeLaSemanaAgrupaLosDiasIguales(): void
    {
        $html = $this->portal();
        $semana = substr($html, strpos($html, 'class="horario-semana"'));
        $semana = substr($semana, 0, strpos($semana, '</table>'));

        $this->assertSame(1, substr_count($semana, '<tr'), 'Los siete días tienen el mismo horario: una sola fila.');
        $this->assertStringContainsString('<th scope="row">Lun–Dom</th>', $semana);
        $this->assertStringContainsString('<td>8:00–12:00 · 14:00–18:00</td>', $semana);
        $this->assertStringContainsString('is-hoy', $semana);
    }

    public function testLaFichaTraeLasClinicasDeLaMascotaYLaDeCadaRegistro(): void
    {
        $_GET = ['id_mascota' => '1'];
        ob_start();
        (new PortalController($this->db))->verDetalleMascotaAjax();
        $respuesta = json_decode((string) ob_get_clean(), true);

        $this->assertTrue($respuesta['success']);
        $this->assertSame(['Clínica Norte', 'Clínica Sur'], array_column($respuesta['clinicas'], 'nombre'));
        $this->assertSame('Clínica Sur', $respuesta['vacunas'][0]['clinica_nombre']);
        $this->assertSame('Clínica Norte', $respuesta['citas'][0]['clinica_nombre']);
    }
}
