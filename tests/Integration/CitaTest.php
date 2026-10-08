<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/CitaSinValidacionPrevia.php';
require_once __DIR__ . '/../../models/Cita.php';

/**
 * Módulo 4 — Agenda v1 sobre el modelo v2 (C5), con el fixture de dos clínicas.
 *
 * Cita por clínica e id_veterinario (RN-G13), duración y margen copiados del
 * tipo (RE-4.13.6), solapamiento del veterinario y de la mascota (RN-401,
 * RE-4.1.2, RE-4.2.4) también entre clínicas, y la doble reserva que decide
 * el índice único de la base (D-2, RE-4.9.3).
 */
final class CitaTest extends TestCase
{
    private const FECHA = '2030-01-07';
    private const LUNA = 1;

    private PDO $db;
    private Cita $citas;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->citas = new Cita($this->db);
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ── RE-4.1.1, RE-4.13.6: lo que guarda una reserva ──────────────────

    public function testRegistrarGuardaClinicaVeterinarioYCopiaDuracionYMargenDelTipo(): void
    {
        $id = $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);

        $cita = $this->fila($id);
        $this->assertSame(DosClinicas::NORTE, (int) $cita['id_clinica']);
        $this->assertSame(DosClinicas::VET_NORTE, (int) $cita['id_veterinario']);
        $this->assertSame(30, (int) $cita['duracion_minutos']);
        $this->assertSame(10, (int) $cita['margen_minutos']);
        $this->assertSame('09:00:00', $cita['hora']);
        $this->assertSame('09:30:00', $cita['hora_fin']);
        $this->assertSame('verde', $cita['prioridad']);
        $this->assertSame(0, (int) $cita['es_sobrecupo']);
        $this->assertSame('confirmada', $cita['estado']);
    }

    public function testSinTipoUsaTreintaMinutosYUnTipoDeOtraClinicaSeRechaza(): void
    {
        $id = $this->reservar(DosClinicas::VET_NORTE, '09:00', null);
        $this->assertSame(30, (int) $this->fila($id)['duracion_minutos']);
        $this->assertSame(0, (int) $this->fila($id)['margen_minutos']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tipo de cita');
        $this->reservar(DosClinicas::VET_NORTE, '11:00', 2);
    }

    // ── RN-G13, RE-T.15.2: aislamiento ──────────────────────────────────

    public function testUnaCitaDeANoSeVeNiSeModificaDesdeB(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $id = $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertAccesoDenegado(fn () => $this->citas->getById($id));
        $this->assertSame([], $this->citas->listarRango(self::FECHA, self::FECHA));
        $this->assertSame([], $this->citas->listarTodas());

        // Una fila «armada» con la clínica de B tampoco cambia la cita de A.
        $falsa = ['id_cita' => $id, 'id_clinica' => DosClinicas::SUR];
        $this->assertFalse($this->citas->cancelar($falsa));
        $this->assertSame('confirmada', $this->fila($id)['estado']);

        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND tabla_afectada = 'citas'")->fetchColumn());
    }

    public function testUnaMascotaNoVinculadaNoSeAgendaYUnaInactivaTampoco(): void
    {
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertAccesoDenegado(fn () => $this->reservar(DosClinicas::VET_SUR, '09:00', 2));

        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 1');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inactiva');
        $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);
    }

    public function testSoloSeAgendaConVeterinariosActivosDeLaClinica(): void
    {
        $this->assertSame([DosClinicas::VET_NORTE, DosClinicas::DOBLE], array_map('intval', array_column($this->citas->veterinariosActivos(), 'id_usuario')));
        $this->assertFalse($this->citas->esVeterinarioDeLaClinica(DosClinicas::VET_SUR));
        $this->assertFalse($this->citas->esVeterinarioDeLaClinica(DosClinicas::INACTIVO), 'Hugo tiene la cuenta inactiva');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('veterinario seleccionado');
        $this->reservar(DosClinicas::VET_SUR, '09:00', 1);
    }

    // ── RN-401, RE-4.1.2, RE-4.9.3: doble reserva ───────────────────────

    public function testDosReservasAlMismoVeterinarioSeRechazanConMensaje(): void
    {
        $this->crearMascota(2, DosClinicas::NORTE);
        $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);

        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_NORTE, '09:00', 1, 2), 'veterinario no está disponible');
        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_NORTE, '09:15', 1, 2), 'veterinario no está disponible');
        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::DOBLE, '09:15', 1), 'mascota ya tiene otra cita');
        $this->assertSame(1, $this->contarCitas());
    }

    /** Dos peticiones pasan la validación a la vez: el índice único decide y la segunda recibe un mensaje claro. */
    public function testLaCarreraLaDecideElIndiceUnicoYNoEsUn500(): void
    {
        $this->crearMascota(2, DosClinicas::NORTE);
        $sinValidacion = new CitaSinValidacionPrevia($this->db);
        $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);

        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_NORTE, '09:00', 1, 2, $sinValidacion), 'acaba de ocuparse');
        $this->assertSame(1, $this->contarCitas());
    }

    /** D-2 sin id_clinica: un veterinario que trabaja en dos clínicas no queda reservado dos veces. */
    public function testElMismoVeterinarioNoSeReservaDosVecesEntreClinicas(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->crearMascota(2, DosClinicas::SUR);
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (2, 2, 2, 'activo')");
        $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);

        $this->comoAdministrador(DosClinicas::SUR, DosClinicas::ADMIN_SUR);
        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_NORTE, '09:00', 2, 2), 'veterinario no está disponible');
        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_NORTE, '09:20', 2, 2), 'veterinario no está disponible');
        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_NORTE, '09:00', 2, 2, new CitaSinValidacionPrevia($this->db)), 'acaba de ocuparse');

        // La mascota tampoco: Luna ya tiene cita a las 9 en Norte.
        $this->assertHorarioOcupado(fn () => $this->reservar(DosClinicas::VET_SUR, '09:10', 2), 'mascota ya tiene otra cita');

        // Las sugerencias de Sur no ofrecen la hora ocupada en Norte (solo horas, sin datos de Norte).
        $sugerencias = $this->citas->sugerencias(DosClinicas::VET_NORTE, self::FECHA, 30, null, new DateTimeImmutable('2030-01-01 08:00'));
        $this->assertNotContains('09:00', $sugerencias);
        $this->assertContains('09:30', $sugerencias);

        $id = $this->reservar(DosClinicas::VET_NORTE, '10:00', 2, 2);
        $this->assertSame(DosClinicas::SUR, (int) $this->fila($id)['id_clinica']);
    }

    /** RE-4.7.2: una cita cancelada o no asistida libera su horario, también para el índice. */
    public function testUnaCitaCanceladaLiberaElHorario(): void
    {
        $id = $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);
        $this->assertTrue($this->citas->cancelar($this->citas->getById($id)));

        $nueva = $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);

        $this->assertSame('confirmada', $this->fila($nueva)['estado']);
        $this->assertSame('cancelada', $this->fila($id)['estado']);
    }

    // ── HU-4.2, RE-4.2.4: reprogramar ───────────────────────────────────

    public function testReprogramarRespetaElHorarioYConservaLaDuracion(): void
    {
        $primera = $this->reservar(DosClinicas::VET_NORTE, '09:00', 1);
        $segunda = $this->reservar(DosClinicas::DOBLE, '10:00', 1);

        $this->assertHorarioOcupado(fn () => $this->citas->reprogramar($this->citas->getById($primera), DosClinicas::DOBLE, self::FECHA, '10:15'), 'veterinario no está disponible');

        $this->citas->reprogramar($this->citas->getById($primera), DosClinicas::VET_NORTE, self::FECHA, '11:00');

        $cita = $this->fila($primera);
        $this->assertSame('11:00:00', $cita['hora']);
        $this->assertSame('11:30:00', $cita['hora_fin']);
        $this->assertSame(10, (int) $cita['margen_minutos']);
        $this->assertSame('10:00:00', $this->fila($segunda)['hora']);
    }

    /** A.6 riesgo 10: los tipos de cita salen del catálogo de la clínica, con su nombre para la pantalla de atención. */
    public function testLosTiposDeCitaSonLosDeLaClinicaActiva(): void
    {
        $this->assertSame(['Consulta Norte'], array_column($this->citas->getTiposCita(), 'nombre'));

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame(['Control Sur'], array_column($this->citas->getTiposCita(), 'nombre'));
        $this->assertFalse($this->citas->getTipoCitaById(1));
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function reservar(int $veterinario, string $hora, ?int $tipo, int $mascota = self::LUNA, ?Cita $modelo = null): int
    {
        $modelo ??= $this->citas;
        return $modelo->registrar([
            'id_mascota' => $mascota,
            'id_veterinario' => $veterinario,
            'fecha' => self::FECHA,
            'hora' => $hora,
            'motivo' => 'Control',
            'id_tipo_cita' => $tipo,
        ]);
    }

    private function crearMascota(int $id, int $clinica): void
    {
        $this->db->prepare("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (?, 6, ?, ?, 'Kira', 1, 1)")->execute([$id, $clinica, str_repeat((string) $id, 43)]);
        $this->db->prepare("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (?, ?, 'activo')")->execute([$id, $clinica]);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function comoAdministrador(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::ADMIN), 1);
    }

    private function assertHorarioOcupado(callable $accion, string $parteDelMensaje): void
    {
        try {
            $accion();
            $this->fail('Debió rechazar la reserva: ' . $parteDelMensaje);
        } catch (HorarioOcupado $e) {
            $this->assertStringContainsString($parteDelMensaje, $e->getMessage());
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

    private function fila(int $id): array
    {
        return $this->db->query("SELECT * FROM citas WHERE id_cita = $id")->fetch(PDO::FETCH_ASSOC);
    }

    private function contarCitas(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn();
    }
}
