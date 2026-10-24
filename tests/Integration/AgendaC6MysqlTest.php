<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../models/AusenciaAgenda.php';

/** RE-4.14.4/6: aceptación frente a reserva y vencimiento, ambos órdenes y espera real. */
final class AgendaC6MysqlTest extends TestCase
{
    private PDO $db;
    private string $motor;

    protected function setUp(): void
    {
        if (!getenv('ZOOKI_TEST_MYSQL_HOST')) {
            $this->markTestSkipped('Sin motor configurado para C6.');
        }
        if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
            throw new RuntimeException('C6 solo admite la base explícita zooki_test_base_v2.');
        }
        $this->db = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
            getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->motor = (string) $this->db->query('SELECT VERSION()')->fetchColumn();
        $this->db->exec('DROP DATABASE IF EXISTS zooki_test_base_v2');
        $this->db->exec('CREATE DATABASE zooki_test_base_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->db->exec('USE zooki_test_base_v2');
        foreach (['01_schema.sql', '02_semilla.sql'] as $archivo) {
            $q = $this->db->query(file_get_contents(__DIR__ . '/../../database/' . $archivo));
            while ($q->nextRowset()) {
            }
            $q->closeCursor();
        }
        DosClinicas::poblar($this->db);
        DosClinicas::poblarAgenda($this->db);
        (new CargadorConocimiento($this->db))->cargar(__DIR__ . '/../../database/conocimiento');
        $this->db->exec("UPDATE mascotas SET id_especie=1,id_raza=49,fecha_nacimiento='2022-01-01',peso=8.50,sexo='Hembra' WHERE id_mascota=1");
        foreach ([2, 3] as $id) {
            $this->db->exec('CREATE TEMPORARY TABLE copia AS SELECT * FROM mascotas WHERE id_mascota=1');
            $this->db->prepare('UPDATE copia SET id_mascota=?,token_carnet=?,nombre=?')->execute([$id, str_repeat(chr(97 + $id), 43), 'Mascota ' . $id]);
            $this->db->exec('INSERT INTO mascotas SELECT * FROM copia');
            $this->db->exec('DROP TEMPORARY TABLE copia');
            $this->db->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,1,'activo')")->execute([$id]);
        }
        $this->db->exec('UPDATE clinicas SET tope_sobrecupos=1 WHERE id_clinica=1');
        Auditoria::definirProxiesConfiables([]);
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->exec('DROP DATABASE IF EXISTS zooki_test_base_v2');
        }
        $_SESSION = [];
    }

    private function esperarArchivo(string $ruta): void
    {
        $limite = microtime(true) + 20;
        while (!is_file($ruta)) {
            if (microtime(true) > $limite) {
                $this->fail('No se alcanzó la barrera: ' . basename($ruta));
            }
            usleep(10000);
        }
    }

    private function esperaReal(int $espera, int $bloquea): bool
    {
        $sql = str_contains($this->motor, 'MariaDB')
            ? 'SELECT 1 FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?'
            : 'SELECT 1 FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?';
        $q = $this->db->prepare($sql);
        $limite = microtime(true) + 10;
        do {
            $q->execute([$espera, $bloquea]);
            $hay = $q->fetchColumn() !== false;
            $q->closeCursor();
            if ($hay) {
                return true;
            }
            // MariaDB 10.4 renueva su caché de bloqueos solo tras >100 ms sin lecturas (ver C2.1).
            usleep(str_contains($this->motor, 'MariaDB') ? 125000 : 20000);
        } while (microtime(true) < $limite);
        return false;
    }

    private function lanzar(string $carpeta, string $nombre, array $comando): mixed
    {
        file_put_contents($carpeta . '/' . $nombre . '.json', json_encode($comando, JSON_THROW_ON_ERROR));
        $proceso = proc_open([PHP_BINARY, __DIR__ . '/../Support/ProcesoAgendaC6.php', $carpeta, $nombre],
            [0 => ['pipe', 'r'], 1 => ['file', $carpeta . '/' . $nombre . '.log', 'a'], 2 => ['file', $carpeta . '/' . $nombre . '.log', 'a']], $tuberias);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo abrir el escritor real.');
        }
        fclose($tuberias[0]);
        return $proceso;
    }


    public static function ordenes(): array
    {
        return ['ausencia antes de reserva' => ['aprobar','reservar'], 'reserva antes de ausencia' => ['reservar','aprobar']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ordenes')]
    public function testAusenciaYReservaReleenTrasEsperar(string $primera, string $segunda): void
    {
        $ausencia = (new AusenciaAgenda($this->db,static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00')))->registrar(['id_veterinario'=>2,'inicio'=>'2030-01-07 09:00:00','fin'=>'2030-01-07 10:00:00','motivo'=>'Prueba C6','clave_operacion'=>str_repeat('a',64)])['id_ausencia'];
        $carpeta = sys_get_temp_dir().'/zooki_c6_'.bin2hex(random_bytes(8));
        mkdir($carpeta);
        $procesos = [];
        try {
            $procesos[] = $this->lanzar($carpeta,'primero',['accion'=>$primera,'ausencia'=>$ausencia]);
            $this->esperarArchivo($carpeta.'/bloqueado');
            $procesos[] = $this->lanzar($carpeta,'segundo',['accion'=>$segunda,'ausencia'=>$ausencia,'entrada_controlada'=>true]);
            $this->esperarArchivo($carpeta.'/segundo.listo');
            $a = (int)file_get_contents($carpeta.'/primero.listo');
            $b = (int)file_get_contents($carpeta.'/segundo.listo');
            $this->assertNotSame($a,$b);
            file_put_contents($carpeta.'/entrar_segundo','1');
            $this->assertTrue($this->esperaReal($b,$a),'La ausencia/reserva debe esperar un candado real.');
            $this->assertFileDoesNotExist($carpeta.'/segundo.fin');
            file_put_contents($carpeta.'/liberar','1');
            foreach (['primero','segundo'] as $nombre) {
                $this->esperarArchivo($carpeta.'/'.$nombre.'.fin');
            }
            $a = json_decode(file_get_contents($carpeta.'/primero.fin'),true,512,JSON_THROW_ON_ERROR);
            $b = json_decode(file_get_contents($carpeta.'/segundo.fin'),true,512,JSON_THROW_ON_ERROR);
            $this->assertTrue($a['ok'],json_encode($a));
            $this->assertSame('aprobada',$this->db->query('SELECT estado FROM ausencias_veterinario WHERE id='.$ausencia)->fetchColumn());
            if ($primera === 'aprobar') {
                $this->assertFalse($b['ok'],json_encode($b));
                $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM citas WHERE id_mascota=2')->fetchColumn());
            } else {
                $this->assertTrue($b['ok'],json_encode($b));
                $this->assertSame(5,(int)$this->db->query('SELECT id_veterinario FROM citas WHERE id_mascota=2')->fetchColumn());
                $this->assertSame('09:00:00',$this->db->query('SELECT hora FROM citas WHERE id_mascota=2')->fetchColumn());
                $this->assertSame('aceptada',$this->db->query('SELECT estado FROM reasignaciones')->fetchColumn());
            }
            $this->assertSame(0,(int)$this->db->query("SELECT COUNT(*) FROM reasignaciones WHERE estado='pendiente'")->fetchColumn());
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
    private function sqlArchivo(string $archivo): void
    {
        $q = $this->db->query(file_get_contents(__DIR__.'/../../database/'.$archivo));
        while ($q->nextRowset()) {
        }
        $q->closeCursor();
    }

    public function testMigracion10AceptaPropioYAnteriorRepiteYRechazaPredicadoDistinto(): void
    {
        $expresion = static fn (PDO $db) => $db->query("SELECT GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='citas' AND COLUMN_NAME='ocupa_horario'")->fetchColumn();
        $actual = $expresion($this->db);
        fwrite(STDERR, "C7 metadata " . $this->motor . ': ' . $actual . PHP_EOL);
        $this->sqlArchivo('10_comienzos_urgencia.sql');
        $this->sqlArchivo('10_comienzos_urgencia.sql');
        $this->assertSame($actual,$expresion($this->db));
        $this->db->exec("ALTER TABLE citas MODIFY ocupa_horario TINYINT(1) GENERATED ALWAYS AS (CASE WHEN estado IN ('cancelada','no_asistio') OR es_sobrecupo=1 THEN NULL ELSE 1 END) STORED");
        fwrite(STDERR, 'C7 metadata anterior: ' . $expresion($this->db) . PHP_EOL);
        $this->sqlArchivo('10_comienzos_urgencia.sql');
        $this->sqlArchivo('10_comienzos_urgencia.sql');
        $this->assertSame($actual,$expresion($this->db));
        $this->db->exec("ALTER TABLE citas MODIFY ocupa_horario TINYINT(1) GENERATED ALWAYS AS (CASE WHEN estado IN ('cancelada','no_asistio') OR (es_sobrecupo=1 AND origen='urgencia') THEN NULL ELSE 1 END) STORED");
        try {
            $this->sqlArchivo('10_comienzos_urgencia.sql');
            $this->fail('No se admite una agrupación con AND distinta.');
        } catch (PDOException $error) {
            $this->assertStringContainsString('M4-10',$error->getMessage());
        }
        $this->db->exec("ALTER TABLE citas MODIFY ocupa_horario TINYINT(1) GENERATED ALWAYS AS (CASE WHEN estado IN ('cancelada','no_asistio') OR es_sobrecupo=1 OR origen='ur gen cia' THEN NULL ELSE 1 END) STORED");
        try {
            $this->sqlArchivo('10_comienzos_urgencia.sql');
            $this->fail('No se borran espacios dentro de literales distintos.');
        } catch (PDOException $error) {
            $this->assertStringContainsString('M4-10',$error->getMessage());
        }
    }

    public function testVolumenVacioMigradorDobleYPausaConComienzoIdentico(): void
    {
        $this->db->exec('DROP DATABASE zooki_test_base_v2');
        $this->db->exec('CREATE DATABASE zooki_test_base_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->db->exec('USE zooki_test_base_v2');
        $archivos = glob(__DIR__.'/../../database/[0-9][0-9]_*.sql');
        sort($archivos,SORT_STRING);
        foreach ($archivos as $archivo) {
            $this->sqlArchivo(basename($archivo));
        }
        $this->assertSame(59,(int)$this->db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn());
        require_once __DIR__.'/../../helpers/Migrador.php';
        $migrador = new Migrador($this->db,__DIR__.'/../../database');
        $migrador->migrar();
        $segunda = $migrador->migrar();
        $this->assertSame([], $segunda['ejecutadas']);
        DosClinicas::poblar($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->db->exec("INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,id_especie,estado) VALUES (2,6,1,REPEAT('b',43),'Rojo',1,1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (2,1,'activo')");
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,hora_inicio_real,motivo,estado,duracion_minutos,margen_minutos,duracion_activa_objetivo_segundos,politica_temporal,origen) VALUES (1,1,2,1,'2030-01-07','09:00:00','09:30:00','2030-01-07 09:00:00','C7','en_curso',30,10,1800,'M','reserva')");
        require_once __DIR__.'/../../models/UrgenciaAgenda.php';
        $urgencias = new UrgenciaAgenda($this->db,static fn () => IntervalosAgenda::fecha('2030-01-07 09:00:00'));
        $s = $urgencias->ingresar(['id_mascota'=>2,'motivo'=>'Rojo presencial','clave_operacion'=>str_repeat('c',64)]);
        $r = $urgencias->atender($s['id_solicitud'],1,'',str_repeat('d',64));
        $this->assertFalse($r['espera']);
        $this->assertSame(2,(int)$this->db->query("SELECT COUNT(*) FROM citas WHERE hora='09:00:00'")->fetchColumn());
        $this->assertSame('pausada',$this->db->query("SELECT estado FROM citas WHERE origen='reserva'")->fetchColumn());
        $this->assertSame(0,(int)$this->db->query("SELECT es_sobrecupo FROM citas WHERE origen='urgencia'")->fetchColumn());
    }

    public function testConciliacionNativaConRollbackReferenciasRevisionYContactos(): void
    {
        require_once __DIR__.'/../../models/UrgenciaAgenda.php';
        require_once __DIR__.'/../../models/ConciliacionAgenda.php';
        $reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 09:10:00');
        $u = new UrgenciaAgenda($this->db,$reloj);
        $id = $u->ingresar(['motivo'=>'Rojo sin identidad verificable','telefono_acompanante'=>'3001234567','clave_operacion'=>str_repeat('e',64)])['id_solicitud'];
        $s = $this->db->query('SELECT * FROM solicitudes_agenda WHERE id_solicitud='.$id)->fetch(PDO::FETCH_ASSOC);
        $cita = $u->atender($id,1,'',str_repeat('f',64))['id_cita'];
        $resolver = new ConciliacionAgenda($this->db,$reloj);
        $entrada = ['id_mascota_final'=>1,'id_propietario'=>6,'evidencia'=>'Cuenta y vínculo legítimos; ficha verificada','clave_operacion'=>str_repeat('a',64)];
        $this->db->exec("CREATE TRIGGER fallo_conciliacion_c7 BEFORE INSERT ON auditoria_sistema FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Prueba rollback C7'");
        try {
            $resolver->resolver((int)$s['id_ingreso'],$entrada);
            $this->fail('No se confirma una conciliación sin auditoría.');
        } catch (RuntimeException $error) {
            $this->assertSame((int)$s['id_mascota'],(int)$this->db->query('SELECT id_mascota FROM citas WHERE id_cita='.$cita)->fetchColumn());
            $this->assertSame('pendiente',$this->db->query('SELECT estado FROM ingresos_emergencia')->fetchColumn());
        } finally {
            $this->db->exec('DROP TRIGGER fallo_conciliacion_c7');
        }
        $r = $resolver->resolver((int)$s['id_ingreso'],$entrada);
        $this->assertSame(1,$r['id_mascota']);
        $this->assertSame(1,(int)$this->db->query('SELECT id_mascota FROM citas WHERE id_cita='.$cita)->fetchColumn());
        $this->assertSame('pendiente',$this->db->query('SELECT estado_revision FROM solicitudes_agenda WHERE id_solicitud='.$id)->fetchColumn());
        $this->assertSame($r,$resolver->resolver((int)$s['id_ingreso'],$entrada));
        $this->assertSame('3001234567',$this->db->query('SELECT telefono_acompanante FROM ingresos_emergencia')->fetchColumn());
        $version = (int)$this->db->query('SELECT version_solicitud FROM solicitudes_agenda WHERE id_solicitud='.$id)->fetchColumn();
        $u->revisarContexto($id,$version,'Revisión actual del paciente final',str_repeat('b',64));
        (new CoordinadorAgenda($this->db))->ejecutar(1,static fn () => ['usuarios'=>[2,6],'mascotas'=>[1]],function () use ($id,$cita,$resolver,$s): void {
            $this->db->exec("UPDATE solicitudes_agenda SET estado='finalizada' WHERE id_solicitud=$id");
            $this->db->exec("UPDATE citas SET estado='completada',hora_fin_real='2030-01-07 09:30:00' WHERE id_cita=$cita");
            $resolver->limpiarContacto((int)$s['id_ingreso']);
        });
        $this->assertNull($this->db->query('SELECT telefono_acompanante FROM ingresos_emergencia')->fetchColumn());
    }

}

