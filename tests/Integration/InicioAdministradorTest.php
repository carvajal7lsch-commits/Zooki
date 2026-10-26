<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
require_once __DIR__ . '/../../controllers/PanelController.php';

/**
 * Inicio del administrador (HU-6.2, RE-6.2.7 a RE-6.2.13) con dos clínicas:
 * nada de la otra clínica (citas, veterinarios, ausencias, propuestas, uso del
 * plan), veterinario sin citas en la agenda, huecos y ausencias iguales a los
 * de la disponibilidad real, cambio de día por panel_admin_dia_ajax, conteos de
 * pendientes, uso del plan contra LimitePlan y estados vacíos (RNF-11, RE-6.2.6).
 *
 * Norte: Ana administra; Beto y Elena son veterinarios (Hugo tiene la cuenta
 * inactiva). Sur: Carla administra y Diego es veterinario. Todos con la
 * jornada del fixture: 8:00–12:00 y 14:00–18:00.
 */
class InicioAdministradorTest extends TestCase
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
     * RNF-11 y RE-6.2.7 a RE-6.2.12: el Inicio de Norte no trae nada de Sur, ni
     * citas, ni veterinarios, ni ausencias, ni propuestas, ni uso del plan.
     */
    public function testElInicioDeNorteNoTraeNadaDeSur(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'confirmada');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, self::HOY, '09:00:00', 'confirmada', 'rojo', self::HOY . ' 08:50:00');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-10-20', '09:00:00', 'pendiente');
        $this->ausencia(DosClinicas::SUR, DosClinicas::VET_SUR, '2026-10-12 08:00:00', '2026-10-12 18:00:00', 'solicitada');
        $this->propuesta(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->db->exec("INSERT INTO solicitudes_agenda (id_clinica, id_mascota, id_actor, origen, estado, estado_revision, nivel_calculado, contexto_triage, huella_entrada, fecha_llegada)
            VALUES (2, 3, 4, 'llegada', 'espera_presencial', 'no_requerida', 'verde', '{}', 'x', '2026-10-07 09:30:00')");

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $norte = $this->panel('10:00');

        $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($norte['agenda']['columnas'], 'nombre'), 'Hugo tiene la cuenta inactiva y Diego es de Sur');
        $this->assertSame(['Luna'], $this->mascotasDeLaAgenda($norte));
        $this->assertSame(0, $norte['en_espera']);
        $this->assertSame(0, $norte['urgencias']);
        $this->assertSame([], $norte['ausencias_por_aprobar']);
        $this->assertSame([], $norte['propuestas_por_aprobar']);
        $this->assertSame([self::HOY], $this->diasConCitas($norte), 'La cita del 20 es de Sur');
        $limiteNorte = (new LimitePlan($this->db, DosClinicas::NORTE))->resumen(self::HOY);
        $this->assertSame($limiteNorte['citas']['uso'], $norte['plan']['recursos']['citas']['uso']);
        $this->assertSame(1, $norte['plan']['recursos']['citas']['uso']);

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR);
        $sur = $this->panel('10:00');
        $this->assertSame(['Diego Sur'], array_column($sur['agenda']['columnas'], 'nombre'));
        $this->assertSame(['Toby'], $this->mascotasDeLaAgenda($sur));
        $this->assertSame(2, $sur['en_espera'], 'Toby llegó y una llegada sin reserva espera');
        $this->assertSame(1, $sur['urgencias']);
        $this->assertCount(1, $sur['ausencias_por_aprobar']);
        $this->assertCount(1, $sur['propuestas_por_aprobar']);
        $this->assertSame(2, $sur['plan']['recursos']['citas']['uso']);
    }

    /** RE-6.2.2 y RE-6.2.9: un veterinario activo sin citas aparece con su jornada libre. */
    public function testUnVeterinarioSinCitasApareceEnLaAgenda(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'confirmada');
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);

        $elena = $this->columna($this->panel('07:00'), 'Elena Doble');
        $this->assertSame(0, $elena['citas']);
        $this->assertSame(0, $elena['ocupacion']);
        $this->assertSame(['8:00 a. m. libre', '12:00 p. m. bloqueo', '2:00 p. m. libre'], array_map(fn($l) => $l['hora'] . ' ' . $l['tipo'], $elena['lineas']));
    }

    /**
     * RE-6.2.9 y RN-415: cada columna dice lo mismo que el Inicio del propio
     * veterinario: huecos, almuerzo, cierres y ausencias. La ausencia corta la
     * tarde y la ocupación se calcula sobre la jornada efectiva.
     */
    public function testHuecosYAusenciasIgualesALaDisponibilidadReal(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::DOBLE, self::HOY, '09:00:00', 'confirmada');
        $this->cita(DosClinicas::NORTE, self::KIRA, DosClinicas::DOBLE, self::HOY, '10:00:00', 'completada', 'rojo');
        $this->ausencia(DosClinicas::NORTE, DosClinicas::DOBLE, self::HOY . ' 14:00:00', self::HOY . ' 16:00:00', 'aprobada');
        $this->cierre(DosClinicas::NORTE, self::HOY . ' 17:00:00', self::HOY . ' 18:00:00', 'Fumigación');

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $columna = $this->columna($this->panel('07:00'), 'Elena Doble');

        $this->como(DosClinicas::NORTE, DosClinicas::DOBLE, Roles::VETERINARIO);
        $propio = (new PanelController($this->db, $this->reloj('07:00')))->datosVeterinario(DosClinicas::DOBLE, $this->reloj('07:00')());

        $sinSiguiente = fn(array $l) => $l['tipo'] === 'cita' ? $l['hora'] . ' cita ' . $l['mascota'] : $l['hora'] . ' ' . $l['tipo'] . ' ' . $l['texto'];
        $this->assertSame(array_map($sinSiguiente, $propio['dia']['lineas']), array_map($sinSiguiente, $columna['lineas']));
        $this->assertSame([
            '8:00 a. m. libre Libre · 1 h',
            '9:00 a. m. cita Luna',
            '10:00 a. m. cita Kira',
            '10:40 a. m. libre Libre · 1 h 20 min',
            '12:00 p. m. bloqueo Almuerzo · hasta las 2:00 p. m.',
            '2:00 p. m. bloqueo Ausencia · hasta las 4:00 p. m.',
            '4:00 p. m. libre Libre · 1 h',
            '5:00 p. m. bloqueo Clínica cerrada · Fumigación · resto del día',
        ], array_map($sinSiguiente, $columna['lineas']));
        $this->assertSame(['almuerzo', 'ausencia', 'cierre'], array_column(array_filter($columna['lineas'], fn($l) => $l['tipo'] === 'bloqueo'), 'clase'));
        $this->assertSame([false, true], array_column(array_filter($columna['lineas'], fn($l) => $l['tipo'] === 'cita'), 'urgencia'), 'La cita roja se marca');
        // Jornada efectiva 8 h − 2 h de ausencia − 1 h de cierre = 5 h. Libres 3 h 40 min: también
        // cuenta el hueco de 20 min (9:40–10:00), que no se ofrece para agendar pero no está ocupado → 27 %.
        $this->assertSame(27, $columna['ocupacion']);
        $this->assertSame('por_atender', $columna['lineas'][1]['estado'], 'En la agenda del administrador no hay «siguiente»');
    }

    /** RE-6.2.3 y RE-6.2.11: los conteos de pendientes, con ausencias y propuestas por aprobar. */
    public function testConteosDePendientes(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-06', '09:00:00', 'sin_cerrar');
        $this->cita(DosClinicas::NORTE, self::KIRA, DosClinicas::DOBLE, '2026-10-06', '11:00:00', 'confirmada');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::DOBLE, '2026-10-09', '09:00:00', 'pendiente');
        $this->ausencia(DosClinicas::NORTE, DosClinicas::DOBLE, '2026-10-12 08:00:00', '2026-10-12 18:00:00', 'solicitada');
        $this->ausencia(DosClinicas::NORTE, DosClinicas::VET_NORTE, '2026-10-09 08:00:00', '2026-10-09 12:00:00', 'solicitada');
        $this->ausencia(DosClinicas::NORTE, DosClinicas::VET_NORTE, '2026-10-10 08:00:00', '2026-10-10 12:00:00', 'rechazada');
        $this->propuesta(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->db->exec("INSERT INTO propuestas_horario (id_veterinario, id_clinica, franjas, estado) VALUES (5, 1, '[]', 'aprobada')");

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $panel = $this->panel('10:00');

        $this->assertSame(['Luna'], array_column($panel['sin_cerrar'], 'mascota'));
        $this->assertSame('Ayer', $panel['sin_cerrar'][0]['dia']);
        $this->assertSame(1, $panel['pendientes']['sin_marcar']);
        $this->assertSame(1, $panel['pendientes']['por_confirmar']);
        $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($panel['ausencias_por_aprobar'], 'veterinario'), 'Solo las solicitadas, de la más próxima a la más lejana');
        $this->assertSame('vie 9 oct', $panel['ausencias_por_aprobar'][0]['dia']);
        $this->assertSame(['Beto Norte'], array_column($panel['propuestas_por_aprobar'], 'veterinario'));

        $html = $this->vista($panel);
        $this->assertStringContainsString('6 por resolver', $html);
        $this->assertStringContainsString('Luna, de Beto (ayer)', $html);
        $this->assertStringContainsString('index.php?action=admin_configuracion#ausencias', $html);
        $this->assertStringContainsString('index.php?action=admin_configuracion#horarios', $html);
        $this->assertStringNotContainsString('Todo al día', $html);
    }

    /** RE-6.2.10 (HU-0.4): el uso del plan es el de LimitePlan, con su nivel. */
    public function testUsoDelPlanContraLimitePlan(): void
    {
        $this->db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 1');
        $this->db->exec('UPDATE suscripciones SET id_plan = 1 WHERE id_clinica = 1');
        for ($i = 0; $i < 80; $i++) {
            $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-01', sprintf('%02d:%02d:00', 8 + intdiv($i, 6), ($i % 6) * 10), 'completada');
        }
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $panel = $this->panel('10:00');
        $limite = (new LimitePlan($this->db, DosClinicas::NORTE))->resumen(self::HOY);

        $this->assertSame('Gratuito', $panel['plan']['nombre']);
        foreach (['mascotas', 'citas', 'personal'] as $recurso) {
            $this->assertSame($limite[$recurso]['uso'], $panel['plan']['recursos'][$recurso]['uso'], $recurso);
            $this->assertSame($limite[$recurso]['limite'], $panel['plan']['recursos'][$recurso]['limite'], $recurso);
        }
        $this->assertSame(['aviso', 80], [$panel['plan']['recursos']['citas']['nivel'], $panel['plan']['recursos']['citas']['porcentaje']]);
        $this->assertSame('limite', $panel['plan']['recursos']['personal']['nivel'], 'Ana, Beto, Elena y Hugo pasan de 2');

        $this->db->exec('UPDATE clinicas SET id_plan = 2 WHERE id_clinica = 1');
        $this->db->exec('UPDATE suscripciones SET id_plan = 2 WHERE id_clinica = 1');
        $profesional = $this->panel('10:00')['plan'];
        $this->assertSame('Profesional', $profesional['nombre']);
        $this->assertSame([null, 'libre'], [$profesional['recursos']['citas']['limite'], $profesional['recursos']['citas']['nivel']]);
    }

    /** RE-6.2.6: una clínica sin citas muestra ceros y estados vacíos reales. */
    public function testEstadosVacios(): void
    {
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $panel = $this->panel('10:00');

        $this->assertSame(0, $panel['contadores']['citas']);
        $this->assertSame([0, 0], [$panel['en_espera'], $panel['urgencias']]);
        $this->assertSame(0, $panel['agenda']['citas']);
        $this->assertSame([0, 0], array_column($panel['agenda']['columnas'], 'citas'));
        $this->assertSame([], $panel['sin_cerrar']);
        $this->assertSame(['no_asistio' => 0, 'agendadas' => 0, 'porcentaje' => 0], $panel['no_asistencia']);
        $this->assertSame(0, $panel['propietarios_nuevos']);
        $this->assertSame([0], array_values(array_unique(array_merge(array_column($panel['tendencia'], 'atendidas'), array_column($panel['tendencia'], 'no_asistidas')))));

        $html = $this->vista($panel);
        $this->assertStringContainsString('Todo al día · la clínica no tiene nada pendiente', $html);
        $this->assertStringContainsString('Sin datos de septiembre para comparar', $html);
        $this->assertStringContainsString('0 citas · toca un día del calendario', $html);
        $this->assertStringNotContainsString('Agendar', $html, 'RN-401: el administrador no agenda');
    }

    /** RE-6.2.12: propietarios nuevos del mes y no asistencia del mes hasta hoy. */
    public function testEsteMesPropietariosNuevosYNoAsistencia(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-02', '09:00:00', 'no_asistio');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-03', '09:00:00', 'completada');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-05', '09:00:00', 'completada');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-06', '09:00:00', 'cancelada');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-20', '09:00:00', 'pendiente');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-09-20', '09:00:00', 'no_asistio');
        $this->db->exec("UPDATE propietario_clinica SET fecha_vinculo = '2026-10-03 10:00:00' WHERE id_propietario = 6 AND id_clinica = 1");
        $this->db->exec("UPDATE propietario_clinica SET fecha_vinculo = '2026-09-03 10:00:00' WHERE id_propietario = 5 AND id_clinica = 1");
        $this->db->exec("UPDATE propietario_clinica SET fecha_vinculo = '2026-10-03 10:00:00' WHERE id_propietario = 9 AND id_clinica = 2");

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $panel = $this->panel('10:00');
        $this->assertSame(['no_asistio' => 1, 'agendadas' => 3, 'porcentaje' => 33], $panel['no_asistencia'], 'Sin canceladas ni citas futuras');
        $this->assertSame(1, $panel['propietarios_nuevos'], 'Fabio en octubre; Elena en septiembre e Iris en Sur no cuentan');
    }

    /**
     * RE-6.2.8 y RE-6.2.9: panel_admin_dia_ajax cambia la agenda de día y el
     * calendario de mes, solo de la clínica activa; las fechas inválidas fallan.
     */
    public function testCambioDeDiaYDeMesPorLaAccionDelAdministrador(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::DOBLE, '2026-10-09', '09:00:00', 'pendiente');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-10-09', '10:00:00', 'pendiente');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-11-04', '10:00:00', 'pendiente');
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);

        $dia = $this->diaAjax(['fecha' => '2026-10-09']);
        $this->assertTrue($dia['success']);
        $this->assertStringContainsString('Agenda del viernes 9 por veterinario', $dia['html']);
        $this->assertStringContainsString('Luna', $dia['html']);
        $this->assertStringNotContainsString('Toby', $dia['html']);
        $this->assertStringNotContainsString('Diego', $dia['html']);

        $mes = $this->diaAjax(['mes' => '2026-11', 'elegido' => '2026-10-09']);
        $this->assertStringContainsString('Noviembre 2026', $mes['html']);
        $this->assertStringNotContainsString('con citas', $mes['html'], 'La cita del 4 de noviembre es de Sur');

        foreach ([['fecha' => '2026-02-30'], ['mes' => '2026-13'], []] as $consulta) {
            $this->assertFalse($this->diaAjax($consulta)['success'], json_encode($consulta));
        }
    }

    /** RE-6.2.9: el administrador lee la disponibilidad de los veterinarios de su clínica; la de otra clínica, no. */
    public function testElAdministradorSoloLeeVeterinariosDeSuClinica(): void
    {
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $dia = (new DisponibilidadAgenda($this->db))->diaDelVeterinario(DosClinicas::DOBLE, self::HOY);
        $this->assertCount(2, $dia['jornada']);
        try {
            (new DisponibilidadAgenda($this->db))->diaDelVeterinario(DosClinicas::VET_SUR, self::HOY);
            $this->fail('Diego no es veterinario de Norte.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function como(int $clinica, int $idUsuario, int $rol = Roles::ADMIN): void
    {
        $_SESSION = ['id_usuario' => $idUsuario, 'usuario_nombre' => 'Ana Norte'];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', $rol), 1);
    }

    private function reloj(string $hora): Closure
    {
        $ahora = new DateTimeImmutable(self::HOY . ' ' . $hora, new DateTimeZone(ReglaAtencion::ZONA));
        return fn (): DateTimeImmutable => $ahora;
    }

    private function panel(string $hora): array
    {
        $reloj = $this->reloj($hora);
        return (new PanelController($this->db, $reloj))->datosAdministrador($reloj());
    }

    private function diaAjax(array $consulta): array
    {
        $_GET = $consulta;
        ob_start();
        (new PanelController($this->db, $this->reloj('10:00')))->diaAdminAjax();
        return json_decode((string) ob_get_clean(), true);
    }

    /** La vista real del Inicio con los datos del panel. */
    private function vista(array $panel): string
    {
        ob_start();
        require __DIR__ . '/../../views/admin/panel.php';
        return (string) ob_get_clean();
    }

    private function columna(array $panel, string $nombre): array
    {
        foreach ($panel['agenda']['columnas'] as $columna) {
            if ($columna['nombre'] === $nombre) {
                return $columna;
            }
        }
        $this->fail("No hay columna de $nombre.");
    }

    private function mascotasDeLaAgenda(array $panel): array
    {
        $mascotas = [];
        foreach ($panel['agenda']['columnas'] as $columna) {
            foreach ($columna['lineas'] as $linea) {
                if ($linea['tipo'] === 'cita') {
                    $mascotas[] = $linea['mascota'];
                }
            }
        }
        return $mascotas;
    }

    private function diasConCitas(array $panel): array
    {
        return array_column(array_filter(array_filter($panel['mes']['celdas']), fn($c) => $c['citas']), 'fecha');
    }

    private function cita(int $clinica, int $mascota, int $veterinario, string $fecha, string $hora, string $estado, string $prioridad = 'verde', ?string $llegada = null): void
    {
        $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado, duracion_minutos, margen_minutos, prioridad, hora_llegada)
            VALUES (?, ?, ?, ?, ?, ?, 'Control', ?, 30, 10, ?, ?)")->execute([$clinica, $mascota, $veterinario, $clinica, $fecha, $hora, $estado, $prioridad, $llegada]);
    }

    private function ausencia(int $clinica, int $veterinario, string $inicio, string $fin, string $estado): void
    {
        $this->db->prepare('INSERT INTO ausencias_veterinario (id_veterinario, id_clinica, fecha_hora_inicio, fecha_hora_fin, motivo, estado) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$veterinario, $clinica, $inicio, $fin, 'Personal', $estado]);
    }

    private function propuesta(int $clinica, int $veterinario): void
    {
        $this->db->prepare("INSERT INTO propuestas_horario (id_veterinario, id_clinica, franjas, estado) VALUES (?, ?, '[]', 'pendiente')")
            ->execute([$veterinario, $clinica]);
    }

    private function cierre(int $clinica, string $inicio, string $fin, string $motivo): void
    {
        $this->db->prepare('INSERT INTO cierres_clinica (id_clinica, inicio, fin, motivo, activo, id_autor) VALUES (?, ?, ?, ?, 1, 1)')
            ->execute([$clinica, $inicio, $fin, $motivo]);
    }
}
