<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/UrgenciaAgenda.php';
require_once __DIR__ . '/../../models/ConciliacionAgenda.php';
require_once __DIR__ . '/../../models/Consulta.php';

/** RE-4.14 y RE-4.5: hechos, proyección, aceptación y comunicaciones separadas. */
final class AgendaC7Test extends TestCase
{
    private PDO $db;
    private string $ahora = '2030-01-07 09:10:00';
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

    private function urgencias(): UrgenciaAgenda
    {
        return new UrgenciaAgenda($this->db,fn () => IntervalosAgenda::fecha($this->ahora));
    }

    private function rojo(int $pet = 1): int
    {
        return $this->urgencias()->ingresar(['id_mascota'=>$pet,'motivo'=>'Decisión clínica presencial','clave_operacion'=>hash('sha256','rojo'.$pet)])['id_solicitud'];
    }

    public function testLibrePresencialSinCuotaNiMoraYSinSobrecupo(): void
    {
        $this->db->exec('UPDATE suscripciones SET al_dia=0,id_plan=1 WHERE id_clinica=1');
        $this->db->exec('UPDATE planes SET limite_citas_mes=0,limite_mascotas=0 WHERE id_plan=1');
        $id = $this->rojo();
        $r = $this->urgencias()->atender($id,1,'',str_repeat('a',64));
        $c = $this->fila($r['id_cita']);
        $this->assertFalse($r['espera']);
        $this->assertSame('urgencia',$c['origen']);
        $this->assertSame('en_curso',$c['estado']);
        $this->assertSame('2030-01-07 09:10:00',$c['hora_inicio_real']);
        $this->assertSame(0,(int)$c['es_sobrecupo']);
        $this->assertSame($r,$this->urgencias()->atender($id,1,'',str_repeat('a',64)));
    }

    public function testPausaYConsultaReanudanConPoliticaMYNoCierranPausada(): void
    {
        $ordinaria = $this->cita(1,'09:00:00','en_curso');
        $siguiente = $this->cita(3,'09:40:00');
        $this->db->exec("UPDATE citas SET hora_inicio_real='2030-01-07 09:00:00' WHERE id_cita=$ordinaria");
        $rojo = $this->rojo(2);
        $r = $this->urgencias()->atender($rojo,1,'',str_repeat('b',64));
        $this->assertFalse($r['espera']);
        $this->assertSame('pausada',$this->fila($ordinaria)['estado']);
        $this->assertFalse((new Cita($this->db))->completarAtencion($this->fila($ordinaria),$this->ahora));
        $this->ahora = '2030-01-07 09:30:00';
        (new Consulta($this->db,fn () => IntervalosAgenda::fecha($this->ahora)))->registrar(['id_mascota'=>2,'id_cita'=>$r['id_cita'],'peso'=>'8.5','motivo'=>'Urgencia','diagnostico'=>'Atención documentada'],[],[],static fn () => []);
        $this->assertSame('en_curso',$this->fila($ordinaria)['estado']);
        $this->assertSame('2030-01-07 09:30:00',$this->db->query('SELECT fin FROM pausas_cita')->fetchColumn());
        $this->assertSame('2030-01-07 09:50:00',$this->fila($ordinaria)['fin_estimado']);
        $this->assertSame('2030-01-07 10:00:00',$this->fila($siguiente)['inicio_estimado']);
        $this->assertSame('09:00:00',$this->fila($ordinaria)['hora']);
        $this->assertSame('2030-01-07 09:00:00',$this->fila($ordinaria)['hora_inicio_real']);
    }

    public function testNoPausableEsperaYRemotoNoDesplaza(): void
    {
        $ordinaria = $this->cita(1,'09:00:00','en_curso');
        $this->db->exec("UPDATE citas SET hora_inicio_real='2030-01-07 09:00:00' WHERE id_cita=$ordinaria");
        $this->db->exec('UPDATE tipos_cita SET pausable=0 WHERE id_tipo_cita=1');
        $id = $this->rojo(2);
        $r = $this->urgencias()->atender($id,1,'',str_repeat('c',64));
        $this->assertTrue($r['espera']);
        $this->assertSame('en_curso',$this->fila($ordinaria)['estado']);
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM pausas_cita')->fetchColumn());
        $this->db->exec("UPDATE solicitudes_agenda SET fecha_llegada=NULL,estado='derivada' WHERE id_solicitud=$id");
        $this->expectException(InvalidArgumentException::class);
        $this->urgencias()->atender($id,1,'',str_repeat('d',64));
    }

    public function testOrdenPresencialReordenamientoMotivadoYNoPausaAnidada(): void
    {
        $a = $this->rojo(1);
        $b = $this->rojo(2);
        try {
            $this->urgencias()->atender($b,1,'',str_repeat('e',64));
            $this->fail('Debe respetar la llegada.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('otro rojo',$error->getMessage());
        }
        $this->urgencias()->reordenar($b,'Prioridad clínica documentada',str_repeat('f',64));
        $this->assertFalse($this->urgencias()->atender($b,1,'',str_repeat('1',64))['espera']);
        $this->assertFalse($this->urgencias()->atender($a,1,'',str_repeat('2',64))['espera']);
        $c = $this->rojo(3);
        try {
            $this->urgencias()->atender($c,1,'',str_repeat('3',64));
            $this->fail('Exige decisión ante otra pausa.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('decisión',$error->getMessage());
        }
        $this->assertTrue($this->urgencias()->atender($c,1,'Continuar atención actual; rojo espera',str_repeat('4',64))['espera']);
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM pausas_cita WHERE fin IS NULL')->fetchColumn());
    }

    private function provisional(): array
    {
        $s = $this->urgencias()->ingresar(['motivo'=>'Ingreso sin relación acreditada','nombre_acompanante'=>'Persona Acompañante','telefono_acompanante'=>'3001234567','clave_operacion'=>str_repeat('5',64)])['id_solicitud'];
        return $this->db->query('SELECT * FROM solicitudes_agenda WHERE id_solicitud='.$s)->fetch(PDO::FETCH_ASSOC);
    }

    public function testProvisionalSinCuentaPropietarioCarnetNiContactoEnBitacoras(): void
    {
        $usuarios = $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $s = $this->provisional();
        $p = $this->db->query('SELECT * FROM mascotas WHERE id_mascota='.$s['id_mascota'])->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($p['id_propietario']);
        $this->assertSame(0,(int)$p['carnet_activo']);
        $this->assertSame(1,(int)$p['ficha_por_completar']);
        $this->assertSame($usuarios,$this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $this->assertNotNull($s['id_ingreso']);
        foreach (['operaciones_agenda','auditoria_sistema','eventos_agenda'] as $tabla) {
            $json = json_encode($this->db->query('SELECT * FROM '.$tabla)->fetchAll(PDO::FETCH_ASSOC));
            $this->assertStringNotContainsString('3001234567',$json);
            $this->assertStringNotContainsString('Persona Acompa',$json);
        }
        $this->assertFalse($this->urgencias()->atender((int)$s['id_solicitud'],1,'',str_repeat('6',64))['espera']);
    }

    private function conciliar(array $s, array $extra = []): array
    {
        return (new ConciliacionAgenda($this->db,fn () => IntervalosAgenda::fecha($this->ahora)))->resolver((int)$s['id_ingreso'],$extra+['id_mascota_final'=>1,'id_propietario'=>6,'evidencia'=>'Cuenta activa y vínculo vigente del titular, ficha existente verificada','clave_operacion'=>str_repeat('7',64)]);
    }

    public function testConsolidarActualizaActivasYTerminalesConservaContextoYRequiereRevision(): void
    {
        $s = $this->provisional();
        $r = $this->urgencias()->atender((int)$s['id_solicitud'],1,'',str_repeat('8',64));
        $p = (int)$s['id_mascota'];
        $this->db->prepare("INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado,estado_revision,nivel_aprobado,contexto_triage,huella_entrada,huella_revision) VALUES (1,?,2,'llegada','finalizada','resuelta','rojo',?,'historica','histórica')")->execute([$p,json_encode(['evaluada'=>$p])]);
        $this->db->exec("UPDATE mascota_clinica SET numero_historia_clinica='HC-000001' WHERE id_mascota=$p AND id_clinica=1");
        $contexto = $s['contexto_triage'];
        $this->conciliar($s);
        $actual = $this->db->query('SELECT * FROM solicitudes_agenda WHERE id_solicitud='.$s['id_solicitud'])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1,(int)$actual['id_mascota']);
        $this->assertSame($contexto,$actual['contexto_triage']);
        $this->assertSame('pendiente',$actual['estado_revision']);
        $this->assertNull($actual['huella_revision']);
        $this->assertSame(1,(int)$this->fila($r['id_cita'])['id_mascota']);
        $terminal = $this->db->query("SELECT * FROM solicitudes_agenda WHERE estado='finalizada'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1,(int)$terminal['id_mascota']);
        $this->assertSame('resuelta',$terminal['estado_revision']);
        $this->assertSame('histórica',$terminal['huella_revision']);
        $this->assertSame('HC-000001',$this->db->query('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota=1 AND id_clinica=1')->fetchColumn());
        $this->assertSame($this->conciliar($s)['id_mascota'],1);
        $this->urgencias()->revisarContexto((int)$s['id_solicitud'],(int)$actual['version_solicitud'],'Contexto final revisado',str_repeat('9',64));
        $this->ahora = '2030-01-07 09:30:00';
        (new Consulta($this->db,fn () => IntervalosAgenda::fecha($this->ahora)))->registrar(['id_mascota'=>1,'id_cita'=>$r['id_cita'],'peso'=>'8.5','motivo'=>'Control','diagnostico'=>'Atendido'],[],[],static fn () => []);
        $ingreso = $this->db->query('SELECT * FROM ingresos_emergencia')->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($ingreso['nombre_acompanante']);
        $this->assertNull($ingreso['telefono_acompanante']);
        $this->assertNotNull($ingreso['contacto_anonimizado_en']);
    }

    public function testCompletarMismaFichaSinActivarCarnet(): void
    {
        $s = $this->provisional();
        $p = (int)$s['id_mascota'];
        $this->conciliar($s,['id_mascota_final'=>$p,'nombre'=>'Nueva mascota','especie'=>'1','raza'=>'49','sexo'=>'Hembra','peso'=>'8.5']);
        $m = $this->db->query('SELECT * FROM mascotas WHERE id_mascota='.$p)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(6,(int)$m['id_propietario']);
        $this->assertSame(0,(int)$m['ficha_por_completar']);
        $this->assertSame(0,(int)$m['carnet_activo']);
        $this->assertSame('completado',$this->db->query('SELECT estado FROM ingresos_emergencia')->fetchColumn());
    }

    public function testRollbackConsolidacionYBarreraDeEnvio(): void
    {
        $s = $this->provisional();
        $r = $this->urgencias()->atender((int)$s['id_solicitud'],1,'',str_repeat('0',64));
        $antes = $this->fila($r['id_cita']);
        $this->db->exec("CREATE TRIGGER fallo_conciliar BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='ingresos_emergencia' BEGIN SELECT RAISE(ABORT,'Auditoría fallida'); END");
        try {
            $this->conciliar($s);
            $this->fail('Debe revertir identidad y citas.');
        } catch (RuntimeException $error) {
            $this->assertSame($antes,$this->fila($r['id_cita']));
            $this->assertSame('pendiente',$this->db->query('SELECT estado FROM ingresos_emergencia')->fetchColumn());
        }
        $this->db->exec('DROP TRIGGER fallo_conciliar');
        $e = (new CoordinadorAgenda($this->db))->ejecutar(1,fn () => ['usuarios'=>[2,6],'mascotas'=>[(int)$s['id_mascota']]],fn () => (new EventoAgenda($this->db,1))->correo('NUEVA_CITA',(int)$r['id_cita'],0,6));
        $this->db->exec("UPDATE eventos_agenda SET estado='incierto' WHERE id_evento=$e");
        $this->expectException(InvalidArgumentException::class);
        $this->conciliar($s);
    }
    public function testPortalConocidoConservaIdentidadAdminSoloAcreditaPresenciaYAislamiento(): void
    {
        $this->db->prepare("INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado,estado_revision,nivel_calculado,contexto_triage,huella_entrada) VALUES (1,1,6,'portal','derivada','no_requerida','rojo',?,?)")->execute([json_encode(['entrada'=>['id_mascota'=>1],'propietario'=>6]),str_repeat('a',64)]);
        $id = (int)$this->db->lastInsertId();
        $this->como(1,1,Roles::ADMIN);
        $this->urgencias()->acreditarLlegada($id,str_repeat('a',64));
        $s = $this->db->query('SELECT * FROM solicitudes_agenda WHERE id_solicitud='.$id)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1,(int)$s['id_mascota']);
        $this->assertSame(6,(int)$s['id_actor']);
        $this->assertSame('portal',$s['origen']);
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        try {
            $this->urgencias()->atender($id,1,'',str_repeat('b',64));
            $this->fail('El administrador no atiende ni crea citas.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403,$error->codigo());
        }
        $this->como(4,2,Roles::VETERINARIO);
        $this->assertSame([],$this->urgencias()->listar());
        $this->expectException(AccesoDenegado::class);
        $this->urgencias()->acreditarLlegada($id,str_repeat('c',64));
    }

    public function testProvisionalNoAdmiteReservaOrdinariaNiTitularNoVerificado(): void
    {
        $s = $this->provisional();
        try {
            (new DisponibilidadAgenda($this->db,fn () => IntervalosAgenda::fecha($this->ahora)))->sugerir(2,(int)$s['id_mascota'],'2030-01-08',30,10);
            $this->fail('La provisional no es un acceso al agendamiento ordinario.');
        } catch (HorarioOcupado $error) {
            $this->assertStringContainsString('identidad',$error->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $this->conciliar($s,['id_propietario'=>9]);
    }

    public function testConsolidarConservaHcFinalActosAutoresYEpisodios(): void
    {
        $s = $this->provisional();
        $p = (int)$s['id_mascota'];
        $this->db->exec("UPDATE mascota_clinica SET numero_historia_clinica='HC-000010' WHERE id_clinica=1 AND id_mascota=1");
        $this->db->exec("UPDATE mascota_clinica SET numero_historia_clinica='HC-000020' WHERE id_clinica=1 AND id_mascota=$p");
        $this->db->prepare("INSERT INTO consultas (id_clinica,id_mascota,id_veterinario,fecha_hora,motivo_consulta,anamnesis,diagnostico,plan_tratamiento) VALUES (1,?,2,'2030-01-07 09:00:00','Ingreso','Hechos','Decisión histórica','Plan histórico')")->execute([$p]);
        $consulta = (int)$this->db->lastInsertId();
        $this->conciliar($s);
        $c = $this->db->query('SELECT * FROM consultas WHERE id_consulta='.$consulta)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1,(int)$c['id_mascota']);
        $this->assertSame(2,(int)$c['id_veterinario']);
        $this->assertSame(1,(int)$c['id_clinica']);
        $this->assertSame('Decisión histórica',$c['diagnostico']);
        $this->assertSame('HC-000010',$this->db->query('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota=1 AND id_clinica=1')->fetchColumn());
        $this->assertSame('HC-000020',$this->db->query('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota='.$p.' AND id_clinica=1')->fetchColumn());
    }

    public function testDatosFinalesCambiadosInvalidanRevisionYReplayNoAutorizaContextoNuevo(): void
    {
        $s = $this->provisional();
        $r = $this->urgencias()->atender((int)$s['id_solicitud'],1,'',str_repeat('d',64));
        $this->conciliar($s);
        $actual = $this->db->query('SELECT * FROM solicitudes_agenda WHERE id_solicitud='.$s['id_solicitud'])->fetch(PDO::FETCH_ASSOC);
        $id = (int)$s['id_solicitud'];
        $version = (int)$actual['version_solicitud'];
        $this->urgencias()->revisarContexto($id,$version,'Revisión del contexto final',str_repeat('e',64));
        $this->db->exec('UPDATE mascotas SET esterilizado=1 WHERE id_mascota=1');
        $this->urgencias()->revisarContexto($id,$version,'Revisión del contexto final',str_repeat('e',64));
        $this->assertFalse($this->urgencias()->listar()[0]['revision_vigente']);
        try {
            (new Consulta($this->db,fn () => IntervalosAgenda::fecha($this->ahora)))->registrar(['id_mascota'=>1,'id_cita'=>$r['id_cita'],'peso'=>'8.5','motivo'=>'Control','diagnostico'=>'Atendido'],[],[],static fn () => []);
            $this->fail('El replay de revisión no habilita el contexto cambiado.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('contexto vigente',$error->getMessage());
        }
        $versionActual = (int)$this->db->query('SELECT version_solicitud FROM solicitudes_agenda WHERE id_solicitud='.$id)->fetchColumn();
        $this->urgencias()->revisarContexto($id,$versionActual,'Revisión nueva con dato actual',str_repeat('f',64));
        $this->assertTrue($this->urgencias()->listar()[0]['revision_vigente']);
    }

}
