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

        $this->assertSame(['ejecutadas' => ['03_confirmacion_vinculo_propietario.sql'], 'semilla' => true], $primera);
        $this->assertSame(['ejecutadas' => [], 'semilla' => true], $segunda);
        $this->assertSame($antes, $this->conteos());
    }

    /** C3: aislamiento, consentimiento, confirmacion y ficha global en el esquema real. */
    public function testC3MascotasYConfirmacionEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../../models/Mascota.php';
        require_once __DIR__ . '/../../models/PropietarioClinica.php';
        $this->cargar('01_schema.sql'); $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db); DosClinicas::completarMascota($this->db);
        $_SESSION=['id_usuario'=>4]; Contexto::activar(Contexto::deClinica(2,'Clínica Sur',Roles::VETERINARIO),1);
        try {
            $mascotas=new Mascota($this->db); $propietarios=new PropietarioClinica($this->db);
            $this->assertSame([],$mascotas->getAll());
            try { $mascotas->getById(1); $this->fail('Debió rechazar'); } catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
            $this->assertNull($propietarios->buscarExacto('fabio@'));
            $solicitud=$propietarios->solicitarVinculo('fabio@zooki.test');
            $this->assertFalse($mascotas->esPropietarioValido(6));
            $this->assertTrue($propietarios->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
            $this->assertFalse($propietarios->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
            $mascotas->vincular(1,6); $mascotas->vincular(1,6);
            $this->assertSame(1,$this->contar('mascotas')); $this->assertSame(2,$this->contar('mascota_clinica'));
            try { $mascotas->update(['id_mascota'=>1,'id_especie'=>2]); $this->fail('Debió rechazar'); } catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
            $mascotas->update(['id_mascota'=>1,'peso'=>'9.25']);
            $this->assertSame('9.25',(string)$mascotas->getById(1)['peso']);
            $this->assertSame(1,$this->contar('notificaciones'));
            $datos=['id_propietario'=>6,'nombre'=>'Nueva ficha','id_especie'=>1,'id_raza'=>null,
                'raza_indicada'=>'Raza por confirmar','fecha_nacimiento'=>'2022-01-01','peso'=>'3.20','sexo'=>'Macho','colores'=>[1,2]];
            $id=$mascotas->insert($datos); $otra=$mascotas->insert($datos);
            $this->assertSame(54,$this->contar('razas'));
            $m=$mascotas->getById($id); $this->assertNull($m['numero_historia_clinica']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D',$m['token_carnet']);
            $this->assertNotSame($m['token_carnet'],$mascotas->getById($otra)['token_carnet']);
            $this->assertSame('1,2',$m['colores_ids']);
            $cuentas=$this->contar('usuarios');
            $datos=['nombre_completo'=>'Titular Nuevo','tipo_documento'=>'CC','documento'=>'1000000030','telefono'=>'3001234567',
                'email'=>'nuevo@zooki.test','titular_presente'=>'1','acepta_politica'=>'0'];
            try { $propietarios->registrar($datos,'politica-1','127.0.0.1'); $this->fail('Debió rechazar'); }
            catch (InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
            $this->assertSame($cuentas,$this->contar('usuarios'));
            $datos['acepta_politica']='1'; $alta=$propietarios->registrar($datos,'politica-1','127.0.0.1');
            $this->assertNull($this->db->query('SELECT password FROM usuarios WHERE id_usuario=' . $alta['id_usuario'])->fetchColumn());
            $this->assertSame('alta_personal',$this->db->query('SELECT medio FROM consentimientos_datos')->fetchColumn());
            $this->assertSame(1,$this->contar('password_resets'));
        } finally { $_SESSION=[]; }
    }

    public function testMigracionC3ActualizaUnaBaseAnteriorYSePuedeRepetir(): void
    {
        $this->cargar('01_schema.sql'); $this->cargar('02_semilla.sql');
        // Reproduce la forma publicada en B antes de la confirmación adelantada.
        $this->db->exec('ALTER TABLE verificaciones_email DROP FOREIGN KEY fk_verif_clinica_vinculo');
        $this->db->exec('ALTER TABLE verificaciones_email DROP COLUMN id_clinica_vinculo, DROP COLUMN proposito');
        $this->cargar('03_confirmacion_vinculo_propietario.sql');
        $this->cargar('03_confirmacion_vinculo_propietario.sql');
        $columnas=$this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='verificaciones_email'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('id_clinica_vinculo',$columnas); $this->assertContains('proposito',$columnas);
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_verif_clinica_vinculo'")->fetchColumn());
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

    /**
     * C1 — El fixture de dos clínicas que usan las pruebas en SQLite también
     * carga en el esquema real, y los contextos salen iguales: si 01_schema.sql
     * cambia una columna que el fixture usa, esta prueba lo detecta.
     */
    public function testElFixtureDeDosClinicasCargaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../../models/Usuario.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');

        DosClinicas::poblar($this->db);
        $usuario = new Usuario($this->db);

        $this->assertSame(['clinica:1:2', 'clinica:2:1', 'propietario'], array_column($usuario->contextosDe(DosClinicas::DOBLE), 'clave'));
        $this->assertSame(['plataforma'], array_column($usuario->contextosDe(DosClinicas::SUPER_ADMIN), 'clave'));
        $this->assertSame([1, 2, 5, 8], array_map('intval', array_column($usuario->personalDeClinica(DosClinicas::NORTE), 'id_usuario')));
        $this->assertSame(1, (int) $usuario->propietariosDeClinica(DosClinicas::NORTE)[1]['num_mascotas']);
    }

    /** C2: copia repetible, relaciones reales y restauración aislada en MySQL/MariaDB. */
    public function testConfiguracionDeDosClinicasEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../../helpers/InicializadorClinica.php';
        require_once __DIR__ . '/../../models/CatalogoClinica.php';
        require_once __DIR__ . '/../../models/HorarioClinica.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        $inicializador = new InicializadorClinica($this->db);
        $inicializador->copiar(1);
        $inicializador->copiar(2);
        $inicializador->copiar(1);
        $inicializador->copiar(2);
        foreach (['tipos_cita'=>12,'horarios_clinica'=>14,'vacunas_base'=>30,'especie_vacunas'=>42,
                  'laboratorios_base'=>22,'productos_desparasitacion_base'=>30] as $tabla=>$cantidad) {
            $this->assertSame($cantidad, $this->contar($tabla));
        }
        $_SESSION = ['id_usuario'=>1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $catalogo = new CatalogoClinica($this->db);
        $tipos = $catalogo->tiposCita();
        $this->assertCount(6, $tipos);
        $this->assertCount(12, $catalogo->vacunasPorEspecie(1));
        $horario = new HorarioClinica($this->db);
        $horario->guardar([[1,1,1,0,'09:00:00','11:00:00',null,null]]);
        $horario->restaurar();
        $this->assertSame('08:00:00', $horario->dia(1)['bloque_morning_inicio']);
        Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 1);
        $this->assertNull($catalogo->tipoCita((int) $tipos[0]['id_tipo_cita']));
        $this->assertSame('08:00:00', $horario->dia(1)['bloque_morning_inicio']);
        $this->db->beginTransaction();
        $inicializador->copiar(3);
        $this->db->rollBack();
        $this->assertSame(30, $this->contar('vacunas_base'));
        $_SESSION = [];
    }

    /**
     * C4 en el esquema real: registro atómico con clínica y veterinario,
     * número de historia por clínica, RN-113 con y sin autorización y 403
     * al modificar lo de otra clínica.
     */
    public function testC4HistoriaClinicaEnElEsquemaReal(): void
    {
        $this->prepararHistoriaClinica();
        try {
            $this->comoVeterinario(1, 2);
            $tratamiento = ['medicamento' => 'Amoxicilina', 'dosis' => '1 ml', 'via_administracion' => 'Oral', 'duracion' => '5 días', 'fecha_inicio' => '2026-10-07'];
            $guardar = fn (int $idConsulta, array $adjunto, int $indice): array => $this->metadatosDeAdjunto();
            $consultas = new Consulta($this->db);
            $idConsulta = $consultas->registrar(['id_mascota' => 1, 'motivo' => 'Oído', 'diagnostico' => 'Otitis'], [$tratamiento], [['nombre' => 'rx.jpg']], $guardar);
            $consultas->registrar(['id_mascota' => 1, 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], $guardar);
            $idVacuna = (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01']);
            $idArchivo = (int) $this->db->query('SELECT id_archivo FROM archivos_clinicos')->fetchColumn();

            $fila = $this->db->query("SELECT id_clinica, id_veterinario FROM consultas WHERE id_consulta = $idConsulta")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame([1, 2], [(int) $fila['id_clinica'], (int) $fila['id_veterinario']]);
            $this->assertSame('HC-000001', $this->numeroHistoria(1, 1));

            // Sin fecha de inicio en un tratamiento no queda nada (RE-2.6.1).
            unset($tratamiento['fecha_inicio']);
            try {
                $consultas->registrar(['id_mascota' => 1, 'motivo' => 'X', 'diagnostico' => 'Y'], [$tratamiento], [], $guardar);
                $this->fail('Debió rechazar el tratamiento sin fecha de inicio.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('fecha de inicio', $e->getMessage());
            }
            $this->assertSame(2, $this->contar('consultas'));

            $this->comoVeterinario(2, 4);
            $this->assertSame([], $consultas->historialDeMascota(1));
            $this->assertSame('Clínica Norte', (new Vacuna($this->db))->findByMascota(1)[0]['clinica_nombre']);
            $this->assertDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($idArchivo));

            $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 1 WHERE id_propietario = 6 AND id_clinica = 2');
            $historial = $consultas->historialDeMascota(1);
            $this->assertCount(2, $historial);
            $this->assertCount(1, array_merge(...array_column($historial, 'tratamientos')));
            $this->assertSame('CLI_real.jpg', (new ArchivoClinico($this->db))->paraDescargar($idArchivo)['nombre_servidor']);
            $this->assertDenegado(fn () => $consultas->paraModificar($idConsulta));
            $this->assertDenegado(fn () => (new Vacuna($this->db))->paraModificar($idVacuna));

            $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 0 WHERE id_propietario = 6 AND id_clinica = 2');
            $this->assertSame([], $consultas->historialDeMascota(1));

            $consultas->registrar(['id_mascota' => 1, 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], $guardar);
            $this->assertSame('HC-000001', $this->numeroHistoria(1, 2));
            $this->assertSame('HC-000001', $this->numeroHistoria(1, 1));
            $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND accion = 'OTHER'")->fetchColumn());
        } finally {
            $_SESSION = [];
        }
    }

    /**
     * RF-2.4: mientras otra sesión asigna un número en la misma clínica, una
     * primera consulta espera el candado; si no lo obtiene no guarda nada, y
     * al soltarlo toma el número siguiente sin repetir.
     */
    public function testC4ElNumeroDeHistoriaSeAsignaBajoCandadoSinRepetirse(): void
    {
        $this->prepararHistoriaClinica();
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (2, 6, 1, '" . str_repeat('b', 43) . "', 'Kira', 1, 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 1, 'activo')");
        $otraSesion = $this->otraConexion();
        try {
            $this->comoVeterinario(1, 2);
            $sinAdjuntos = fn (): array => [];
            $consultas = new ConsultaConEsperaCorta($this->db);
            $consultas->registrar(['id_mascota' => 1, 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);

            $this->assertSame(1, (int) $otraSesion->query("SELECT GET_LOCK('zooki_numero_hc_1', 0)")->fetchColumn());
            try {
                $consultas->registrar(['id_mascota' => 2, 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);
                $this->fail('Debió esperar el candado de numeración.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('número de historia', $e->getMessage());
            }
            $this->assertSame(1, $this->contar('consultas'));
            $this->assertNull($this->numeroHistoria(2, 1));

            // Una consulta de una mascota que ya tiene número no necesita el candado.
            $consultas->registrar(['id_mascota' => 1, 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);

            $otraSesion->query("SELECT RELEASE_LOCK('zooki_numero_hc_1')");
            $consultas->registrar(['id_mascota' => 2, 'motivo' => 'A', 'diagnostico' => 'B'], [], [], $sinAdjuntos);
            $this->assertSame('HC-000002', $this->numeroHistoria(2, 1));
            $this->assertSame(1, (int) $otraSesion->query("SELECT IS_FREE_LOCK('zooki_numero_hc_1')")->fetchColumn());
        } finally {
            $_SESSION = [];
        }
    }

    /**
     * C5 en el esquema real: duración y margen copiados, aislamiento, doble
     * reserva entre clínicas por la aplicación y por el índice único (error
     * 1062 convertido en mensaje) y RN-409 con NotificacionInterna v2.
     */
    public function testC5AgendaEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../Support/CitaSinValidacionPrevia.php';
        require_once __DIR__ . '/../../helpers/VigilanteAtenciones.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::completarMascota($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (2, 2, 2, 'activo')");
        $reserva = ['id_mascota' => 1, 'id_veterinario' => 2, 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'Control', 'id_tipo_cita' => 1];
        try {
            $_SESSION = ['id_usuario' => 2];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
            $citas = new Cita($this->db);
            $id = $citas->registrar($reserva);
            $fila = $this->db->query("SELECT id_clinica, duracion_minutos, margen_minutos, hora_fin, prioridad, es_sobrecupo, ocupa_horario FROM citas WHERE id_cita = $id")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame(['1', '30', '10', '09:30:00', 'verde', '0', '1'], array_map('strval', array_values($fila)));

            $_SESSION = ['id_usuario' => 3];
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 1);
            try {
                $citas->getById($id);
                $this->fail('Debió responder 403.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
            $this->assertSame([], $citas->listarRango('2030-01-07', '2030-01-07'));

            $enSur = ['id_tipo_cita' => 2] + $reserva;
            foreach ([$citas, new CitaSinValidacionPrevia($this->db)] as $modelo) {
                try {
                    $modelo->registrar($enSur);
                    $this->fail('Debió rechazar la doble reserva entre clínicas.');
                } catch (HorarioOcupado $e) {
                    $this->assertNotSame('', $e->getMessage());
                }
            }
            $this->assertSame(1, $this->contar('citas'));

            $_SESSION = ['id_usuario' => 2];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
            $this->assertTrue($citas->cancelar($citas->getById($id)));
            $_SESSION = ['id_usuario' => 3];
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 1);
            $deSur = $citas->registrar($enSur);
            $this->assertSame(20, (int) $this->db->query("SELECT duracion_minutos FROM citas WHERE id_cita = $deSur")->fetchColumn());

            $this->db->exec("UPDATE citas SET estado = 'en_curso' WHERE id_cita = $deSur");
            $vigilante = new VigilanteAtenciones($this->db, function (): void {
            });
            $zona = new DateTimeZone(ReglaAtencion::ZONA);
            $this->assertSame(1, $vigilante->revisar(new DateTimeImmutable('2030-01-07 09:31', $zona))['avisadas']);
            $this->assertSame(0, $vigilante->revisar(new DateTimeImmutable('2030-01-07 09:45', $zona))['avisadas']);
            $aviso = $this->db->query("SELECT id_clinica, id_usuario FROM notificaciones_internas WHERE tipo = 'ATENCION_ABIERTA'")->fetchAll(PDO::FETCH_NUM);
            $this->assertSame([['2', '2']], array_map(fn ($f) => array_map('strval', $f), $aviso));
        } finally {
            $_SESSION = [];
        }
    }

    /**
     * C6 en el esquema real: el propietario se vincula a Sur, agenda allí
     * (la mascota se vincula en la misma transacción), edita la ficha con
     * auditoría sin clínica y, al desvincularse, Sur queda en solo lectura.
     */
    public function testC6PortalEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../../controllers/CitaController.php';
        require_once __DIR__ . '/../../models/VinculosPropietario.php';
        require_once __DIR__ . '/../../models/MascotaPropietario.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::completarMascota($this->db);
        DosClinicas::poblarAgenda($this->db);
        try {
            $_SESSION = ['id_usuario' => 6];
            Contexto::activar(Contexto::dePropietario(), 1);
            $vinculos = new VinculosPropietario($this->db);
            $vinculos->vincular(2);

            $_POST = ['id_clinica' => '2', 'id_mascota' => '1', 'id_veterinario' => '4', 'id_tipo_cita' => '2', 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'Control'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $reloj = fn (): DateTimeImmutable => new DateTimeImmutable('2030-01-07 07:00', new DateTimeZone(ReglaAtencion::ZONA));
            ob_start();
            (new CitaController($this->db, $reloj))->agendarDesdePortalAjax();
            $respuesta = json_decode(ob_get_clean(), true);
            $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
            $this->assertSame('activo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());

            $cambios = (new MascotaPropietario($this->db))->actualizar(1, ['sexo' => 'Macho', 'peso' => '8.50']);
            $this->assertSame(['sexo'], $cambios);
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica IS NULL AND tabla_afectada = 'mascotas' AND accion = 'UPDATE'")->fetchColumn());

            try {
                $vinculos->desvincular(2);
                $this->fail('Con una cita sin resolver no se desvincula.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('sin resolver', $e->getMessage());
            }
            $cita = (new Cita($this->db))->paraPropietario((int) $respuesta['id_cita'], 6);
            $this->assertTrue((new Cita($this->db))->cancelar($cita));
            $vinculos->desvincular(2);
            $this->assertSame('inactivo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());

            $_SESSION = ['id_usuario' => 4];
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 1);
            try {
                (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'X', 'fecha_aplicacion' => '2026-10-01']);
                $this->fail('Tras desvincularse, Sur no registra nada nuevo.');
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
            $this->assertSame([], (new Vacuna($this->db))->findByMascota(1));
        } finally {
            $_SESSION = [];
            $_POST = [];
        }
    }

    /**
     * C7 en el esquema real: el panel de cada clínica cuenta solo lo suyo,
     * la carga sale de usuario_clinica y una mascota desvinculada no es
     * paciente activo. Elena ve su agenda en Norte y la clínica en Sur.
     */
    public function testC7PanelesEnElEsquemaReal(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../../controllers/PanelController.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, estado)
            VALUES (2, 9, 2, '" . str_repeat('b', 43) . "', 'Toby', 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 2, 'inactivo')");
        $cita = $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado)
            VALUES (?, 1, ?, ?, '2030-01-07', ?, 'Control', ?)");
        $cita->execute([1, 2, 1, '09:00:00', 'completada']);
        $cita->execute([1, 5, 1, '10:00:00', 'confirmada']);
        $cita->execute([2, 4, 2, '09:00:00', 'pendiente']);
        $ahora = new DateTimeImmutable('2030-01-07 08:00', new DateTimeZone(ReglaAtencion::ZONA));
        try {
            $_SESSION = ['id_usuario' => 5];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 2);
            $vet = (new PanelController($this->db))->datosVeterinario(5, $ahora);
            $this->assertSame(['10:00:00'], array_column($vet['agenda'], 'hora'));

            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::ADMIN), 2);
            $sur = (new PanelController($this->db))->datosAdministrador($ahora);
            $this->assertSame([4], array_map('intval', array_column($sur['citas'], 'id_veterinario')));
            $this->assertSame(['Diego Sur'], array_column($sur['carga'], 'veterinario'));
            $this->assertSame(1, $sur['pacientes_activos'], 'Toby está desvinculado de Sur.');
            $this->assertSame(2, $sur['propietarios']);

            $_SESSION = ['id_usuario' => 1];
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
            $norte = (new PanelController($this->db))->datosAdministrador($ahora);
            $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($norte['carga'], 'veterinario'));
            $this->assertEquals([1, 1], array_column($norte['carga'], 'total'));
            $this->assertSame(1, $norte['pacientes_activos']);
        } finally {
            $_SESSION = [];
        }
    }

    // -----------------------------------------------------------------------

    private function prepararHistoriaClinica(): void
    {
        require_once __DIR__ . '/../Support/DosClinicas.php';
        require_once __DIR__ . '/../Support/ConsultaConEsperaCorta.php';
        require_once __DIR__ . '/../../models/Consulta.php';
        require_once __DIR__ . '/../../models/Vacuna.php';
        $this->cargar('01_schema.sql');
        $this->cargar('02_semilla.sql');
        DosClinicas::poblar($this->db);
        DosClinicas::completarMascota($this->db);
        DosClinicas::vincularLunaASur($this->db);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function metadatosDeAdjunto(): array
    {
        return [
            'nombre_original' => 'rx.jpg',
            'nombre_servidor' => 'CLI_real.jpg',
            'ruta_archivo' => 'uploads/clinicos/CLI_real.jpg',
            'tipo_archivo' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano_bytes' => 2048,
        ];
    }

    private function numeroHistoria(int $mascota, int $clinica): ?string
    {
        $numero = $this->db->query("SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota = $mascota AND id_clinica = $clinica")->fetchColumn();
        return $numero === false ? null : $numero;
    }

    private function assertDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió responder 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    /** Segunda sesión sobre la misma base, para simular otra petición en curso. */
    private function otraConexion(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('ZOOKI_TEST_MYSQL_HOST'),
            (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306),
            $this->base
        );
        $usuario = getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root';
        $clave = getenv('ZOOKI_TEST_MYSQL_PASS') ?: '';
        return new PDO($dsn, $usuario, $clave, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

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
