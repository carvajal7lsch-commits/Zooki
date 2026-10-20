<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ComparaRespuestaIdempotente.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/HorarioClinica.php';

/** C2/P04: dos escritores reales, barreras observadas y lecturas tras la espera. */
final class AgendaC2MysqlTest extends TestCase
{
    use ComparaRespuestaIdempotente;

    private PDO $db;
    private string $motor;

    protected function setUp(): void
    {
        if (!getenv('ZOOKI_TEST_MYSQL_HOST')) {
            $this->markTestSkipped('Sin motor configurado para C2.');
        }
        if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
            throw new RuntimeException('C2 solo admite la base explícita zooki_test_base_v2.');
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

    public function testAtomicidadConAuditoriaFallidaYClaveDurableEnElEsquemaReal(): void
    {
        $m = new CoordinacionHorarios($this->db);
        $antes = $m->leer(2);
        $this->db->exec("CREATE TRIGGER sin_auditoria_c2 BEFORE INSERT ON auditoria_sistema FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria C2 rechazada'");
        try {
            $m->guardarVeterinario(2,[['dia_semana'=>1,'hora_inicio'=>'10:00','hora_fin'=>'12:00']],str_repeat('a',64));
            $this->fail('Se confirmó sin auditoría.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('auditar',$error->getMessage());
        }
        $this->assertSame($antes,$m->leer(2));
        $this->assertSame(0,(int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        $this->db->exec('DROP TRIGGER sin_auditoria_c2');
        $r = $m->guardarVeterinario(2,[['dia_semana'=>1,'hora_inicio'=>'10:00','hora_fin'=>'12:00']],str_repeat('a',64));
        $this->assertRespuestaIdempotenteIgual($r,$m->guardarVeterinario(2,[['dia_semana'=>1,'hora_inicio'=>'10:00','hora_fin'=>'12:00']],str_repeat('a',64)));
        $this->assertSame(1,(int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        $cierre = $m->cerrar('2030-01-07T10:00','2030-01-07T11:00','Cierre real',str_repeat('b',64));
        $retirado = $m->retirarCierre($cierre['id_cierre'],str_repeat('c',64));
        $this->assertSame(0,$retirado['activo']);
        $this->assertRespuestaIdempotenteIgual($retirado,$m->retirarCierre($cierre['id_cierre'],str_repeat('c',64)));
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM cierres_clinica')->fetchColumn());
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
        $proceso = proc_open([PHP_BINARY,__DIR__.'/../Support/ProcesoAgendaC2.php',$carpeta,$nombre],
            [0=>['pipe','r'],1=>['file',$carpeta.'/'.$nombre.'.log','a'],2=>['file',$carpeta.'/'.$nombre.'.log','a']],$tuberias);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo abrir el escritor real.');
        }
        fclose($tuberias[0]);
        return $proceso;
    }

    private function prepararCarrera(string $accion): int
    {
        $this->db->exec('DELETE FROM operaciones_agenda');
        $this->db->exec('DELETE FROM propuestas_horario');
        $this->db->exec('DELETE FROM cierres_clinica');
        $this->db->exec('DELETE FROM citas');
        $this->db->exec('DELETE FROM horarios_veterinario');
        $this->db->exec("UPDATE horarios_clinica SET activo=1,bloque_morning_activo=1,bloque_afternoon_activo=1,bloque_morning_inicio='08:00:00',bloque_morning_fin='12:00:00',bloque_afternoon_inicio='14:00:00',bloque_afternoon_fin='18:00:00'");
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin) VALUES (1,2,1,'08:00:00','12:00:00')");
        if ($accion==='restaurar') {
            $this->db->exec("UPDATE horarios_clinica SET bloque_morning_inicio='06:00:00',bloque_morning_fin='08:00:00',bloque_afternoon_activo=0 WHERE id_clinica=1 AND dia_semana=1");
            $this->db->exec("UPDATE horarios_veterinario SET hora_inicio='06:00:00',hora_fin='08:00:00'");
        }
        if ($accion==='aprobar') {
            $_SESSION = ['id_usuario'=>2];
            Contexto::activar(Contexto::deClinica(1,'Norte',Roles::VETERINARIO),1);
            $p = (new CoordinacionHorarios($this->db))->proponer(2,[['dia_semana'=>1,'hora_inicio'=>'10:00','hora_fin'=>'12:00']]);
            $this->comoAdmin();
            return $p['id_propuesta'];
        }
        return 0;
    }

    public function testDosEscritoresConBarrerasEnAmbosOrdenesYRelecturaActual(): void
    {
        foreach (['vet','clinica','restaurar','aprobar','cerrar'] as $accion) {
            foreach ([true,false] as $configuracionPrimero) {
                $idPropuesta = $this->prepararCarrera($accion);
                $this->assertFalse($this->db->inTransaction(), 'La preparación debe estar confirmada antes de abrir escritores.');
                $carpeta = sys_get_temp_dir().'/zooki_c2_'.bin2hex(random_bytes(8));
                mkdir($carpeta);
                $procesos = [];
                try {
                    $base = ['caso'=>$accion.($configuracionPrimero?'_c':'_r'),'hora'=>$accion==='restaurar'?'06:30':'09:00','id_propuesta'=>$idPropuesta];
                    $procesos[] = $this->lanzar($carpeta,'primero',$base+['accion'=>$configuracionPrimero?$accion:'reservar']);
                    $this->esperarArchivo($carpeta.'/bloqueado');
                    $procesos[] = $this->lanzar($carpeta,'segundo',$base+['accion'=>$configuracionPrimero?'reservar':$accion,'entrada_controlada'=>true]);
                    $this->esperarArchivo($carpeta.'/segundo.listo');
                    $primeroId = (int) file_get_contents($carpeta.'/primero.listo');
                    $segundoId = (int) file_get_contents($carpeta.'/segundo.listo');
                    $observadorId = (int) $this->db->query('SELECT CONNECTION_ID()')->fetchColumn();
                    $this->assertGreaterThan(0, $primeroId);
                    $this->assertGreaterThan(0, $segundoId);
                    $this->assertCount(3, array_unique([$primeroId, $segundoId, $observadorId]));
                    $retenido = json_decode(file_get_contents($carpeta.'/bloqueado'), true, 512, JSON_THROW_ON_ERROR);
                    $this->assertSame('zooki_test_base_v2', $retenido['base']);
                    $this->assertTrue($retenido['transaccion']);
                    $this->assertSame(1, (int) $retenido['autocommit']);
                    // Reproducir metadata heredada sin espera antes de permitir el intento real.
                    $previa = $this->consultaEspera();
                    $previa->execute([$segundoId, $primeroId]);
                    $this->assertFalse($previa->fetchColumn());
                    $previa->closeCursor();
                    file_put_contents($carpeta.'/entrar_segundo', '1');
                    $esperando = $this->esperaReal($segundoId, $primeroId);
                    $diagnostico = [];
                    if (!$esperando) {
                        foreach (['bloqueado','primero.fin','segundo.fin','primero.log','segundo.log'] as $archivo) {
                            $diagnostico[$archivo] = is_file($carpeta.'/'.$archivo) ? file_get_contents($carpeta.'/'.$archivo) : 'sin resultado';
                        }
                        $q = $this->db->prepare('SELECT ID,DB,COMMAND,STATE,INFO FROM information_schema.PROCESSLIST WHERE ID IN (?,?) ORDER BY ID');
                        $q->execute([$primeroId, $segundoId]);
                        $diagnostico['conexiones_propias'] = $q->fetchAll(PDO::FETCH_ASSOC);
                        $diagnostico['observador'] = ['id'=>$observadorId, 'en_transaccion'=>$this->db->inTransaction()];
                    }
                    $this->assertTrue($esperando,'Espera real en '.$accion.' '.json_encode($diagnostico));
                    file_put_contents($carpeta.'/liberar','1');
                    $this->esperarArchivo($carpeta.'/primero.fin');
                    $this->esperarArchivo($carpeta.'/segundo.fin');
                    $primero = json_decode(file_get_contents($carpeta.'/primero.fin'),true);
                    $segundo = json_decode(file_get_contents($carpeta.'/segundo.fin'),true);
                    $this->assertTrue($primero['ok'],json_encode($primero));
                    $this->assertSame(!$configuracionPrimero,$segundo['ok'],json_encode($segundo));
                    $filas = $this->db->query('SELECT * FROM citas')->fetchAll(PDO::FETCH_ASSOC);
                    $this->assertCount($configuracionPrimero?0:1,$filas);
                    if (!$configuracionPrimero) {
                        $this->assertSame('pendiente_calculo',$filas[0]['estado_reajuste']);
                        $this->assertSame(1,(int)$filas[0]['version_agenda']);
                        $this->assertSame($base['hora'].':00',$filas[0]['hora']);
                        $this->assertSame(30,(int)$filas[0]['duracion_minutos']);
                        $this->assertSame(10,(int)$filas[0]['margen_minutos']);
                    }
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
}
