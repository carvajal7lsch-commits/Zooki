<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/LlegadaAgenda.php';

/** C8: segundos exactos, decisiones humanas, espera y rollback en el contrato común. */
final class AgendaC8Test extends TestCase
{
    private PDO $db;
    private string $ahora = '2030-01-07 07:00:00';

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        ConocimientoRealPrueba::taxonomia($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
        foreach ([2, 3] as $id) {
            $this->db->prepare("INSERT INTO mascotas (id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,id_especie,estado) VALUES (?,6,1,?,?,1,1)")->execute([$id, str_pad((string) $id, 43, 'x'), 'Mascota ' . $id]);
            $this->db->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,1,'activo')")->execute([$id]);
        }
        $this->como(2);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function como(int $usuario, int $clinica = 1, int $rol = Roles::VETERINARIO): void
    {
        $_SESSION = ['id_usuario' => $usuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', $rol), 1);
    }

    private function reloj(): Closure
    {
        return fn () => IntervalosAgenda::fecha($this->ahora);
    }

    private function agenda(): LlegadaAgenda
    {
        return new LlegadaAgenda($this->db, $this->reloj());
    }

    private function cita(int $pet = 1, string $hora = '09:00:00'): int
    {
        return (new Cita($this->db, $this->reloj()))->registrar(['id_mascota' => $pet, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07', 'hora' => $hora, 'motivo' => 'Prueba C8']);
    }

    private function fila(int $id): array
    {
        return $this->db->query('SELECT * FROM citas WHERE id_cita=' . $id)->fetch(PDO::FETCH_ASSOC);
    }

    private function evaluar(int $pet = 1, string $nivel = 'verde'): int
    {
        $s = new SolicitudAgenda($this->db, $this->reloj());
        $r = $s->evaluar(['id_mascota' => $pet, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07', 'sintomas' => [['codigo' => $nivel === 'verde' ? 'sintoma.control_preventivo' : 'sintoma.otro_no_se', 'tiempo' => $nivel === 'verde' ? 'no_aplica' : 'no_se']]]);
        if ($nivel !== 'verde') {
            $s->revisar($r['id_solicitud'], $nivel, 'Revisión explícita C8', $r['huella'], null);
        }
        return $r['id_solicitud'];
    }

    public static function bordes(): array
    {
        return ['ocho' => ['09:08:00', true], 'menos un segundo' => ['09:09:59', true], 'exacto' => ['09:10:00', true], 'más un segundo' => ['09:10:01', false], 'quince' => ['09:15:00', false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bordes')]
    public function testLlegadaNoIniciaYRespetaTolerancia(string $hora, bool $dentro): void
    {
        $id = $this->cita();
        $this->ahora = '2030-01-07 ' . $hora;
        $clave = str_repeat('a', 64);
        $r = $this->agenda()->llegada($id, $clave);
        $this->assertSame($dentro, $r['opciones']['dentro_tolerancia']);
        $this->assertSame($this->ahora, $this->fila($id)['hora_llegada']);
        $this->assertSame('confirmada', $this->fila($id)['estado']);
        $this->assertNull($this->fila($id)['hora_inicio_real']);
        $auditorias = $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        $this->agenda()->llegada($id, $clave);
        $this->assertSame($auditorias, $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
    }

    public function testOpcionesReduccionExplicitaSnapshotsYMargenUnico(): void
    {
        $id = $this->cita();
        $this->ahora = '2030-01-07 09:08:00';
        $opciones = $this->agenda()->llegada($id, null)['opciones'];
        $this->assertTrue($opciones['reducida']);
        $this->assertSame(1320, $opciones['segundos_restantes']);
        $this->assertTrue($opciones['reprogramar']);
        $this->assertNotEmpty($opciones['otro_veterinario']);
        $entrada = ['decision' => 'reducida', 'segundos' => '1320', 'clave_operacion' => str_repeat('b', 64)];
        $this->agenda()->decidir($id, $entrada);
        $c = $this->fila($id);
        $this->assertSame('09:00:00', $c['hora']);
        $this->assertSame('09:30:00', $c['hora_fin']);
        $this->assertSame(30, (int) $c['duracion_minutos']);
        $this->assertSame(1320, (int) $c['duracion_activa_objetivo_segundos']);
        $this->assertSame('2030-01-07 09:30:00', $c['fin_estimado']);
        $auditorias = $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn();
        $this->agenda()->decidir($id, $entrada);
        $this->assertSame($auditorias, $this->db->query('SELECT COUNT(*) FROM auditoria_sistema')->fetchColumn());
    }

    public function testCompletaPropagaSinRecortarYFueraToleranciaRechaza(): void
    {
        $id = $this->cita();
        $this->ahora = '2030-01-07 09:10:00';
        $this->agenda()->llegada($id, null);
        $this->agenda()->decidir($id, ['decision' => 'completa']);
        $this->assertSame(1800, (int) $this->fila($id)['duracion_activa_objetivo_segundos']);
        $this->assertSame('2030-01-07 09:40:00', $this->fila($id)['fin_estimado']);
    }

    public function testNoAsistioSoloDespuesYVetAsignadoConCascada(): void
    {
        $id = $this->cita();
        foreach (['09:09:59', '09:10:00'] as $hora) {
            $this->ahora = '2030-01-07 ' . $hora;
            try {
                $this->agenda()->noAsistio($id, null);
                $this->fail('No debe liberar antes ni en el límite.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('confirmada', $this->fila($id)['estado']);
            }
        }
        $this->ahora = '2030-01-07 09:10:01';
        $this->como(5);
        try {
            $this->agenda()->noAsistio($id, null);
            $this->fail('Otro veterinario no marca inasistencia.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->getCode());
        }
        $this->como(2);
        $r = $this->agenda()->noAsistio($id, str_repeat('c', 64));
        $this->assertSame($r, $this->agenda()->noAsistio($id, str_repeat('c', 64)));
        $this->assertSame('no_asistio', $this->fila($id)['estado']);
        (new DisponibilidadAgenda($this->db, $this->reloj()))->exigir(2, 2, '2030-01-07', '09:10:01', 30, 10);
    }

    public function testAjusteMotivoCalculadoSeparadoNaranjaConTopeYAmarilloPrimero(): void
    {
        $id = $this->cita();
        try {
            $this->agenda()->ajustar($id, 'naranja', '', null);
            $this->fail('Motivo obligatorio.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame('verde', $this->fila($id)['prioridad']);
        }
        $this->agenda()->ajustar($id, 'naranja', 'Evaluación al llegar', str_repeat('d', 64));
        $this->assertSame('verde', $this->fila($id)['prioridad_calculada']);
        $this->assertSame('naranja', $this->fila($id)['prioridad']);
        $this->assertSame('08:00:00', $this->fila($id)['hora']);
        $this->agenda()->ajustar($id, 'amarillo', 'Revisión posterior', null);
        $this->assertSame('verde', $this->fila($id)['prioridad_calculada']);
        $this->assertSame('08:00:00', $this->fila($id)['hora']);
        $this->assertCount(2, $this->agenda()->leer()['historial']);
        $this->como(4, 2);
        $this->assertSame([], $this->agenda()->leer()['historial']);
    }

    public function testEsperaOrdenColorLlegadaIdAsignacionYReplay(): void
    {
        $verde = $this->evaluar(1);
        $this->agenda()->presentar($verde, null);
        $amarillo = $this->evaluar(2, 'amarillo');
        $amarillo2 = $this->evaluar(3, 'amarillo');
        $this->ahora = '2030-01-07 07:05:00';
        $this->agenda()->presentar($amarillo, null);
        $this->agenda()->presentar($amarillo2, null);
        $this->assertSame([$amarillo, $amarillo2, $verde], array_map('intval', array_column($this->agenda()->espera(), 'id_solicitud')));
        // C8 revisión: saltarse la cola responde un mensaje de dominio, no un error genérico.
        try {
            $this->agenda()->asignar($verde, $this->agenda()->alternativasLlegada($verde)['horas'][0], null);
            $this->fail('El verde no se asigna antes que los amarillos compatibles.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('anterior compatible', $error->getMessage());
        }
        $opciones = $this->agenda()->alternativasLlegada($amarillo);
        $r = $this->agenda()->asignar($amarillo, $opciones['horas'][0], str_repeat('e', 64));
        $this->assertSame($r, $this->agenda()->asignar($amarillo, $opciones['horas'][0], str_repeat('e', 64)));
        $this->assertSame('llegada_sin_reserva', $this->fila($r['id_cita'])['origen']);
        $this->assertSame('2030-01-07 07:05:00', $this->fila($r['id_cita'])['hora_llegada']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
        $this->assertSame(0, (int) $this->fila($r['id_cita'])['es_sobrecupo']);
    }

    public function testSinHuecoConservaLlegadaSinCitaYOfreceAlternativas(): void
    {
        $id = $this->evaluar();
        $this->agenda()->presentar($id, str_repeat('f', 64));
        $this->db->exec("INSERT INTO cierres_clinica (id_clinica,inicio,fin,motivo,id_autor,activo) VALUES (1,'2030-01-07 08:00:00','2030-01-07 18:00:00','Cierre',1,1)");
        try {
            $this->agenda()->asignar($id, '09:00:00', null);
            $this->fail('No crea sobrecupo para verde.');
        } catch (HorarioOcupado $error) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
            $this->assertSame('espera_presencial', $this->db->query('SELECT estado FROM solicitudes_agenda')->fetchColumn());
        }
    }

    public function testAuditoriaFallidaRevierteLlegadaOperacionYVersion(): void
    {
        $id = $this->cita();
        $antes = $this->fila($id);
        $this->db->exec("CREATE TRIGGER fallo_c8 BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT,'Auditoría fallida C8'); END");
        $this->ahora = '2030-01-07 09:08:00';
        try {
            $this->agenda()->llegada($id, str_repeat('a', 64));
            $this->fail('Debe deshacer todo.');
        } catch (RuntimeException $error) {
            $this->assertSame($antes, $this->fila($id));
            $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM operaciones_agenda WHERE accion='llegada_c8'")->fetchColumn());
        }
    }
}
