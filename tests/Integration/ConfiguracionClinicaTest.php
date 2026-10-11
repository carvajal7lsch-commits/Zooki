<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/InicializadorClinica.php';
require_once __DIR__ . '/../../models/CatalogoClinica.php';
require_once __DIR__ . '/../../controllers/HorarioClinicaController.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Desparasitacion.php';

/** C2: aislamiento, copia inicial y horario efectivo con el fixture de C1. */
final class ConfiguracionClinicaTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearCatalogosSqlite($this->db);
        (new InicializadorClinica($this->db))->copiar(1);
        (new InicializadorClinica($this->db))->copiar(2);
        $this->comoAdmin(1);
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void { $_SESSION = []; $_POST = []; $_GET = []; }

    private function comoAdmin(int $clinica): void
    {
        $_SESSION = ['id_usuario' => $clinica === 1 ? 1 : 3];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::ADMIN), 1);
    }

    private function json(callable $accion): array
    {
        ob_start();
        try { $accion(); return json_decode(ob_get_contents(), true); }
        finally { ob_end_clean(); }
    }

    public function testCatalogosSoloSeLeenDesdeLaClinicaActiva(): void
    {
        $tipos = (new Cita($this->db))->getTiposCita();
        $this->assertCount(6, $tipos);
        $this->assertSame([1], array_unique(array_map('intval', array_column($tipos,'id_clinica'))));
        $this->assertArrayHasKey('margen_minutos', $tipos[0]);
        $this->assertArrayHasKey('pausable', $tipos[0]);
        $vacunas = (new Vacuna($this->db))->getVacunasPorEspecie(1);
        $laboratorios = (new Vacuna($this->db))->getLaboratorios();
        $productos = (new Desparasitacion($this->db))->getProductos();
        $this->assertCount(12, $vacunas);
        $this->assertCount(11, $laboratorios);
        $this->assertCount(15, $productos);
        $this->comoAdmin(2);
        $this->assertFalse((new Cita($this->db))->getTipoCitaById($tipos[0]['id_tipo_cita']));
        foreach ([[$vacunas,(new Vacuna($this->db))->getVacunasPorEspecie(1),'id_vacuna_base'],
                  [$laboratorios,(new Vacuna($this->db))->getLaboratorios(),'id_laboratorio'],
                  [$productos,(new Desparasitacion($this->db))->getProductos(),'id_producto']] as [$norte,$sur,$clave]) {
            $this->assertSame([], array_intersect(array_column($norte,$clave), array_column($sur,$clave)));
        }
    }

    public function testGuardarYRestaurarSoloModificanLaClinicaActiva(): void
    {
        $sur = $this->db->query('SELECT * FROM horarios_clinica WHERE id_clinica = 2')->fetchAll(PDO::FETCH_ASSOC);
        $controlador = new HorarioClinicaController($this->db);
        $_POST = ['horarios' => [1 => ['activo'=>1,'morning_activo'=>1,'afternoon_activo'=>0,
            'morning_inicio'=>'09:00','morning_fin'=>'11:00']]];
        $this->assertTrue($this->json(fn () => $controlador->guardarHorariosAjax())['success']);
        $this->assertSame('09:00', (new HorarioClinica($this->db))->dia(1)['bloque_morning_inicio']);
        $this->assertFalse($controlador->validarHorarioLaboral('2026-10-12','08:30')['valido']);
        $this->assertTrue($controlador->validarHorarioLaboral('2026-10-12','09:30')['valido']);
        $this->assertFalse($controlador->validarHorarioLaboral('2026-10-12','14:30')['valido']);
        $this->assertTrue($this->json(fn () => $controlador->restaurarPorDefectoAjax())['success']);
        $this->assertSame('08:00:00', (new HorarioClinica($this->db))->dia(1)['bloque_morning_inicio']);
        $this->assertSame($sur, $this->db->query('SELECT * FROM horarios_clinica WHERE id_clinica = 2')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 1')->fetchColumn());
        $this->comoAdmin(2);
        $this->assertSame($sur, $this->json(fn () => $controlador->getHorariosAjax())['horarios']);
    }

    public function testDiaInactivoYBloqueApagadoNoOfrecenHoras(): void
    {
        $controlador = new HorarioClinicaController($this->db);
        $this->assertSame([], $controlador->obtenerHorasDisponibles('2026-10-10'));
        $this->db->exec('UPDATE horarios_clinica SET bloque_morning_activo = 0 WHERE id_clinica = 1 AND dia_semana = 1');
        $this->assertSame(['14:00','14:30','15:00','15:30','16:00','16:30','17:00','17:30'], $controlador->obtenerHorasDisponibles('2026-10-12'));
    }

    public function testValidacionNoGuardaParcialmenteNiAceptaHorasODiasInvalidos(): void
    {
        $antes = (new HorarioClinica($this->db))->listar();
        foreach ([8, 0, 'otro'] as $dia) {
            $_POST = ['horarios'=>[$dia=>['activo'=>1]]];
            $this->assertFalse($this->json(fn () => (new HorarioClinicaController($this->db))->guardarHorariosAjax())['success']);
        }
        $_POST = ['horarios'=>[1=>['activo'=>0],2=>['activo'=>1,'morning_inicio'=>'08:99','morning_fin'=>'12:00']]];
        $this->assertFalse($this->json(fn () => (new HorarioClinicaController($this->db))->guardarHorariosAjax())['success']);
        $this->assertSame($antes, (new HorarioClinica($this->db))->listar());
    }

    public function testInicializadorNoDuplicaNiSobrescribeCatalogosPropios(): void
    {
        $this->db->exec("UPDATE tipos_cita SET nombre_tipo = 'Personalizado' WHERE id_tipo_cita = 1");
        $antes = $this->catalogos();
        $inicializador = new InicializadorClinica($this->db);
        $inicializador->copiar(1);
        $inicializador->copiar(1);
        $this->assertSame($antes, $this->catalogos());
        $this->assertCount(42, $antes['especie_vacunas']);
    }

    public function testCopiaInicialFallaSinDejarDatosParcialesYRespetaTransaccionExterior(): void
    {
        $antes = $this->catalogos();
        $this->db->exec("CREATE TRIGGER rechazar_producto BEFORE INSERT ON productos_desparasitacion_base
            WHEN NEW.id_clinica = 3 BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
        foreach ([false, true] as $exterior) {
            if ($exterior) $this->db->beginTransaction();
            try {
                (new InicializadorClinica($this->db))->copiar(3);
                $this->fail('Debió fallar la copia completa.');
            } catch (PDOException $e) {
                $this->assertSame($antes, $this->catalogos());
                $this->assertSame($exterior, $this->db->inTransaction());
            }
            if ($exterior) $this->db->rollBack();
        }
        $this->db->exec('DROP TRIGGER rechazar_producto');
        $this->db->beginTransaction();
        (new InicializadorClinica($this->db))->copiar(3);
        $this->assertTrue($this->db->inTransaction());
        $this->db->rollBack();
        $this->assertSame($antes, $this->catalogos());
    }

    public function testSinContextoNoSeLeenCatalogos(): void
    {
        $_SESSION = [];
        $this->expectException(AccesoDenegado::class);
        (new CatalogoClinica($this->db))->productos();
    }

    private function catalogos(): array
    {
        $datos = [];
        foreach (['horarios_clinica','tipos_cita','vacunas_base','especie_vacunas','laboratorios_base','productos_desparasitacion_base'] as $tabla) {
            $datos[$tabla] = $this->db->query("SELECT * FROM $tabla")->fetchAll(PDO::FETCH_ASSOC);
        }
        return $datos;
    }
}
