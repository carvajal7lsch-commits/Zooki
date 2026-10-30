<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/SolicitudAgenda.php';
require_once __DIR__ . '/../../models/CoordinacionHorarios.php';
require_once __DIR__ . '/../../models/MascotaPropietario.php';
require_once __DIR__ . '/../../helpers/ValoresInicialesClinica.php';

/** Reproducciones del dueño: cada caso se ejecuta primero contra 379515f. */
final class CorreccionesDueno230Test extends TestCase
{
    private PDO $db;
    private Closure $reloj;
    private array $entrada;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        ConocimientoRealPrueba::taxonomia($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
        $this->como(2, Roles::VETERINARIO);
        $this->reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 07:40:00');
        $this->entrada = ['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07',
            'sintomas' => [['codigo' => 'sintoma.otro_no_se', 'tiempo' => 'no_se']]];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function como(int $id, int $rol): void
    {
        $_SESSION = ['id_usuario' => $id];
        Contexto::activar($rol === Roles::PROPIETARIO ? Contexto::dePropietario() : Contexto::deClinica(1, 'Norte', $rol), 1);
    }

    public function test01LunesAViernesOchoDoceYCatorceDieciochoProponeYAvisaSoloAlAdminDeSuClinica(): void
    {
        $this->db->exec('UPDATE horarios_clinica SET activo=0 WHERE dia_semana IN (6,7)');
        $franjas = [];
        foreach (range(1, 5) as $dia) {
            foreach ([['08:00', '12:00'], ['14:00', '18:00']] as [$desde, $hasta]) {
                $franjas[] = ['dia_semana' => $dia, 'hora_inicio' => $desde, 'hora_fin' => $hasta];
            }
        }
        $r = (new CoordinacionHorarios($this->db))->proponer(2, $franjas);
        $this->assertSame('pendiente', $r['estado']);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones_internas WHERE id_clinica=1 AND id_rol_destino=1 AND tipo='PROPUESTA_HORARIO'")->fetchColumn());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones_internas WHERE id_clinica=2")->fetchColumn());
        $this->expectException(CampoInvalido::class);
        (new CoordinacionHorarios($this->db))->proponer(2, [...$franjas, ['dia_semana' => 6, 'hora_inicio' => '08:00', 'hora_fin' => '12:00']]);
    }

    public function test02PortalNoOfreceVeterinarioSinHorarioAprobado(): void
    {
        $this->db->exec('UPDATE horarios_veterinario SET activo=0 WHERE id_clinica=1 AND id_veterinario=2');
        $this->como(6, Roles::PROPIETARIO);
        $vets = (new Cita($this->db))->enClinicaDelPropietario(1)->veterinariosActivos();
        $this->assertNotContains(2, array_map('intval', array_column($vets, 'id_usuario')));
        $this->assertContains(5, array_map('intval', array_column($vets, 'id_usuario')));
    }

    public function test04PropietarioVeterinarioCreaSuMascotaVinculadaYReservaConAuditoria(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (2,1,'activo'),(2,2,'activo')");
        $this->como(2, Roles::PROPIETARIO);
        $id = (new MascotaPropietario($this->db))->registrar(['nombre' => 'Propia', 'id_especie' => 1, 'sexo' => 'Macho']);
        $this->assertSame([1, 2], array_map('intval', $this->db->query('SELECT id_clinica FROM mascota_clinica WHERE id_mascota=' . $id . " AND estado='activo' ORDER BY id_clinica")->fetchAll(PDO::FETCH_COLUMN)));
        $entrada = ['id_mascota' => $id] + $this->entrada;
        $agenda = (new SolicitudAgenda($this->db, $this->reloj))->enClinicaDelPropietario(1);
        $r = $agenda->evaluar($entrada);
        $reserva = $agenda->reservar($entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '08:00']);
        $this->assertArrayHasKey('id_cita', $reserva);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='citas' AND descripcion LIKE '%RN-G18%'")->fetchColumn());
        $this->como(2, Roles::VETERINARIO);
        $entrada['fecha'] = '2030-01-08';
        $agenda = new SolicitudAgenda($this->db, $this->reloj);
        $r = $agenda->evaluar($entrada);
        $this->assertArrayHasKey('id_cita', $agenda->reservar($entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '08:00']));
    }

    public function test06CirugiaInicialEsSoloPersonalYNoPausable(): void
    {
        $cirugias = array_values(array_filter(ValoresInicialesClinica::TIPOS, static fn ($tipo) => $tipo[1] === 'Cirugía programada'));
        $this->assertCount(1, $cirugias);
        $this->assertSame([120, 15, 0, 1], [$cirugias[0][2], $cirugias[0][6], $cirugias[0][7], $cirugias[0][8]]);
    }

    public function test06FinAnticipadoLiberaDesdeFinRealSinMargen(): void
    {
        $datos = ['fecha' => '2030-01-07', 'hora' => '08:00:00', 'estado' => 'completada',
            'hora_inicio_real' => '2030-01-07 08:00:00', 'hora_fin_real' => '2030-01-07 09:00:00',
            'duracion_activa_objetivo_segundos' => 7200, 'margen_minutos' => 15];
        $r = PoliticaTemporalAgenda::calcular($datos, [], IntervalosAgenda::fecha('2030-01-07 09:00:00'));
        $this->assertSame(strtotime('2030-01-07 09:00:00'), $r['extremo']);
    }

    public function test07AmarilloVeTodosYEligeUnoPosterior(): void
    {
        $agenda = new SolicitudAgenda($this->db, $this->reloj);
        $r = $agenda->evaluar($this->entrada);
        $this->assertGreaterThan(3, count($agenda->horas($r['id_solicitud'], $this->entrada)));
        $guardada = $agenda->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '10:00']);
        $this->assertSame('10:00:00', Cita::normalizarHora($guardada['hora']));
    }

    public function test09VeterinarioNoCancelaDentroDeTreintaMinutos(): void
    {
        $citas = new Cita($this->db, $this->reloj);
        $id = $citas->registrar($this->entrada + ['hora' => '08:00', 'motivo' => 'Prueba']);
        $this->expectException(InvalidArgumentException::class);
        $citas->cancelar($citas->getById($id));
    }

    public function test06FinRealLiberaReservaYRecuperaRetrasoEnLaCascada(): void
    {
        $this->db->exec("UPDATE tipos_cita SET duracion_minutos=120,margen_minutos=15,pausable=0 WHERE id_tipo_cita=1");
        $this->db->exec("INSERT INTO tipos_cita (id_tipo_cita,id_clinica,nombre_tipo,duracion_minutos,margen_minutos) VALUES (3,1,'Control',30,0)");
        $this->db->exec("INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,id_especie,estado) VALUES (2,6,1,'otro-token','Otro',1,1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (2,1,'activo')");
        $citas = new Cita($this->db, $this->reloj);
        $id = $citas->registrar($this->entrada + ['hora' => '08:00', 'motivo' => 'Cirugía']);
        $siguiente = $citas->registrar(['id_mascota' => 2, 'id_tipo_cita' => 3, 'hora' => '10:15', 'motivo' => 'Control'] + $this->entrada);
        $citas->iniciarAtencion($citas->getById($id), '2030-01-07 08:30:00');
        $this->assertSame('2030-01-07 10:45:00', $citas->getById($siguiente)['inicio_estimado']);
        $citas->completarAtencion($citas->getById($id), '2030-01-07 09:00:00');
        $this->assertSame('2030-01-07 10:15:00', $citas->getById($siguiente)['inicio_estimado']);
        $this->assertSame('10:15', substr($citas->getById($siguiente)['hora'], 0, 5));
        $ahora = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 09:00:00'));
        $this->assertContains('09:00', $ahora->sugerencias(2, 1, 3, '2030-01-07'));
    }

    public function test06SoloPersonalNoSeListaNiSeReservaPorPostDelPortal(): void
    {
        $this->db->exec('UPDATE tipos_cita SET solo_personal=1 WHERE id_tipo_cita=1');
        $this->como(6, Roles::PROPIETARIO);
        $catalogo = (new CatalogoClinica($this->db))->enClinicaDelPropietario(1);
        $this->assertSame([], $catalogo->tiposCita());
        $this->assertNull($catalogo->tipoCita(1));
        $agenda = (new SolicitudAgenda($this->db, $this->reloj))->enClinicaDelPropietario(1);
        $r = $agenda->evaluar($this->entrada);
        $this->expectException(InvalidArgumentException::class);
        $agenda->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '08:00']);
    }

    public function test07NaranjaMuestraTresAsignaPrimeroLibreYNoSaltaOcupacion(): void
    {
        $citas = new Cita($this->db, $this->reloj);
        $citas->registrar($this->entrada + ['hora' => '08:00', 'motivo' => 'Ocupada']);
        $agenda = new SolicitudAgenda($this->db, $this->reloj);
        $r = $agenda->evaluar($this->entrada);
        $r = $agenda->revisar($r['id_solicitud'], 'naranja', 'Urgente al examen', $r['huella'], null);
        $horas = $agenda->horas($r['id_solicitud'], $this->entrada);
        $this->assertCount(3, $horas);
        $this->assertSame('09:00:00', Cita::normalizarHora($horas[0]));
        $this->assertStringContainsString('Nivel naranja: se le asigna el primer horario libre', $r['message']);
        $guardada = $agenda->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => $horas[2], 'ignore_warning' => '1']);
        $this->assertSame('09:00:00', Cita::normalizarHora($guardada['hora']));
    }

    public function test09PlazoExactoCeroPortalReprogramacionYAdminConMotivo(): void
    {
        $citas = new Cita($this->db, $this->reloj);
        $id = $citas->registrar($this->entrada + ['hora' => '08:00', 'motivo' => 'Control']);
        $fila = $citas->getById($id);
        $limite = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 07:30:00'));
        $this->assertTrue($limite->permiteCambio($fila));
        $this->assertFalse($citas->permiteCambio($fila));
        try {
            $citas->reprogramar($fila, 2, '2030-01-07', '10:00');
            $this->fail('El veterinario no elude el plazo por POST.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(ReglaCambioCita::MENSAJE, $error->getMessage());
        }
        $this->como(6, Roles::PROPIETARIO);
        $portal = (new Cita($this->db, $this->reloj))->enClinicaDelPropietario(1);
        try {
            $portal->cancelar($portal->getById($id));
            $this->fail('El propietario no elude el plazo por POST.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(ReglaCambioCita::MENSAJE, $error->getMessage());
        }
        $this->db->exec('UPDATE clinicas SET anticipacion_cambios_min=0 WHERE id_clinica=1');
        $this->assertTrue($portal->permiteCambio($portal->getById($id)));
        $this->assertSame(30, (int) $this->db->query('SELECT anticipacion_cambios_min FROM clinicas WHERE id_clinica=2')->fetchColumn());
        $this->db->exec('UPDATE clinicas SET anticipacion_cambios_min=30 WHERE id_clinica=1');
        $this->como(1, Roles::ADMIN);
        $admin = new Cita($this->db, $this->reloj);
        try {
            $admin->cancelar($admin->getById($id));
            $this->fail('La excepción administrativa requiere motivo.');
        } catch (InvalidArgumentException) {
        }
        $this->assertTrue($admin->cancelar($admin->getById($id), null, 'Solicitud telefónica verificada'));
        $this->assertStringContainsString('Solicitud telefónica verificada', $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE descripcion LIKE 'Cancelación coordinada%' ORDER BY id_auditoria DESC LIMIT 1")->fetchColumn());
    }

    public function test04SinVincularClinicasInactivasYRollbackSiAlgunaNoTieneCupo(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (2,1,'activo'),(2,2,'activo'),(2,3,'activo')");
        $this->como(2, Roles::PROPIETARIO);
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn();
        $this->db->exec('UPDATE planes SET limite_mascotas=0 WHERE id_plan=(SELECT id_plan FROM clinicas WHERE id_clinica=2)');
        try {
            (new MascotaPropietario($this->db))->registrar(['nombre' => 'Sin cupo', 'id_especie' => 1, 'sexo' => 'Macho']);
            $this->fail('Los vínculos automáticos no saltan los límites ni dejan un alta parcial.');
        } catch (LimiteAlcanzado) {
        }
        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
    }
}
