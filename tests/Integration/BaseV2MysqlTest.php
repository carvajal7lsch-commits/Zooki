<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Migrador.php';
require_once __DIR__ . '/../../helpers/CreadorSuperAdmin.php';

/**
 * Plan M0, etapa B — La base v2 limpia contra un MySQL o MariaDB real.
 *
 * El esquema usa columnas calculadas, JSON, information_schema y GET_LOCK, que
 * SQLite no tiene; por eso esta prueba necesita un servidor. Se configura solo
 * con variables de entorno (nunca con .env, para no tocar la base de la app):
 *
 *   ZOOKI_TEST_MYSQL_HOST  (obligatoria; sin ella la prueba se salta)
 *   ZOOKI_TEST_MYSQL_PORT  (3306)
 *   ZOOKI_TEST_MYSQL_USER  (root)
 *   ZOOKI_TEST_MYSQL_PASS  ('')
 *   ZOOKI_TEST_MYSQL_DB    (zooki_test_base_v2) — se BORRA y se crea en cada prueba
 *
 * En CI la provee el servicio MySQL 8 de .github/workflows/ci.yml.
 */
class BaseV2MysqlTest extends TestCase
{
    private const DATABASE = __DIR__ . '/../../database';

    private PDO $db;
    private string $base;
    private ?string $carpetaTemporal = null;

    protected function setUp(): void
    {
        $host = getenv('ZOOKI_TEST_MYSQL_HOST');
        if ($host === false || $host === '') {
            $this->markTestSkipped('Sin ZOOKI_TEST_MYSQL_HOST: no hay MySQL de prueba.');
        }
        $this->base = getenv('ZOOKI_TEST_MYSQL_DB') ?: 'zooki_test_base_v2';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->base)) {
            $this->fail('ZOOKI_TEST_MYSQL_DB solo admite letras, números y guion bajo.');
        }

        try {
            $this->db = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)),
                getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root',
                getenv('ZOOKI_TEST_MYSQL_PASS') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $this->markTestSkipped('No se pudo conectar al MySQL de prueba: ' . $e->getMessage());
        }

        $this->db->exec("DROP DATABASE IF EXISTS `{$this->base}`");
        $this->db->exec("CREATE DATABASE `{$this->base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $this->db->exec("USE `{$this->base}`");
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->exec("DROP DATABASE IF EXISTS `{$this->base}`");
        }
        if ($this->carpetaTemporal !== null) {
            array_map('unlink', glob($this->carpetaTemporal . '/*'));
            rmdir($this->carpetaTemporal);
        }
    }

    public function testElEsquemaCreaLasCincuentaTablasEnInnoDbYUtf8mb4(): void
    {
        $this->cargar('01_schema.sql');

        $tablas = $this->db->query(
            "SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(50, $tablas);
        foreach ($tablas as $t) {
            $this->assertSame('InnoDB', $t['ENGINE']);
            $this->assertSame('utf8mb4_general_ci', $t['TABLE_COLLATION']);
        }
    }

    public function testLaSemillaCargaLosCatalogosYNoDuplicaAlRepetirse(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->cargar('02_semilla.sql');

        $this->assertSame(['roles' => 4, 'planes' => 2, 'especies' => 6, 'razas' => 54, 'colores_base' => 9, 'especialidades' => 0], $this->conteos());
        $this->assertSame(
            [1 => 'administrador', 2 => 'veterinario', 4 => 'propietario', 5 => 'super-administrador'],
            $this->db->query('SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol')->fetchAll(PDO::FETCH_KEY_PAIR)
        );
        $this->assertSame(0, $this->contar('usuarios'));
        $this->assertSame(0, $this->contar('clinicas'));
    }

    public function testElMigradorEstaAlDiaDosVecesSeguidas(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $antes = $this->conteos();

        $primera = $this->migrador()->migrar();
        $segunda = $this->migrador()->migrar();

        $this->assertSame(['ejecutadas' => [], 'semilla' => true], $primera);
        $this->assertSame(['ejecutadas' => [], 'semilla' => true], $segunda);
        $this->assertSame($antes, $this->conteos());
    }

    /** D-5: la semilla solo inserta lo que falta y nunca sobrescribe. */
    public function testLaSemillaNoSobrescribeNiRecreaLoQueCambioLaPlataforma(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $this->db->exec("UPDATE planes SET precio_mensual = 120000 WHERE id_plan = 2");
        $this->db->exec("UPDATE colores_base SET nombre_color = 'Café oscuro' WHERE id_color = 3");

        $this->migrador()->migrar();

        $this->assertSame(120000, (int) $this->db->query('SELECT precio_mensual FROM planes WHERE id_plan = 2')->fetchColumn());
        $this->assertSame('Café oscuro', $this->db->query('SELECT nombre_color FROM colores_base WHERE id_color = 3')->fetchColumn());
        $this->assertSame(9, $this->contar('colores_base'));
    }

    public function testUnaMigracionNuevaSeAplicaUnaSolaVez(): void
    {
        $this->cargar('01_schema.sql');
        $carpeta = $this->carpetaConMigracion(
            '03_prueba_migrador.sql',
            "CREATE TABLE IF NOT EXISTS prueba_migrador (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB;\n"
            . "INSERT IGNORE INTO prueba_migrador (id) VALUES (1);"
        );

        $revision = (new Migrador($this->db, $carpeta))->migrar(true);
        $primera = (new Migrador($this->db, $carpeta))->migrar();
        $segunda = (new Migrador($this->db, $carpeta))->migrar();

        $this->assertSame(['03_prueba_migrador.sql'], $revision['ejecutadas']);
        $this->assertSame(['03_prueba_migrador.sql'], $primera['ejecutadas']);
        $this->assertSame([], $segunda['ejecutadas']);
        $this->assertSame(1, $this->contar('schema_migraciones'));
        $this->assertSame(4, $this->contar('roles'), 'La semilla también se aplicó');
    }

    public function testSeNiegaSobreUnaBaseV1(): void
    {
        // La forma de la v1: usuarios con el documento como clave y sin clinicas.
        $this->db->exec("CREATE TABLE usuarios (documento VARCHAR(20) NOT NULL PRIMARY KEY, id_rol INT) ENGINE=InnoDB");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('esquema v1');
        $this->migrador()->migrar();
    }

    public function testSeNiegaSobreUnaBaseSinEsquema(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('01_schema.sql');
        $this->migrador()->migrar();
    }

    /** D-2 / RN-401: la base impide dos reservas del mismo veterinario a la misma hora. */
    public function testLaDobleReservaSeRechazaSalvoSiLaCitaEstaLibreOEsSobrecupo(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        [$clinicaA, $clinicaB, $vet, $mascota] = $this->datosDeAgenda();

        $primera = $this->reservar($clinicaA, $mascota, $vet, 0);

        // Mismo veterinario y hora, aunque sea en otra clínica: se rechaza.
        try {
            $this->reservar($clinicaB, $mascota, $vet, 0);
            $this->fail('Se aceptó una doble reserva del mismo veterinario.');
        } catch (PDOException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        // Un sobrecupo no ocupa el horario.
        $this->reservar($clinicaA, $mascota, $vet, 1);

        // Al cancelar la primera, el horario queda libre otra vez.
        $this->db->exec("UPDATE citas SET estado = 'cancelada' WHERE id_cita = {$primera}");
        $this->reservar($clinicaA, $mascota, $vet, 0);

        $this->assertSame(3, $this->contar('citas'));
    }

    public function testCreaElSuperAdministradorSinClinicaYNoRepiteElCorreo(): void
    {
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        $creador = new CreadorSuperAdmin($this->db);

        $id = $creador->crear('operador@zooki.test', 'Operador Zooki', 'Plataforma#Vet2026');

        $fila = $this->db->query("SELECT * FROM usuarios WHERE id_usuario = {$id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $fila['es_super_admin']);
        $this->assertNull($fila['documento']);
        $this->assertTrue(password_verify('Plataforma#Vet2026', $fila['password']));
        $this->assertSame(0, $this->contar('usuario_clinica'));
        $this->assertSame(0, $this->contar('propietario_clinica'));
        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM auditoria_sistema WHERE id_usuario = {$id} AND id_clinica IS NULL AND accion = 'INSERT'"
        )->fetchColumn());

        try {
            $creador->crear('OPERADOR@zooki.test', 'Otra persona', 'Plataforma#Vet2026');
            $this->fail('Se creó una segunda cuenta con el mismo correo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('correo', $e->getMessage());
        }
        $this->assertSame(1, $this->contar('usuarios'));
    }

    // -----------------------------------------------------------------------

    private function migrador(): Migrador
    {
        return new Migrador($this->db, self::DATABASE);
    }

    /** Ejecuta un archivo de varias sentencias, como lo hace el migrador. */
    private function cargar(string $archivo): void
    {
        $st = $this->db->query(file_get_contents(self::DATABASE . '/' . $archivo));
        while ($st->nextRowset()) {
        }
        $st->closeCursor();
    }

    /** Copia 02_semilla.sql y una migración de prueba a una carpeta temporal. */
    private function carpetaConMigracion(string $nombre, string $sql): string
    {
        $this->carpetaTemporal = sys_get_temp_dir() . '/zooki_migrador_' . bin2hex(random_bytes(4));
        mkdir($this->carpetaTemporal);
        copy(self::DATABASE . '/02_semilla.sql', $this->carpetaTemporal . '/02_semilla.sql');
        file_put_contents($this->carpetaTemporal . '/' . $nombre, $sql);
        return $this->carpetaTemporal;
    }

    private function contar(string $tabla): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
    }

    /** @return array<string, int> */
    private function conteos(): array
    {
        $r = [];
        foreach (['roles', 'planes', 'especies', 'razas', 'colores_base', 'especialidades'] as $t) {
            $r[$t] = $this->contar($t);
        }
        return $r;
    }

    /** @return int[] [clínica A, clínica B, veterinario, mascota] */
    private function datosDeAgenda(): array
    {
        $this->db->exec("INSERT INTO clinicas (nombre, nit, id_plan, estado) VALUES ('Clínica A', '900000001', 1, 'activa'), ('Clínica B', '900000002', 1, 'activa')");
        $this->db->exec("INSERT INTO usuarios (nombre_completo, email) VALUES ('Vet', 'vet@zooki.test'), ('Dueña', 'duena@zooki.test')");
        $this->db->exec("INSERT INTO mascotas (id_propietario, token_carnet, id_especie, id_raza, nombre) VALUES (2, '" . str_repeat('a', 43) . "', 1, 49, 'Luna')");
        return [1, 2, 1, 1];
    }

    private function reservar(int $clinica, int $mascota, int $vet, int $sobrecupo): int
    {
        $this->db->prepare(
            "INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, es_sobrecupo)
             VALUES (?, ?, ?, '2026-11-02', '09:00:00', 'Control', ?)"
        )->execute([$clinica, $mascota, $vet, $sobrecupo]);
        return (int) $this->db->lastInsertId();
    }
}
