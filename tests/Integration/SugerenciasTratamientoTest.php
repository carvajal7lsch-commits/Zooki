<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../controllers/PrescripcionController.php';
require_once __DIR__ . '/../../models/Usuario.php';

final class SugerenciasTratamientoTest extends TestCase
{
    private PDO $db;
    private string $carpeta;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        ConocimientoRealPrueba::taxonomia($this->db);
        $this->carpeta = ConocimientoPrueba::copiar(__DIR__ . '/../Fixtures/conocimiento/pautas');
        (new CargadorConocimiento($this->db))->cargar($this->carpeta);
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        $_SESSION = ['id_usuario' => 2, 'csrf_default' => 'token'];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id_mascota' => 1, 'diagnostico' => 'diagnostico.prueba', 'peso' => '24', 'csrf_token' => 'token'];
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        ConocimientoPrueba::borrar($this->carpeta);
        Security::definirFuenteDeContextos(null);
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
    }

    private function api(): array
    {
        Security::autorizar('sugerir_tratamientos_ajax');
        ob_start();
        try {
            (new PrescripcionController($this->db))->sugerirAjax();
        } finally {
            $json = ob_get_clean();
        }
        return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }

    public function testApiCargaPautaYPlanYRespetaElPesoDeConsulta(): void
    {
        $r = $this->api();
        $this->assertTrue($r['success']);
        $this->assertSame(['Examen sintético'], $r['plan']['examenes']);
        $this->assertSame('12 mg (0,5 mg/kg) cada 24 h', $r['tratamientos'][0]['dosis']);
        $this->assertNotContains('farmaco.bloqueado', array_column($r['tratamientos'], 'farmaco'));
        $_POST['peso'] = '';
        $this->assertStringContainsString('calcula con el peso', $this->api()['tratamientos'][0]['dosis']);
        foreach (['2000', '-1', 'nan', []] as $peso) {
            $_POST['peso'] = $peso;
            $this->assertFalse($this->api()['success']);
            $this->assertSame(422, http_response_code());
        }
    }

    public function testCargaIdempotenteYCambiarPautaOPlanCambiaVersionSinCambiarIds(): void
    {
        $cargador = new CargadorConocimiento($this->db);
        $antes = ConocimientoPrueba::instantanea($this->db);
        $cargador->cargar($this->carpeta);
        $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
        $version = $this->api()['version'];
        $ids = $this->db->query('SELECT codigo, id_arista FROM grafo_aristas ORDER BY codigo')->fetchAll(PDO::FETCH_KEY_PAIR);
        ConocimientoPrueba::cambiar($this->carpeta, 'aristas', static function (array &$filas): void {
            $filas[1]['pauta']['dosis_mg_kg'] = ['minimo' => 1, 'maximo' => 1];
        });
        $cargador->cargar($this->carpeta);
        $r = $this->api();
        $this->assertNotSame($version, $r['version']);
        $this->assertSame('24 mg (1 mg/kg) cada 24 h', $r['tratamientos'][0]['dosis']);
        $version = $r['version'];
        ConocimientoPrueba::cambiar($this->carpeta, 'nodos', static function (array &$filas): void {
            $filas[3]['metadatos']['plan']['examenes'] = ['Otro examen sintético'];
        });
        $cargador->cargar($this->carpeta);
        $r = $this->api();
        $this->assertNotSame($version, $r['version']);
        $this->assertSame(['Otro examen sintético'], $r['plan']['examenes']);
        $this->assertSame($ids, $this->db->query('SELECT codigo, id_arista FROM grafo_aristas ORDER BY codigo')->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function testSoloVeterinarioCsrfYVinculoActivo(): void
    {
        foreach ([[1, Contexto::deClinica(1, 'Norte', Roles::ADMIN)], [6, Contexto::dePropietario()], [7, Contexto::dePlataforma()]] as [$id, $contexto]) {
            $_SESSION['id_usuario'] = $id;
            Contexto::activar($contexto, 1);
            try {
                $this->api();
                $this->fail('Entró otro rol.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
        }
        $_SESSION['id_usuario'] = 2;
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $_POST['csrf_token'] = 'incorrecto';
        try {
            $this->api();
            $this->fail('Entró sin CSRF.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
        $_POST['csrf_token'] = 'token';
        foreach (['inactivo', 'sin_vinculo'] as $estado) {
            if ($estado === 'inactivo') {
                $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 1 AND id_clinica = 1");
            } else {
                $this->db->exec('DELETE FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 1');
            }
            try {
                $this->api();
                $this->fail('Entró sin vínculo activo.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
        }
    }

    public function testAlergiaGlobalExcluyeYTratamientoProtegidoSoloAdvierte(): void
    {
        $this->db->exec("INSERT INTO consultas (id_consulta, id_clinica, id_mascota, id_veterinario, fecha_hora, motivo_consulta, anamnesis, diagnostico, plan_tratamiento) VALUES (999, 2, 1, 4, '2026-01-01 08:00:00', 'Motivo secreto', '', 'Diagnóstico secreto', '')");
        $this->db->exec("INSERT INTO tratamientos (id_consulta, medicamento, dosis, via_administracion, duracion, fecha_inicio, fecha_fin, id_nodo_farmaco) SELECT 999, 'Medicamento secreto', 'Dosis secreta', 'Oral', 'Continua', '2026-01-01', '2099-01-01', id_nodo FROM grafo_nodos WHERE codigo = 'farmaco.vigente'");
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, id_veterinario, tipo, id_nodo, descripcion) SELECT 1, 2, 4, 'alergia', id_nodo, 'Alergia global sintética' FROM grafo_nodos WHERE codigo = 'farmaco.alergeno'");
        $r = $this->api();
        $this->assertTrue($r['success']);
        $this->assertNotContains('farmaco.alergeno', array_column($r['tratamientos'], 'farmaco'));
        $seguridad = $r['tratamientos'][0]['seguridad'];
        $this->assertTrue($seguridad['requiere_decision']);
        $this->assertTrue($seguridad['requiere_conciliacion']);
        $this->assertSame('interaccion_protegida', $seguridad['advertencias'][0]['tipo']);
        foreach (['farmaco.vigente', 'secreto', 'secreta', '999', 'interaccion.prueba', 'id_clinica'] as $secreto) {
            $this->assertStringNotContainsString($secreto, json_encode($r));
        }
        $this->db->exec('UPDATE alertas_medicas SET activa = 0');
        $this->assertContains('farmaco.alergeno', array_column($this->api()['tratamientos'], 'farmaco'));
    }
}
