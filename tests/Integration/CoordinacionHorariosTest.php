<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ComparaRespuestaIdempotente.php';
require_once __DIR__ . '/../../models/CoordinacionHorarios.php';
require_once __DIR__ . '/../../models/HorarioClinica.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../controllers/CoordinacionHorariosController.php';
require_once __DIR__ . '/../../controllers/HorarioClinicaController.php';

/** C2, P01/P04: permisos, vigencia, intervalos y atomicidad sobre el destino B. */
final class CoordinacionHorariosTest extends TestCase
{
    use ComparaRespuestaIdempotente;

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        Auditoria::definirProxiesConfiables([]);
        Csrf::token();
        $this->como(1, 1, Roles::ADMIN);
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

    private function como(int $clinica, int $usuario, int $rol): void
    {
        $_SESSION = ['id_usuario' => $usuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', $rol), 1);
    }

    private function franjas(string $inicio = '10:00', string $fin = '12:00'): array
    {
        return [['dia_semana' => 1, 'hora_inicio' => $inicio, 'hora_fin' => $fin]];
    }

    private function cita(string $fecha = '2030-01-07', string $hora = '09:00', ?string $clave = null): int
    {
        return (new Cita($this->db))->registrar(['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1,
            'fecha' => $fecha, 'hora' => $hora, 'motivo' => 'Control', 'clave_operacion' => $clave]);
    }

    private function rechaza(callable $accion, string $clase = InvalidArgumentException::class): Throwable
    {
        try {
            $accion();
            $this->fail('Se admitió una operación inválida.');
        } catch (Throwable $error) {
            $this->assertInstanceOf($clase, $error);
            return $error;
        }
    }

    public function testRolesContextoPertenenciaYLecturasAisladas(): void
    {
        $m = new CoordinacionHorarios($this->db);
        $this->assertSame([2,5], array_map('intval', array_column($m->leer()['veterinarios'], 'id_usuario')));
        $this->rechaza(fn () => $m->guardarVeterinario(4, $this->franjas()), AccesoDenegado::class);
        foreach ([[1,2,Roles::VETERINARIO], [1,6,Roles::PROPIETARIO], [1,7,Roles::SUPER_ADMIN], [2,1,Roles::ADMIN]] as [$c,$u,$r]) {
            $this->como($c,$u,$r);
            $this->rechaza(fn () => $m->guardarVeterinario(2, $this->franjas()), AccesoDenegado::class);
        }
        $this->como(1,2,Roles::VETERINARIO);
        $this->assertSame(2, $m->leer(5)['id_veterinario']);
        $this->rechaza(fn () => $m->proponer(5, $this->franjas()), AccesoDenegado::class);
        $this->como(2,3,Roles::ADMIN);
        $this->assertSame([4], array_map('intval', array_column($m->leer()['veterinarios'], 'id_usuario')));
    }

    public function testLecturaAjenaAuditaYEscritorReauditaSoloUnaVezTrasRollback(): void
    {
        $m = new CoordinacionHorarios($this->db);
        $tablas = ['usuario_clinica', 'horarios_clinica', 'horarios_veterinario', 'propuestas_horario', 'cierres_clinica', 'citas', 'operaciones_agenda'];
        $negocio = function () use ($tablas): array {
            $filas = [];
            foreach ($tablas as $tabla) {
                $filas[$tabla] = $this->db->query('SELECT * FROM ' . $tabla)->fetchAll(PDO::FETCH_ASSOC);
            }
            return $filas;
        };
        $antes = $negocio();
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        $mensaje = null;
        foreach ([fn () => $m->leer(4), fn () => $m->leer(999), fn () => $m->guardarVeterinario(4, $this->franjas())] as $i => $accion) {
            $error = $this->rechaza($accion, AccesoDenegado::class);
            $this->assertSame(403, $error->codigo());
            if ($i < 2) {
                $mensaje ??= $error->getMessage();
                $this->assertSame($mensaje, $error->getMessage());
            }
            $this->assertSame($i + 1, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
            $this->assertSame($antes, $negocio());
            $this->assertFalse($this->db->inTransaction());
        }
        $auditoria = $this->db->query('SELECT * FROM auditoria_sistema ORDER BY id_auditoria')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($auditoria as $fila) {
            $this->assertSame(1, (int) $fila['id_usuario']);
            $this->assertSame(1, (int) $fila['id_clinica']);
            $this->assertSame('OTHER', $fila['accion']);
            $this->assertSame('horarios_veterinario', $fila['tabla_afectada']);
        }
        // Denegación durante la vista canónica, después de haber escrito y auditado.
        $this->db->exec("CREATE TRIGGER revocar_admin_c21 AFTER INSERT ON horarios_veterinario BEGIN UPDATE usuario_clinica SET estado='inactivo' WHERE id_clinica=1 AND id_usuario=1; END");
        $this->rechaza(fn () => $m->guardarVeterinario(2, $this->franjas()), AccesoDenegado::class);
        $this->assertSame(4, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        $this->assertSame($antes, $negocio());
        $this->db->exec('DROP TRIGGER revocar_admin_c21');
        $m->guardarVeterinario(2, $this->franjas(), str_repeat('e', 64));
        $this->assertSame(5, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        $m->guardarVeterinario(2, $this->franjas(), str_repeat('e', 64));
        $this->assertSame(5, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
    }

    public function testIntervalosPropiosAperturaYLimitesExactos(): void
    {
        $m = new CoordinacionHorarios($this->db);
        foreach ([$this->franjas('10:00','10:00'), $this->franjas('07:59','09:00'), $this->franjas('11:00','14:00'),
            [['dia_semana'=>8,'hora_inicio'=>'09:00','hora_fin'=>'10:00']],
            [...$this->franjas('09:00','10:01'), ...$this->franjas('10:00','11:00')]] as $franjas) {
            $this->rechaza(fn () => $m->guardarVeterinario(2, $franjas));
        }
        $resultado = $m->guardarVeterinario(2, [...$this->franjas('08:00','10:00'), ...$this->franjas('10:00','12:00')]);
        $this->assertCount(2, $resultado['franjas']);
        $this->assertSame('12:00:00', $resultado['franjas'][1]['hora_fin']);
    }

    public function testConflictoGlobalSoloDaAvisoGenerico(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario,id_clinica,id_rol,estado) VALUES (2,2,2,'activo')");
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin) VALUES (2,2,1,'10:00:00','11:00:00')");
        $error = $this->rechaza(fn () => (new CoordinacionHorarios($this->db))->guardarVeterinario(2, $this->franjas()));
        $this->assertSame('Choca con otro compromiso del veterinario.', $error->getMessage());
        $this->assertStringNotContainsString('Sur', $error->aviso);
        $this->assertStringNotContainsString('10:00', $error->aviso);
    }

    public function testPropuestaPendienteResolucionRepetidaYRechazoMotivado(): void
    {
        $m = new CoordinacionHorarios($this->db);
        $antes = $m->leer(2)['franjas'];
        $this->como(1,2,Roles::VETERINARIO);
        $p = $m->proponer(2, $this->franjas(), str_repeat('a',64));
        $this->assertSame($antes, $m->leer()['franjas']);
        $this->assertSame($p['id_propuesta'], $m->proponer(2, $this->franjas(), str_repeat('a',64))['id_propuesta']);
        $this->como(1,1,Roles::ADMIN);
        $r = $m->resolver($p['id_propuesta'], true, '', str_repeat('b',64));
        $this->assertSame('aprobada', $r['estado']);
        $this->assertRespuestaIdempotenteIgual($r, $m->resolver($p['id_propuesta'], true, '', str_repeat('b',64)));
        $this->rechaza(fn () => $m->resolver($p['id_propuesta'], false, 'Otro comando'));
        $this->como(1,2,Roles::VETERINARIO);
        $p = $m->proponer(2, $this->franjas());
        $this->como(1,1,Roles::ADMIN);
        $this->rechaza(fn () => $m->resolver($p['id_propuesta'], false, ''));
        $this->assertSame('rechazada', $m->resolver($p['id_propuesta'], false, 'Sin cobertura')['estado']);
    }

    public function testAprobacionRevalidaAperturaYMembresiaActuales(): void
    {
        $m = new CoordinacionHorarios($this->db);
        $this->como(1,2,Roles::VETERINARIO);
        $p = $m->proponer(2, $this->franjas('09:00','12:00'));
        $this->como(1,1,Roles::ADMIN);
        (new HorarioClinica($this->db))->guardar([[1,1,1,0,'10:00:00','12:00:00',null,null]]);
        $this->rechaza(fn () => $m->resolver($p['id_propuesta'], true));
        $this->assertSame('pendiente', $m->leer()['propuestas'][0]['estado']);
        $this->db->exec("UPDATE usuario_clinica SET estado='inactivo' WHERE id_clinica=1 AND id_usuario=2");
        $this->rechaza(fn () => $m->resolver($p['id_propuesta'], true), AccesoDenegado::class);
    }

    public function testMarcasSnapshotsIdempotenciaYClaveConCargaDistinta(): void
    {
        $this->como(1,2,Roles::VETERINARIO);
        $id = $this->cita();
        $antes = $this->db->query('SELECT * FROM citas')->fetch(PDO::FETCH_ASSOC);
        $this->como(1,1,Roles::ADMIN);
        $m = new CoordinacionHorarios($this->db);
        $r = $m->guardarVeterinario(2, $this->franjas(), str_repeat('c',64));
        $this->assertSame([$id], $r['afectadas']);
        $durable = json_decode($this->db->query("SELECT resultado FROM operaciones_agenda WHERE accion='guardar_horario_vet'")->fetchColumn(), true);
        $this->assertRespuestaIdempotenteIgual(['id_veterinario' => 2, 'afectadas' => [$id]], $durable);
        $this->assertRespuestaIdempotenteIgual($r, $m->guardarVeterinario(2, $this->franjas(), str_repeat('c',64)));
        $despues = $this->db->query('SELECT * FROM citas')->fetch(PDO::FETCH_ASSOC);
        foreach (['estado_reajuste','motivo_reajuste','version_agenda'] as $campo) {
            unset($antes[$campo],$despues[$campo]);
        }
        $this->assertSame($antes,$despues);
        $this->assertSame(1, (int) $m->leer()['afectadas'][0]['version_agenda']);
        $this->rechaza(fn () => $m->guardarVeterinario(2, [], str_repeat('c',64)));
    }

    public function testCierreDeUnLunesNoEsRecurrenteYBajaLogica(): void
    {
        $this->como(1,2,Roles::VETERINARIO);
        $id = $this->cita();
        $siguiente = $this->cita('2030-01-14');
        $this->como(1,1,Roles::ADMIN);
        $m = new CoordinacionHorarios($this->db);
        $apertura = (new HorarioClinica($this->db))->listar();
        $r = $m->cerrar('2030-01-07T09:00','2030-01-07T09:30','Mantenimiento');
        $this->assertSame([$id],$r['afectadas']);
        $this->assertSame($apertura,(new HorarioClinica($this->db))->listar());
        $this->assertSame('ninguno',$this->db->query('SELECT estado_reajuste FROM citas WHERE id_cita='.$siguiente)->fetchColumn());
        $m->retirarCierre($r['id_cierre']);
        $this->assertSame(0,(int) $m->leer()['cierres'][0]['activo']);
        $this->assertCount(1,$m->leer()['afectadas']);
    }

    public function testFalloAuditoriaRevierteHorarioMarcasOperacionYCierre(): void
    {
        $this->como(1,2,Roles::VETERINARIO);
        $this->cita();
        $propuesta = (new CoordinacionHorarios($this->db))->proponer(2,$this->franjas());
        $this->como(1,1,Roles::ADMIN);
        $m = new CoordinacionHorarios($this->db);
        $antes = $m->leer();
        $operaciones = (int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn();
        $this->db->exec("CREATE TRIGGER sin_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'auditoria C2 simulada'); END");
        foreach ([fn () => $m->guardarVeterinario(2,$this->franjas()), fn () => (new HorarioClinica($this->db))->restaurar(),
            fn () => $m->cerrar('2030-01-07T09:00','2030-01-07T10:00','Mantenimiento'),
            fn () => $m->resolver($propuesta['id_propuesta'],true), fn () => $m->resolver($propuesta['id_propuesta'],false,'Otro turno')] as $accion) {
            $this->rechaza($accion,RuntimeException::class);
            $this->assertSame($antes,$m->leer());
            $this->assertSame($operaciones,(int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        }
    }

    public function testCierreUsaExtremosSemiabiertosYNoMarcaOtraFechaDesconocida(): void
    {
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,fecha,hora,duracion_minutos,estado,motivo) VALUES (1,1,2,'2030-01-14','09:00',NULL,'confirmada','Dato histórico')");
        $m = new CoordinacionHorarios($this->db);
        $r = $m->cerrar('2030-01-07T09:00:00','2030-01-07T10:00:00','Límite exacto');
        $this->assertSame([],$r['afectadas']);
        $this->como(1,2,Roles::VETERINARIO);
        $this->assertGreaterThan(0,$this->cita('2030-01-07','08:20'));
        $this->assertGreaterThan(0,$this->cita('2030-01-07','10:00'));
        $error = $this->rechaza(fn () => $this->cita('2030-01-07','08:21'));
        $this->assertStringContainsString('cierre',$error->getMessage());
        $this->assertSame(0,(int)$this->db->query("SELECT COUNT(*) FROM citas WHERE estado_reajuste<>'ninguno'")->fetchColumn());
    }

    public function testReprogramacionRevierteTambienOperacionSiFallaAuditoria(): void
    {
        $this->como(1,2,Roles::VETERINARIO);
        $id = $this->cita();
        $m = new Cita($this->db);
        $antes = $m->getById($id);
        $operaciones = $this->db->query('SELECT * FROM operaciones_agenda')->fetchAll(PDO::FETCH_ASSOC);
        $this->db->exec("CREATE TRIGGER sin_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'auditoria C2 simulada'); END");
        $this->rechaza(fn () => $m->reprogramar($antes,2,'2030-01-07','11:00',str_repeat('f',64)),RuntimeException::class);
        $this->assertSame($antes,$m->getById($id));
        $this->assertSame($operaciones,$this->db->query('SELECT * FROM operaciones_agenda')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testReenvioReservaYReprogramacionNoDuplicaNiAdmiteCargaDistinta(): void
    {
        $this->como(1,2,Roles::VETERINARIO);
        $id = $this->cita('2030-01-07','09:00',str_repeat('d',64));
        $this->assertSame($id,$this->cita('2030-01-07','09:00',str_repeat('d',64)));
        $reordenada = ['clave_operacion'=>str_repeat('d',64),'motivo'=>'Control','hora'=>'09:00','fecha'=>'2030-01-07',
            'id_tipo_cita'=>1,'id_veterinario'=>2,'id_mascota'=>1];
        $this->assertSame($id,(new Cita($this->db))->registrar($reordenada));
        $this->rechaza(fn () => $this->cita('2030-01-07','10:00',str_repeat('d',64)));
        $m = new Cita($this->db);
        $cita = $m->getById($id);
        $m->reprogramar($cita,2,'2030-01-07','11:00',str_repeat('e',64));
        $m->reprogramar($cita,2,'2030-01-07','11:00',str_repeat('e',64));
        $this->assertSame('11:00:00',$m->getById($id)['hora']);
        $this->assertSame(1,(int)$m->getById($id)['version_agenda']);
        $this->assertSame(1,(int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    public function testNoSeMezclaTransaccionExteriorYControladorExigeCsrf(): void
    {
        $this->db->beginTransaction();
        $this->rechaza(fn () => (new CoordinacionHorarios($this->db))->guardarVeterinario(2,$this->franjas()),LogicException::class);
        $this->assertTrue($this->db->inTransaction());
        $this->db->rollBack();
        ob_start();
        try {
            $this->rechaza(fn () => (new CoordinacionHorariosController($this->db))->escribir('guardar'),AccesoDenegado::class);
            $_POST = ['csrf_token'=>Csrf::token(),'id_clinica'=>'2'];
            $this->rechaza(fn () => (new HorarioClinicaController($this->db))->restaurarPorDefectoAjax(),AccesoDenegado::class);
            $this->rechaza(fn () => (new CoordinacionHorariosController($this->db))->escribir('cerrar'),AccesoDenegado::class);
        } finally {
            ob_end_clean();
        }
    }
}
