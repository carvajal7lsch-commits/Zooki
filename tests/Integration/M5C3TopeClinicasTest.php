<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/VinculosPropietario.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../controllers/PortalController.php';

/**
 * M5 C3 — RN-116 y RE-5.13.5 (decisión del usuario, 2026-10-12): desde el
 * portal, un propietario llega como máximo a 3 clínicas con vínculo activo.
 * El tope no aplica cuando una clínica lo registra o confirma su vínculo (una
 * atención nunca se bloquea); desvincularse libera el cupo.
 */
final class M5C3TopeClinicasTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->db->exec("INSERT INTO clinicas (id_clinica, nombre, nit, estado, id_plan) VALUES
            (4, 'Clínica Este', '900000004-1', 'activa', 2),
            (5, 'Clínica Oeste', '900000005-1', 'activa', 2)");
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->comoFabio();
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    private function comoFabio(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO, 'usuario_nombre' => 'Fabio Dueño'];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    private function activos(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = 6 AND estado = 'activo'")->fetchColumn();
    }

    /** Fabio ya es cliente de Norte; desde el portal suma Sur y Este (3). */
    private function llegarAlTope(VinculosPropietario $vinculos): void
    {
        $vinculos->vincular(DosClinicas::SUR);
        $vinculos->vincular(4);
        $this->assertSame(3, $this->activos());
    }

    public function testLaCuartaVinculacionDesdeElPortalSeRechazaEnElServidor(): void
    {
        $vinculos = new VinculosPropietario($this->db);
        $this->llegarAlTope($vinculos);

        try {
            $vinculos->vincular(5);
            $this->fail('Debió rechazar la cuarta clínica.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(VinculosPropietario::MENSAJE_TOPE, $e->getMessage());
        }
        $this->assertSame(3, $this->activos());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 5')->fetchColumn());

        // Por el controlador, igual: 422 con el mensaje que sale en la tarjeta.
        $_POST = ['id_clinica' => '5'];
        ob_start();
        (new PortalController($this->db))->vincularClinicaAjax();
        $respuesta = json_decode((string) ob_get_clean(), true);
        $this->assertSame(422, http_response_code());
        $this->assertSame(VinculosPropietario::MENSAJE_TOPE, $respuesta['message']);
    }

    public function testLaClinicaSiLigaAUnPropietarioQueYaTieneTres(): void
    {
        $this->llegarAlTope(new VinculosPropietario($this->db));

        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(5, 'Clínica Oeste', Roles::ADMIN), 1);
        $oeste = new PropietarioClinica($this->db);
        $solicitud = $oeste->solicitarVinculo('fabio@zooki.test');

        $this->assertTrue($oeste->confirmarVinculo($solicitud['id_enlace'], $solicitud['token']), 'Una atención nunca se bloquea por el tope.');
        $this->assertSame(4, $this->activos());
    }

    public function testDesvincularseLiberaElCupo(): void
    {
        $vinculos = new VinculosPropietario($this->db);
        $this->llegarAlTope($vinculos);
        $this->assertTrue($vinculos->alTope());

        $vinculos->desvincular(4);
        $this->assertFalse($vinculos->alTope());
        $vinculos->vincular(5);

        $this->assertSame(3, $this->activos());
        $this->assertSame('activo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 5')->fetchColumn());
    }

    public function testUnaClinicaSuspendidaNoOcupaCupo(): void
    {
        $vinculos = new VinculosPropietario($this->db);
        $this->llegarAlTope($vinculos);
        $this->db->exec("UPDATE clinicas SET estado = 'suspendida' WHERE id_clinica = 4");

        $vinculos->vincular(5);

        $this->assertSame(3, count($vinculos->clinicas()), 'Las mismas que muestra «Clínicas».');
    }

    /** RE-5.13.5: al tope, «Vincularme» deshabilitado con una línea que lo explica; antes, habilitado y sin la línea. */
    public function testAlTopeElPortalDeshabilitaVincularmeConUnaLinea(): void
    {
        $antes = $this->clinicas();
        $this->assertStringNotContainsString('data-tope-clinicas', $antes);
        $this->assertMatchesRegularExpression('/data-vincular-clinica="4" data-nombre="Clínica Este">/', $antes);

        $this->llegarAlTope(new VinculosPropietario($this->db));
        $despues = $this->clinicas();

        $this->assertStringContainsString('data-tope-clinicas', $despues);
        $this->assertStringContainsString(htmlspecialchars(VinculosPropietario::MENSAJE_TOPE, ENT_QUOTES, 'UTF-8'), $despues);
        $this->assertMatchesRegularExpression('/data-vincular-clinica="5" data-nombre="Clínica Oeste" disabled>/', $despues);
    }

    private function clinicas(): string
    {
        ob_start();
        try {
            (new PortalController($this->db))->index();
        } finally {
            $html = (string) ob_get_clean();
        }
        $inicio = strpos($html, 'id="screen-clinicas"');
        return substr($html, $inicio, strpos($html, '<!-- ══ PERFIL', $inicio) - $inicio);
    }
}
