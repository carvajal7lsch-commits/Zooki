<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';

/** HU-0.5: componer la sección no consulta Wompi ni cambia sus permisos o datos. */
final class AjustesSuscripcionTest extends TestCase
{
    private PDO $db;
    private ClienteWompiFalso $cliente;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $this->cliente = new ClienteWompiFalso();
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        Security::definirFuenteDeContextos(null);
    }

    private function contexto(array $contexto, int $usuario): void
    {
        $_SESSION = ['id_usuario' => $usuario];
        Contexto::activar($contexto, 1);
        Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
    }

    private function controlador(): SuscripcionController
    {
        return new SuscripcionController($this->db, static fn () => VigenciaSuscripcion::fecha('2026-10-26 12:00:00'), new ConfiguracionWompi([]), $this->cliente);
    }

    public function testDatosDeLaSeccionSonDeLaClinicaActivaSinConsultarPasarelaNiEscribir(): void
    {
        $antes = $this->db->query('SELECT * FROM suscripciones')->fetchAll();
        foreach ([1 => 1, 2 => 3] as $clinica => $usuario) {
            $this->contexto(Contexto::deClinica($clinica, 'Clínica', Roles::ADMIN), $usuario);
            $_GET = [];
            $datos = $this->controlador()->datosVista();
            $this->assertSame($clinica, (int) $datos['estadoSuscripcion']['clinica']['id_clinica']);
            $this->assertFalse($datos['pagoDisponible']);
            $_GET = ['id_clinica' => $clinica === 1 ? '2' : '1'];
            try {
                $this->controlador()->datosVista();
                $this->fail('La sección no permite elegir una clínica ajena.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $this->assertSame($antes, $this->db->query('SELECT * FROM suscripciones')->fetchAll());
        $this->assertSame([], $this->cliente->consultas);
        $this->assertSame([], $this->cliente->referenciasConsultadas);
        $this->assertSame([], $this->cliente->creaciones);
    }

    public function testOtrosContextosNoLeenLaSeccionDePago(): void
    {
        foreach ([[Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 2], [Contexto::dePropietario(), 6], [Contexto::dePlataforma(), 7]] as [$contexto, $usuario]) {
            $this->contexto($contexto, $usuario);
            try {
                $this->controlador()->datosVista();
                $this->fail('Un contexto distinto al administrador no puede leer Suscripción.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
    }

    public function testAvisoDelRetornoSeConsumeSoloEnSuClinica(): void
    {
        $this->contexto(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $_SESSION['suscripcion_aviso_retorno'] = ['clinica' => 1, 'mensaje' => 'Consulta completada.'];
        $this->assertSame('Consulta completada.', $this->controlador()->datosVista()['mensajePago']);
        $this->assertArrayNotHasKey('suscripcion_aviso_retorno', $_SESSION);
        $_SESSION['suscripcion_aviso_retorno'] = ['clinica' => 2, 'mensaje' => 'Aviso de Sur'];
        $this->assertNotSame('Aviso de Sur', $this->controlador()->datosVista()['mensajePago']);
    }
}
