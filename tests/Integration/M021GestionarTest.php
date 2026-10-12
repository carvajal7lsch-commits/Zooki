<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/PlataformaFixture.php';
require_once __DIR__ . '/../../models/Plataforma.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../controllers/PlataformaController.php';

/**
 * M0.2.1 — El modal «Gestionar» solo ofrece las acciones que el estado
 * admite, pero quien decide sigue siendo el servidor (Plataforma::cambiarEstado):
 * una transición no permitida se rechaza aunque llegue por POST.
 */
final class M021GestionarTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = PlataformaFixture::sqlite();
        PlataformaFixture::datos($this->db);
        PlataformaFixture::plataforma();
        // Una clínica pendiente aún no tiene plan (se asigna al verificar, HU-0.2).
        $this->db->exec('UPDATE clinicas SET id_plan = NULL WHERE id_clinica = 4');
        $_SERVER['REMOTE_ADDR'] = '192.0.2.8';
        Auditoria::definirProxiesConfiables([]);
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
    }

    protected function tearDown(): void
    {
        Security::definirAlmacenDeIntentos(null);
        Auditoria::definirProxiesConfiables(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    /** POST de la pestaña Estado, como lo envía el modal. */
    private function cambiarEstado(int $clinica, string $estado): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf_default'] = 'seguro';
        $_POST = ['csrf_token' => 'seguro', 'id_clinica' => (string) $clinica, 'estado' => $estado, 'motivo' => 'Revisión de la plataforma'];
        http_response_code(200);
        ob_start();
        (new PlataformaController($this->db))->modificar('plataforma_estado_ajax');
        $respuesta = json_decode((string) ob_get_clean(), true);
        return [http_response_code(), $respuesta];
    }

    private function estado(int $clinica): string
    {
        return (string) $this->db->query('SELECT estado FROM clinicas WHERE id_clinica = ' . $clinica)->fetchColumn();
    }

    public function testElServidorRechazaLasTransicionesQueElModalNoOfrece(): void
    {
        $rechazos = [
            'activa a activa' => [1, 'activa'],
            'pendiente a activa' => [4, 'activa'],
            'suspendida a suspendida' => [3, 'suspendida'],
            'estado inventado' => [1, 'eliminada'],
        ];
        foreach ($rechazos as $caso => [$clinica, $destino]) {
            $antes = $this->estado($clinica);
            [$codigo, $respuesta] = $this->cambiarEstado($clinica, $destino);
            $this->assertSame(422, $codigo, $caso);
            $this->assertFalse($respuesta['success'], $caso);
            $this->assertSame($antes, $this->estado($clinica), $caso . ': no cambia nada.');
        }
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'clinicas'")->fetchColumn(), 'Sin cambios, sin auditoría.');
    }

    public function testUnaPendienteSuspendidaNoSeReactivaYDeBajaNoVuelve(): void
    {
        // Lo que el modal ofrece a una pendiente: suspender (permitido).
        [$codigo] = $this->cambiarEstado(4, 'suspendida');
        $this->assertSame(200, $codigo);
        $this->assertSame('suspendida', $this->estado(4));
        // Sin registro verificado el modal no ofrece «Reactivar»; por POST tampoco pasa.
        [$codigo, $respuesta] = $this->cambiarEstado(4, 'activa');
        $this->assertSame(422, $codigo);
        $this->assertSame('No se puede activar una clínica cuyo registro no se ha verificado.', $respuesta['message']);
        $this->assertSame('suspendida', $this->estado(4));
        // Dar de baja (la zona de riesgo) es definitivo desde el panel.
        [$codigo] = $this->cambiarEstado(1, 'baja');
        $this->assertSame(200, $codigo);
        foreach (['activa', 'suspendida', 'baja'] as $destino) {
            [$codigo] = $this->cambiarEstado(1, $destino);
            $this->assertSame(422, $codigo, 'De baja a ' . $destino);
            $this->assertSame('baja', $this->estado(1));
        }
    }

    public function testElModalUsaTarjetasDeEleccionYDarDeBajaEsUnaAccionMas(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        ob_start();
        (new PlataformaController($this->db))->inicio();
        $html = (string) ob_get_clean();
        $modal = substr($html, strpos($html, 'id="gestionarClinica"'));
        // Revisión del usuario (2026-10-12): tarjetas de plan con sus topes, interruptor de suscripción y sin listas nativas.
        $this->assertStringNotContainsString('<select', $modal);
        $this->assertStringContainsString('<input type="radio" name="id_plan" value="1" required data-plan-gratuito="1">', $modal);
        $this->assertStringContainsString('<input type="radio" name="id_plan" value="2" required data-plan-gratuito="0">', $modal);
        $this->assertStringContainsString('2 personal · 50 mascotas · 100 citas al mes', $modal);
        $this->assertStringContainsString('Sin límites de personal, mascotas ni citas', $modal);
        $this->assertStringContainsString('data-suscripcion-activa', $modal);
        // Estado: tres tarjetas de acción; «Dar de baja» es una más, en rojo.
        foreach (['suspendida', 'activa', 'baja'] as $accion) {
            $this->assertStringContainsString('<input type="radio" name="estado" value="' . $accion . '"', $modal);
        }
        $this->assertStringContainsString('<label class="plataforma-opcion plataforma-opcion--riesgo" data-opcion-estado="baja">', $modal);
        $this->assertStringContainsString('data-motivo-sugerido="Falta de pago"', $modal);
        $this->assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=|<script(?![^>]*src=)/', $html);
    }
}
