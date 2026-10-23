<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/AusenciaAgenda.php';

/** RE-4.14 y RE-4.5: hechos, proyección, aceptación y comunicaciones separadas. */
final class AgendaC6Test extends TestCase
{
    private PDO $db;
    private string $ahora = '2030-01-07 07:00:00';
    private CascadaAgenda $agenda;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        foreach ([2,3,4] as $id) {
            $this->db->prepare("INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,id_especie,estado) VALUES (?,6,1,?,?,1,1)")->execute([$id,str_pad((string)$id,43,'x'),'Mascota '.$id]);
            $this->db->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,1,'activo')")->execute([$id]);
        }
        $this->como(2);
        $this->agenda = new CascadaAgenda($this->db,fn () => IntervalosAgenda::fecha($this->ahora));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function como(int $id, int $clinica = 1, int $rol = Roles::VETERINARIO): void
    {
        $_SESSION = ['id_usuario'=>$id];
        Contexto::activar(Contexto::deClinica($clinica,'Prueba',$rol),1);
    }

    private function cita(int $pet, string $hora, string $estado = 'confirmada', int $vet = 2): int
    {
        $this->db->prepare("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,motivo,estado,duracion_minutos,margen_minutos,duracion_activa_objetivo_segundos,politica_temporal,origen) VALUES (1,?,?,1,'2030-01-07',?,?,'C5',?,30,10,1800,'M','reserva')")
            ->execute([$pet,$vet,$hora,date('H:i:s',strtotime($hora)+1800),$estado]);
        return (int)$this->db->lastInsertId();
    }

    private function fila(int $id): array
    {
        return $this->db->query('SELECT * FROM citas WHERE id_cita='.$id)->fetch(PDO::FETCH_ASSOC);
    }

    private function ausencias(): AusenciaAgenda
    {
        return new AusenciaAgenda($this->db, fn () => IntervalosAgenda::fecha($this->ahora));
    }

    private function entrada(string $inicio = '2030-01-07 09:00:00', string $fin = '2030-01-07 10:00:00'): array
    {
        return ['id_veterinario' => 2, 'inicio' => $inicio, 'fin' => $fin, 'motivo' => 'Ausencia de prueba', 'clave_operacion' => hash('sha256', $inicio . $fin)];
    }

    public function testSolicitudPropiaNoBloqueaAprobacionCubreSinVetoYReplay(): void
    {
        $id = $this->cita(1, '09:00:00');
        $entrada = $this->entrada();
        $solicitud = $this->ausencias()->registrar($entrada);
        $this->assertSame('solicitada', $solicitud['estado']);
        $this->assertSame(2, (int) $this->fila($id)['id_veterinario']);
        (new DisponibilidadAgenda($this->db, fn () => IntervalosAgenda::fecha($this->ahora)))->exigir(2, 2, '2030-01-07', '09:40:00', 30, 10);
        $this->como(1, 1, Roles::ADMIN);
        $resultado = $this->ausencias()->resolver($solicitud['id_ausencia'], true, '', str_repeat('a',64));
        $this->assertSame([ $id ], $resultado['afectadas']);
        $this->assertSame(5, (int) $this->fila($id)['id_veterinario']);
        $this->assertSame('aceptada', $this->db->query('SELECT estado FROM reasignaciones')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM reasignaciones WHERE estado='pendiente'")->fetchColumn());
        $this->assertSame('09:00:00', $this->fila($id)['hora']);
        $this->assertSame(30, (int) $this->fila($id)['duracion_minutos']);
        $this->assertSame(10, (int) $this->fila($id)['margen_minutos']);
        $auditoria = $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        $this->assertSame($resultado, $this->ausencias()->resolver($solicitud['id_ausencia'], true, '', str_repeat('a',64)));
        $this->assertSame($auditoria, $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE canal='correo' AND tipo='CITA_REASIGNADA'")->fetchColumn());
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE canal='interno' AND tipo='CITA_REASIGNADA'")->fetchColumn());
        $this->agenda->procesar('2030-01-07');
        $this->assertSame(5, (int) $this->fila($id)['id_veterinario']);
    }

    public function testAdminEfectivoExtremosExactosYDiaCompleto(): void
    {
        $antes = $this->cita(1, '08:20:00');
        $dentro = $this->cita(2, '09:00:00');
        $despues = $this->cita(3, '10:00:00');
        $this->como(1, 1, Roles::ADMIN);
        $r = $this->ausencias()->registrar($this->entrada());
        $this->assertSame('aprobada', $r['estado']);
        $this->assertSame([$dentro], $r['afectadas']);
        $this->assertSame(2, (int) $this->fila($antes)['id_veterinario']);
        $this->assertSame(2, (int) $this->fila($despues)['id_veterinario']);
        $this->ausencias()->registrar($this->entrada('2030-01-08 00:00:00', '2030-01-09 00:00:00'));
        $this->como(2);
        $disponibilidad = new DisponibilidadAgenda($this->db, fn () => IntervalosAgenda::fecha($this->ahora));
        $this->assertSame([], $disponibilidad->sugerir(2, 1, '2030-01-08',30,10));
        $this->assertNotEmpty($disponibilidad->sugerir(2, 1, '2030-01-09',30,10));
    }

    public function testSinCoberturaPermanecePendienteYNoReintroduceVeto(): void
    {
        $id = $this->cita(1, '09:00:00');
        $this->db->exec('DELETE FROM horarios_veterinario WHERE id_clinica=1 AND id_veterinario=5');
        $this->como(1, 1, Roles::ADMIN);
        $this->ausencias()->registrar($this->entrada());
        $this->assertSame('pendiente_reprogramacion', $this->fila($id)['estado_reajuste']);
        $this->agenda->procesar('2030-01-07');
        $this->assertSame('pendiente_reprogramacion', $this->fila($id)['estado_reajuste']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM reasignaciones')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='REPROGRAMAR_NECESARIO' AND canal='correo'")->fetchColumn());
    }

    public function testRechazoRequiereMotivoNoBloqueaYAislamiento(): void
    {
        $solicitud = $this->ausencias()->registrar($this->entrada());
        $this->como(1, 1, Roles::ADMIN);
        try {
            $this->ausencias()->resolver($solicitud['id_ausencia'], false, '', str_repeat('b',64));
            $this->fail('El rechazo exige motivo.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('motivo', $error->getMessage());
        }
        $this->ausencias()->resolver($solicitud['id_ausencia'], false, 'Sin aprobación', str_repeat('c',64));
        $this->como(3, 2, Roles::ADMIN);
        $this->assertSame([], $this->ausencias()->listar());
        try {
            $this->ausencias()->resolver($solicitud['id_ausencia'], true, '', str_repeat('d',64));
            $this->fail('No se accede a Norte desde Sur.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $this->como(2);
        $this->assertNotEmpty((new DisponibilidadAgenda($this->db, fn () => IntervalosAgenda::fecha($this->ahora)))->sugerir(2, 1, '2030-01-07',30,10));
        $entrada = $this->entrada();
        $entrada['id_veterinario'] = 5;
        $this->expectException(AccesoDenegado::class);
        $this->ausencias()->registrar($entrada);
    }

    public function testFalloAuditoriaRevierteAusenciaCoberturaEventosYOperacion(): void
    {
        $id = $this->cita(1, '09:00:00');
        $this->como(1, 1, Roles::ADMIN);
        $this->db->exec("CREATE TRIGGER fallo_cobertura BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='citas' BEGIN SELECT RAISE(ABORT, 'Auditoría bloqueada'); END");
        try {
            $this->ausencias()->registrar($this->entrada());
            $this->fail('Debe revertir el comando completo.');
        } catch (RuntimeException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        $this->assertSame(2, (int) $this->fila($id)['id_veterinario']);
        foreach (['ausencias_veterinario','reasignaciones','eventos_agenda','operaciones_agenda','auditoria_sistema'] as $tabla) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla)->fetchColumn(), $tabla);
        }
    }

    public function testRangoInvalidoNoSeGuarda(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ausencias()->registrar($this->entrada('2030-01-07 10:00:00','2030-01-07 10:00:00'));
    }
}
