<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/SolicitudAgenda.php';

/** HU-4.13 y B1: los colores del cliente no autorizan, la revisión tiene contexto. */
final class AgendaC4Test extends TestCase
{
    private PDO $db;
    private SolicitudAgenda $agenda;
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
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $this->agenda = new SolicitudAgenda($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00'));
        $this->entrada = ['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07',
            'sintomas' => [['codigo' => 'sintoma.control_preventivo', 'tiempo' => 'no_aplica']]];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testVerdeConSintomasInstantaneaAvisosYReplaySinDuplicacion(): void
    {
        $r = $this->agenda->evaluar($this->entrada + ['clave_operacion' => str_repeat('a', 64)]);
        $this->assertSame('verde', $r['nivel']);
        $this->assertFalse($r['revision']);
        $comando = $this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '09:00:30', 'motivo' => 'Control', 'clave_operacion' => str_repeat('b', 64)];
        $guardada = $this->agenda->reservar($comando);
        $this->assertSame($guardada, $this->agenda->reservar($comando));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM cita_sintomas')->fetchColumn());
        $this->assertSame('interno', $this->db->query('SELECT origen FROM solicitudes_agenda')->fetchColumn());
        $this->assertNull($this->db->query('SELECT fecha_llegada FROM solicitudes_agenda')->fetchColumn());
    }

    public function testRojoDerivaSinReservaNiCuotaConAvisoUnico(): void
    {
        $this->entrada['sintomas'] = [['codigo' => 'sintoma.dificultad_respiratoria', 'tiempo' => 'no_se']];
        $this->entrada['clave_operacion'] = str_repeat('c', 64);
        $this->db->exec('UPDATE planes SET limite_citas_mes=0');
        $r = $this->agenda->evaluar($this->entrada);
        $this->assertSame('rojo', $r['nivel']);
        $this->assertFalse($r['reservable']);
        $this->assertSame('derivada', $this->agenda->obtener($r['id_solicitud'])['estado']);
        $this->assertSame([], $this->agenda->horas($r['id_solicitud'], $this->entrada));
        $this->agenda->evaluar($this->entrada);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='DERIVACION_ROJA'")->fetchColumn());
    }

    public function testOtroExigeRevisionMotivadaYNoReutilizaHuellaObsoleta(): void
    {
        $this->entrada['sintomas'] = [['codigo' => 'sintoma.otro_no_se', 'tiempo' => 'no_se']];
        $r = $this->agenda->evaluar($this->entrada);
        $this->assertTrue($r['revision']);
        $this->assertTrue($r['reservable']);
        $this->assertGreaterThan(1, count($this->agenda->horas($r['id_solicitud'], $this->entrada)));
        $this->assertSame('Nivel provisional: un veterinario lo confirmará. Ya puedes elegir horario.', $r['message']);
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='REVISION_TRIAGE' AND estado='enviado'"));
        $revisada = $this->agenda->revisar($r['id_solicitud'], 'amarillo', 'Evaluación veterinaria', $r['huella'], str_repeat('d', 64));
        $this->assertFalse($revisada['revision']);
        $this->assertGreaterThan(1, count($this->agenda->horas($r['id_solicitud'], $this->entrada)));
        $this->db->exec("UPDATE mascotas SET fecha_nacimiento='2029-12-01' WHERE id_mascota=1");
        $this->expectException(InvalidArgumentException::class);
        $this->agenda->horas($r['id_solicitud'], $this->entrada);
    }
    public function testRevisionInmediataPermiteReservarYMarcaElAviso(): void
    {
        $this->db->exec('UPDATE mascotas SET id_especie=3, id_raza=NULL WHERE id_mascota=1');
        $this->entrada['sintomas'] = [
            ['codigo' => 'sintoma.no_come', 'tiempo' => 'conocido', 'minutos' => 1440],
            ['codigo' => 'sintoma.no_defeca', 'tiempo' => 'conocido', 'minutos' => 1440],
        ];
        $r = $this->agenda->evaluar($this->entrada);
        $this->assertTrue($r['resultado']['revision_inmediata']);
        $this->assertTrue($r['reservable']);
        $this->assertStringContainsString('inmediata', $this->db->query("SELECT titulo FROM notificaciones_internas WHERE tipo='REVISION_TRIAGE'")->fetchColumn());
        $guardada = $this->agenda->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '08:00']);
        $this->assertArrayHasKey('id_cita', $guardada);
    }

    public function testRevisionPosteriorARojoDerivaLaCitaYConservaAuditoriaYReplay(): void
    {
        [$r, $guardada] = $this->reservarProvisional();
        $clave = hash('sha256', 'revision-posterior-roja');
        $revisada = $this->agenda->revisar($r['id_solicitud'], 'rojo', 'Empeoramiento confirmado', $r['huella'], $clave);
        $this->assertSame($revisada, $this->agenda->revisar($r['id_solicitud'], 'rojo', 'Empeoramiento confirmado', $r['huella'], $clave));
        $cita = (new Cita($this->db))->getById($guardada['id_cita']);
        $this->assertSame('rojo', $cita['prioridad']);
        $this->assertSame('amarillo', $cita['prioridad_calculada']);
        $this->assertSame('cancelada', $cita['estado']);
        require_once __DIR__ . '/../../models/UrgenciaAgenda.php';
        $urgencias = (new UrgenciaAgenda($this->db))->listar();
        $this->assertCount(1, $urgencias);
        $this->assertSame('resuelta', $this->agenda->obtener($r['id_solicitud'])['estado_revision']);
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada='citas' AND descripcion LIKE 'Reclasificación veterinaria%'"));
    }

    public function testRevisionPosteriorBajaOConfirmaYConservaElEspacio(): void
    {
        foreach (['verde', 'amarillo'] as $nivel) {
            [$r, $guardada] = $this->reservarProvisional();
            $antes = (new Cita($this->db))->getById($guardada['id_cita']);
            $this->assertContains($r['id_solicitud'], array_map('intval', array_column($this->agenda->listar()['solicitudes'], 'id_solicitud')));
            $this->agenda->revisar($r['id_solicitud'], $nivel, 'Valoración posterior', $r['huella'], null);
            $despues = (new Cita($this->db))->getById($guardada['id_cita']);
            foreach (['hora', 'hora_fin', 'fecha', 'id_veterinario', 'duracion_minutos', 'margen_minutos'] as $campo) {
                $this->assertSame($antes[$campo], $despues[$campo], $campo);
            }
            $this->assertSame($nivel, $despues['prioridad']);
            $this->assertSame('Valoración posterior', $despues['motivo_ajuste_prioridad']);
            $this->assertSame('asignada', $this->agenda->obtener($r['id_solicitud'])['estado']);
        }
    }

    public function testRevisionPosteriorNaranjaRespetaTopeYRevierteLaResolucion(): void
    {
        [$r, $guardada] = $this->reservarProvisional();
        $this->db->exec('UPDATE clinicas SET tope_sobrecupos=0 WHERE id_clinica=1');
        try {
            $this->agenda->revisar($r['id_solicitud'], 'naranja', 'Dolor confirmado', $r['huella'], null);
            $this->fail('El tope también se exige al revisar una reserva.');
        } catch (HorarioOcupado) {
        }
        $this->assertSame('pendiente', $this->agenda->obtener($r['id_solicitud'])['estado_revision']);
        $this->assertSame('amarillo', (new Cita($this->db))->getById($guardada['id_cita'])['prioridad']);
        $this->db->exec('UPDATE clinicas SET tope_sobrecupos=2 WHERE id_clinica=1');
        $this->agenda->revisar($r['id_solicitud'], 'naranja', 'Dolor confirmado', $r['huella'], null);
        $this->assertSame('naranja', (new Cita($this->db))->getById($guardada['id_cita'])['prioridad']);
    }

    private function reservarProvisional(): array
    {
        $this->entrada['sintomas'] = [['codigo' => 'sintoma.otro_no_se', 'tiempo' => 'no_se']];
        $r = $this->agenda->evaluar($this->entrada);
        $hora = $this->agenda->horas($r['id_solicitud'], $this->entrada)[0];
        $guardada = $this->agenda->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => $hora, 'ignore_warning' => '1']);
        $this->assertSame('pendiente', $this->agenda->obtener($r['id_solicitud'])['estado_revision']);
        return [$r, $guardada];
    }

    /** Copia la ficha de Luna como otra mascota de Fabio vinculada a Norte. */
    private function mascota(int $id, int $propietario = DosClinicas::PROPIETARIO, int $clinica = DosClinicas::NORTE): void
    {
        $this->db->exec('CREATE TEMP TABLE IF NOT EXISTS copia AS SELECT * FROM mascotas WHERE 0');
        $this->db->exec('DELETE FROM copia');
        $this->db->exec('INSERT INTO copia SELECT * FROM mascotas WHERE id_mascota=1');
        $this->db->prepare('UPDATE copia SET id_mascota=?,id_propietario=?,id_clinica_registro=?,token_carnet=?,nombre=?')
            ->execute([$id, $propietario, $clinica, str_repeat(chr(97 + $id), 43), 'Mascota ' . $id]);
        $this->db->exec('INSERT INTO mascotas SELECT * FROM copia');
        $this->db->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,?,'activo')")->execute([$id, $clinica]);
    }

    /** Naranja por la vía real: «Otro / no sé» exige revisión y el vet aprueba naranja con motivo. */
    private function naranja(int $mascota, string $letra): array
    {
        $entrada = ['id_mascota' => $mascota, 'sintomas' => [['codigo' => 'sintoma.otro_no_se', 'tiempo' => 'no_se']]] + $this->entrada;
        $r = $this->agenda->evaluar($entrada);
        $this->agenda->revisar($r['id_solicitud'], 'naranja', 'Dolor agudo al examen', $r['huella'], hash('sha256', $letra . '1'));
        return $this->agenda->reservar($entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '11:00', 'clave_operacion' => hash('sha256', $letra . '2')]);
    }

    private function cuenta(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    /** RN-422/RE-4.13.4: el tope cuenta los naranjas del bloque; un tercero avisa y no crea cita; cancelada/no_asistio liberan. */
    public function testTopeNaranjaTerceroAvisaYLiberaPorCanceladaONoAsistio(): void
    {
        foreach ([2, 3, 4] as $id) {
            $this->mascota($id);
        }
        $primera = $this->naranja(1, 'e');
        $segunda = $this->naranja(2, 'f');
        $this->assertSame('08:00', substr($primera['hora'], 0, 5));
        $this->assertSame([1, 2], array_map('intval', $this->db->query("SELECT orden_sobrecupo FROM citas ORDER BY id_cita")->fetchAll(PDO::FETCH_COLUMN)));
        $this->assertSame(1, $this->cuenta('SELECT COUNT(DISTINCT id_bloque) FROM citas'));

        $tercera = $this->naranja(3, 'g');
        $this->assertTrue($tercera['sin_cupo']);
        $this->assertSame(2, $this->cuenta('SELECT COUNT(*) FROM citas'));
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='TOPE_NARANJA'"));

        $this->db->exec("UPDATE citas SET estado='cancelada' WHERE id_cita=" . (int) $primera['id_cita']);
        $this->assertArrayHasKey('id_cita', $this->naranja(3, 'h'));
        $this->assertTrue($this->naranja(4, 'i')['sin_cupo']);
        $this->db->exec("UPDATE citas SET estado='no_asistio' WHERE id_cita=" . (int) $segunda['id_cita']);
        $this->assertArrayHasKey('id_cita', $this->naranja(4, 'j'));
        // Atendidas o completadas siguen consumiendo el cupo (decisión del 2026-10-17).
        $this->db->exec("UPDATE citas SET estado='completada',hora_inicio_real=fecha || ' ' || hora,hora_fin_real=fecha || ' ' || hora_fin,duracion_activa_objetivo_segundos=duracion_minutos*60 WHERE estado IN ('confirmada','pendiente')");
        $this->mascota(5);
        $this->assertTrue($this->naranja(5, 'k')['sin_cupo']);
    }

    /** RE-4.13.3: sin hueco ordinario el naranja entra como sobrecupo, sin ocupar el horario de otro paciente como libre. */
    public function testNaranjaSinHuecoEntraComoSobrecupo(): void
    {
        $this->mascota(2);
        $this->db->exec("UPDATE horarios_veterinario SET hora_fin='08:40:00' WHERE id_veterinario=2 AND hora_inicio='08:00:00'");
        $this->db->exec("UPDATE horarios_veterinario SET activo=0 WHERE id_veterinario=2 AND hora_inicio='14:00:00'");
        $r = $this->agenda->evaluar($this->entrada);
        $this->agenda->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '08:00', 'clave_operacion' => hash('sha256', 'l')]);
        $this->assertSame([], $this->agenda->horas($this->agenda->evaluar(['id_mascota' => 2] + $this->entrada)['id_solicitud'], ['id_mascota' => 2] + $this->entrada));

        $naranja = $this->naranja(2, 'm');
        $this->assertTrue($naranja['es_sobrecupo']);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM citas WHERE es_sobrecupo=1'));
        $this->assertSame(2, $this->cuenta('SELECT COUNT(*) FROM citas'));
    }

    /** RE-4.13.5: decisión del dueño 2026-10-29, amarillo también elige un horario compatible. */
    public function testAmarilloEligeHorarioCompatiblePosterior(): void
    {
        $entrada = ['sintomas' => [['codigo' => 'sintoma.otro_no_se', 'tiempo' => 'no_se']]] + $this->entrada;
        $r = $this->agenda->evaluar($entrada);
        $this->agenda->revisar($r['id_solicitud'], 'amarillo', 'Prioritario', $r['huella'], hash('sha256', 'n'));
        $guardada = $this->agenda->reservar($entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '11:00', 'clave_operacion' => hash('sha256', 'o')]);
        $this->assertSame('11:00', substr($guardada['hora'], 0, 5));
        $this->assertSame('amarillo', $this->db->query('SELECT prioridad FROM citas')->fetchColumn());
        $this->assertSame('amarillo', $this->db->query('SELECT nivel_aprobado FROM solicitudes_agenda')->fetchColumn());
    }

    /** RN-422: solo el vet de la cita reordena la cola naranja, con motivo y auditoría. */
    public function testReordenarNaranjaExigeMotivoYVeterinarioAsignado(): void
    {
        $this->mascota(2);
        $primera = $this->naranja(1, 'p');
        $segunda = $this->naranja(2, 'q');
        $citas = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00'));
        try {
            $citas->reordenarSobrecupo((int) $segunda['id_cita'], 1, '  ', hash('sha256', 'r'));
            $this->fail('Sin motivo no se reordena.');
        } catch (InvalidArgumentException) {
        }
        $citas->reordenarSobrecupo((int) $segunda['id_cita'], 1, 'Deshidratación visible', hash('sha256', 's'));
        $this->assertSame(1, $this->cuenta('SELECT orden_sobrecupo FROM citas WHERE id_cita=' . (int) $segunda['id_cita']));
        $this->assertSame(2, $this->cuenta('SELECT orden_sobrecupo FROM citas WHERE id_cita=' . (int) $primera['id_cita']));
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Reordenamiento naranja%'"));

        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $this->expectException(AccesoDenegado::class);
        $citas->reordenarSobrecupo((int) $primera['id_cita'], 1, 'Intento admin', hash('sha256', 't'));
    }

    /** Decisión 2 (HU-4.13): el administrador no evalúa ni reserva; el rechazo queda auditado. */
    public function testAdministradorNoAgendaYQuedaAuditado(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $antes = $this->cuenta('SELECT COUNT(*) FROM auditoria_sistema');
        try {
            $this->agenda->evaluar($this->entrada);
            $this->fail('El administrador no agenda.');
        } catch (AccesoDenegado) {
        }
        $this->assertSame($antes + 1, $this->cuenta('SELECT COUNT(*) FROM auditoria_sistema'));
        $this->assertSame(0, $this->cuenta('SELECT COUNT(*) FROM solicitudes_agenda'));
    }

    /** RE-4.13.2: con la clínica cerrada esa fecha no se ofrecen horas y se muestra el teléfono de urgencias. */
    public function testClinicaCerradaNoOfreceHorasYMuestraUrgencias(): void
    {
        $this->db->exec("UPDATE clinicas SET telefono_urgencias='3001234567' WHERE id_clinica=1");
        $this->db->exec("INSERT INTO cierres_clinica (id_clinica,inicio,fin,motivo,id_autor) VALUES (1,'2030-01-07 00:00:00','2030-01-08 00:00:00','Festivo',1)");
        $r = $this->agenda->evaluar($this->entrada);
        $this->assertTrue($r['clinica_cerrada']);
        $this->assertFalse($r['reservable']);
        $this->assertSame('3001234567', $r['telefono_urgencias']);
        $this->expectException(HorarioOcupado::class);
        $this->agenda->horas($r['id_solicitud'], $this->entrada);
    }

    /** RN-G02/RNF-11: el portal no evalúa mascotas ajenas y otra clínica no lee la solicitud. */
    public function testPortalMascotaAjenaYOtraClinicaDenegadas(): void
    {
        $r = $this->agenda->evaluar($this->entrada);
        $this->mascota(2, DosClinicas::GOOGLE, DosClinicas::NORTE);
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        Contexto::activar(Contexto::dePropietario(), 1);
        $portal = (new SolicitudAgenda($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00')))->enClinicaDelPropietario(1);
        $propia = $portal->evaluar($this->entrada);
        $this->assertSame('portal', $this->db->query('SELECT origen FROM solicitudes_agenda WHERE id_solicitud=' . (int) $propia['id_solicitud'])->fetchColumn());
        try {
            $portal->evaluar(['id_mascota' => 2] + $this->entrada);
            $this->fail('Mascota ajena.');
        } catch (AccesoDenegado) {
        }
        try {
            $portal->obtener((int) $r['id_solicitud']);
            $this->fail('Solicitud creada por otro actor.');
        } catch (AccesoDenegado) {
        }
        $_SESSION = ['id_usuario' => DosClinicas::VET_SUR];
        Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 1);
        $this->expectException(AccesoDenegado::class);
        (new SolicitudAgenda($this->db))->obtener((int) $r['id_solicitud']);
    }

    /** HU-5.6: el propietario reprograma su cita con la disponibilidad única; el replay no duplica. */
    public function testPropietarioReprogramaSuCitaConReplay(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        Contexto::activar(Contexto::dePropietario(), 1);
        $reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00');
        $portal = (new SolicitudAgenda($this->db, $reloj))->enClinicaDelPropietario(1);
        $r = $portal->evaluar($this->entrada);
        $guardada = $portal->reservar($this->entrada + ['id_solicitud' => $r['id_solicitud'], 'hora' => '09:00', 'clave_operacion' => hash('sha256', 'u')]);
        $citas = (new Cita($this->db, $reloj))->enClinicaDelPropietario(1);
        $cita = $citas->getById((int) $guardada['id_cita']);
        $this->assertTrue($citas->reprogramar($cita, 2, '2030-01-07', '10:00', hash('sha256', 'v')));
        $this->assertFalse($citas->reprogramar($cita, 2, '2030-01-07', '10:00', hash('sha256', 'v')));
        $this->assertSame('10:00', substr((string) $this->db->query('SELECT hora FROM citas')->fetchColumn(), 0, 5));
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Reprogramación coordinada%'"));
    }
}
