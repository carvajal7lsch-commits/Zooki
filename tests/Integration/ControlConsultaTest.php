<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';
require_once __DIR__ . '/../../controllers/ControlConsultaController.php';

/** Cambia el fixture después de una lectura concreta, sin alterar el servicio. */
final class LecturaControlConsultaPrueba extends PDOStatement
{
    protected function __construct(private Closure $despues)
    {
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $fila = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        ($this->despues)($this->queryString);
        return $fila;
    }
}

/** RE-4.3.10/11 y RN-431: cierre clínico firme y reserva por el flujo de agenda. */
final class ControlConsultaTest extends TestCase
{
    private PDO $db;
    private Closure $reloj;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->db->exec("UPDATE tipos_cita SET nombre_tipo='Consulta general' WHERE id_tipo_cita=1");
        (new CargadorConocimiento($this->db))->cargar(__DIR__ . '/../Fixtures/conocimiento/control');
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $this->reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 10:00:00');
        $this->db->exec("INSERT INTO citas (id_cita,id_clinica,id_mascota,id_veterinario,fecha,hora,motivo,estado) VALUES (1,1,1,2,'2030-01-07','09:00:00','Atención','en_curso')");
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES = [];
        $_POST = ['id_cita' => '1', 'id_mascota' => '1', 'motivo' => 'Control', 'diagnostico' => 'Diagnóstico sintético', 'peso' => '8.5', 'control_activo' => '1', 'control_fecha' => '2030-01-08'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_FILES = [];
        http_response_code(200);
    }

    private function finalizar(): array
    {
        ob_start();
        try {
            (new ConsultaController($this->db, 'copy', sys_get_temp_dir(), $this->reloj))->registrarAjax();
        } finally {
            $json = ob_get_clean();
        }
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testContextoAdmiteFichaIncompletaSinSexoRazaNacimientoNiPeso(): void
    {
        $this->db->exec("UPDATE mascotas SET sexo=NULL,id_raza=NULL,fecha_nacimiento=NULL,peso=NULL,esterilizado=NULL WHERE id_mascota=1");
        $contexto = (new ControlConsulta($this->db, $this->reloj))->contexto(1);
        self::assertSame(1, $contexto['id_clinica']);
        self::assertSame(2, $contexto['id_veterinario']);
        self::assertNull($contexto['ultimo_peso']);
        self::assertSame([37.5, 39.2], $contexto['referencias']['temperatura']);
        $_GET = ['id_mascota' => '1'];
        ob_start();
        (new ControlConsultaController($this->db))->contextoAjax();
        $respuesta = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        $_GET = [];
        self::assertTrue($respuesta['success']);
        self::assertNull($respuesta['ficha']['sexo']);
        self::assertNull($respuesta['ficha']['fecha_nacimiento']);
    }

    public function testCompletarFichaIncompletaGuardaTresDatosAuditaYNotificaSinInventarPesoRaza(): void
    {
        $this->db->exec("UPDATE mascotas SET sexo='Desconocido',id_raza=NULL,fecha_nacimiento=NULL,peso=NULL,esterilizado=NULL WHERE id_mascota=1");
        $_POST = ['id_mascota' => '1', 'sexo' => 'Hembra', 'esterilizado' => '0', 'fecha_nacimiento' => '2022-01-01'];
        ob_start();
        (new ControlConsultaController($this->db))->completarFichaAjax();
        $r = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($r['success'], $r['message']);
        $ficha = (new Mascota($this->db))->getById(1);
        self::assertSame('Hembra', $ficha['sexo']);
        self::assertSame(0, (int) $ficha['esterilizado']);
        self::assertSame('2022-01-01', $ficha['fecha_nacimiento']);
        self::assertNull($ficha['peso']);
        self::assertNull($ficha['id_raza']);
        $auditoria = $this->db->query('SELECT id_clinica,id_usuario,campo_modificado FROM auditoria_mascotas ORDER BY id_auditoria')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(3, $auditoria);
        self::assertEqualsCanonicalizing(['sexo', 'esterilizado', 'fecha_nacimiento'], array_column($auditoria, 'campo_modificado'));
        self::assertSame([1], array_unique(array_column($auditoria, 'id_clinica')));
        self::assertSame([2], array_unique(array_column($auditoria, 'id_usuario')));
        self::assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones WHERE tipo_notificacion='cambio_ficha'")->fetchColumn());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datosFichaInvalidos')]
    public function testCompletarFichaRechazaDatosConReglasDeEditar(array $datos, string $campo): void
    {
        try {
            (new Mascota($this->db))->completarFichaConsulta(1, $datos);
            self::fail('La ficha no debe aceptar el dato inválido.');
        } catch (CampoInvalido $error) {
            self::assertSame($campo, $error->campo);
        }
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_mascotas')->fetchColumn());
    }

    public static function datosFichaInvalidos(): array
    {
        return [
            [['sexo' => 'Otro'], 'sexo'],
            [['sexo' => ['Hembra']], 'sexo'],
            [['esterilizado' => '2'], 'esterilizado'],
            [['fecha_nacimiento' => '2099-01-01'], 'fecha_nacimiento'],
            [['fecha_nacimiento' => '1900-01-01'], 'fecha_nacimiento'],
            [['fecha_nacimiento' => '2024-02-31'], 'fecha_nacimiento'],
            [['fecha_nacimiento' => ''], 'fecha_nacimiento'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accesosFichaDenegados')]
    public function testCompletarFichaRespetaRolVinculoAislamientoYProteccion(string $caso): void
    {
        if ($caso === 'admin') {
            $_SESSION['id_usuario'] = 1;
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        }
        if ($caso === 'veterinario_inactivo') {
            $this->db->exec("UPDATE usuario_clinica SET estado='inactivo' WHERE id_usuario=2 AND id_clinica=1");
        }
        if ($caso === 'vinculo_inactivo') {
            $this->db->exec("UPDATE mascota_clinica SET estado='desvinculado' WHERE id_mascota=1 AND id_clinica=1");
        }
        if ($caso === 'otra_clinica' || $caso === 'identidad_protegida') {
            $_SESSION['id_usuario'] = 4;
            Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 1);
            if ($caso === 'identidad_protegida') {
                $this->db->exec("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (1,2,'activo')");
            }
        }
        try {
            (new Mascota($this->db))->completarFichaConsulta(1, ['sexo' => 'Macho']);
            self::fail('No debe actualizar sin permiso.');
        } catch (AccesoDenegado $error) {
            self::assertSame(403, $error->getCode());
        }
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_mascotas')->fetchColumn());
        self::assertGreaterThan(0, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
    }

    public static function accesosFichaDenegados(): array
    {
        return array_map(static fn (string $caso): array => [$caso], ['admin', 'veterinario_inactivo', 'vinculo_inactivo', 'otra_clinica', 'identidad_protegida']);
    }

    public function testFinalizarReservaPrimerHuecoEmiteEventosYRepiteSinDuplicar(): void
    {
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,duracion_minutos,margen_minutos,fecha,hora,motivo,estado) VALUES (1,1,2,1,30,10,'2030-01-08','08:00:00','Ocupado','pendiente')");
        $r = $this->finalizar();
        self::assertTrue($r['success'], $r['message']);
        self::assertTrue($r['control']['creado']);
        self::assertSame('09:00:00', $r['control']['hora']);
        self::assertSame('completada', $this->db->query('SELECT estado FROM citas WHERE id_cita=1')->fetchColumn());
        $nueva = $this->db->query('SELECT * FROM citas WHERE id_cita=' . (int) $r['control']['id_cita'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $nueva['id_mascota']);
        self::assertSame(2, (int) $nueva['id_veterinario']);
        self::assertSame('verde', $nueva['prioridad']);
        self::assertGreaterThan(0, (int) $this->db->query('SELECT COUNT(*) FROM eventos_agenda WHERE id_cita=' . (int) $nueva['id_cita'])->fetchColumn());
        $cuenta = (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn();
        self::assertSame($r['control'], (new ControlConsulta($this->db, $this->reloj))->programar($r['id_consulta'], '2030-01-08'));
        self::assertSame($cuenta, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    public function testDiaCerradoPasaAlSiguienteHabilYAvisa(): void
    {
        $this->db->exec('UPDATE horarios_clinica SET activo=0 WHERE id_clinica=1 AND dia_semana=2');
        $r = $this->finalizar();
        self::assertTrue($r['control']['creado']);
        self::assertSame('2030-01-09', $r['control']['fecha']);
        self::assertTrue($r['control']['fecha_ajustada']);
        self::assertStringContainsString('no tenía hueco', $r['control']['message']);
        self::assertNotContains('2030-01-08', (new ControlConsulta($this->db, $this->reloj))->diasAbiertos(1, '2030-01-08', '2030-01-09'));
    }

    public function testSinHorarioConservaConsultaYCitaCompletadaConEnlaceSeguro(): void
    {
        $this->db->exec('UPDATE horarios_veterinario SET activo=0 WHERE id_clinica=1 AND id_veterinario=2');
        $r = $this->finalizar();
        self::assertTrue($r['success']);
        self::assertFalse($r['control']['creado']);
        self::assertStringContainsString('control_mascota=1', $r['control']['url_agendar']);
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
        self::assertSame('completada', $this->db->query('SELECT estado FROM citas WHERE id_cita=1')->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        self::assertFalse((new ControlConsulta($this->db, $this->reloj))->contexto(1)['tiene_horario']);
    }

    public function testCalendarioRestaAusenciasYContextoNoExponeAlertasProtegidas(): void
    {
        $modelo = new ControlConsulta($this->db, $this->reloj);
        self::assertSame(['2030-01-08', '2030-01-09'], $modelo->diasAbiertos(1, '2030-01-08', '2030-01-09'));
        $this->db->exec("INSERT INTO ausencias_veterinario (id_clinica,id_veterinario,fecha_hora_inicio,fecha_hora_fin,motivo) VALUES (1,2,'2030-01-08 00:00:00','2030-01-08 23:59:59','Ausente')");
        self::assertSame(['2030-01-09'], $modelo->diasAbiertos(1, '2030-01-08', '2030-01-09'));
        self::assertArrayNotHasKey('alertas_activas', $modelo->contexto(1));
    }

    public function testCambioDeIdentidadEntreLecturaYCandadoNoReservaMascotaAnterior(): void
    {
        $_POST['control_activo'] = '0';
        $r = $this->finalizar();
        $this->db->exec("INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,estado) VALUES (2,6,1,'" . str_repeat('z', 43) . "','Ficha actual',1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (2,1,'activo')");
        $cambiada = false;
        $this->db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LecturaControlConsultaPrueba::class, [function (string $sql) use (&$cambiada): void {
            if ($cambiada || !str_contains($sql, 'SELECT id_consulta, id_mascota, id_veterinario FROM consultas')) return;
            $cambiada = true;
            $this->db->exec('UPDATE consultas SET id_mascota=2');
            $this->db->exec('UPDATE citas SET id_mascota=2');
        }]]);
        try {
            (new ControlConsulta($this->db, $this->reloj))->programar($r['id_consulta'], '2030-01-08');
            self::fail('Reservó con la identidad anterior.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('identidad', $e->getMessage());
        }
        self::assertTrue($cambiada);
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    public function testReplayRevalidaVinculoQueCambioAntesDelCandado(): void
    {
        $r = $this->finalizar();
        self::assertTrue($r['control']['creado']);
        $cambiada = false;
        $this->db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LecturaControlConsultaPrueba::class, [function (string $sql) use (&$cambiada): void {
            if ($cambiada || !str_contains($sql, 'SELECT control_busqueda_dias')) return;
            $cambiada = true;
            $this->db->exec("UPDATE mascota_clinica SET estado='desvinculado' WHERE id_clinica=1");
        }]]);
        $this->expectException(AccesoDenegado::class);
        (new ControlConsulta($this->db, $this->reloj))->programar($r['id_consulta'], '2030-01-08');
    }

    public function testVinculoInactivoNiegaCalendario(): void
    {
        $this->db->exec("UPDATE mascota_clinica SET estado='desvinculado' WHERE id_clinica=1");
        $this->expectException(AccesoDenegado::class);
        (new ControlConsulta($this->db, $this->reloj))->diasAbiertos(1, '2030-01-08', '2030-01-09');
    }

    public function testOtraClinicaNoPuedeCrearControlDeConsultaAjena(): void
    {
        $r = $this->finalizar();
        $_SESSION['id_usuario'] = 4;
        Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 1);
        $this->expectException(AccesoDenegado::class);
        (new ControlConsulta($this->db, $this->reloj))->programar($r['id_consulta'], '2030-01-08');
    }

    public function testSoloVeterinarioPuedeConsultarCalendario(): void
    {
        $_SESSION['id_usuario'] = 1;
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $this->expectException(AccesoDenegado::class);
        (new ControlConsulta($this->db, $this->reloj))->contexto(1);
    }
}
