<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/** C3: RE-4.9.1–3, RE-4.1.2/3, RE-4.6.2, RE-7.4.3, RE-4.8.3/4.11.3. */
final class DisponibilidadAgendaTest extends TestCase
{
    private PDO $db;
    private Cita $citas;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $this->citas = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 08:00:01'));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    private function reservar(string $hora, array $extra = []): int
    {
        return $this->citas->registrar($extra + ['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1,
            'fecha' => '2030-01-07', 'hora' => $hora, 'motivo' => 'C3']);
    }

    public static function iniciosConSegundosC31(): array
    {
        return [
            'apertura' => ['08:00:30', '08:00:00', '08:00:30'],
            'jornada' => ['08:00:00', '08:00:30', '08:00:30'],
            'intersección' => ['08:00:30', '08:01:07', '08:01:07'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('iniciosConSegundosC31')]
    public function testC31SugerenciaLiteralConSegundosSeReservaYReprograma(string $apertura, string $jornada, string $esperada): void
    {
        $this->db->prepare('UPDATE horarios_clinica SET bloque_morning_inicio=? WHERE id_clinica=1 AND dia_semana=1')->execute([$apertura]);
        $this->db->prepare("UPDATE horarios_veterinario SET hora_inicio=? WHERE id_clinica=1 AND id_veterinario=2 AND hora_inicio='08:00:00'")->execute([$jornada]);
        $reloj = static fn () => IntervalosAgenda::fecha('2030-01-07 07:00:00');
        $this->citas = new Cita($this->db, $reloj);
        $controlador = new CitaController($this->db, $reloj);
        $_GET = ['id_veterinario' => '2', 'id_mascota' => '1', 'id_tipo_cita' => '1', 'fecha' => '2030-01-07'];
        $leer = static function (callable $accion): array {
            ob_start();
            try {
                $accion();
                return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
            } finally {
                ob_end_clean();
            }
        };
        $interna = $leer(fn () => $controlador->getSugerenciasHorarioAjax());
        $this->assertTrue($interna['success']);
        $this->assertSame($esperada, $interna['sugerencias'][0]);
        $this->assertSame(1800, strtotime($interna['sugerencias'][1]) - strtotime($esperada));
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $_GET['id_clinica'] = '1';
        foreach (['portalHorasAjax', 'portalSugerenciasAjax'] as $ruta) {
            $portal = $leer(fn () => $controlador->$ruta());
            $this->assertTrue($portal['success']);
            $this->assertSame($esperada, $portal['sugerencias'][0]);
        }
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $id = $this->reservar($interna['sugerencias'][0]);
        $this->assertSame($esperada, $this->citas->getById($id)['hora']);
        $this->assertSame(date('H:i:s', strtotime($esperada) + 1800), $this->citas->getById($id)['hora_fin']);
        $_GET['fecha'] = '2030-01-14';
        $_GET['id_cita_excluir'] = (string) $id;
        $reprogramar = $leer(fn () => $controlador->getSugerenciasHorarioAjax());
        $this->assertSame($esperada, $reprogramar['sugerencias'][0]);
        $this->citas->reprogramar($this->citas->getById($id), 2, '2030-01-14', $reprogramar['sugerencias'][0]);
        $this->assertSame($esperada, $this->citas->getById($id)['hora']);
        $this->assertSame(10, (int) $this->citas->getById($id)['margen_minutos']);
    }

    private function rechaza(callable $accion, string $clase = HorarioOcupado::class): void
    {
        try {
            $accion();
            $this->fail('Debía rechazar el intervalo.');
        } catch (Throwable $error) {
            $this->assertInstanceOf($clase, $error);
        }
    }

    public function testBordesExactosMargenSegundosYFinClinico(): void
    {
        $this->rechaza(fn () => $this->reservar('11:20:01'));
        $id = $this->reservar('11:20:00', ['duracion_minutos' => 1, 'margen_minutos' => 0, 'prioridad' => 'rojo']);
        $fila = $this->citas->getById($id);
        $this->assertSame('11:50:00', $fila['hora_fin']);
        $this->assertSame(30, (int) $fila['duracion_minutos']);
        $this->assertSame(10, (int) $fila['margen_minutos']);
        $this->assertSame('verde', $fila['prioridad']);
        $this->assertSame('reserva', $fila['origen']);
        $this->assertSame(1800, (int) $fila['duracion_activa_objetivo_segundos']);
        $this->assertSame('M', $fila['politica_temporal']);
        $this->rechaza(fn () => $this->reservar('23:40'));
        $this->rechaza(fn () => $this->reservar('08:00:00'));
        $otro = $this->reservar('14:00:07');
        $this->assertSame('14:30:07', $this->citas->getById($otro)['hora_fin']);
    }

    public function testSinJornadaSinAperturaYFranjaDiscontinua(): void
    {
        $this->rechaza(fn () => $this->reservar('11:30'));
        $this->rechaza(fn () => $this->reservar('13:50'));
        $this->db->exec('DELETE FROM horarios_veterinario WHERE id_veterinario=2');
        $this->assertSame([], $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
        $this->rechaza(fn () => $this->reservar('09:00'));
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin) VALUES (1,2,1,'08:00:00','12:00:00')");
        $this->db->exec('DELETE FROM horarios_clinica WHERE id_clinica=1');
        $this->assertSame([], $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
    }

    public function testBloqueOFranjaInactivosNoOfrecenSusHorasConservadas(): void
    {
        $this->db->exec('UPDATE horarios_clinica SET bloque_morning_activo=0 WHERE id_clinica=1 AND dia_semana=1');
        $horas = $this->citas->sugerencias(2, 1, 1, '2030-01-07');
        $this->assertNotContains('09:00', $horas);
        $this->assertContains('14:00', $horas);
        $this->db->exec("UPDATE horarios_veterinario SET activo=0 WHERE id_clinica=1 AND id_veterinario=2 AND hora_inicio='14:00:00'");
        $this->assertSame([], $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
    }

    public function testCierrePuntualAusenciaParcialYEstadosNoEfectivos(): void
    {
        $this->db->exec("INSERT INTO cierres_clinica (id_clinica,inicio,fin,motivo,id_autor) VALUES (1,'2030-01-07 09:00:00','2030-01-07 10:00:00','Cierre',1)");
        $this->db->exec("INSERT INTO ausencias_veterinario (id_clinica,id_veterinario,fecha_hora_inicio,fecha_hora_fin,estado) VALUES (1,2,'2030-01-07 10:30:00','2030-01-07 11:00:00','aprobada')");
        $this->rechaza(fn () => $this->reservar('08:20:01'));
        $this->reservar('08:20:00');
        $this->rechaza(fn () => $this->reservar('10:00'));
        $this->reservar('11:00');
        $this->assertContains('09:00', $this->citas->sugerencias(2, 1, 1, '2030-01-14'));
        $this->db->exec('DELETE FROM citas');
        foreach (['solicitada', 'rechazada', 'anulada'] as $estado) {
            $this->db->exec("UPDATE ausencias_veterinario SET estado='$estado'");
            $this->assertContains('10:00', $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
        }
    }

    public function testSugerirYGuardarMismoRelojTipoActualYSnapshotReprogramado(): void
    {
        $horas = $this->citas->sugerencias(2, 1, 1, '2030-01-07');
        $this->assertNotContains('08:00', $horas);
        $id = $this->reservar($horas[0]);
        $this->db->exec("UPDATE citas SET inicio_estimado='2030-01-07 08:40:00',fin_estimado='2030-01-07 09:10:00',fecha_ultimo_calculo='2030-01-07 08:00:00'");
        $this->db->exec('UPDATE tipos_cita SET duracion_minutos=90,margen_minutos=20,activo=0 WHERE id_tipo_cita=1');
        $this->assertContains('11:00', $this->citas->sugerencias(2, 1, 1, '2030-01-07', $id));
        $this->citas->reprogramar($this->citas->getById($id), 2, '2030-01-07', '11:20:00');
        $this->assertSame('11:50:00', $this->citas->getById($id)['hora_fin']);
        $this->assertNull($this->citas->getById($id)['inicio_estimado']);
        $this->assertNull($this->citas->getById($id)['fin_estimado']);
        $this->assertSame('pendiente_calculo', $this->citas->getById($id)['estado_reajuste']);
        $this->rechaza(fn () => $this->reservar('14:00'), InvalidArgumentException::class);
    }

    public function testPrivacidadGlobalExclusionLegitimaYEstadosLibres(): void
    {
        $auditorias = (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        $this->rechaza(fn () => $this->citas->sugerencias(4, 1, 1, '2030-01-07'), AccesoDenegado::class);
        $this->assertSame($auditorias + 1, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota,id_clinica) VALUES (1,2)");
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,duracion_minutos,margen_minutos,motivo,estado) VALUES (2,1,4,2,'2030-01-07','09:00:00','09:20:00',20,5,'Secreto Sur','confirmada')");
        $horas = $this->citas->sugerencias(2, 1, 1, '2030-01-07');
        $this->assertNotContains('09:00', $horas);
        $this->assertContains('09:30', $horas);
        $this->db->exec('UPDATE citas SET es_sobrecupo=1');
        $this->assertNotContains('09:00', $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
        $this->rechaza(fn () => $this->citas->sugerencias(2, 1, 1, '2030-01-07', 1), AccesoDenegado::class);
        $this->rechaza(fn () => $this->reservar('08:59:59'));
        foreach (['cancelada', 'no_asistio'] as $estado) {
            $this->db->exec("UPDATE citas SET estado='$estado'");
            $this->assertContains('09:00', $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
        }
        $_SESSION = ['id_usuario' => 7];
        Contexto::activar(Contexto::dePlataforma(), 1);
        $this->rechaza(fn () => $this->citas->sugerencias(2, 1, 1, '2030-01-07'), AccesoDenegado::class);
    }

    public function testLegadoDesconocidoYAtencionPausadaRequierenRevisionSinMutacion(): void
    {
        $id = $this->reservar('09:00');
        $this->db->exec('UPDATE citas SET duracion_minutos=NULL,hora_fin=NULL');
        $antes = $this->citas->getById($id);
        $this->rechaza(fn () => $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
        $this->rechaza(fn () => $this->reservar('15:00'));
        $this->assertSame($antes, $this->citas->getById($id));
        $this->db->exec("UPDATE citas SET duracion_minutos=30,hora_fin='09:30:00',estado='pausada',hora_inicio_real='2030-01-07 09:00:00',duracion_activa_objetivo_segundos=1800");
        $this->rechaza(fn () => $this->reservar('15:00'));
    }

    public function testReprogramarCuotaDestinoConservaOriginalYNoCuentaDosVeces(): void
    {
        $id = $this->reservar('09:00');
        $this->db->exec('UPDATE planes SET limite_citas_mes=1 WHERE id_plan=2');
        $this->citas->reprogramar($this->citas->getById($id), 2, '2030-01-07', '10:00');
        $antes = $this->citas->getById($id);
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,duracion_minutos,margen_minutos,motivo) VALUES (1,1,2,1,'2030-02-04','08:00:00','08:30:00',30,10,'Cuota')");
        $this->rechaza(fn () => $this->citas->reprogramar($antes, 2, '2030-02-04', '11:00'), LimiteAlcanzado::class);
        $this->assertSame($antes, $this->citas->getById($id));
    }

    public function testAuditoriaFallidaRevierteCitaOperacionYReprogramacion(): void
    {
        $id = $this->reservar('09:00');
        $antes = $this->citas->getById($id);
        $operaciones = (int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn();
        $this->db->exec("CREATE TRIGGER auditoria_falla BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'Fallo C3'); END");
        $this->rechaza(fn () => $this->citas->reprogramar($antes, 2, '2030-01-07', '11:00', str_repeat('a', 64)), RuntimeException::class);
        $this->rechaza(fn () => $this->reservar('14:00'), RuntimeException::class);
        $this->assertSame($antes, $this->citas->getById($id));
        $this->assertSame($operaciones, (int) $this->db->query('SELECT COUNT(*) FROM operaciones_agenda')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }

    public function testRutasInternasUnificadasYParametrosAdulterados(): void
    {
        $_GET = ['id_veterinario' => '2', 'id_mascota' => '1', 'id_tipo_cita' => '1', 'fecha' => '2030-01-07',
            'duracion_minutos' => '1', 'margen_minutos' => '0', 'prioridad' => 'rojo'];
        $controlador = new CitaController($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 08:00:01'));
        ob_start();
        try {
            $controlador->getSugerenciasHorarioAjax();
            $respuesta = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        $this->assertTrue($respuesta['success']);
        $this->assertSame($this->citas->sugerencias(2, 1, 1, '2030-01-07'), $respuesta['horas']);
        $this->assertSame($respuesta['horas'], $respuesta['sugerencias']);
        $this->assertNotContains('11:30', $respuesta['horas']);
    }

    public function testCompletadaReconstruyeMargenUnicoConPausaSinModificarSellos(): void
    {
        $id = $this->reservar('09:00');
        $this->db->exec("UPDATE citas SET estado='completada',hora_inicio_real='2030-01-07 09:00:00',hora_fin_real='2030-01-07 09:50:00',duracion_activa_objetivo_segundos=1800");
        $this->db->exec("INSERT INTO pausas_cita (id_clinica,id_cita,inicio,fin) VALUES (1,$id,'2030-01-07 09:10:00','2030-01-07 09:30:00')");
        $antes = $this->citas->getById($id);
        $this->rechaza(fn () => $this->reservar('09:59:59'));
        $horas = $this->citas->sugerencias(2, 1, 1, '2030-01-07');
        $this->assertContains('10:00', $horas);
        $this->assertSame($horas, $this->citas->sugerencias(2, 1, 1, '2030-01-07'));
        $this->assertSame($antes, $this->citas->getById($id));
        $this->reservar('10:00');
    }

    public function testOcupacionDeOtraFechaUsaDatetimesCompletos(): void
    {
        $this->db->exec("UPDATE horarios_clinica SET bloque_morning_inicio='00:00:00',bloque_morning_fin='02:00:00' WHERE id_clinica=1 AND dia_semana=1");
        $this->db->exec("UPDATE horarios_veterinario SET hora_inicio='00:00:00',hora_fin='02:00:00' WHERE id_clinica=1 AND id_veterinario=2 AND dia_semana=1 AND hora_inicio='08:00:00'");
        $this->citas = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 00:00:00'));
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,fecha,hora,hora_fin,duracion_minutos,margen_minutos,motivo,estado,hora_inicio_real,hora_fin_real,duracion_activa_objetivo_segundos) VALUES (1,1,2,'2030-01-06','23:00:00','23:30:00',30,10,'Histórico','completada','2030-01-06 23:00:00','2030-01-07 00:10:00',1800)");
        $this->rechaza(fn () => $this->reservar('00:09:59'));
        $this->reservar('00:10:00');
        $this->db->exec('DELETE FROM citas');
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,fecha,hora,hora_fin,duracion_minutos,margen_minutos,motivo,estado) VALUES (1,1,2,'2030-01-06','23:50:00','00:10:00',20,5,'Intervalo legado demostrado','confirmada')");
        $this->rechaza(fn () => $this->reservar('00:14:59'));
        $this->reservar('00:15:00');
    }

    public function testAtencionConObjetivoExplicitoNoLiberaAntesDeSuCotaNiPorAgotarElReloj(): void
    {
        $id = $this->reservar('09:00');
        $this->assertTrue($this->citas->iniciarAtencion($this->citas->getById($id), '2030-01-07 09:00:00'));
        $this->citas = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 09:10:00'));
        $horas = $this->citas->sugerencias(2, 1, 1, '2030-01-07');
        $this->assertNotContains('09:30', $horas);
        $this->assertContains('10:00', $horas);
        $this->rechaza(fn () => $this->reservar('09:39:59'));
        $this->citas = new Cita($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 09:40:00'));
        $this->rechaza(fn () => $this->reservar('14:00:00'));
    }
}
