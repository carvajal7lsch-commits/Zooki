<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
require_once __DIR__ . '/../../models/Panel.php';
require_once __DIR__ . '/../../controllers/PanelController.php';
require_once __DIR__ . '/../../controllers/DashboardController.php';

/**
 * C7: paneles de inicio y pendientes del día con dos clínicas (HU-6.1,
 * HU-6.2, HU-6.4, RNF-11). Cada cifra sale solo de la clínica activa, el
 * veterinario ve lo suyo por id_usuario y cambiar de contexto cambia los
 * números.
 */
class PanelTest extends TestCase
{
    private const LUNA = 1;
    private const KIRA = 2;
    private const TOBY = 3;
    private const REX = 4;
    private const HOY = '2026-10-07';

    private PDO $db;
    private DateTimeImmutable $ahora;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->ahora = new DateTimeImmutable(self::HOY . ' 10:00', new DateTimeZone(ReglaAtencion::ZONA));

        // Kira es de Fabio en Norte; Toby y Rex son de Iris en Sur, y Rex
        // ya no está vinculado a Sur (RN-115).
        $mascota = $this->db->prepare("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (?, ?, ?, ?, ?, 1, 1)");
        $mascota->execute([self::KIRA, DosClinicas::PROPIETARIO, DosClinicas::NORTE, str_repeat('b', 43), 'Kira']);
        $mascota->execute([self::TOBY, DosClinicas::GOOGLE, DosClinicas::SUR, str_repeat('c', 43), 'Toby']);
        $mascota->execute([self::REX, DosClinicas::GOOGLE, DosClinicas::SUR, str_repeat('d', 43), 'Rex']);
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES
            (2, 1, 'activo'), (3, 2, 'activo'), (4, 2, 'inactivo')");

        // Norte: Beto y Elena. Sur: Diego.
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '09:00:00', 'completada');
        $this->cita(DosClinicas::NORTE, self::KIRA, DosClinicas::VET_NORTE, self::HOY, '11:00:00', 'confirmada');
        $this->cita(DosClinicas::NORTE, self::KIRA, DosClinicas::DOBLE, self::HOY, '08:00:00', 'pendiente');
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-09-10', '09:00:00', 'no_asistio');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, self::HOY, '09:00:00', 'pendiente');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, self::HOY, '15:00:00', 'pendiente');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-09-10', '10:00:00', 'completada');
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-10-01', '09:00:00', 'sin_cerrar');

        $this->consulta(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-02 10:00:00');
        $this->consulta(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-10-03 10:00:00');
        $this->consulta(DosClinicas::SUR, self::TOBY, DosClinicas::VET_SUR, '2026-10-05 10:00:00');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    /** RNF-11 / RE-6.2.1–5: el panel del administrador de Norte no incluye nada de Sur, y al revés. */
    public function testCadaAdministradorVeSoloSuClinica(): void
    {
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $norte = (new PanelController($this->db))->datosAdministrador($this->ahora);

        $this->assertSame(['Kira', 'Luna', 'Kira'], array_column($norte['citas'], 'mascota'));
        $this->assertSame(1, $norte['consultas_mes']);
        $this->assertSame([], $norte['pendientes']['sin_cerrar']);
        $this->assertSame(1, $norte['pendientes']['por_confirmar']);
        $this->assertSame(1, $norte['pendientes']['sin_marcar']);
        $this->assertSame(['Beto Norte', 'Elena Doble'], array_column($norte['carga'], 'veterinario'));

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $sur = (new PanelController($this->db))->datosAdministrador($this->ahora);

        $this->assertSame(['Toby', 'Toby'], array_column($sur['citas'], 'mascota'));
        $this->assertSame(2, $sur['consultas_mes']);
        $this->assertSame(['Diego Sur' => 1], $sur['pendientes']['sin_cerrar']);
        $this->assertSame(2, $sur['pendientes']['por_confirmar']);
        $this->assertSame(1, $sur['pendientes']['sin_marcar']);
        $this->assertSame(['Diego Sur'], array_column($sur['carga'], 'veterinario'));
    }

    /** RE-6.2.4: la tendencia mensual también es solo de la clínica activa. */
    public function testLaTendenciaEsDeLaClinicaActiva(): void
    {
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $norte = (new Panel($this->db))->tendenciaMensual('2026-05-01', self::HOY);
        $this->assertSame(['2026-09', '2026-10'], array_column($norte, 'mes'));
        $this->assertEquals([0, 1], [$norte[0]['atendidas'], $norte[0]['no_asistidas']]);
        $this->assertEquals([1, 0], [$norte[1]['atendidas'], $norte[1]['no_asistidas']]);

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $sur = (new Panel($this->db))->tendenciaMensual('2026-05-01', self::HOY);
        $this->assertEquals([1, 0], [$sur[0]['atendidas'], $sur[0]['no_asistidas']]);
        $this->assertEquals([0, 0], [$sur[1]['atendidas'], $sur[1]['no_asistidas']]);
    }

    /** RE-6.1.4: la misma persona, veterinaria en dos clínicas, ve números distintos según el contexto. */
    public function testElMismoVeterinarioVeNumerosDistintosSegunLaClinica(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (2, 2, 2, 'activo')");
        $this->cita(DosClinicas::SUR, self::TOBY, DosClinicas::VET_NORTE, self::HOY, '14:00:00', 'pendiente');

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE, Roles::VETERINARIO);
        $enNorte = (new PanelController($this->db))->datosVeterinario(DosClinicas::VET_NORTE, $this->ahora);
        $this->assertSame(['Luna', 'Kira'], array_column($enNorte['agenda'], 'mascota'));

        $this->como(DosClinicas::SUR, DosClinicas::VET_NORTE, Roles::VETERINARIO);
        $enSur = (new PanelController($this->db))->datosVeterinario(DosClinicas::VET_NORTE, $this->ahora);
        $this->assertSame(['Toby'], array_column($enSur['agenda'], 'mascota'));
        $this->assertSame([], $enSur['pendientes']);

        // En Sur la carga ya lo cuenta, pero solo con su cita de Sur.
        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $carga = array_column((new Panel($this->db))->cargaPorVeterinario(self::HOY), 'total', 'veterinario');
        $this->assertEquals(['Diego Sur' => 2, 'Beto Norte' => 1], $carga);
    }

    /** RN-G01: Elena es veterinaria en Norte y administradora en Sur; cada contexto ve lo suyo. */
    public function testElenaVeSuAgendaEnNorteYLaClinicaEnSur(): void
    {
        $this->como(DosClinicas::NORTE, DosClinicas::DOBLE, Roles::VETERINARIO);
        $vet = (new PanelController($this->db))->datosVeterinario(DosClinicas::DOBLE, $this->ahora);
        $this->assertSame(['Kira'], array_column($vet['agenda'], 'mascota'));
        $this->assertSame([DosClinicas::DOBLE], array_map('intval', array_column($vet['agenda'], 'id_veterinario')));

        $this->como(DosClinicas::SUR, DosClinicas::DOBLE, Roles::ADMIN);
        $admin = (new PanelController($this->db))->datosAdministrador($this->ahora);
        $this->assertSame([DosClinicas::VET_SUR], array_unique(array_map('intval', array_column($admin['citas'], 'id_veterinario'))));
        $this->assertSame(['Diego Sur'], array_column($admin['carga'], 'veterinario'));
    }

    /**
     * RE-6.2.2: la carga lista los veterinarios activos de la clínica por
     * usuario_clinica: ni administradores, ni cuentas inactivas, ni
     * vínculos inactivos, ni veterinarios de otra clínica.
     */
    public function testLaCargaSoloListaLosVeterinariosActivosDeLaClinica(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (4, 1, 2, 'inactivo')");
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, self::HOY, '16:00:00', 'cancelada');

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $carga = (new Panel($this->db))->cargaPorVeterinario(self::HOY);

        $this->assertSame([DosClinicas::VET_NORTE, DosClinicas::DOBLE], array_map('intval', array_column($carga, 'id_usuario')));
        $this->assertEquals([2, 1], [$carga[0]['total'], $carga[0]['atendidas']]);
        $this->assertEquals([1, 0], [$carga[1]['total'], $carga[1]['atendidas']]);
    }

    /** RE-6.2.2 y RE-6.2.6: un veterinario activo sin citas aparece en cero. */
    public function testUnVeterinarioSinCitasApareceEnCero(): void
    {
        $this->db->exec('DELETE FROM citas WHERE id_veterinario = 5');

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $carga = array_column((new Panel($this->db))->cargaPorVeterinario(self::HOY), null, 'veterinario');

        $this->assertEquals(0, $carga['Elena Doble']['total']);
        $this->assertEquals(0, $carga['Elena Doble']['atendidas']);
    }

    /**
     * RE-6.4.1 / RN-115: pacientes activos y propietarios de la clínica activa.
     * Una mascota con el vínculo inactivo no es paciente activo.
     */
    public function testPacientesYPropietariosSonDeLaClinicaYSinVinculosInactivos(): void
    {
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $norte = new Panel($this->db);
        $this->assertSame(2, $norte->contarPacientesActivos());
        $this->assertSame(2, $norte->contarPropietarios());

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $sur = new Panel($this->db);
        $this->assertSame(1, $sur->contarPacientesActivos(), 'Rex está desvinculado de Sur.');
        $this->assertSame(1, $sur->contarPropietarios());

        DosClinicas::vincularLunaASur($this->db);
        $this->assertSame(2, $sur->contarPacientesActivos());
        $this->assertSame(2, $sur->contarPropietarios());

        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 1 AND id_clinica = 2");
        $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 2");
        $this->assertSame(1, $sur->contarPacientesActivos());
        $this->assertSame(1, $sur->contarPropietarios());

        // Una mascota desactivada tampoco cuenta, aunque su vínculo siga activo.
        $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 2');
        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $panel = (new PanelController($this->db))->datosAdministrador($this->ahora);
        $this->assertSame(1, $panel['pacientes_activos']);
        $this->assertSame(2, $panel['propietarios']);
    }

    /**
     * RE-6.1.1: los recordatorios son de la clínica activa y de los pacientes
     * del veterinario en ella; una mascota desvinculada deja de aparecer.
     */
    public function testLosRecordatoriosSonDeSusPacientesEnLaClinicaActiva(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->vacuna(DosClinicas::NORTE, self::LUNA, 'Rabia', '2026-10-09');
        $this->vacuna(DosClinicas::NORTE, self::LUNA, 'Leptospira', '2026-10-30');
        $this->vacuna(DosClinicas::SUR, self::LUNA, 'Parvovirus', '2026-10-08');
        $this->db->exec("INSERT INTO desparasitaciones (id_clinica, id_mascota, tipo, producto, periodicidad, fecha_aplicacion, fecha_proxima)
            VALUES (1, 2, 'interna', 'Bravecto', 'trimestral', '2026-07-10', '2026-10-10')");

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE, Roles::VETERINARIO);
        $panel = new Panel($this->db);
        $r = $panel->recordatoriosDeSusPacientes(DosClinicas::VET_NORTE, self::HOY, '2026-10-14');
        $this->assertSame(['Rabia', 'Bravecto'], array_column($r, 'nombre'));

        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 2 AND id_clinica = 1");
        $r = $panel->recordatoriosDeSusPacientes(DosClinicas::VET_NORTE, self::HOY, '2026-10-14');
        $this->assertSame(['Rabia'], array_column($r, 'nombre'));

        // Diego no ha atendido a Luna en Sur: la vacuna de Sur no es suya.
        $this->como(DosClinicas::SUR, DosClinicas::VET_SUR, Roles::VETERINARIO);
        $this->assertSame([], (new Panel($this->db))->recordatoriosDeSusPacientes(DosClinicas::VET_SUR, self::HOY, '2026-10-14'));
    }

    /** RN-409: las atenciones abiertas son de la clínica activa y, si se pide, del veterinario. */
    public function testLasAtencionesAbiertasSonDeLaClinicaActiva(): void
    {
        $this->cita(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-06', '09:00:00', 'sin_cerrar');

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $this->assertSame([DosClinicas::VET_NORTE], array_map('intval', array_column((new Panel($this->db))->atencionesAbiertas(), 'id_veterinario')));
        $this->assertSame([], (new Panel($this->db))->atencionesAbiertas(DosClinicas::DOBLE));

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $this->assertSame(['2026-10-01'], array_column((new Panel($this->db))->atencionesAbiertas(), 'fecha'));
    }

    public function testLasConsultasSeCuentanEnUnRangoSemiabiertoDeLaClinica(): void
    {
        $this->consulta(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-01 00:00:00');
        $this->consulta(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-07 23:59:00');
        $this->consulta(DosClinicas::NORTE, self::LUNA, DosClinicas::VET_NORTE, '2026-10-08 00:00:00');

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $this->assertSame(3, (new Panel($this->db))->contarConsultas('2026-10-01 00:00:00', '2026-10-08 00:00:00'));
    }

    /** Etapa E: el super-administrador no tiene panel clínico; sin clínica activa falla cerrado. */
    public function testSinClinicaActivaElPanelFallaCerrado(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::SUPER_ADMIN];
        Contexto::activar(Contexto::dePlataforma(), 1);

        try {
            (new PanelController($this->db))->datosAdministrador($this->ahora);
            $this->fail('Sin clínica activa no hay panel.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    /** RE-6.1.4: el aviso de citas de hoy es de la clínica activa; el veterinario solo ve las suyas. */
    public function testLosPendientesDelDiaSonDeLaClinicaYDelVeterinario(): void
    {
        $this->vacuna(DosClinicas::NORTE, self::LUNA, 'Rabia', '2026-10-09');
        $this->vacuna(DosClinicas::SUR, self::TOBY, 'Moquillo', '2026-10-09');

        $this->como(DosClinicas::NORTE, DosClinicas::VET_NORTE, Roles::VETERINARIO);
        $beto = $this->pendientes();
        $this->assertSame(['Kira'], array_column($beto['citas_hoy'], 'mascota'));

        $this->como(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE, Roles::ADMIN);
        $ana = $this->pendientes();
        $this->assertSame(['08:00:00', '11:00:00'], array_column($ana['citas_hoy'], 'hora'));
        $this->assertSame(['Rabia'], array_column($ana['vacunas_proximas'], 'nombre_vacuna'));

        $this->como(DosClinicas::SUR, DosClinicas::ADMIN_SUR, Roles::ADMIN);
        $carla = $this->pendientes();
        $this->assertSame(['Toby', 'Toby'], array_column($carla['citas_hoy'], 'mascota'));
        $this->assertSame(['Moquillo'], array_column($carla['vacunas_proximas'], 'nombre_vacuna'));
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function como(int $clinica, int $idUsuario, int $rol): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', $rol), 2);
    }

    private function cita(int $clinica, int $mascota, int $veterinario, string $fecha, string $hora, string $estado): void
    {
        $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, id_tipo_cita, fecha, hora, motivo, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'Control', ?)")->execute([$clinica, $mascota, $veterinario, $clinica, $fecha, $hora, $estado]);
    }

    private function consulta(int $clinica, int $mascota, int $veterinario, string $fechaHora): void
    {
        $this->db->prepare("INSERT INTO consultas (id_clinica, id_mascota, id_veterinario, fecha_hora, motivo_consulta, anamnesis, diagnostico, plan_tratamiento)
            VALUES (?, ?, ?, ?, 'Control', 'Sin novedad', 'Sano', 'Ninguno')")->execute([$clinica, $mascota, $veterinario, $fechaHora]);
    }

    private function vacuna(int $clinica, int $mascota, string $nombre, string $proxima): void
    {
        $this->db->prepare("INSERT INTO vacunas (id_clinica, id_mascota, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis)
            VALUES (?, ?, ?, '2025-10-01', ?)")->execute([$clinica, $mascota, $nombre, $proxima]);
    }

    private function pendientes(): array
    {
        ob_start();
        (new DashboardController($this->db, fn (): DateTimeImmutable => $this->ahora))->getPendientesAjax();
        $respuesta = json_decode(ob_get_clean(), true);
        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        return $respuesta['pendientes'];
    }
}
