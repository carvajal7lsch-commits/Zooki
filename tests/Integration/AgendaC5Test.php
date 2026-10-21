<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../models/Cita.php';

/** RE-4.14 y RE-4.5: hechos, proyección, aceptación y comunicaciones separadas. */
final class AgendaC5Test extends TestCase
{
    private PDO $db;
    private string $ahora = '2030-01-07 09:30:00';
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

    public function testTresCitasCascadaAbsorbeHuecoConservaPactadasYEsIdempotente(): void
    {
        $a = $this->cita(1,'09:00:00','en_curso');
        $b = $this->cita(2,'09:40:00');
        $c = $this->cita(3,'11:00:00');
        $this->db->exec("UPDATE citas SET hora_inicio_real='2030-01-07 09:20:00' WHERE id_cita=$a");
        $this->agenda->procesar('2030-01-07');
        $this->assertSame('2030-01-07 10:00:00',$this->fila($b)['inicio_estimado']);
        $this->assertSame('2030-01-07 11:00:00',$this->fila($c)['inicio_estimado']);
        $this->assertSame('09:40:00',$this->fila($b)['hora']);
        $this->assertSame('2030-01-07 09:20:00',$this->fila($a)['hora_inicio_real']);
        $this->assertCount(2,$this->agenda->grafo->secuencia);
        $antes = $this->db->query('SELECT * FROM citas')->fetchAll(PDO::FETCH_ASSOC);
        $auditoria = $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        $this->agenda->procesar('2030-01-07');
        $this->assertSame($antes,$this->db->query('SELECT * FROM citas')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame($auditoria,$this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
    }

    private function propuesta(): array
    {
        $id = $this->cita(1,'11:30:00');
        $this->ahora = '2030-01-07 11:20:00';
        $this->db->exec("UPDATE horarios_veterinario SET hora_fin='11:45:00' WHERE id_veterinario=2 AND hora_inicio='08:00:00'");
        $this->db->exec("UPDATE citas SET estado_reajuste='pendiente_calculo' WHERE id_cita=$id");
        $this->agenda->procesar('2030-01-07');
        $r = $this->db->query("SELECT * FROM reasignaciones WHERE estado='pendiente'")->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($r);
        $this->assertSame(5,(int)$r['id_veterinario_destino']);
        return $r;
    }

    public function testPropuestaNoApartaEspacioAceptacionReplayYSnapshots(): void
    {
        $r = $this->propuesta();
        $id = (int)$r['id_cita'];
        $this->assertSame(2,(int)$this->fila($id)['id_veterinario']);
        $this->como(5);
        $this->assertCount(1,$this->agenda->listar());
        $respuesta = $this->agenda->responder((int)$r['id_reasignacion'],'aceptar',str_repeat('a',64));
        $this->assertSame($respuesta,$this->agenda->responder((int)$r['id_reasignacion'],'aceptar',str_repeat('a',64)));
        $this->assertSame(5,(int)$this->fila($id)['id_veterinario']);
        $this->assertSame('11:30:00',$this->fila($id)['hora']);
        $this->assertSame('30',(string)$this->fila($id)['duracion_minutos']);
        $this->assertSame('10',(string)$this->fila($id)['margen_minutos']);
        $this->assertSame('aceptada',$this->db->query('SELECT estado FROM reasignaciones')->fetchColumn());
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='CITA_REASIGNADA' AND canal='correo'")->fetchColumn());
    }

    public static function decisiones(): array
    {
        return ['rechazo'=>['rechazar','11:20:00','rechazada'],'límite exacto'=>['aceptar','11:30:00','vencida'],'hueco ocupado'=>['ocupar','11:20:00','vencida']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('decisiones')]
    public function testRechazoVencimientoYHuecoPerdidoConservanOriginal(string $accion, string $hora, string $estado): void
    {
        $r = $this->propuesta();
        $this->ahora = '2030-01-07 '.$hora;
        if ($accion === 'ocupar') {
            $this->cita(2,'11:30:00','confirmada',5);
            $accion = 'aceptar';
        }
        $this->como(5);
        $this->agenda->responder((int)$r['id_reasignacion'],$accion,str_repeat('b',64));
        $this->assertSame($estado,$this->db->query('SELECT estado FROM reasignaciones WHERE id_reasignacion='.$r['id_reasignacion'])->fetchColumn());
        $this->assertSame(2,(int)$this->fila((int)$r['id_cita'])['id_veterinario']);
        // Si la tarde ofrece otro candidato, se conserva el mismo ciclo sin repetir destino.
        $this->assertSame('pendiente_reprogramacion',$this->fila((int)$r['id_cita'])['estado_reajuste']);
    }

    public function testAislamientoAdminYOtroVetNoAceptan(): void
    {
        $r = $this->propuesta();
        foreach ([[1,1,Roles::ADMIN],[2,1,Roles::VETERINARIO],[4,2,Roles::VETERINARIO]] as [$actor,$clinica,$rol]) {
            $this->como($actor,$clinica,$rol);
            try {
                $this->agenda->responder((int)$r['id_reasignacion'],'aceptar',str_repeat('c',64));
                $this->fail('Solo destino y clínica propios.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403,$e->codigo());
            }
        }
        $this->assertSame('pendiente',$this->db->query('SELECT estado FROM reasignaciones')->fetchColumn());
    }

    public function testDiezRecalculosTresMinutosCoalescenYVentanaSoloCambiaAlEntregar(): void
    {
        $a = $this->cita(1,'09:00:00','en_curso');
        $b = $this->cita(2,'09:40:00');
        $this->db->exec("UPDATE citas SET hora_inicio_real='2030-01-07 09:00:00' WHERE id_cita=$a");
        $enviados = 0;
        for ($i=1;$i<=10;$i++) {
            $this->ahora = PoliticaTemporalAgenda::sello(IntervalosAgenda::fecha('2030-01-07 09:40:00')->getTimestamp()+$i*180);
            $this->agenda->procesar('2030-01-07');
            foreach ($this->db->query("SELECT id_evento FROM eventos_agenda WHERE tipo='ESTIMACION_CITA' AND estado='pendiente'")->fetchAll(PDO::FETCH_COLUMN) as $evento) {
                $this->assertNull($this->fila($b)['ultimo_aviso_estimacion']);
                $servicio = new EventoAgenda($this->db,1,fn () => IntervalosAgenda::fecha($this->ahora));
                $enviados += (int)$servicio->despachar((int)$evento,static fn () => true);
            }
        }
        $this->assertSame(1,$enviados);
        $this->assertSame('2030-01-07 10:10:00',$this->fila($b)['inicio_estimado']);
        $this->assertSame('09:40:00',$this->fila($b)['hora']);
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
    }

    public function testAuditoriaFallidaRevierteEstimacionesPropuestaYEventos(): void
    {
        $id = $this->cita(1,'11:30:00');
        $antes = $this->fila($id);
        $this->db->exec("CREATE TRIGGER fallo_c5 BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'Fallo C5'); END");
        try {
            $this->agenda->procesar('2030-01-07');
            $this->fail('Auditoría obligatoria.');
        } catch (RuntimeException $e) {
            $this->assertSame($antes,$this->fila($id));
            $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM reasignaciones')->fetchColumn());
            $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM eventos_agenda')->fetchColumn());
        }
    }

    public function testRechazoBuscaSiguienteCandidatoSinAsignarloAutomaticamente(): void
    {
        $r = $this->propuesta();
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario,id_clinica,id_rol,estado) VALUES (4,1,2,'activo')");
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin) VALUES (1,4,1,'14:00:00','18:00:00')");
        $this->como(5);
        $this->agenda->responder((int)$r['id_reasignacion'],'rechazar',str_repeat('d',64));
        $siguiente = $this->db->query("SELECT * FROM reasignaciones WHERE estado='pendiente'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(4,(int)$siguiente['id_veterinario_destino']);
        $this->assertSame($r['ciclo'],$siguiente['ciclo']);
        $this->assertSame(2,(int)$this->fila((int)$r['id_cita'])['id_veterinario']);
    }

    public function testEventoPendienteCoalesceSinConsumirVentanaYElWorkerNoNecesitaCalendario(): void
    {
        $a = $this->cita(1,'09:00:00','en_curso');
        $b = $this->cita(2,'09:40:00');
        $this->db->exec("UPDATE citas SET hora_inicio_real='2030-01-07 09:00:00' WHERE id_cita=$a");
        foreach (['10:00:00','10:03:00','10:06:00'] as $hora) {
            $this->ahora = '2030-01-07 '.$hora;
            CascadaAgenda::procesarPendientes($this->db,fn () => IntervalosAgenda::fecha($this->ahora));
        }
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
        $e = $this->db->query("SELECT * FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('2030-01-07 10:06:00',$e['hora_a_comunicar']);
        $this->assertNull($this->fila($b)['ultimo_aviso_estimacion']);
        $this->assertTrue((new EventoAgenda($this->db,1,fn () => IntervalosAgenda::fecha($this->ahora)))->despachar((int)$e['id_evento'],static fn () => true));
        $this->assertSame('2030-01-07 10:06:00',$this->fila($b)['ultima_hora_comunicada']);
        $this->assertSame('2030-01-07 10:06:00',$this->fila($b)['ultimo_aviso_estimacion']);
        $this->ahora = '2030-01-07 10:35:59';
        $this->agenda->procesar('2030-01-07');
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
        $this->ahora = '2030-01-07 10:36:00';
        $this->agenda->procesar('2030-01-07');
        $this->assertSame(2,(int)$this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
    }

    public function testRepetirAceptacionExigeAccesoVigenteYRechazaCargaAdulterada(): void
    {
        $r = $this->propuesta();
        $this->como(5);
        $clave = str_repeat('e',64);
        $this->agenda->responder((int)$r['id_reasignacion'],'aceptar',$clave);
        try {
            $this->agenda->responder((int)$r['id_reasignacion'],'rechazar',$clave);
            $this->fail('La misma clave no acepta otra intención.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('otro comando',$e->getMessage());
        }
        $this->db->exec('UPDATE usuarios SET estado=0 WHERE id_usuario=5');
        $antes = (int)$this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        try {
            $this->agenda->responder((int)$r['id_reasignacion'],'aceptar',$clave);
            $this->fail('Acceso revocado también impide replay.');
        } catch (AccesoDenegado $e) {
            $this->assertSame($antes+1,(int)$this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        }
    }

    public function testCierreConConsultaYMarcaSeReviertenSiFallaSuAuditoria(): void
    {
        require_once __DIR__ . '/../../models/Consulta.php';
        $id = $this->cita(1,'09:00:00','en_curso');
        $antes = $this->fila($id);
        $this->db->exec("CREATE TRIGGER fallo_cierre_c5 BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='citas' BEGIN SELECT RAISE(ABORT,'Fallo cierre C5'); END");
        try {
            (new Consulta($this->db))->registrar(['id_cita'=>$id,'id_mascota'=>1,'peso'=>'8.5','motivo'=>'Control','diagnostico'=>'Sano'],[],[],static fn () => []);
            $this->fail('Acto y marca exigen auditoría en la misma transacción.');
        } catch (RuntimeException $e) {
            $this->assertSame($antes,$this->fila($id));
            $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
            $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        }
    }
    /** C5.1 (RN-428): durante un exceso en curso la propuesta no se reemite en cada recálculo y se acepta tarde corriendo el inicio. */
    public function testPropuestaSobreviveRecalculosDelExcesoYSeAceptaTarde(): void
    {
        $a = $this->cita(1,'09:00:00','en_curso');
        $b = $this->cita(2,'09:40:00');
        $this->db->exec("UPDATE citas SET hora_inicio_real='2030-01-07 09:00:00' WHERE id_cita=$a");
        $this->db->exec("UPDATE horarios_veterinario SET hora_fin='10:15:00' WHERE id_veterinario=2 AND hora_inicio='08:00:00'");
        $this->ahora = '2030-01-07 09:50:00';
        $this->agenda->procesar('2030-01-07');
        $propuesta = (int)$this->db->query("SELECT id_reasignacion FROM reasignaciones WHERE estado='pendiente'")->fetchColumn();
        $this->assertGreaterThan(0,$propuesta);
        foreach (['09:51:00','09:52:00','09:53:00'] as $hora) {
            $this->ahora = '2030-01-07 '.$hora;
            $this->agenda->procesar('2030-01-07');
        }
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM reasignaciones')->fetchColumn());
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo LIKE 'PROPUESTA_REASIGNACION_%'")->fetchColumn());
        $this->como(5);
        $this->agenda->responder($propuesta,'aceptar',hash('sha256','tarde'));
        $this->assertSame(5,(int)$this->fila($b)['id_veterinario']);
        $this->assertSame('2030-01-07 09:53:00',$this->fila($b)['inicio_estimado']);
        $this->assertSame('09:40:00',$this->fila($b)['hora']);
    }
}
