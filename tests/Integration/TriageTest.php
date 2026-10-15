<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../controllers/TriageController.php';
require_once __DIR__ . '/../../models/Usuario.php';

final class TriageTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $fecha;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        ConocimientoRealPrueba::taxonomia($this->db);
        (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        $this->fecha = new DateTimeImmutable('2026-10-15T12:00:00-05:00');
        $this->como(2, Contexto::deClinica(1, 'Norte', Roles::VETERINARIO));
        $_POST = ['csrf_token' => 'token', 'id_mascota' => 1, 'sintomas' => [['codigo' => 'sintoma.dificultad_respiratoria', 'tiempo' => 'no_se']]];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
    }

    private function como(int $id, array $contexto): void
    {
        $_SESSION = ['id_usuario' => $id, 'csrf_default' => 'token'];
        Contexto::activar($contexto, 1);
    }

    private function llamar(string $accion = 'calcular_triage_ajax'): array
    {
        Security::autorizar($accion);
        ob_start();
        try {
            $controlador = new TriageController($this->db, $this->fecha);
            if ($accion === 'buscar_sintomas_ajax') {
                $controlador->buscarAjax();
            } else {
                $controlador->calcularAjax();
            }
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true, 64, JSON_THROW_ON_ERROR);
    }

    public function testVeterinarioYPropietarioCalculanSinEscribirYContextoSaleDeFicha(): void
    {
        $this->db->exec("UPDATE mascotas SET id_especie = 2, id_raza = 50 WHERE id_mascota = 1");
        $antes = $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        foreach ([[2, Contexto::deClinica(1, 'Norte', Roles::VETERINARIO)], [6, Contexto::dePropietario()]] as [$id, $contexto]) {
            $this->como($id, $contexto);
            $_POST['sintomas'] = [];
            $_POST['exposiciones'] = [['codigo' => 'farmaco.paracetamol', 'tiempo' => 'no_se']];
            $_POST['especie'] = 'especie.canino';
            $_POST['nivel'] = 'verde';
            $resultado = $this->llamar();
            $this->assertTrue($resultado['success']);
            $this->assertSame('rojo', $resultado['nivel']);
            $this->assertFalse($resultado['revision']);
            $this->assertSame(64, strlen($resultado['version']));
        }
        $this->assertSame($antes, $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        foreach (['citas', 'consultas'] as $tabla) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla)->fetchColumn());
        }
    }

    public function test403SinVinculoActivoAuditadoYPropietarioAjeno(): void
    {
        foreach (['inactivo', 'sin_vinculo'] as $estado) {
            $sql = $estado === 'inactivo' ? "UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 1 AND id_clinica = 1" : 'DELETE FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 1';
            $this->db->exec($sql);
            try {
                $this->llamar();
                $this->fail('Permitió el cálculo sin vínculo activo.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'mascotas' AND accion = 'OTHER'")->fetchColumn());
        $this->como(6, Contexto::dePropietario());
        $_POST['id_mascota'] = 2;
        try {
            $this->llamar();
            $this->fail('Permitió acceder a la mascota de otro propietario.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
    }

    public function test403PlataformaYAdministradorYCsrf(): void
    {
        foreach ([[7, Contexto::dePlataforma()], [1, Contexto::deClinica(1, 'Norte', Roles::ADMIN)]] as [$id, $contexto]) {
            $this->como($id, $contexto);
            foreach (['buscar_sintomas_ajax', 'calcular_triage_ajax'] as $accion) {
                try {
                    $this->llamar($accion);
                    $this->fail('Un rol no clínico obtuvo acceso.');
                } catch (AccesoDenegado $error) {
                    $this->assertSame(403, $error->codigo());
                }
            }
        }
        $this->como(6, Contexto::dePropietario());
        unset($_POST['csrf_token']);
        try {
            $this->llamar();
            $this->fail('Aceptó POST sin CSRF.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
    }

    public function test422PorCodigosInvalidosRetiradosOTiposIncorrectos(): void
    {
        foreach (['inexistente', 'farmaco.paracetamol', 'farmaco.retirado'] as $codigo) {
            $_POST['sintomas'] = [['codigo' => $codigo, 'tiempo' => 'no_se']];
            $this->assertFalse($this->llamar()['success']);
            $this->assertSame(422, http_response_code());
        }
        $_POST['sintomas'] = [];
        $_POST['exposiciones'] = [['codigo' => 'sintoma.vomito', 'tiempo' => 'conocido', 'minutos' => 4]];
        $this->assertFalse($this->llamar()['success']);
        $this->assertSame(422, http_response_code());
    }

    public function testTiemposValidacionDuplicadosYNotaNoCalcula(): void
    {
        foreach ([
            [['codigo' => 'sintoma.vomito', 'tiempo' => 'no_aplica']],
            [['codigo' => 'sintoma.vomito', 'tiempo' => 'conocido', 'minutos' => -1]],
            [['codigo' => 'sintoma.vomito', 'tiempo' => 'conocido', 'minutos' => 'NaN']],
            [['codigo' => 'sintoma.vomito', 'tiempo' => 'no_se', 'minutos' => 1]],
            [['codigo' => 'sintoma.vomito'], ['codigo' => 'sintoma.vomito']],
        ] as $marcas) {
            $_POST['sintomas'] = $marcas;
            $this->assertFalse($this->llamar()['success']);
            $this->assertSame(422, http_response_code());
        }
        $_POST['sintomas'] = [];
        $_POST['nota'] = 'Convulsiona y no respira';
        $this->assertFalse($this->llamar()['success']);
        $_POST['sintomas'] = [['codigo' => 'sintoma.control_preventivo', 'tiempo' => 'no_aplica']];
        $this->assertSame('verde', $this->llamar()['nivel']);
        foreach ([['vacunacion' => 'inventada'], ['dias_desde_celo' => '-1']] as $declarados) {
            $original = $_POST;
            $_POST = array_replace($_POST, $declarados);
            $this->assertFalse($this->llamar()['success']);
            $this->assertSame(422, http_response_code());
            $_POST = $original;
        }
    }

    public function testBuscadorGlobalParaVetYPropietarioSinSeleccionarNiGuardar(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['q' => 'bomita', 'especie' => 'especie.ave'];
        foreach ([[2, Contexto::deClinica(1, 'Norte', Roles::VETERINARIO)], [6, Contexto::dePropietario()]] as [$id, $contexto]) {
            $this->como($id, $contexto);
            $resultado = $this->llamar('buscar_sintomas_ajax');
            $this->assertContains('sintoma.vomito', array_column($resultado['sugerencias'], 'codigo'));
            $this->assertContains('sintoma.vomito_sangre', array_column($resultado['sugerencias'], 'codigo'));
            $this->assertContains('sintoma.otro_no_se', array_column($resultado['sugerencias'], 'codigo'));
            $this->assertArrayNotHasKey('nivel', $resultado);
            $this->assertArrayNotHasKey('seleccionado', $resultado['sugerencias'][0]);
        }
        $_GET['q'] = str_repeat('x', BuscadorSintomas::CONSULTA_MAX + 1);
        $this->assertFalse($this->llamar('buscar_sintomas_ajax')['success']);
        $_GET = ['especie' => 'inventada'];
        $this->assertFalse($this->llamar('buscar_sintomas_ajax')['success']);
    }
}
