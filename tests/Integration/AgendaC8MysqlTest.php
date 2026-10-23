<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/LlegadaAgenda.php';

/** RE-2.2.4/5: dos llegadas sin reserva compiten por el último hueco en procesos reales; una cita, la otra sigue en espera. */
final class AgendaC8MysqlTest extends TestCase
{
    private PDO $db;
    private string $motor;

    protected function setUp(): void
    {
        if (!getenv('ZOOKI_TEST_MYSQL_HOST')) {
            $this->markTestSkipped('Sin motor configurado para C8.');
        }
        if (getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
            throw new RuntimeException('C8 solo admite la base explícita zooki_test_base_v2.');
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
        // Un solo hueco de 30+10 el lunes 2030-01-07: 08:00–08:40.
        $this->db->exec("UPDATE horarios_veterinario SET hora_fin='08:40:00' WHERE id_veterinario=2 AND id_clinica=1 AND hora_inicio='08:00:00'");
        $this->db->exec("UPDATE horarios_veterinario SET activo=0 WHERE id_veterinario=2 AND id_clinica=1 AND hora_inicio='14:00:00'");
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

    /** Verde evaluado y presentado como llegada sin reserva de hoy. */
    private function llegada(int $mascota): array
    {
        $reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 07:05:00');
        $r = (new SolicitudAgenda($this->db, $reloj))->evaluar(['id_mascota' => $mascota, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07',
            'sintomas' => [['codigo' => 'sintoma.control_preventivo', 'tiempo' => 'no_aplica']]]);
        (new LlegadaAgenda($this->db, $reloj))->presentar((int) $r['id_solicitud'], hash('sha256', 'llegada' . $mascota));
        return ['id_solicitud' => $r['id_solicitud'], 'id_mascota' => $mascota];
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
        $proceso = proc_open([PHP_BINARY, __DIR__ . '/../Support/ProcesoAgendaC8.php', $carpeta, $nombre],
            [0 => ['pipe', 'r'], 1 => ['file', $carpeta . '/' . $nombre . '.log', 'a'], 2 => ['file', $carpeta . '/' . $nombre . '.log', 'a']], $tuberias);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo abrir el escritor real.');
        }
        fclose($tuberias[0]);
        return $proceso;
    }

    public function testDosLlegadasPorElUltimoHuecoSoloUnaObtieneCita(): void
    {
        $a = $this->llegada(2);
        $b = $this->llegada(3) + ['entrada_controlada' => true];
        $carpeta = sys_get_temp_dir() . '/zooki_c8_' . bin2hex(random_bytes(8));
        mkdir($carpeta);
        $procesos = [];
        try {
            $procesos[] = $this->lanzar($carpeta, 'primero', $a);
            $this->esperarArchivo($carpeta . '/bloqueado');
            $procesos[] = $this->lanzar($carpeta, 'segundo', $b);
            $this->esperarArchivo($carpeta . '/segundo.listo');
            $primeroId = (int) file_get_contents($carpeta . '/primero.listo');
            $segundoId = (int) file_get_contents($carpeta . '/segundo.listo');
            file_put_contents($carpeta . '/entrar_segundo', '1');
            $this->assertTrue($this->esperaReal($segundoId, $primeroId), 'La segunda llegada no espera el candado de la primera.');
            $this->assertFileDoesNotExist($carpeta . '/segundo.fin');
            file_put_contents($carpeta . '/liberar', '1');
            $this->esperarArchivo($carpeta . '/primero.fin');
            $this->esperarArchivo($carpeta . '/segundo.fin');
            $primero = json_decode(file_get_contents($carpeta . '/primero.fin'), true, 512, JSON_THROW_ON_ERROR);
            $segundo = json_decode(file_get_contents($carpeta . '/segundo.fin'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue($primero['ok'], json_encode($primero));
            $this->assertArrayHasKey('id_cita', $primero['resultado']);
            $this->assertFalse($segundo['ok'], json_encode($segundo));
            $this->assertTrue(is_a($segundo['clase'], InvalidArgumentException::class, true), json_encode($segundo));
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM citas WHERE origen='llegada_sin_reserva'")->fetchColumn());
            $this->assertSame('espera_presencial', $this->db->query('SELECT estado FROM solicitudes_agenda WHERE id_solicitud=' . (int) $b['id_solicitud'])->fetchColumn());
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas WHERE es_sobrecupo=1')->fetchColumn());
        } finally {
            file_put_contents($carpeta . '/entrar_segundo', '1');
            file_put_contents($carpeta . '/liberar', '1');
            foreach ($procesos as $proceso) {
                proc_close($proceso);
            }
            foreach (glob($carpeta . '/*') as $archivo) {
                unlink($archivo);
            }
            rmdir($carpeta);
        }
    }
}
