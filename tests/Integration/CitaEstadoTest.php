<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Cita.php';

/**
 * Estados de una cita (RN-406, RN-408, RN-409, RN-410, RN-411).
 *
 * Cada transición solo ocurre desde los estados que la permiten: iniciar
 * exige una cita abierta, completar exige una atención iniciada, "no asistió"
 * solo cierra citas que no se empezaron a atender y "cerrada sin consulta"
 * solo cierra atenciones iniciadas. Así una cita no queda en un estado del que
 * no se pueda salir, ni un cambio pisa a otro.
 */
class CitaEstadoTest extends TestCase
{
    private const AHORA = '2026-09-10 10:17:00';

    private PDO $db;
    private Cita $modelo;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("CREATE TABLE citas (
            id_cita INTEGER PRIMARY KEY,
            doc_veterinario TEXT DEFAULT 'V1',
            fecha TEXT DEFAULT '2026-09-10',
            hora TEXT DEFAULT '10:15:00',
            hora_fin TEXT DEFAULT '10:45:00',
            duracion_minutos INTEGER DEFAULT 30,
            estado TEXT DEFAULT 'pendiente',
            hora_inicio_real TEXT,
            hora_fin_real TEXT,
            aviso_atencion_abierta TEXT,
            motivo_cierre TEXT
        )");
        $this->db->exec("INSERT INTO citas (id_cita) VALUES (1)");

        $this->modelo = new Cita($this->db);
    }

    private function campo(string $columna): ?string
    {
        $valor = $this->db->query("SELECT $columna FROM citas WHERE id_cita = 1")->fetchColumn();
        return $valor === null ? null : (string) $valor;
    }

    private function ponerEstado(string $estado): void
    {
        $this->db->exec("UPDATE citas SET estado = '$estado' WHERE id_cita = 1");
    }

    public function testIniciarAtencionGuardaEnCursoYSellaLaHoraReal(): void
    {
        $this->assertTrue($this->modelo->iniciarAtencion(1, self::AHORA));
        $this->assertSame('en_curso', $this->campo('estado'));
        $this->assertSame(self::AHORA, $this->campo('hora_inicio_real'));
    }

    public function testUnEstadoFueraDelEnumSeRechazaSinTocarLaCita(): void
    {
        $this->assertFalse($this->modelo->cambiarEstado(1, 'encurso'));
        $this->assertSame('pendiente', $this->campo('estado'));
    }

    public function testElFlujoCompletoDeAtencion(): void
    {
        $this->assertTrue($this->modelo->iniciarAtencion(1, self::AHORA));
        $this->assertTrue($this->modelo->completarAtencion(1, '2026-09-10 10:52:00'));

        $this->assertSame('completada', $this->campo('estado'));
        $this->assertSame('2026-09-10 10:52:00', $this->campo('hora_fin_real'));
    }

    /** RN-406: no se completa una cita que nadie empezó a atender. */
    public function testNoSeCompletaUnaCitaQueNoEstaEnCurso(): void
    {
        $this->assertFalse($this->modelo->completarAtencion(1, self::AHORA));
        $this->assertSame('pendiente', $this->campo('estado'));
        $this->assertNull($this->campo('hora_fin_real'));
    }

    public function testUnaCitaCerradaNoSePuedeIniciar(): void
    {
        foreach (['cancelada', 'completada', 'no_asistio', 'sin_cerrar', 'cerrada_sin_consulta'] as $estado) {
            $this->ponerEstado($estado);
            $this->assertFalse($this->modelo->iniciarAtencion(1, self::AHORA), "No debería iniciarse una cita $estado");
            $this->assertSame($estado, $this->campo('estado'));
        }
    }

    /** Iniciar dos veces (dos pestañas) no cambia la hora real de inicio. */
    public function testIniciarDosVecesConservaElSelloOriginal(): void
    {
        $this->assertTrue($this->modelo->iniciarAtencion(1, self::AHORA));
        $this->assertFalse($this->modelo->iniciarAtencion(1, '2026-09-10 11:40:00'));
        $this->assertSame(self::AHORA, $this->campo('hora_inicio_real'));
    }

    /** RN-409: "no asistió" solo cierra citas que no se empezaron a atender. */
    public function testNoAsistioSoloDesdeUnaCitaAbierta(): void
    {
        $this->assertTrue($this->modelo->marcarNoAsistio(1));
        $this->assertSame('no_asistio', $this->campo('estado'));

        $this->ponerEstado('en_curso');
        $this->assertFalse($this->modelo->marcarNoAsistio(1));
        $this->assertSame('en_curso', $this->campo('estado'));
    }

    /** RN-409 / RE-29.2: una cita no asistida deja libre su espacio. */
    public function testUnaCitaNoAsistidaLiberaElEspacio(): void
    {
        $this->assertFalse($this->modelo->checkDisponibilidad('V1', '2026-09-10', '10:15', 30));

        $this->modelo->marcarNoAsistio(1);

        $this->assertTrue($this->modelo->checkDisponibilidad('V1', '2026-09-10', '10:15', 30));
    }

    public function testUnaCitaCanceladaTambienLiberaElEspacio(): void
    {
        $this->ponerEstado('cancelada');
        $this->assertTrue($this->modelo->checkDisponibilidad('V1', '2026-09-10', '10:15', 30));
    }

    /** RN-410: solo una atención en curso pasa a "sin cerrar". */
    public function testSoloUnaAtencionEnCursoQuedaSinCerrar(): void
    {
        $this->assertFalse($this->modelo->marcarSinCerrar(1));
        $this->assertSame('pendiente', $this->campo('estado'));

        $this->ponerEstado('en_curso');
        $this->assertTrue($this->modelo->marcarSinCerrar(1));
        $this->assertSame('sin_cerrar', $this->campo('estado'));
    }

    /** RN-406 / RN-410: una atención que quedó sin cerrar todavía se completa con su consulta. */
    public function testUnaAtencionSinCerrarTodaviaSeCompleta(): void
    {
        $this->ponerEstado('sin_cerrar');
        $this->assertTrue($this->modelo->completarAtencion(1, '2026-09-11 08:00:00'));
        $this->assertSame('completada', $this->campo('estado'));
    }

    /** RN-411: se cierra sin consulta una atención iniciada, y queda su motivo. */
    public function testCerrarSinConsultaGuardaElMotivo(): void
    {
        foreach (['en_curso', 'sin_cerrar'] as $estado) {
            $this->ponerEstado($estado);
            $this->assertTrue($this->modelo->cerrarSinConsulta(1, 'Se inició por error', self::AHORA), "Debería cerrarse desde $estado");
            $this->assertSame('cerrada_sin_consulta', $this->campo('estado'));
            $this->assertSame('Se inició por error', $this->campo('motivo_cierre'));
        }
    }

    public function testNoSeCierraSinConsultaUnaCitaQueNoSeEmpezoAAtender(): void
    {
        foreach (['pendiente', 'confirmada', 'completada', 'cancelada', 'no_asistio'] as $estado) {
            $this->ponerEstado($estado);
            $this->assertFalse($this->modelo->cerrarSinConsulta(1, 'Motivo cualquiera', self::AHORA), "No debería cerrarse desde $estado");
            $this->assertSame($estado, $this->campo('estado'));
        }
    }

    /** RN-411: una atención cerrada sin consulta libera su horario. */
    public function testUnaAtencionCerradaSinConsultaLiberaElEspacio(): void
    {
        $this->ponerEstado('cerrada_sin_consulta');
        $this->assertTrue($this->modelo->checkDisponibilidad('V1', '2026-09-10', '10:15', 30));
    }

    /** RN-410: el aviso de atención abierta se sella una sola vez. */
    public function testElAvisoDeAtencionAbiertaSeSellaUnaVez(): void
    {
        $this->ponerEstado('en_curso');
        $this->assertTrue($this->modelo->sellarAvisoAtencionAbierta(1, self::AHORA));
        $this->assertFalse($this->modelo->sellarAvisoAtencionAbierta(1, '2026-09-10 11:00:00'));
        $this->assertSame(self::AHORA, $this->campo('aviso_atencion_abierta'));
    }
}
