<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Desparasitacion.php';
require_once __DIR__ . '/../../models/CatalogoClinica.php';
require_once __DIR__ . '/../../controllers/VacunaController.php';

/**
 * Módulo 3 — Vacunación y desparasitación en la clínica activa (C4).
 *
 * HU-3.1 y HU-3.4 con RN-112 (clínica y veterinario), RN-207 (mascota activa
 * y vinculada), pendientes de la semana solo de la clínica activa (RE-3.5.1)
 * y catálogo de la clínica (C2, RN-702). Los recordatorios por correo son de C8.
 */
final class PrevencionTest extends TestCase
{
    private const LUNA = 1;
    private const HOY = '2026-10-07';

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    /** RE-3.1.1 / RN-112: la vacuna guarda sus datos, la clínica y el veterinario. */
    public function testLaVacunaGuardaSusDatosLaClinicaYElVeterinario(): void
    {
        $id = (new Vacuna($this->db))->registrar([
            'id_mascota' => self::LUNA,
            'nombre_vacuna' => 'Rabia',
            'laboratorio' => 'Zoetis',
            'lote' => 'L-01',
            'fecha_aplicacion' => '2026-10-01',
            'fecha_proxima' => '2027-10-01',
        ]);

        $vacuna = $this->fila("SELECT * FROM vacunas WHERE id_vacuna = $id");
        $this->assertSame(DosClinicas::NORTE, (int) $vacuna['id_clinica']);
        $this->assertSame(DosClinicas::VET_NORTE, (int) $vacuna['id_veterinario']);
        $this->assertSame('Zoetis', $vacuna['laboratorio']);
        $this->assertSame('L-01', $vacuna['lote']);
        $this->assertSame('2027-10-01', $vacuna['fecha_proxima_dosis']);
    }

    /** RE-3.4.1, RE-3.4.2 / RN-112: tipo, periodicidad, próxima aplicación, clínica y veterinario. */
    public function testLaDesparasitacionCalculaLaProximaYGuardaClinicaYVeterinario(): void
    {
        $id = (new Desparasitacion($this->db))->registrar([
            'id_mascota' => self::LUNA,
            'tipo' => 'externa',
            'periodicidad' => 'trimestral',
            'producto' => 'Bravecto',
            'fecha_aplicacion' => '2026-10-01',
        ]);

        $desparasitacion = $this->fila("SELECT * FROM desparasitaciones WHERE id_desparasitacion = $id");
        $this->assertSame('2027-01-01', $desparasitacion['fecha_proxima']);
        $this->assertSame('externa', $desparasitacion['tipo']);
        $this->assertSame(DosClinicas::NORTE, (int) $desparasitacion['id_clinica']);
        $this->assertSame(DosClinicas::VET_NORTE, (int) $desparasitacion['id_veterinario']);
    }

    public function testDatosInvalidosNoSeGuardan(): void
    {
        $vacunas = new Vacuna($this->db);
        $desparasitaciones = new Desparasitacion($this->db);

        $this->assertRechazo(fn () => $vacunas->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => '', 'fecha_aplicacion' => '2026-10-01']));
        $this->assertRechazo(fn () => $vacunas->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2999-01-01']));
        $this->assertRechazo(fn () => $vacunas->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01', 'fecha_proxima' => '2026-09-01']));
        $this->assertRechazo(fn () => $desparasitaciones->registrar(['id_mascota' => self::LUNA, 'tipo' => 'oral', 'periodicidad' => 'mensual', 'producto' => 'X', 'fecha_aplicacion' => '2026-10-01']));
        $this->assertRechazo(fn () => $desparasitaciones->registrar(['id_mascota' => self::LUNA, 'tipo' => 'interna', 'periodicidad' => 'anual', 'producto' => 'X', 'fecha_aplicacion' => '2026-10-01']));

        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM vacunas'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM desparasitaciones'));
    }

    /** RN-207: una mascota inactiva no admite registros; una no vinculada da 403 auditado. */
    public function testMascotaInactivaONoVinculadaNoAdmiteVacunaNiDesparasitacion(): void
    {
        $vacuna = ['id_mascota' => self::LUNA, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01'];
        $desparasitacion = ['id_mascota' => self::LUNA, 'tipo' => 'interna', 'periodicidad' => 'mensual', 'producto' => 'X', 'fecha_aplicacion' => '2026-10-01'];

        $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 1');
        $this->assertRechazo(fn () => (new Vacuna($this->db))->registrar($vacuna));
        $this->assertRechazo(fn () => (new Desparasitacion($this->db))->registrar($desparasitacion));

        $this->db->exec('UPDATE mascotas SET estado = 1 WHERE id_mascota = 1');
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertAccesoDenegado(fn () => (new Vacuna($this->db))->registrar($vacuna));
        $this->assertAccesoDenegado(fn () => (new Desparasitacion($this->db))->registrar($desparasitacion));

        $this->assertSame(2, $this->contar("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND accion = 'OTHER'"));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM vacunas'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM desparasitaciones'));
    }

    /** Petición directa al controlador con una mascota de otra clínica: 403, no un 200 con error. */
    public function testElControladorDeVacunasDejaPasarEl403(): void
    {
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $_POST = ['id_mascota' => '1', 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01'];

        ob_start();
        try {
            $this->assertAccesoDenegado(fn () => (new VacunaController($this->db))->registrarAjax());
        } finally {
            ob_end_clean();
        }
    }

    /** RE-3.5.1: próximas dosis de 7 días, solo las que aplicó la clínica activa. */
    public function testLosPendientesDeLaSemanaSonSoloDeLaClinicaActiva(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->insertarVacuna(DosClinicas::NORTE, 'Rabia Norte', '2026-10-10');
        $this->insertarVacuna(DosClinicas::NORTE, 'Fuera de rango', '2026-10-20');
        $this->insertarVacuna(DosClinicas::SUR, 'Moquillo Sur', '2026-10-09');
        $this->insertarDesparasitacion(DosClinicas::NORTE, 'Drontal Norte', '2026-10-08');
        $this->insertarDesparasitacion(DosClinicas::SUR, 'Bravecto Sur', '2026-10-08');
        $hoy = new DateTimeImmutable(self::HOY);

        $this->assertSame(['Rabia Norte'], array_column((new Vacuna($this->db))->pendientesSemana($hoy), 'nombre_vacuna'));
        $this->assertSame(['Drontal Norte'], array_column((new Desparasitacion($this->db))->pendientesSemana($hoy), 'producto'));

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame(['Moquillo Sur'], array_column((new Vacuna($this->db))->pendientesSemana($hoy), 'nombre_vacuna'));

        // Una mascota inactiva ya no genera pendientes.
        $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 1');
        $this->assertSame([], (new Vacuna($this->db))->pendientesSemana($hoy));
    }

    /** C2 / RN-702: lo que agrega el veterinario («Otra…») queda en el catálogo de su clínica. */
    public function testLasAltasDelCatalogoQuedanEnLaClinicaActivaSinDuplicar(): void
    {
        $catalogo = new CatalogoClinica($this->db);

        $vacuna = $catalogo->agregarVacuna('Leptospira X', null, 1);
        $repetida = $catalogo->agregarVacuna('leptospira x', null, 1);
        $laboratorio = $catalogo->agregarLaboratorio('Lab Norte');
        $producto = $catalogo->agregarProducto('Antipulgas Norte', 'externa');

        $this->assertSame($vacuna, $repetida);
        $this->assertSame(DosClinicas::NORTE, (int) $this->db->query("SELECT id_clinica FROM vacunas_base WHERE id_vacuna_base = $vacuna")->fetchColumn());
        $this->assertSame(DosClinicas::NORTE, (int) $this->db->query("SELECT id_clinica FROM laboratorios_base WHERE id_laboratorio = $laboratorio")->fetchColumn());
        $this->assertSame(DosClinicas::NORTE, (int) $this->db->query("SELECT id_clinica FROM productos_desparasitacion_base WHERE id_producto = $producto")->fetchColumn());
        $this->assertSame(['Leptospira X'], array_column($catalogo->vacunasPorEspecie(1), 'nombre_vacuna'));
        $this->assertRechazo(fn () => $catalogo->agregarProducto('Otro', 'oral'));

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame([], $catalogo->vacunasPorEspecie(1));
        $this->assertSame([], $catalogo->laboratorios());
        $this->assertSame([], $catalogo->productos());
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function insertarVacuna(int $clinica, string $nombre, string $proxima): void
    {
        $this->db->prepare("INSERT INTO vacunas (id_clinica, id_mascota, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis)
            VALUES (?, 1, ?, '2025-10-01', ?)")->execute([$clinica, $nombre, $proxima]);
    }

    private function insertarDesparasitacion(int $clinica, string $producto, string $proxima): void
    {
        $this->db->prepare("INSERT INTO desparasitaciones (id_clinica, id_mascota, tipo, producto, periodicidad, fecha_aplicacion, fecha_proxima)
            VALUES (?, 1, 'interna', ?, 'mensual', '2026-09-08', ?)")->execute([$clinica, $producto, $proxima]);
    }

    private function assertRechazo(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió rechazarse.');
        } catch (InvalidArgumentException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }

    private function assertAccesoDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió responder 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    private function fila(string $sql): array
    {
        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }
}
