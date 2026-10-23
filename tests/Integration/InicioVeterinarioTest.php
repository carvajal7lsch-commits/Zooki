<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
require_once __DIR__ . '/../../controllers/PanelController.php';

/**
 * Inicio del veterinario (HU-6.1, RE-6.1.8 a RE-6.1.11) con dos clínicas:
 * «Tu día» con huecos de la disponibilidad real, almuerzo y cierre puntual;
 * día sin citas; calendario y «Tu día» de otra fecha por panel_dia_ajax;
 * pacientes recientes; y el aislamiento por clínica y por veterinario (RNF-11).
 *
 * El fixture da a Beto (Norte), Elena (Norte) y Diego (Sur) la jornada de
 * 8:00 a 12:00 y de 14:00 a 18:00 todos los días; la cita más corta de Norte
 * dura 30 minutos.
 */
class InicioVeterinarioTest extends TestCase
{
    private const LUNA = 1;
    private const KIRA = 2;
    private const TOBY = 3;
    private const HOY = '2026-10-07';

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $mascota = $this->db->prepare("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (?, ?, ?, ?, ?, 1, 1)");
        $mascota->execute([self::KIRA, DosClinicas::PROPIETARIO, DosClinicas::NORTE, str_repeat('b', 43), 'Kira']);
        $mascota->execute([self::TOBY, DosClinicas::GOOGLE, DosClinicas::SUR, str_repeat('c', 43), 'Toby']);
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 1, 'activo'), (3, 2, 'activo')");
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    /**
     * RE-6.1.9: las citas y la disponibilidad real en orden de hora. El hueco
     * de 20 minutos no se ofrece (la cita más corta dura 30), el almuerzo sale
     * entre franjas y el cierre puntual corta la tarde.
     */
    public function testTuDiaMezclaCitasHuecosAlmuerzoYCierre(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'confirmada');
        $this->cita(DosClinicas::NORTE, self::KIRA, DosClinicas::VET_NORTE, self::HOY, '10:00:00', 'pendiente');
        $this->cierre(DosClinicas::NORTE, self::HOY . ' 15:00:00', self::HOY . ' 16:00:00', 'Capacitación');

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $dia = $this->panel('08:00')['dia'];

        $this->assertSame([
            '8:00 a. m. libre Libre · 1 h',
            '9:00 a. m. cita Luna',
            '10:00 a. m. cita Kira',
            '10:40 a. m. libre Libre · 1 h 20 min',
            '12:00 p. m. bloqueo Almuerzo · hasta las 2:00 p. m.',
            '2:00 p. m. libre Libre · 1 h',
            '3:00 p. m. bloqueo Clínica cerrada · Capacitación · hasta las 4:00 p. m.',
            '4:00 p. m. libre Libre · 2 h',
        ], $this->resumen($dia['lineas']));
        $this->assertSame(['siguiente', 'por_atender'], array_column(array_filter($dia['lineas'], fn($l) => $l['tipo'] === 'cita'), 'estado'));
        $this->assertSame(2, $dia['citas']);
        $this->assertSame(320, $dia['minutos_libres']);
        $this->assertSame(['2026-10-07', '10:40'], [$dia['lineas'][3]['fecha'], $dia['lineas'][3]['hora_iso']], '«+ Agendar» lleva la fecha y la hora del hueco');
    }

    /** RE-6.1.9: de hoy solo se ofrece lo que queda desde ahora; de un día pasado, ningún hueco. */
    public function testLosHuecosDeHoyEmpiezanAhoraYLosDeAyerNoSeOfrecen(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'completada');
        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);

        $libres = array_values(array_filter($this->panel('10:52')['dia']['lineas'], fn($l) => $l['tipo'] === 'libre'));
        $this->assertSame(['10:55', '14:00'], array_column($libres, 'hora_iso'));
        $this->assertSame('Libre · 1 h 5 min', $libres[0]['texto']);

        $ayer = $this->diaAjax(['fecha' => self::HOY], '2026-10-08 09:00');
        $this->assertStringContainsString('Luna', $ayer['html']);
        $this->assertStringNotContainsString('+ Agendar', $ayer['html']);
    }

    /** RE-6.1.9: un día sin citas es «Día libre», con lo libre y el próximo día con citas. */
    public function testDiaSinCitasMuestraLoLibreYElProximoDia(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-09', '09:00:00', 'pendiente');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-08', '09:00:00', 'cancelada');
        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);

        $dia = $this->panel('07:00')['dia'];
        $this->assertSame(0, $dia['citas']);
        $this->assertSame(480, $dia['minutos_libres']);
        $this->assertSame('2026-10-09', $dia['proximo']['fecha'], 'Una cita cancelada no cuenta como día con citas');
        $this->assertSame('viernes 9', $dia['proximo']['texto']);

        $html = $this->diaAjax(['fecha' => self::HOY], self::HOY . ' 07:00')['html'];
        $this->assertStringContainsString('Día libre', $html);
        $this->assertStringContainsString('Te quedan 8 h libres para agendar.', $html);
        $this->assertStringContainsString('vet_agenda&amp;agendar=2026-10-07', $html);

        // Sin horario ese día no hay nada que agendar, y se dice.
        $this->db->exec('DELETE FROM horarios_veterinario WHERE id_veterinario = 2 AND dia_semana = 3');
        $dia = $this->panel('07:00')['dia'];
        $this->assertTrue($dia['sin_jornada']);
        $this->assertSame([], $dia['lineas']);
    }

    /**
     * RE-6.1.4 y RNF-11: cada veterinario ve solo su agenda y solo la de la
     * clínica activa; la disponibilidad de otro veterinario no se puede pedir.
     */
    public function testAislamientoPorClinicaYPorVeterinario(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (2, 2, 2, 'activo')");
        // Beto también atiende en Sur los miércoles por la tarde.
        $this->db->exec("INSERT INTO horarios_veterinario (id_clinica, id_veterinario, dia_semana, hora_inicio, hora_fin) VALUES (2, 2, 3, '14:00:00', '18:00:00')");
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'pendiente');
        $this->cita(DosClinicas::NORTE, self::KIRA, DosClinicas::DOBLE, self::HOY, '11:00:00', 'pendiente');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_NORTE, self::HOY, '15:00:00', 'pendiente');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_NORTE, '2026-10-20', '15:00:00', 'pendiente');
        $this->cierre(DosClinicas::SUR, self::HOY . ' 16:00:00', self::HOY . ' 17:00:00', 'Fumigación');

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $norte = $this->panel('07:00');
        $this->assertSame(['Luna'], array_column(array_filter($norte['dia']['lineas'], fn($l) => $l['tipo'] === 'cita'), 'mascota'));
        $this->assertStringNotContainsString('Fumigación', implode(' ', array_column($norte['dia']['lineas'], 'texto')), 'El cierre de Sur no es de Norte');
        // Su cita de Sur no se muestra, pero tampoco se ofrece como libre (RN-401: sin detalles).
        $this->assertSame(['8:00 a. m.', '9:40 a. m.', '2:00 p. m.', '3:40 p. m.'], array_column(array_filter($norte['dia']['lineas'], fn($l) => $l['tipo'] === 'libre'), 'hora'));
        $diasNorte = array_column(array_filter(array_filter($norte['mes']['celdas']), fn($c) => $c['citas']), 'fecha');
        $this->assertSame([self::HOY], $diasNorte, 'La cita del 20 es de Sur');

        $this->como(DosClinicas::NORTE, DosClinicas::DOBLE);
        $elena = $this->panel('07:00');
        $this->assertSame(['Kira'], array_column(array_filter($elena['dia']['lineas'], fn($l) => $l['tipo'] === 'cita'), 'mascota'));

        $this->como(DosClinicas::SUR, DosClinicas::VET_NORTE);
        $sur = $this->diaAjax(['fecha' => self::HOY], self::HOY . ' 07:00');
        $this->assertStringContainsString('Toby', $sur['html']);
        $this->assertStringNotContainsString('Luna', $sur['html']);
        $this->assertStringContainsString('Fumigación', $sur['html']);

        // La disponibilidad propia sale de la sesión: la de otro veterinario se deniega.
        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        try {
            (new DisponibilidadAgenda($this->db))->diaDelVeterinario(DosClinicas::DOBLE, self::HOY);
            $this->fail('La agenda de otro veterinario no se lee desde el Inicio.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
        $_GET = ['fecha' => self::HOY, 'id_veterinario' => (string) DosClinicas::DOBLE];
        $this->assertStringNotContainsString('Kira', $this->diaAjax($_GET, self::HOY . ' 07:00')['html'], 'Un id_veterinario en la petición no cambia de agenda');
    }

    /** RE-6.1.8: el calendario de otro mes, con los días con citas y el día elegido. */
    public function testElCalendarioDeOtroMesYLasFechasInvalidas(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-11-03', '09:00:00', 'pendiente');
        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);

        $mes = $this->diaAjax(['mes' => '2026-11', 'elegido' => '2026-11-03'], self::HOY . ' 07:00');
        $this->assertStringContainsString('Noviembre 2026', $mes['html']);
        $this->assertMatchesRegularExpression('/data-inicio-fecha="2026-11-03"\s+aria-label="martes 3 de noviembre, con citas"\s+aria-pressed="true"/', $mes['html']);
        $this->assertSame(6, substr_count($mes['html'], 'inicio-cal__vacio'), 'Noviembre de 2026 empieza en domingo');

        foreach ([['fecha' => '2026-02-30'], ['fecha' => '7/10/2026'], ['mes' => '2026-13']] as $consulta) {
            $respuesta = $this->diaAjax($consulta, self::HOY . ' 07:00');
            $this->assertFalse($respuesta['success'], json_encode($consulta));
        }
    }

    /**
     * RE-6.1.11: los últimos pacientes que atendió el veterinario en la clínica
     * activa, uno por mascota, sin consultas de otros veterinarios, de otra
     * clínica ni de mascotas desvinculadas.
     */
    public function testPacientesRecientes(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->consulta(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-01 09:00:00', 'Vacunación');
        $this->consulta(DosClinicas::NORTE, self::KIRA, DosClinicas::VET_NORTE, '2026-10-05 10:00:00', 'Control');
        $this->consulta(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-06 11:00:00', 'Seguimiento');
        $this->consulta(DosClinicas::NORTE, self::KIRA, DosClinicas::DOBLE, '2026-10-07 08:00:00', 'De Elena');
        $this->consulta(DosClinicas::SUR, self::LUNA, DosClinicas::VET_SUR, '2026-10-07 09:00:00', 'De Sur');

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $recientes = (new Panel($this->db))->pacientesRecientes(DosClinicas::VET_NORTE, 8);
        $this->assertSame(['Luna', 'Kira'], array_column($recientes, 'mascota'));
        $this->assertSame(['Seguimiento', 'Control'], array_column($recientes, 'motivo'), 'La consulta más reciente de cada mascota');
        $this->assertSame([self::LUNA, self::KIRA], array_map('intval', array_column($recientes, 'id_mascota')));
        $this->assertCount(1, (new Panel($this->db))->pacientesRecientes(DosClinicas::VET_NORTE, 1));

        $panel = $this->panel('12:00');
        $this->assertSame(['Ayer', 'lun 5 oct'], array_column($panel['recientes'], 'cuando'));

        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 2 AND id_clinica = 1");
        $this->assertSame(['Luna'], array_column((new Panel($this->db))->pacientesRecientes(DosClinicas::VET_NORTE, 8), 'mascota'));

        $this->como(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame(['De Sur'], array_column((new Panel($this->db))->pacientesRecientes(DosClinicas::VET_SUR, 8), 'motivo'), 'En Sur, solo su consulta de Sur');
    }

    /** RE-6.1.5: el siguiente paciente trae nivel, llegada, edad y sus alergias primero. */
    public function testElSiguientePacienteTraeNivelLlegadaYAlergias(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'confirmada', 'naranja');
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, tipo, descripcion, activa) VALUES
            (1, 1, 'condicion_cronica', 'Epilepsia', 1), (1, 2, 'alergia', 'Penicilina', 1), (1, 1, 'alergia', 'Retirada', 0)");
        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE);

        $siguiente = $this->panel('08:40')['siguiente'];
        $this->assertSame('naranja', $siguiente['prioridad']);
        $this->assertSame('en 20 min', $siguiente['cuenta']);
        $this->assertSame('4 años', $siguiente['edad']);
        $this->assertNull($siguiente['hora_llegada']);
        $this->assertSame(['Penicilina', 'Epilepsia'], array_column($siguiente['alertas'], 'descripcion'), 'RN-113: también la alergia registrada en otra clínica; las inactivas no');
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function como(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 2);
    }

    private function reloj(string $instante): Closure
    {
        $ahora = new DateTimeImmutable($instante, new DateTimeZone(ReglaAtencion::ZONA));
        return fn (): DateTimeImmutable => $ahora;
    }

    private function panel(string $hora): array
    {
        $reloj = $this->reloj(self::HOY . ' ' . $hora);
        return (new PanelController($this->db, $reloj))->datosVeterinario((int) Contexto::idUsuario(), $reloj());
    }

    private function diaAjax(array $consulta, string $instante): array
    {
        $_GET = $consulta;
        ob_start();
        (new PanelController($this->db, $this->reloj($instante)))->diaAjax();
        return json_decode((string) ob_get_clean(), true);
    }

    /** «hora tipo texto» de cada fila; de las citas, el nombre de la mascota. */
    private function resumen(array $lineas): array
    {
        return array_map(fn($l) => $l['hora'] . ' ' . $l['tipo'] . ' ' . ($l['tipo'] === 'cita' ? $l['mascota'] : $l['texto']), $lineas);
    }

    private function cita(int $clinica, int $mascota, int $veterinario, string $fecha, string $hora, string $estado, string $prioridad = 'verde'): void
    {
        $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado, duracion_minutos, margen_minutos, prioridad)
            VALUES (?, ?, ?, ?, ?, ?, 'Control', ?, 30, 10, ?)")->execute([$clinica, $mascota, $veterinario, $clinica, $fecha, $hora, $estado, $prioridad]);
    }

    private function cierre(int $clinica, string $inicio, string $fin, string $motivo): void
    {
        $this->db->prepare('INSERT INTO cierres_clinica (id_clinica, inicio, fin, motivo, activo, id_autor) VALUES (?, ?, ?, ?, 1, 1)')
            ->execute([$clinica, $inicio, $fin, $motivo]);
    }

    private function consulta(int $clinica, int $mascota, int $veterinario, string $fechaHora, string $motivo): void
    {
        $this->db->prepare("INSERT INTO consultas (id_clinica, id_mascota, id_veterinario, fecha_hora, motivo_consulta, anamnesis, diagnostico, plan_tratamiento)
            VALUES (?, ?, ?, ?, ?, '-', '-', '-')")->execute([$clinica, $mascota, $veterinario, $fechaHora, $motivo]);
    }
}
