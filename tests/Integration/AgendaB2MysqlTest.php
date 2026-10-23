<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Migrador.php';

/** M4 B2: contrato B1/B1.1 en motores reales, nunca en la base de la aplicación. */
final class AgendaB2MysqlTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../..';
    private const BASE = 'zooki_test_base_v2';
    private PDO $db;

    protected function setUp(): void
    {
        if (!getenv('ZOOKI_TEST_MYSQL_HOST')) {
            $this->markTestSkipped('Sin MySQL/MariaDB de pruebas.');
        }
        $this->assertSame(self::BASE, getenv('ZOOKI_TEST_MYSQL_DB') ?: self::BASE);
        $this->db = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
            getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root',
            getenv('ZOOKI_TEST_MYSQL_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->reiniciar();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->exec('DROP DATABASE IF EXISTS ' . self::BASE);
        }
    }

    private function reiniciar(): void
    {
        $this->db->exec('DROP DATABASE IF EXISTS ' . self::BASE);
        $this->db->exec('CREATE DATABASE ' . self::BASE . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->db->exec('USE ' . self::BASE);
    }

    private function sql(string $texto): void
    {
        $consulta = $this->db->query($texto);
        while ($consulta->nextRowset()) {
        }
        $consulta->closeCursor();
    }

    private function cargar(string $ruta): void
    {
        $this->sql(file_get_contents(self::RAIZ . '/' . $ruta));
    }

    private function migrar(): void
    {
        $this->cargar('database/08_agenda_grafo_ii.sql');
        // C4: 09 completa el destino de M4 (origen interno); el legado también debe llegar ahí.
        $this->cargar('database/09_origen_interno_agenda.sql');
        $this->cargar('database/10_comienzos_urgencia.sql');
    }

    private function instalar(bool $anterior = false): void
    {
        $this->cargar($anterior ? 'tests/Fixtures/agenda_alpha4.sql' : 'database/01_schema.sql');
        $this->cargar('database/02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::poblarAgenda($this->db);
    }

    private function legado(): void
    {
        $this->instalar(true);
        $this->db->exec("UPDATE clinicas SET tope_sobrecupos=7, umbral_aviso_min=23, url_logo='propio.svg' WHERE id_clinica=1");
        $this->db->exec("UPDATE mascota_clinica SET numero_historia_clinica='HC-000731' WHERE id_clinica=1");
        $this->db->exec("UPDATE tipos_cita SET duracion_minutos=75, margen_minutos=17 WHERE id_tipo_cita=1");
        $this->db->exec("INSERT INTO citas (id_cita,id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,motivo,duracion_minutos,margen_minutos,estado,hora_inicio_real,hora_fin_real) VALUES
            (11,1,1,2,1,'2099-11-01','09:00','09:30','Snapshot propio',30,10,'confirmada',NULL,NULL),
            (12,1,1,2,NULL,'2099-11-01','10:00',NULL,'Desconocida',NULL,3,'pendiente',NULL,NULL),
            (13,1,1,2,1,'2026-10-01','11:00','11:30','Completada',30,10,'completada','2026-10-01 11:07','2026-10-01 11:43'),
            (14,1,1,2,1,'2099-11-01','12:00','12:30','Pausada sin segmentos',30,10,'pausada','2099-11-01 12:04',NULL)");
        $this->db->exec("INSERT INTO consultas (id_consulta,id_clinica,id_cita,id_mascota,id_veterinario,fecha_hora,motivo_consulta,anamnesis,diagnostico,plan_tratamiento) VALUES (61,1,13,1,2,'2026-10-01 11:07','Acto previo','Datos previos','Diagnóstico previo','Plan previo')");
        $this->db->exec("INSERT INTO ausencias_veterinario (id,id_veterinario,id_clinica,fecha_hora_inicio,fecha_hora_fin,motivo) VALUES (71,2,1,'2099-11-02 09:00','2099-11-02 10:00','Parcial previa')");
        $this->db->exec("INSERT INTO ingresos_emergencia (id_ingreso,id_clinica,id_mascota_provisional,nombre_acompanante,telefono_acompanante) VALUES (81,1,1,'Contacto de fixture','3001234567')");
        $this->db->exec("INSERT INTO reasignaciones (id_reasignacion,id_cita,id_veterinario_origen,id_veterinario_destino,fecha_limite) VALUES (91,11,2,5,'2099-11-01 10:00')");
    }

    /** Compara columnas/índices/FK/CHECK, sin confundir orden físico con definición. */
    private function estructura(): array
    {
        $consultas = [
            "SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME <> 'schema_migraciones' ORDER BY TABLE_NAME",
            'SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,GENERATION_EXPRESSION,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,COLUMN_NAME',
            'SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,SUB_PART,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX',
            'SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION',
            'SELECT tc.TABLE_NAME,cc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA=cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME=cc.CONSTRAINT_NAME WHERE cc.CONSTRAINT_SCHEMA=DATABASE() ORDER BY tc.TABLE_NAME,cc.CONSTRAINT_NAME',
        ];
        return array_map(fn (string $sql): array => $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC), $consultas);
    }

    private function filas(string $tabla): array
    {
        return $this->db->query('SELECT * FROM ' . $tabla . ' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function rechazo(string $sql): void
    {
        try {
            $this->db->exec($sql);
            $this->fail('La BD admitió datos incompatibles: ' . $sql);
        } catch (PDOException $e) {
            $this->assertContains((int) $e->errorInfo[1], [1062, 1452, 4025, 3819, 1906, 3105], $e->getMessage());
        }
    }

    public function testInstalacionYActualizacionDanElMismoDestinoYSeRepiten(): void
    {
        $this->instalar();
        $this->migrar();
        $nueva = $this->estructura();
        $this->assertCount(58, $nueva[0]);
        $this->migrar();
        $this->assertSame($nueva, $this->estructura());
        $this->reiniciar();
        $this->legado();
        $this->migrar();
        $this->assertSame($nueva, $this->estructura());
        $antes = $this->filas('citas');
        $this->migrar();
        $this->assertSame($antes, $this->filas('citas'));
        $this->assertSame($nueva, $this->estructura());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()")->fetchColumn());
    }

    public function testConservaIdentidadActosSellosDesconocidosYPersonalizacionesPosteriores(): void
    {
        $this->legado();
        $tablas = ['consultas', 'mascota_clinica', 'mascotas', 'clinicas', 'usuario_clinica', 'propietario_clinica', 'tipos_cita'];
        $previas = [];
        foreach ($tablas as $t) {
            $previas[$t] = $this->filas($t);
        }
        $citas = $this->filas('citas');
        $this->migrar();
        foreach ($tablas as $t) {
            $this->assertSame($previas[$t], $this->filas($t), $t);
        }
        $posteriores = $this->filas('citas');
        foreach ($citas as $i => $cita) {
            $this->assertSame($cita, array_intersect_key($posteriores[$i], $cita));
            $this->assertSame('legado', $posteriores[$i]['origen']);
            $this->assertNull($posteriores[$i]['politica_temporal']);
            $this->assertNull($posteriores[$i]['inicio_estimado']);
            $this->assertNull($posteriores[$i]['id_bloque']);
        }
        $this->assertSame(1800, (int) $posteriores[0]['duracion_activa_objetivo_segundos']);
        $this->assertNull($posteriores[1]['duracion_activa_objetivo_segundos']);
        $this->assertNull($posteriores[2]['duracion_activa_objetivo_segundos']);
        $this->assertNull($posteriores[3]['duracion_activa_objetivo_segundos']);
        $this->assertSame('pendiente_revision', $posteriores[1]['estado_reajuste']);
        $this->assertSame('aprobada', $this->filas('ausencias_veterinario')[0]['estado']);
        $this->assertNull($this->filas('ausencias_veterinario')[0]['id_revisor']);
        $this->assertSame(1, (int) $this->filas('reasignaciones')[0]['id_clinica']);
        $this->assertNull($this->filas('reasignaciones')[0]['inicio_propuesto']);
        foreach (['solicitudes_agenda','pausas_cita','eventos_agenda','operaciones_agenda','bloques_agenda','cierres_clinica'] as $t) {
            $this->assertSame([], $this->filas($t));
        }
        $this->db->exec("UPDATE citas SET origen='urgencia', estado_reajuste='ninguno', motivo_reajuste='Personalizado', duracion_activa_objetivo_segundos=1234 WHERE id_cita=12");
        $this->db->exec("UPDATE ausencias_veterinario SET estado='solicitada', id_solicitante=2 WHERE id=71");
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,fecha,hora,motivo,duracion_minutos,hora_fin) VALUES (1,1,2,'2099-11-05','15:00','Posterior',30,'15:30')");
        $antes = [$this->filas('citas'), $this->filas('ausencias_veterinario'), $this->filas('ingresos_emergencia')];
        $this->migrar();
        $this->assertSame($antes, [$this->filas('citas'), $this->filas('ausencias_veterinario'), $this->filas('ingresos_emergencia')]);
    }

    public function testReanudaDdlParcialAntesYDespuesDelRelleno(): void
    {
        $this->legado();
        $sql = file_get_contents(self::RAIZ . '/database/08_agenda_grafo_ii.sql');
        $corte = strpos($sql, '-- Columnas aditivas;');
        $this->assertNotFalse($corte);
        $this->sql(substr($sql, 0, $corte));
        $this->assertSame(58, (int) $this->db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn());
        $corteFinal = strpos($sql, '-- Verificacion final de columnas,');
        $this->assertNotFalse($corteFinal);
        $this->sql(substr($sql, $corte, $corteFinal - $corte));
        $this->assertSame(1800, (int) $this->db->query('SELECT duracion_activa_objetivo_segundos FROM citas WHERE id_cita=11')->fetchColumn());
        $this->migrar();
        $antes = $this->estructura();
        $this->db->exec('ALTER TABLE pausas_cita DROP FOREIGN KEY fk_m4_pausascita_3');
        $this->db->exec('ALTER TABLE citas DROP INDEX idx_cita_reajuste');
        $this->migrar();
        $this->assertSame($antes, $this->estructura());
    }

    public function testAuxiliaresCalculadasUnaAbiertaYVariosNull(): void
    {
        $this->instalar();
        $this->db->exec("INSERT INTO citas (id_cita,id_clinica,id_mascota,id_veterinario,fecha,hora,motivo) VALUES (11,1,1,2,'2099-11-01','09:00','Prueba')");
        $this->db->exec("INSERT INTO solicitudes_agenda (id_solicitud,id_clinica,id_mascota,id_actor,origen,estado,fecha_llegada,id_receptor,estado_revision,contexto_triage,huella_entrada) VALUES (40,1,1,2,'llegada','espera_presencial','2099-11-01 09:00',2,'pendiente','{}',REPEAT('a',64))");
        $pausa = "INSERT INTO pausas_cita (id_clinica,id_cita,id_solicitud_roja,inicio,fin,id_autor,motivo) VALUES (1,11,40,";
        $this->db->exec($pausa . "'2099-11-01 09:10',NULL,2,'Rojo')");
        $this->rechazo($pausa . "'2099-11-01 09:20',NULL,2,'Duplicada')");
        $this->db->exec("UPDATE pausas_cita SET fin='2099-11-01 09:15'");
        $this->db->exec($pausa . "'2099-11-01 09:30','2099-11-01 09:40',2,'Cerrada')");
        $this->db->exec($pausa . "'2099-11-01 09:50',NULL,2,'Abierta')");
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM pausas_cita WHERE cita_abierta IS NULL')->fetchColumn());
        $reasig = "INSERT INTO reasignaciones (id_clinica,id_cita,id_veterinario_origen,id_veterinario_destino,fecha_limite,estado) VALUES (1,11,2,5,'2099-11-01 11:00',";
        $this->db->exec($reasig . "'pendiente')");
        $this->rechazo($reasig . "'pendiente')");
        $this->db->exec("UPDATE reasignaciones SET estado='rechazada'");
        $this->db->exec($reasig . "'vencida')");
        $this->db->exec($reasig . "'pendiente')");
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM reasignaciones WHERE cita_pendiente IS NULL')->fetchColumn());
        // MariaDB puede ignorar la asignación con advertencia; MySQL la rechaza.
        // La propiedad contractual es el valor calculado, nunca el 999 enviado.
        try {
            $this->db->exec('UPDATE pausas_cita SET cita_abierta=999 WHERE fin IS NULL');
        } catch (PDOException $e) {
            $this->assertContains((int) $e->errorInfo[1], [1906, 3105]);
        }
        $this->assertSame(11, (int) $this->db->query('SELECT cita_abierta FROM pausas_cita WHERE fin IS NULL')->fetchColumn());
    }

    public function testFkCompuestasYChecksRechazanCrucesEIncoherenciaLocal(): void
    {
        $this->instalar();
        $this->db->exec("INSERT INTO citas (id_cita,id_clinica,id_mascota,id_veterinario,fecha,hora,motivo) VALUES (11,1,1,2,'2099-11-01','09:00','Norte')");
        $s = "INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado_revision,contexto_triage,huella_entrada,id_cita) VALUES ";
        $this->rechazo($s . "(2,1,4,'portal','pendiente','{}',REPEAT('a',64),11)");
        $this->rechazo($s . "(1,1,2,'portal','resuelta','{}',REPEAT('a',64),11)");
        $this->rechazo("INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado,estado_revision,contexto_triage,huella_entrada) VALUES (1,1,2,'llegada','espera_presencial','pendiente','{}',REPEAT('a',64))");
        $this->rechazo("INSERT INTO cierres_clinica (id_clinica,inicio,fin,motivo,id_autor) VALUES (1,'2099-01-02','2099-01-01','Invalido',1)");
        $this->rechazo('UPDATE citas SET duracion_activa_objetivo_segundos=0 WHERE id_cita=11');
        $this->rechazo("INSERT INTO reasignaciones (id_clinica,id_cita,id_veterinario_origen,id_veterinario_destino,fecha_limite) VALUES (2,11,2,5,'2099-11-01 11:00')");
        $e = "INSERT INTO eventos_agenda (id_clinica,id_cita,tipo,clave_evento,destino_clave,id_destinatario,id_rol_destino,canal,version_recurso) VALUES ";
        $this->rechazo($e . "(1,11,'cita',REPEAT('a',64),'u:6',6,2,'correo',0)");
        $this->rechazo($e . "(1,11,'cita',REPEAT('a',64),'u:6',NULL,NULL,'correo',0)");
        $this->rechazo($e . "(2,11,'cita',REPEAT('a',64),'u:6',6,NULL,'correo',0)");
        $this->db->exec($e . "(1,11,'cita',REPEAT('a',64),'u:6',6,NULL,'correo',0)");
        $this->rechazo($e . "(1,11,'cita',REPEAT('a',64),'u:6',6,NULL,'correo',0)");
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM eventos_agenda')->fetchColumn());
        $this->db->exec("INSERT INTO ingresos_emergencia (id_ingreso,id_clinica,id_mascota_provisional) VALUES (81,2,1)");
        $this->rechazo("INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado_revision,contexto_triage,huella_entrada,id_ingreso) VALUES (1,1,2,'llegada','pendiente','{}',REPEAT('b',64),81)");
        $this->rechazo("INSERT INTO bloques_agenda (id_clinica,id_veterinario,fecha,franja_origen,inicio_original,fin_original) VALUES (2,2,'2099-11-01',REPEAT('c',64),'2099-11-01 08:00','2099-11-01 12:00')");
        $this->db->exec("INSERT INTO bloques_agenda (id_bloque,id_clinica,id_veterinario,fecha,franja_origen,inicio_original,fin_original) VALUES (51,2,4,'2099-11-01',REPEAT('c',64),'2099-11-01 08:00','2099-11-01 12:00')");
        $this->rechazo('UPDATE citas SET id_bloque=51 WHERE id_cita=11');
        // B1.1 permite metadatos de episodio anterior sin atribuirlos al contexto actual.
        $this->db->exec("INSERT INTO solicitudes_agenda (id_solicitud,id_clinica,id_mascota,id_actor,origen,estado,estado_revision,contexto_triage,huella_entrada,huella_revision,id_revisor,fecha_revision,version_solicitud) VALUES (40,1,1,2,'llegada','finalizada','resuelta','{\"id_mascota_evaluada\":1,\"version_solicitud_evaluada\":5}',REPEAT('d',64),REPEAT('e',64),2,'2099-11-01 09:00',6)");
        $this->rechazo("INSERT INTO pausas_cita (id_clinica,id_cita,id_solicitud_roja,inicio,id_autor,motivo) VALUES (2,11,40,'2099-11-01 10:00',4,'Cruce')");
    }

    public function testDuplicadosPendientesDetienenAntesDeCambiosSinElegirFila(): void
    {
        $this->legado();
        $this->db->exec("INSERT INTO reasignaciones (id_cita,id_veterinario_origen,id_veterinario_destino,fecha_limite) VALUES (11,2,5,'2099-11-01 10:00')");
        $antes = $this->filas('reasignaciones');
        try {
            $this->migrar();
            $this->fail('Admitió dos propuestas pendientes.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('pendientes duplicadas cita id=11', $e->getMessage());
        }
        $this->assertSame($antes, $this->filas('reasignaciones'));
        $this->assertSame(52, (int) $this->db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn());
        $this->db->exec("UPDATE reasignaciones SET estado='rechazada' WHERE id_reasignacion <> 91");
        $this->db->exec("UPDATE ausencias_veterinario SET fecha_hora_fin='2099-11-02 08:00' WHERE id=71");
        try {
            $this->migrar();
            $this->fail('Admitió una ausencia de intervalo negativo.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('datos ausencias_veterinario id=71', $e->getMessage());
        }
        $this->assertSame('2099-11-02 08:00:00', $this->filas('ausencias_veterinario')[0]['fecha_hora_fin']);
    }

    public function testHuerfanoDetieneConIdSinEliminarlo(): void
    {
        $this->legado();
        // Solo la fixture incompatible desactiva FK para representar una base dañada.
        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        $this->db->exec('UPDATE reasignaciones SET id_cita=999 WHERE id_reasignacion=91');
        $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        try {
            $this->migrar();
            $this->fail('Admitió una referencia huérfana.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('huerfano reasignaciones.id_cita id=91', $e->getMessage());
        }
        $this->assertSame(999, (int) $this->filas('reasignaciones')[0]['id_cita']);
    }

    /** B1.5/P20: el CHECK propio debe reconocerse en cada motor, sin perder ENFORCED. */
    public function testB22AceptaMetadataPropiaYRepiteSinAlterarla(): void
    {
        $this->instalar();
        $antes = $this->estructura();
        $clausula = $this->db->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_m4_solicitud_espera'")->fetchColumn();
        $this->assertIsString($clausula);
        $this->assertStringContainsString('espera_presencial', $clausula);
        $this->assertStringContainsString('en_atencion', $clausula);
        $this->migrar();
        $this->migrar();
        $this->assertSame($antes, $this->estructura());
        if (!str_contains($this->db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB')) {
            $this->assertSame('YES', $this->db->query("SELECT ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_m4_solicitud_espera'")->fetchColumn());
            $this->db->exec('ALTER TABLE solicitudes_agenda ALTER CHECK ck_m4_solicitud_espera NOT ENFORCED');
            try {
                $this->migrar();
                $this->fail('Admitió un CHECK correcto pero no aplicado.');
            } catch (PDOException $e) {
                $this->assertStringContainsString('CHECK no aplicado ck_m4_solicitud_espera', $e->getMessage());
            }
        }
    }

    /** RE-4.15.1/2: mismas palabras sin paréntesis no acreditan la misma espera. */
    public function testB22RechazaContenidoYAgrupacionDeCheckDistintos(): void
    {
        $this->instalar();
        $mysql = !str_contains($this->db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB');
        $variantes = [
            "estado NOT IN ('espera_presencial','asignada') OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL)",
            "(estado NOT IN ('espera_presencial','en_atencion') OR fecha_llegada IS NOT NULL) AND id_receptor IS NOT NULL",
            "estado NOT IN ('espera_ presencial','en_atencion') OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL)",
            "estado NOT IN ('espera_presencial','_utf8mb4en_atencion') OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL)",
        ];
        foreach ($variantes as $expresion) {
            $this->db->exec('ALTER TABLE solicitudes_agenda DROP ' . ($mysql ? 'CHECK ' : 'CONSTRAINT ') . 'ck_m4_solicitud_espera');
            $this->db->exec('ALTER TABLE solicitudes_agenda ADD CONSTRAINT ck_m4_solicitud_espera CHECK (' . $expresion . ')');
            $antes = $this->estructura();
            try {
                $this->migrar();
                $this->fail('Admitió otro contenido o agrupación lógica.');
            } catch (PDOException $e) {
                $this->assertStringContainsString('CHECK ck_m4_solicitud_espera', $e->getMessage());
            }
            $this->assertSame($antes, $this->estructura());
        }
    }

    /** B1.5/P20: revisar también las dos expresiones STORED con la misma serialización. */
    public function testB22RechazaExpresionesGeneradasDistintas(): void
    {
        $this->instalar();
        foreach (['pausas_cita' => 'cita_abierta', 'reasignaciones' => 'cita_pendiente'] as $tabla => $columna) {
            $condicion = $tabla === 'pausas_cita' ? 'fin IS NULL' : "estado = 'pendiente'";
            $this->db->exec('ALTER TABLE ' . $tabla . ' MODIFY ' . $columna . ' INT GENERATED ALWAYS AS (CASE WHEN ' . $condicion . ' THEN id_cita ELSE 0 END) STORED');
            try {
                $this->migrar();
                $this->fail('Admitió una expresión generada distinta.');
            } catch (PDOException $e) {
                $this->assertStringContainsString('definicion ' . $tabla . '.' . $columna, $e->getMessage());
            }
            $this->db->exec('ALTER TABLE ' . $tabla . ' MODIFY ' . $columna . ' INT GENERATED ALWAYS AS (CASE WHEN ' . $condicion . ' THEN id_cita ELSE NULL END) STORED');
        }
    }

    public function testDefinicionExistenteIncompatibleNoSeAceptaPorNombre(): void
    {
        $this->instalar();
        $this->db->exec('ALTER TABLE citas DROP INDEX idx_cita_reajuste');
        $this->db->exec('ALTER TABLE citas ADD INDEX idx_cita_reajuste (id_clinica,fecha)');
        try {
            $this->migrar();
            $this->fail('Admitió un índice de columnas distintas.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('indice idx_cita_reajuste', $e->getMessage());
        }
        $this->db->exec('ALTER TABLE citas DROP INDEX idx_cita_reajuste');
        $this->db->exec("ALTER TABLE citas MODIFY politica_temporal VARCHAR(8) DEFAULT NULL");
        try {
            $this->migrar();
            $this->fail('Admitió un campo incompatible.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('definicion citas.politica_temporal', $e->getMessage());
        }
        $this->db->exec('ALTER TABLE citas MODIFY politica_temporal VARCHAR(16) DEFAULT NULL');
        $mysql = !str_contains($this->db->query('SELECT VERSION()')->fetchColumn(), 'MariaDB');
        $this->db->exec('ALTER TABLE citas DROP ' . ($mysql ? 'CHECK ' : 'CONSTRAINT ') . 'ck_m4_cita_objetivo');
        $this->db->exec('ALTER TABLE citas ADD CONSTRAINT ck_m4_cita_objetivo CHECK (duracion_activa_objetivo_segundos IS NULL OR duracion_activa_objetivo_segundos >= 0)');
        try {
            $this->migrar();
            $this->fail('Admitió un CHECK diferente.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('CHECK ck_m4_cita_objetivo', $e->getMessage());
        }
        $this->db->exec('ALTER TABLE citas DROP ' . ($mysql ? 'CHECK ' : 'CONSTRAINT ') . 'ck_m4_cita_objetivo');
        $this->db->exec('ALTER TABLE citas ADD CONSTRAINT ck_m4_cita_objetivo CHECK (duracion_activa_objetivo_segundos IS NULL OR duracion_activa_objetivo_segundos > 0)');
        $this->db->exec('ALTER TABLE citas DROP FOREIGN KEY fk_m4_citas_0');
        $this->db->exec('ALTER TABLE citas ADD CONSTRAINT fk_m4_citas_0 FOREIGN KEY (id_bloque,id_clinica) REFERENCES citas(id_cita,id_clinica)');
        try {
            $this->migrar();
            $this->fail('Admitió una FK de destino distinto.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('FK fk_m4_citas_0', $e->getMessage());
        }
    }
}
