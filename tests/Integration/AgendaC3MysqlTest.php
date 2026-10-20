<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ComparaRespuestaIdempotente.php';
require_once __DIR__ . '/../../models/Cita.php';

/** RE-4.9.3: procesos independientes, mutex global incluso sin citas previas. */
final class AgendaC3MysqlTest extends TestCase
{
    use ComparaRespuestaIdempotente;
    private PDO $db;
    private string $motor;

    protected function setUp(): void
    {
        if (!getenv('ZOOKI_TEST_MYSQL_HOST')) {
            $this->markTestSkipped('Sin motor configurado para C3.');
        }
        if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
            throw new RuntimeException('C3 solo admite la base explícita zooki_test_base_v2.');
        }
        $this->db = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
            getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->motor = (string) $this->db->query('SELECT VERSION()')->fetchColumn();
        $this->db->exec('DROP DATABASE IF EXISTS zooki_test_base_v2');
        $this->db->exec('CREATE DATABASE zooki_test_base_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->db->exec('USE zooki_test_base_v2');
        foreach (['01_schema.sql','02_semilla.sql'] as $archivo) {
            $q = $this->db->query(file_get_contents(__DIR__ . '/../../database/' . $archivo));
            while ($q->nextRowset()) {
            }
            $q->closeCursor();
        }
        DosClinicas::poblar($this->db);
        DosClinicas::poblarAgenda($this->db);
        Auditoria::definirProxiesConfiables([]);
        $this->comoAdmin();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->exec('DROP DATABASE IF EXISTS zooki_test_base_v2');
        }
        $_SESSION = [];
    }

    private function comoAdmin(): void
    {
        $_SESSION = ['id_usuario'=>1];
        Contexto::activar(Contexto::deClinica(1,'Norte',Roles::ADMIN),1);
    }

    private function esperarArchivo(string $ruta): void
    {
        $limite = microtime(true)+20;
        while (!is_file($ruta)) {
            if (microtime(true)>$limite) {
                $this->fail('No se alcanzó la barrera: '.basename($ruta));
            }
            usleep(10000);
        }
    }

    private function consultaEspera(): PDOStatement
    {
        $sql = str_contains($this->motor,'MariaDB')
            ? 'SELECT 1 FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?'
            : 'SELECT 1 FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?';
        return $this->db->prepare($sql);
    }

    private function esperaReal(int $espera, int $bloquea): bool
    {
        $q = $this->consultaEspera();
        $limite = microtime(true)+10;
        do {
            $q->execute([$espera,$bloquea]);
            $hay = $q->fetchColumn() !== false;
            $q->closeCursor();
            if ($hay) return true;
            // MariaDB 10.4.32: trx0i_s.cc renueva la caché solo tras >100 ms sin lecturas.
            // Sondeo de metadata, nunca sustituto de la barrera ni evidencia de espera.
            usleep(str_contains($this->motor, 'MariaDB') ? 125000 : 20000);
        } while (microtime(true)<$limite);
        return false;
    }

    private function lanzar(string $carpeta, string $nombre, array $comando): mixed
    {
        file_put_contents($carpeta.'/'.$nombre.'.json',json_encode($comando,JSON_THROW_ON_ERROR));
        $proceso = proc_open([PHP_BINARY,__DIR__.'/../Support/ProcesoAgendaC3.php',$carpeta,$nombre],
            [0=>['pipe','r'],1=>['file',$carpeta.'/'.$nombre.'.log','a'],2=>['file',$carpeta.'/'.$nombre.'.log','a']],$tuberias);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo abrir el escritor real.');
        }
        fclose($tuberias[0]);
        return $proceso;
    }


    private function preparar(): void
    {
        $this->db->exec('DELETE FROM operaciones_agenda');
        $this->db->exec('DELETE FROM auditoria_sistema');
        $this->db->exec('DELETE FROM citas');
        $this->db->exec("INSERT IGNORE INTO usuario_clinica (id_usuario,id_clinica,id_rol,estado) VALUES (2,2,2,'activo')");
        $this->db->exec("INSERT IGNORE INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (1,2,'activo')");
        $this->db->exec("INSERT IGNORE INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,estado) VALUES (2,9,2,'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb','Kira',1)");
        $this->db->exec("INSERT IGNORE INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (2,2,'activo')");
        $this->db->exec('DELETE FROM horarios_veterinario WHERE id_clinica=2 AND id_veterinario=2');
        // Jornada histórica incompatible deliberada: el editor C2 no puede crearla.
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin) VALUES (2,2,1,'08:00:00','12:00:00')");
    }

    public function testMotorReservaReplayMargenYSnapshotSinOrdenarMapas(): void
    {
        $this->preparar();
        $citas = new Cita($this->db);
        $entrada = ['id_mascota'=>1,'id_veterinario'=>2,'id_tipo_cita'=>1,'fecha'=>'2030-01-07','hora'=>'11:20:00','motivo'=>'C3','clave_operacion'=>str_repeat('a',64)];
        $id = $citas->registrar($entrada);
        $this->assertSame($id, $citas->registrar($entrada));
        $this->assertSame('11:50:00', $citas->getById($id)['hora_fin']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $citas->reprogramar($citas->getById($id),2,'2030-01-07','10:00:07',str_repeat('b',64));
        $antes = $citas->getById($id);
        $citas->reprogramar($antes,2,'2030-01-07','10:00:07',str_repeat('b',64));
        $this->assertRespuestaIdempotenteIgual($antes,$citas->getById($id));
        $this->assertSame('10:30:07', $antes['hora_fin']);
        $this->assertSame(2,(int)$this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        $this->assertSame(2,(int)$this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='citas'")->fetchColumn());
    }

    public function testCarrerasGlobalesIndependenciaYReevaluacionTrasRollback(): void
    {
        foreach (['mismo_inicio','vet_solapado','mascota','reprogramar','reserva_reprogramar','independientes','rollback'] as $caso) {
            $this->preparar();
            $a = ['clinica'=>1,'actor'=>1,'vet'=>2,'mascota'=>1,'tipo'=>1,'hora'=>'09:00','accion'=>'reservar','caso'=>$caso];
            $b = ['clinica'=>2,'actor'=>3,'vet'=>2,'mascota'=>2,'tipo'=>2,'hora'=>'09:00','accion'=>'reservar','caso'=>$caso,'entrada_controlada'=>true];
            if ($caso==='vet_solapado') {
                $b['hora']='09:20';
            }
            if ($caso==='mascota') {
                $b['vet']=4;
                $b['mascota']=1;
                $b['hora']='09:20';
            }
            if ($caso==='independientes') {
                $b['vet']=4;
            }
            if (in_array($caso,['reprogramar','reserva_reprogramar'],true)) {
                $id = (new Cita($this->db))->registrar(['id_mascota'=>1,'id_veterinario'=>2,'id_tipo_cita'=>1,'fecha'=>'2030-01-07','hora'=>'11:00','motivo'=>'Original']);
                $a['accion']='reprogramar';
                $a['id_cita']=$id;
                if ($caso==='reserva_reprogramar') {
                    $anterior=$a;
                    $a=$b;
                    unset($a['entrada_controlada']);
                    $b=$anterior;
                    $b['entrada_controlada']=true;
                }
            }
            $a['rollback']=$caso==='rollback';
            $carpeta = sys_get_temp_dir().'/zooki_c3_'.bin2hex(random_bytes(8));
            mkdir($carpeta);
            $procesos=[];
            try {
                $procesos[]=$this->lanzar($carpeta,'primero',$a);
                $this->esperarArchivo($carpeta.'/bloqueado');
                $procesos[]=$this->lanzar($carpeta,'segundo',$b);
                $this->esperarArchivo($carpeta.'/segundo.listo');
                $primeroId=(int)file_get_contents($carpeta.'/primero.listo');
                $segundoId=(int)file_get_contents($carpeta.'/segundo.listo');
                $this->assertNotSame($primeroId,$segundoId);
                $this->assertNotSame($primeroId,(int)$this->db->query('SELECT CONNECTION_ID()')->fetchColumn());
                $this->assertFalse($this->db->inTransaction());
                $previa=$this->consultaEspera();
                $previa->execute([$segundoId,$primeroId]);
                $this->assertFalse($previa->fetchColumn());
                $previa->closeCursor();
                file_put_contents($carpeta.'/entrar_segundo','1');
                if ($caso==='independientes') {
                    $this->esperarArchivo($carpeta.'/segundo.fin');
                    $this->assertFileDoesNotExist($carpeta.'/primero.fin');
                } else {
                    $this->assertTrue($this->esperaReal($segundoId,$primeroId),'No observa espera real: '.$caso);
                    $this->assertFileDoesNotExist($carpeta.'/segundo.fin');
                }
                file_put_contents($carpeta.'/liberar','1');
                $this->esperarArchivo($carpeta.'/primero.fin');
                $this->esperarArchivo($carpeta.'/segundo.fin');
                $primero=json_decode(file_get_contents($carpeta.'/primero.fin'),true,512,JSON_THROW_ON_ERROR);
                $segundo=json_decode(file_get_contents($carpeta.'/segundo.fin'),true,512,JSON_THROW_ON_ERROR);
                $this->assertSame($caso!=='rollback',$primero['ok'],json_encode($primero));
                $this->assertSame(in_array($caso,['independientes','rollback'],true),$segundo['ok'],json_encode($segundo));
                if (!$segundo['ok']) {
                    $this->assertSame(HorarioOcupado::class,$segundo['clase']);
                    $this->assertSame('No hay disponibilidad para el intervalo completo. Elige otro horario.',$segundo['mensaje']);
                }
                $filas=$this->db->query('SELECT * FROM citas ORDER BY id_cita')->fetchAll(PDO::FETCH_ASSOC);
                $this->assertCount(in_array($caso,['independientes','reserva_reprogramar'],true)?2:1,$filas,$caso);
                $this->assertSame($caso==='rollback'?'2':'1',(string)$filas[0]['id_clinica']);
                $this->assertSame($caso==='reserva_reprogramar'?'11:00:00':'09:00:00',$filas[0]['hora']);
                $this->assertSame(in_array($caso,['independientes','reprogramar','reserva_reprogramar'],true)?2:1,(int)$this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
                $this->assertSame(in_array($caso,['independientes','reprogramar','reserva_reprogramar'],true)?2:1,(int)$this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='citas'")->fetchColumn());
            } finally {
                file_put_contents($carpeta.'/entrar_segundo','1');
                file_put_contents($carpeta.'/liberar','1');
                foreach ($procesos as $proceso) {
                    proc_close($proceso);
                }
                foreach (glob($carpeta.'/*') as $archivo) {
                    unlink($archivo);
                }
                rmdir($carpeta);
            }
        }
    }
}
