<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../scripts/semilla_demo.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../helpers/EnviadorRecordatorios.php';

/** Plan semilla-demo: motor real, cuenta ajena protegida y catálogo de producción leído, nunca modificado en archivos. */
final class SemillaDemoTest extends TestCase
{
    private static ?PDO $db = null;
    private SemillaDemo $semilla;
    private DateTimeImmutable $ahora;

    public static function setUpBeforeClass(): void
    {
        if (!getenv('ZOOKI_TEST_MYSQL_HOST')) {
            self::markTestSkipped('Sin motor local de pruebas.');
        }
        if ((getenv('ZOOKI_TEST_MYSQL_DB') ?: 'zooki_test_base_v2') !== 'zooki_test_base_v2') {
            throw new RuntimeException('Solo la base predeterminada de pruebas.');
        }
        self::$db = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', getenv('ZOOKI_TEST_MYSQL_HOST'), (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306)), getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root', getenv('ZOOKI_TEST_MYSQL_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::$db->exec('DROP DATABASE IF EXISTS zooki_test_base_v2');
        self::$db->exec('CREATE DATABASE zooki_test_base_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        self::$db->exec('USE zooki_test_base_v2');
        self::$db->exec(file_get_contents(__DIR__ . '/../../database/01_schema.sql'));
        (new Migrador(self::$db, __DIR__ . '/../../database'))->migrar();
        (new CargadorConocimiento(self::$db))->cargar(__DIR__ . '/../../database/conocimiento');
        DosClinicas::poblar(self::$db);
        self::$db->exec("UPDATE clinicas SET nombre='Zooki',email_contacto='zooki.vet@gmail.com' WHERE id_clinica=1");
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->ahora = new DateTimeImmutable('2026-11-02T10:30:00-05:00');
        $this->semilla = new SemillaDemo(self::$db, fn () => $this->ahora);
        $this->semilla->borrar();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db instanceof PDO) {
            self::$db->exec('DROP DATABASE IF EXISTS zooki_test_base_v2');
            self::$db = null;
        }
    }

    private function filas(string $sql, array $parametros = []): array
    {
        $q = self::$db->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function idClinica(string $clave): int
    {
        return (int) $this->filas('SELECT id_clinica FROM clinicas WHERE nit=?', [ValidadorNit::normalizarEntrada(GuionDemo::nit($clave))])[0]['id_clinica'];
    }

    private function idUsuario(string $alias): int
    {
        return (int) $this->filas('SELECT id_usuario FROM usuarios WHERE email=?', [GuionDemo::correo($alias)])[0]['id_usuario'];
    }

    public function testRepetibleGuionAislamientoYBorradoProtegido(): void
    {
        $ajenas = $this->filas('SELECT * FROM clinicas ORDER BY id_clinica');
        $personasAjenas = $this->filas('SELECT * FROM usuarios ORDER BY id_usuario');
        $mascotasAjenas = $this->filas('SELECT * FROM mascotas ORDER BY id_mascota');
        $super = $this->filas('SELECT * FROM usuarios WHERE es_super_admin=1');
        $conocimiento = $this->filas('SELECT codigo,version_conocimiento FROM grafo_nodos ORDER BY codigo');
        $primera = $this->semilla->crear('2026-11-02', 'Arboles7Lejanos');
        $segunda = $this->semilla->crear('2026-11-02', 'Arboles7Lejanos');
        $this->assertSame($primera['conteos'], $segunda['conteos']);
        $this->assertSame(5, $segunda['conteos']['clinicas']);
        $this->assertSame(41, $segunda['conteos']['usuarios']);
        $this->assertSame(42, $segunda['conteos']['mascotas']);
        $this->assertSame(128, $segunda['conteos']['consultas']);
        $this->assertSame(3, $segunda['correos']['recordatorios_dosis']);
        $this->assertSame(0, $segunda['correos']['eventos_pendientes']);
        $this->assertSame(15, $segunda['correos']['recordatorios_citas_por_dia']['2026-11-02']);
        $this->assertSame(3, $segunda['correos']['maximo_sin_recordatorios_citas']);
        $this->assertCount(41, $segunda['cuentas']);
        foreach ($segunda['cuentas'] as $cuenta) {
            $this->assertTrue(password_verify($cuenta['clave'], $this->filas('SELECT password FROM usuarios WHERE email=?', [$cuenta['correo']])[0]['password']));
        }
        foreach (['Luna' => 'naranja', 'Kira' => 'naranja', 'Nala' => 'amarillo', 'Toby' => 'verde'] as $nombre => $nivel) {
            $this->assertSame($nivel, $segunda['niveles'][$nombre]['nivel']);
        }
        $this->assertFalse($segunda['niveles']['Kira']['revision']);
        $this->assertArrayNotHasKey('Simón', $segunda['niveles']);
        $this->assertSame([], $this->filas("SELECT s.id_solicitud FROM solicitudes_agenda s JOIN mascotas m ON m.id_mascota=s.id_mascota WHERE m.nombre='Simón' AND s.nivel_calculado='rojo'"));
        $clinica = $this->idClinica('huellitas');
        $vet = $this->idUsuario('huellitas-vet-1');
        SemillaDemo::como($vet, $clinica, Roles::VETERINARIO);
        $mascotas = (new Mascota(self::$db))->getAll();
        $this->assertCount(8, $mascotas);
        $this->assertContains('Nala', array_column($mascotas, 'nombre'));
        $this->assertNotContains('Rocco', array_column($mascotas, 'nombre'));
        $this->assertNotContains('Simón', array_column($mascotas, 'nombre'));
        $this->assertSame(['uso' => 2, 'limite' => 2], (new LimitePlan(self::$db, $clinica))->resumen()['personal']);
        $this->assertCount(5, $this->filas("SELECT c.id_clinica FROM clinicas c WHERE c.email_contacto LIKE 'zooki.vet+demo-%@gmail.com' AND c.direccion LIKE '%Florencia%'") );
        $this->assertSame('pendiente_verificacion', $this->filas('SELECT estado FROM clinicas WHERE id_clinica=?', [$this->idClinica('petcare')])[0]['estado']);
        $this->assertSame('suspendida', $this->filas('SELECT estado FROM clinicas WHERE id_clinica=?', [$this->idClinica('estrella')])[0]['estado']);
        $this->assertCount(42, $this->filas("SELECT DISTINCT m.token_carnet FROM mascotas m JOIN usuarios u ON u.id_usuario=m.id_propietario WHERE u.email LIKE 'zooki.vet+demo-%@gmail.com' AND m.carnet_activo=1 AND CHAR_LENGTH(m.token_carnet)=43"));
        $this->comprobarPrescripcionDelGuion();
        // Incluso un vínculo inactivo ajeno impide borrar personas o fichas globales (RNF-11).
        $portal = $this->idUsuario('portal');
        self::$db->prepare("INSERT INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (?,2,'inactivo')")->execute([$portal]);
        try {
            $this->semilla->borrar();
            $this->fail('Se aceptó borrar una persona con vínculo ajeno inactivo.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('clínica ajena', $error->getMessage());
            $this->assertCount(5, $this->filas("SELECT id_clinica FROM clinicas WHERE email_contacto LIKE 'zooki.vet+demo-%@gmail.com'"));
        } finally {
            self::$db->prepare('DELETE FROM propietario_clinica WHERE id_propietario=? AND id_clinica=2')->execute([$portal]);
        }
        self::$db->prepare("INSERT INTO verificaciones_email (id_usuario,id_clinica_vinculo,proposito,email,token_hash,expires_at) VALUES (?,2,'invitacion_personal',?,?,?)")->execute([$portal, GuionDemo::correo('portal'), password_hash('token-ficticio', PASSWORD_DEFAULT), '2026-11-10 18:00:00']);
        $invitacion = (int) self::$db->lastInsertId();
        try {
            $this->semilla->borrar();
            $this->fail('Se aceptó borrar una invitación pendiente de otra clínica.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('clínica ajena', $error->getMessage());
            $this->assertCount(1, $this->filas('SELECT id FROM verificaciones_email WHERE id=?', [$invitacion]));
        } finally {
            self::$db->prepare('DELETE FROM verificaciones_email WHERE id=?')->execute([$invitacion]);
        }
        $borrado = $this->semilla->borrar();
        $this->assertSame(0, array_sum($borrado['conteos']));
        $this->assertSame($ajenas, $this->filas('SELECT * FROM clinicas ORDER BY id_clinica'));
        $this->assertSame($personasAjenas, $this->filas('SELECT * FROM usuarios ORDER BY id_usuario'));
        $this->assertSame($mascotasAjenas, $this->filas('SELECT * FROM mascotas ORDER BY id_mascota'));
        $this->assertSame($super, $this->filas('SELECT * FROM usuarios WHERE es_super_admin=1'));
        $this->assertSame($conocimiento, $this->filas('SELECT codigo,version_conocimiento FROM grafo_nodos ORDER BY codigo'));
    }

    private function comprobarPrescripcionDelGuion(): void
    {
        $clinica = $this->idClinica('sanroque');
        SemillaDemo::como($this->idUsuario('sanroque-vet-1'), $clinica, Roles::VETERINARIO);
        $grafo = (new GrafoConocimiento(self::$db))->instantanea();
        $paciente = new PacienteDiagnostico(self::$db);
        $rocco = (int) $this->filas("SELECT id_mascota FROM mascotas WHERE nombre='Rocco'")[0]['id_mascota'];
        $contexto = $paciente->contexto($rocco, $grafo, $this->ahora, []);
        $resultado = MotorPrescripcion::sugerir('diagnostico.sarna_sarcoptica', 28, $contexto, [], [], $grafo, $this->ahora);
        $this->assertSame('farmaco.selamectina', $resultado['tratamientos'][0]['farmaco']);
        $this->assertNotContains('farmaco.ivermectina', array_column($resultado['tratamientos'], 'farmaco'));
        $bloqueo = MotorPrescripcion::evaluar('farmaco.ivermectina', $contexto, [], [], [], $grafo, $this->ahora);
        $this->assertTrue($bloqueo['bloqueada']);
        $this->assertNotEmpty($bloqueo['motivos'][0]['fuentes']);
        $max = (int) $this->filas("SELECT id_mascota FROM mascotas WHERE nombre='Max'")[0]['id_mascota'];
        $contexto = $paciente->contexto($max, $grafo, $this->ahora, []);
        $vigentes = $this->filas('SELECT n.codigo AS farmaco FROM tratamientos t JOIN consultas c ON c.id_consulta=t.id_consulta JOIN grafo_nodos n ON n.id_nodo=t.id_nodo_farmaco WHERE c.id_mascota=? AND t.fecha_fin IS NULL', [$max]);
        $this->assertContains('farmaco.meloxicam', array_column($vigentes, 'farmaco'));
        $resultado = MotorPrescripcion::sugerir('diagnostico.dermatitis_atopica_canina', 30, $contexto, [], $vigentes, $grafo, $this->ahora);
        $prednisolona = array_column($resultado['tratamientos'], null, 'farmaco')['farmaco.prednisolona'];
        $this->assertFalse($prednisolona['precargar']);
        $this->assertTrue($prednisolona['seguridad']['requiere_decision']);
        $this->assertContains('interaccion_protegida', array_column($prednisolona['seguridad']['advertencias'], 'tipo'));
        $mishi = (int) $this->filas("SELECT id_mascota FROM mascotas WHERE nombre='Mishi'")[0]['id_mascota'];
        $contexto = $paciente->contexto($mishi, $grafo, $this->ahora, []);
        $alergias = $this->filas("SELECT n.codigo AS farmaco FROM alertas_medicas a JOIN grafo_nodos n ON n.id_nodo=a.id_nodo WHERE a.id_mascota=? AND a.tipo='alergia' AND a.activa=1", [$mishi]);
        $this->assertTrue(MotorPrescripcion::evaluar($alergias[0]['farmaco'], $contexto, $alergias, [], [], $grafo, $this->ahora)['bloqueada']);
    }

    public function testEscenaAtomicaUnaAtencionLlegadaYCascada(): void
    {
        $this->semilla->crear('2026-11-02', 'Arboles7Lejanos');
        $clinica = $this->idClinica('sanroque');
        $vet = $this->idUsuario('sanroque-vet-1');
        $dias = $this->filas('SELECT fecha,id_veterinario,COUNT(*) AS cantidad FROM citas WHERE id_clinica=? AND fecha>=? GROUP BY fecha,id_veterinario ORDER BY fecha,id_veterinario', [$clinica, '2026-11-02']);
        $this->assertCount(18, $dias);
        foreach ($dias as $dia) {
            $this->assertSame($dia['fecha'] === '2026-11-02' ? 6 : 5, (int) $dia['cantidad']);
            $this->assertNotSame('7', (new DateTimeImmutable($dia['fecha']))->format('N'));
        }
        $ajenas = $this->filas('SELECT id_cita,id_veterinario,fecha,hora,duracion_minutos,margen_minutos FROM citas WHERE id_clinica=? AND id_veterinario<>? ORDER BY id_cita', [$clinica, $vet]);
        $historiaAntes = $this->filas("SELECT id_consulta FROM consultas WHERE fecha_hora<'2026-11-02' ORDER BY id_consulta");
        foreach (['08:00', '08:05', '09:00', '12:30', '14:00', '15:00', '16:00', '17:55', '18:00'] as $hora) {
            $this->ahora = new DateTimeImmutable('2026-11-02T' . $hora . ':00-05:00');
            $escena = $this->semilla->escena();
            $citas = $this->filas("SELECT * FROM citas WHERE id_clinica=? AND fecha='2026-11-02' AND id_veterinario=?", [$clinica, $vet]);
            $this->assertCount(1, array_filter($citas, static fn (array $c): bool => $c['estado'] === 'en_curso'), $hora);
            $completadas = array_filter($citas, static fn (array $c): bool => $c['estado'] === 'completada');
            $this->assertLessThanOrEqual(3, count($completadas));
            $this->assertSame(in_array($hora, ['08:00', '08:05'], true) ? 1 : 3, count($completadas));
            $this->assertSame([], array_values(array_filter($citas, static fn (array $c): bool => in_array($c['estado'], ['sin_cerrar', 'pausada'], true))));
            $rocco = $this->filas('SELECT * FROM citas WHERE id_cita=?', [$escena['rocco']])[0];
            $this->assertSame($escena['en_curso'], $escena['rocco']);
            $this->assertSame('en_curso', $rocco['estado']);
            $this->assertSame(30, (int) $rocco['duracion_minutos']);
            $this->assertSame('Rocco', $this->filas('SELECT nombre FROM mascotas WHERE id_mascota=?', [$rocco['id_mascota']])[0]['nombre']);
            $this->assertNotNull($rocco['hora_llegada']);
            $this->assertLessThan($rocco['hora_inicio_real'], $rocco['hora_llegada']);
            $this->assertSame([], $this->filas("SELECT c.id_cita FROM citas c JOIN mascotas m ON m.id_mascota=c.id_mascota WHERE c.id_clinica=? AND c.fecha='2026-11-02' AND m.nombre='Mila' AND c.estado<>'completada'", [$clinica]));
            $this->assertCount(1, $this->filas("SELECT c.id_cita FROM citas c JOIN mascotas m ON m.id_mascota=c.id_mascota WHERE c.id_clinica=? AND c.fecha='2026-11-02' AND m.nombre='Mila' AND c.estado='completada'", [$clinica]));
            $this->assertSame([], $this->filas("SELECT s.id_solicitud FROM solicitudes_agenda s JOIN mascotas m ON m.id_mascota=s.id_mascota WHERE s.id_clinica=? AND m.nombre='Simón' AND JSON_UNQUOTE(JSON_EXTRACT(s.contexto_triage,'$.entrada.fecha'))='2026-11-02'", [$clinica]));
            $this->assertSame($this->ahora->modify('-35 minutes')->format('Y-m-d H:i:s'), $this->filas('SELECT hora_inicio_real FROM citas WHERE id_cita=?', [$escena['en_curso']])[0]['hora_inicio_real']);
            $pendientes = array_filter($citas, static fn (array $c): bool => $c['estado'] === 'confirmada');
            $this->assertGreaterThanOrEqual(3, count($pendientes));
            foreach ($pendientes as $cita) {
                $this->assertNotNull($cita['inicio_estimado']);
                $this->assertNotNull($cita['fecha_ultimo_calculo']);
                $this->assertLessThanOrEqual('2026-11-02 20:00:00', (new DateTimeImmutable($cita['fin_estimado']))->modify('+' . $cita['margen_minutos'] . ' minutes')->format('Y-m-d H:i:s'), $hora);
            }
            $this->assertCount(1, array_filter($pendientes, static fn (array $c): bool => (bool) $c['es_sobrecupo'] && $c['prioridad'] === 'naranja'));
            $this->assertLessThanOrEqual(5, $escena['correos']['maximo_sin_recordatorios_citas']);
            $this->assertSame([], $this->filas("SELECT id_evento FROM eventos_agenda WHERE id_clinica=? AND canal='correo' AND estado IN ('pendiente','error') AND id_cita<>?", [$clinica, $escena['rocco']]));
            $this->assertSame($ajenas, $this->filas('SELECT id_cita,id_veterinario,fecha,hora,duracion_minutos,margen_minutos FROM citas WHERE id_clinica=? AND id_veterinario<>? ORDER BY id_cita', [$clinica, $vet]));
            $this->assertSame([], $this->filas("SELECT id_cita FROM citas WHERE id_clinica=? AND fecha='2026-11-02' AND estado_reajuste IN ('pendiente_revision','pendiente_reasignacion','pendiente_reprogramacion')", [$clinica]), $hora);
            $this->assertSame([], $this->filas("SELECT r.id_reasignacion FROM reasignaciones r JOIN citas c ON c.id_cita=r.id_cita AND c.id_clinica=r.id_clinica WHERE r.id_clinica=? AND c.fecha='2026-11-02' AND r.estado='pendiente'", [$clinica]), $hora);
            $otras = $this->filas("SELECT * FROM citas WHERE id_clinica=? AND fecha='2026-11-02' AND id_veterinario<>? ORDER BY id_veterinario,hora", [$clinica, $vet]);
            $extremos = [];
            foreach ($otras as $otra) {
                if ($otra['hora'] < $hora . ':00') {
                    $this->assertSame('completada', $otra['estado']);
                    $this->assertSame($otra['fecha'] . ' ' . $otra['hora'], $otra['hora_inicio_real']);
                    $this->assertGreaterThan($otra['hora_inicio_real'], $otra['hora_fin_real']);
                    $this->assertLessThanOrEqual($this->ahora->format('Y-m-d H:i:s'), $otra['hora_fin_real']);
                    $anterior = $extremos[$otra['id_veterinario']] ?? '2026-11-02 07:00:00';
                    $this->assertGreaterThanOrEqual($anterior, $otra['hora_inicio_real']);
                    $extremos[$otra['id_veterinario']] = $otra['hora_fin_real'];
                } else {
                    $this->assertSame('confirmada', $otra['estado']);
                    $this->assertNotNull($otra['inicio_estimado']);
                    $this->assertLessThanOrEqual('2026-11-02 20:00:00', (new DateTimeImmutable($otra['fin_estimado']))->modify('+' . $otra['margen_minutos'] . ' minutes')->format('Y-m-d H:i:s'));
                }
            }
        }
        $primera = $this->semilla->escena();
        $this->assertSame($escena['cascada']['calculadas'], $primera['cascada']['calculadas']);
        // El Portal crea la única derivación del día; rehacer la escena limpia esa evaluación real.
        SemillaDemo::como($this->idUsuario('portal'), null, Roles::PROPIETARIO);
        $simon = (int) $this->filas("SELECT m.id_mascota FROM mascotas m JOIN usuarios u ON u.id_usuario=m.id_propietario WHERE m.nombre='Simón' AND u.email=?", [GuionDemo::correo('portal')])[0]['id_mascota'];
        $tipo = (int) $this->filas("SELECT id_tipo_cita FROM tipos_cita WHERE id_clinica=? AND nombre_tipo='Consulta general/Enfermedad'", [$clinica])[0]['id_tipo_cita'];
        $evaluacion = (new SolicitudAgenda(self::$db, fn () => $this->ahora))->enClinicaDelPropietario($clinica)->evaluar(['id_mascota' => $simon, 'id_veterinario' => $this->idUsuario('sanroque-vet-2'), 'id_tipo_cita' => $tipo, 'fecha' => '2026-11-02', 'sintomas' => GuionDemo::episodios(['intentos_orinar_minimo', 'dolor_orinar'])]);
        $this->assertSame('rojo', $evaluacion['nivel']);
        $this->assertSame('derivada', $this->filas('SELECT estado FROM solicitudes_agenda WHERE id_solicitud=?', [$evaluacion['id_solicitud']])[0]['estado']);
        $this->semilla->escena();
        $this->assertSame([], $this->filas("SELECT id_solicitud FROM solicitudes_agenda WHERE id_mascota=? AND nivel_calculado='rojo'", [$simon]));
        $this->comprobarCronsSinSmtp($primera['correos']);
        $this->assertSame($historiaAntes, $this->filas("SELECT id_consulta FROM consultas WHERE fecha_hora<'2026-11-02' ORDER BY id_consulta"));
        $this->assertSame([], $this->filas('SELECT id_pausa FROM pausas_cita WHERE id_clinica=? AND fin IS NULL', [$clinica]));
        foreach (['2026-11-08T10:30:00-05:00', '2026-11-02T07:59:00-05:00', '2026-11-02T18:01:00-05:00'] as $fecha) {
            $this->ahora = new DateTimeImmutable($fecha);
            $antes = $this->semilla->conteos();
            try {
                $this->semilla->escena();
                $this->fail('Fuera de la franja permitida se debe rechazar.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('horario aprobado', $error->getMessage());
            }
            $this->assertSame($antes, $this->semilla->conteos());
        }
    }

    private function comprobarCronsSinSmtp(array $previstos): void
    {
        $correo = new class {
            public int $enviados = 0;
            public function obtenerPlantillaBaseHTML(...$argumentos): string
            {
                return 'Correo ficticio';
            }
            public function limpiarDirecciones(): void
            {
            }
            public function enviarCorreoPersonalizado(...$argumentos): bool
            {
                $this->enviados++;
                return true;
            }
        };
        $agenda = EventoAgenda::despacharPendientes(self::$db, static fn (array $evento): bool => true);
        ob_start();
        try {
            (new EnviadorRecordatorios(new Recordatorio(self::$db), $correo))->ejecutar($this->ahora, 'https://demo.invalid/index.php');
        } finally {
            ob_end_clean();
        }
        $diarios = $previstos['recordatorios_citas_por_dia'][$this->ahora->format('Y-m-d')];
        $this->assertSame($diarios + $previstos['recordatorios_dosis'], $correo->enviados);
        $this->assertLessThanOrEqual(5, $agenda + $correo->enviados - $diarios);
    }

    public function testSimulacionGuardasYCodigoFaltanteNoEscriben(): void
    {
        $antes = $this->filas('SELECT * FROM clinicas ORDER BY id_clinica');
        $this->assertSame(5, $this->semilla->revisar()['clinicas']);
        $this->assertSame($antes, $this->filas('SELECT * FROM clinicas ORDER BY id_clinica'));
        $this->assertSame('simular', ComandoSemillaDemo::opciones(['semilla_demo.php', '--entorno=local'])['modo']);
        foreach ([['semilla_demo.php'], ['semilla_demo.php', '--entorno=produccion', '--si', '--borrar'], ['semilla_demo.php', '--entorno=local', '--si', '--dia=2026-02-30']] as $argumentos) {
            try {
                ComandoSemillaDemo::opciones($argumentos);
                $this->fail('Opciones incompatibles aceptadas.');
            } catch (InvalidArgumentException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        try {
            ComandoSemillaDemo::confirmar('produccion', 'pruebas');
            $this->fail('Confirmación cruzada aceptada.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('confirmación', $error->getMessage());
        }
        self::$db->exec("UPDATE grafo_nodos SET activo=0 WHERE codigo='farmaco.ivermectina'");
        try {
            $this->semilla->crear('2026-11-02', 'Arboles7Lejanos');
            $this->fail('La falta de código permite crear una demo parcial.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('farmaco.ivermectina', $error->getMessage());
            $this->assertStringContainsString('php scripts/cargar_conocimiento.php', $error->getMessage());
        } finally {
            self::$db->exec("UPDATE grafo_nodos SET activo=1 WHERE codigo='farmaco.ivermectina'");
        }
        $this->assertSame($antes, $this->filas('SELECT * FROM clinicas ORDER BY id_clinica'));
    }

    public function testColisionesSuperadministradorMigracionYClaveSeRechazanAntesDeEscribir(): void
    {
        $clinicas = $this->filas('SELECT * FROM clinicas ORDER BY id_clinica');
        $super = $this->filas('SELECT * FROM usuarios WHERE es_super_admin=1')[0];
        self::$db->prepare('UPDATE usuarios SET email=? WHERE id_usuario=?')->execute([GuionDemo::correo('protegido'), $super['id_usuario']]);
        try {
            $this->semilla->borrar();
            $this->fail('Se aceptó eliminar un super-administrador del espacio demo.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('super-administradora', $error->getMessage());
        } finally {
            self::$db->prepare('UPDATE usuarios SET email=? WHERE id_usuario=?')->execute([$super['email'], $super['id_usuario']]);
        }
        self::$db->prepare('UPDATE clinicas SET nit=? WHERE id_clinica=1')->execute([ValidadorNit::normalizarEntrada(GuionDemo::nit('sanroque'))]);
        try {
            $this->semilla->crear('2026-11-02');
            $this->fail('Se aceptó sustituir Zooki por una clínica demo.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('clínica no reconocida', $error->getMessage());
        } finally {
            self::$db->prepare('UPDATE clinicas SET nit=? WHERE id_clinica=1')->execute([$clinicas[0]['nit']]);
        }
        self::$db->exec("UPDATE schema_migraciones SET archivo='18_prueba_pendiente.sql' WHERE archivo='18_razas_mdr1.sql'");
        try {
            $this->semilla->crear('2026-11-02');
            $this->fail('Se aceptó crear sin la migración18 registrada.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('php scripts/migrar.php', $error->getMessage());
        } finally {
            self::$db->exec("UPDATE schema_migraciones SET archivo='18_razas_mdr1.sql' WHERE archivo='18_prueba_pendiente.sql'");
        }
        try {
            $this->semilla->crear('2026-11-02', 'corta');
            $this->fail('Se aceptó una clave fuera de política.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('política', $error->getMessage());
        }
        $this->assertSame($clinicas, $this->filas('SELECT * FROM clinicas ORDER BY id_clinica'));
        $this->assertSame($super, $this->filas('SELECT * FROM usuarios WHERE es_super_admin=1')[0]);
    }
}
