<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../helpers/VigilanteAtenciones.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/**
 * Módulo 4 — Estados de la cita en el modelo v2 (C5).
 *
 * RN-405 a RN-409 con el fixture de dos clínicas: cada transición solo
 * parte de sus estados de origen y solo toca la cita de su clínica; la
 * vigilancia de RN-409 avisa una sola vez, en la clínica de la cita y al
 * veterinario por id_usuario. «Cerrar sin consulta» ya no existe (D-3,
 * RN-410 derogada).
 */
final class CitaEstadoTest extends TestCase
{
    private const FECHA = '2030-01-07';

    private PDO $db;
    private Cita $citas;
    private int $id;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->citas = new Cita($this->db);
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->id = $this->reservar('09:00');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ── RN-407, RE-4.3.4, RE-4.5.1 ──────────────────────────────────────

    public function testIniciarGuardaEnCursoYSellaLaHoraRealUnaSolaVez(): void
    {
        $this->assertTrue($this->citas->iniciarAtencion($this->cita(), self::FECHA . ' 08:50:00'));
        $this->assertFalse($this->citas->iniciarAtencion($this->cita(), self::FECHA . ' 09:10:00'), 'Iniciar dos veces no pisa el sello');

        $this->assertSame('en_curso', $this->campo('estado'));
        $this->assertSame(self::FECHA . ' 08:50:00', $this->campo('hora_inicio_real'));
    }

    public function testUnaCitaCerradaNoSeIniciaNiSeCancela(): void
    {
        foreach (['cancelada', 'completada', 'no_asistio', 'sin_cerrar', 'en_curso'] as $estado) {
            $this->ponerEstado($estado);
            $this->assertFalse($this->citas->iniciarAtencion($this->cita(), self::FECHA . ' 09:00:00'), "No debe iniciar una cita $estado");
            $this->assertFalse($this->citas->cancelar($this->cita()), "No debe cancelar una cita $estado");
            $this->assertSame($estado, $this->campo('estado'));
        }
    }

    // ── RN-406, RE-4.3.1 ────────────────────────────────────────────────

    public function testSoloSeCompletaUnaAtencionEnCursoOSinCerrar(): void
    {
        $this->assertFalse($this->citas->completarAtencion($this->cita(), self::FECHA . ' 09:30:00'));

        $this->ponerEstado('sin_cerrar');
        $this->assertTrue($this->citas->completarAtencion($this->cita(), self::FECHA . ' 09:30:00'));
        $this->assertSame('completada', $this->campo('estado'));
        $this->assertSame(self::FECHA . ' 09:30:00', $this->campo('hora_fin_real'));
    }

    // ── RN-408, RE-4.7.1, RE-4.7.2 ──────────────────────────────────────

    public function testNoAsistioSoloDesdeUnaCitaAbiertaYLiberaElHorario(): void
    {
        $this->assertTrue($this->citas->marcarNoAsistio($this->cita()));
        $this->assertFalse($this->citas->marcarNoAsistio($this->cita()));

        $otra = $this->reservar('09:00');

        $this->assertSame('confirmada', $this->db->query("SELECT estado FROM citas WHERE id_cita = $otra")->fetchColumn());
    }

    public function testSoloSeConfirmaUnaCitaPendiente(): void
    {
        $this->assertFalse($this->citas->confirmar($this->cita()), 'Ya estaba confirmada');

        $this->ponerEstado('pendiente');
        $this->assertTrue($this->citas->confirmar($this->cita()));
        $this->assertSame('confirmada', $this->campo('estado'));
    }

    // ── RN-409, RE-4.3.6, RE-4.3.7 ──────────────────────────────────────

    public function testElAvisoDeAtencionAbiertaSaleUnaSolaVezEnLaClinicaDeLaCita(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->ponerEstado('en_curso');
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $deSur = $this->reservar('09:30', DosClinicas::VET_SUR, 2);
        $this->db->exec("UPDATE citas SET estado = 'en_curso' WHERE id_cita = $deSur");

        $correos = [];
        $vigilante = new VigilanteAtenciones($this->db, function (array $cita) use (&$correos): void {
            $correos[] = (int) $cita['id_cita'];
        });

        $this->assertSame(0, $vigilante->revisar($this->hora('09:39'))['avisadas'], 'Antes de los 10 minutos de gracia no se avisa');
        // Norte termina 9:30 (aviso 9:40); Sur, 9:50 (aviso 10:00).
        $this->assertSame(1, $vigilante->revisar($this->hora('09:41'))['avisadas']);
        $this->assertSame(1, $vigilante->revisar($this->hora('10:01'))['avisadas']);
        $this->assertSame(0, $vigilante->revisar($this->hora('10:30'))['avisadas'], 'El aviso sale una sola vez');

        $avisos = $this->db->query("SELECT id_clinica, id_usuario, id_cita FROM notificaciones_internas WHERE tipo = 'ATENCION_ABIERTA' ORDER BY id_cita")->fetchAll(PDO::FETCH_NUM);
        $this->assertSame([[1, 2, $this->id], [2, 4, $deSur]], array_map(fn ($fila) => array_map('intval', $fila), $avisos));
        $this->assertSame([$this->id, $deSur], $correos);
        $this->assertNotNull($this->campo('aviso_atencion_abierta'));
    }

    public function testAlTerminarElDiaLaAtencionQuedaSinCerrarYSeAvisa(): void
    {
        $this->ponerEstado('en_curso');
        $vigilante = new VigilanteAtenciones($this->db, function (): void {
        });

        $resultado = $vigilante->revisar(new DateTimeImmutable('2030-01-08 07:00', new DateTimeZone(ReglaAtencion::ZONA)));

        $this->assertSame(1, $resultado['sin_cerrar']);
        $this->assertSame('sin_cerrar', $this->campo('estado'));
        $mensaje = $this->db->query("SELECT mensaje FROM notificaciones_internas WHERE tipo = 'ATENCION_SIN_CERRAR'")->fetchColumn();
        $this->assertStringContainsString('Registra su consulta', $mensaje);
        $this->assertStringNotContainsString('sin consulta', $mensaje);
        $this->assertTrue($this->citas->completarAtencion($this->cita(), '2030-01-08 07:30:00'), 'Una atención sin cerrar se completa con su consulta');
    }

    // ── D-3, RN-410 derogada ────────────────────────────────────────────

    public function testYaNoExisteCerrarSinConsulta(): void
    {
        $this->assertFalse(method_exists(Cita::class, 'cerrarSinConsulta'));
        $this->assertFalse(method_exists(CitaController::class, 'cerrarSinConsultaAjax'));
        $this->assertNotContains('cerrada_sin_consulta', Cita::ESTADOS_ABIERTOS);
        $this->assertNotContains('cerrada_sin_consulta', Cita::ESTADOS_EN_ATENCION);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function reservar(string $hora, int $veterinario = DosClinicas::VET_NORTE, ?int $tipo = 1): int
    {
        return $this->citas->registrar([
            'id_mascota' => 1,
            'id_veterinario' => $veterinario,
            'fecha' => self::FECHA,
            'hora' => $hora,
            'motivo' => 'Control',
            'id_tipo_cita' => $tipo,
        ]);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    /** Hora de la clínica: ReglaAtencion compara en America/Bogota. */
    private function hora(string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable(self::FECHA . ' ' . $hora, new DateTimeZone(ReglaAtencion::ZONA));
    }

    private function cita(): array
    {
        return ['id_cita' => $this->id, 'id_clinica' => DosClinicas::NORTE];
    }

    private function ponerEstado(string $estado): void
    {
        $this->db->prepare('UPDATE citas SET estado = ? WHERE id_cita = ?')->execute([$estado, $this->id]);
    }

    private function campo(string $columna)
    {
        return $this->db->query("SELECT $columna FROM citas WHERE id_cita = {$this->id}")->fetchColumn();
    }
}
