<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../models/CargadorConocimiento.php';
require_once __DIR__ . '/../../models/SolicitudAgenda.php';
require_once __DIR__ . '/../../models/Panel.php';

/** RE-2.2.1/4: reserva y presencia atómicas, sin cola sin cita ni autoridad del navegador. */
final class LlegadaSinReservaTest extends TestCase
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
        $this->agenda = new SolicitudAgenda($this->db, static fn () => IntervalosAgenda::fecha('2030-01-07 08:00:00'));
        $this->entrada = ['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07',
            'sintomas' => [['codigo' => 'sintoma.control_preventivo', 'tiempo' => 'no_aplica']]];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function comando(): array
    {
        $evaluada = $this->agenda->evaluar($this->entrada);
        return $this->entrada + ['id_solicitud' => $evaluada['id_solicitud'], 'hora' => '09:00:30', 'motivo' => 'Llegó para control',
            'modo_llegada' => 'ahora', 'clave_operacion' => str_repeat('b', 64)];
    }

    public function testCreaLlegadaYOrigenAuditadosYElDobleEnvioNoDuplica(): void
    {
        $datos = $this->comando();
        $primera = $this->agenda->reservar($datos);
        $this->assertSame($primera, $this->agenda->reservar($datos));
        $citas = $this->db->query('SELECT origen,hora_llegada FROM citas')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([['origen' => 'llegada_sin_reserva', 'hora_llegada' => '2030-01-07 08:00:00']], $citas);
        $auditoria = $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE tabla_afectada='citas' AND accion='INSERT'")->fetchColumn();
        $this->assertSame('llegada_sin_reserva', json_decode($auditoria, true)['origen']);
        $this->assertSame(1, (new Panel($this->db))->contarEnEspera('2030-01-07', '2030-01-07 00:00:00', '2030-01-08 00:00:00'));
        $this->db->exec("UPDATE solicitudes_agenda SET estado='espera_presencial',fecha_llegada='2030-01-07 08:00:00',id_cita=NULL");
        $this->assertSame(1, (new Panel($this->db))->contarEnEspera('2030-01-07', '2030-01-07 00:00:00', '2030-01-08 00:00:00'), 'RE-6.2.7: solicitudes sin cita no inflan En espera');
    }

    public function testRechazaOtraFechaOtraClinicaOtroVeterinarioYRol(): void
    {
        $datos = $this->comando();
        foreach ([['fecha' => '2030-01-08'], ['id_clinica' => 2], ['id_veterinario' => 3], ['modo_llegada' => 'inventado']] as $alteracion) {
            try {
                $this->agenda->reservar(array_replace($datos, $alteracion));
                $this->fail('Debe rechazar el modo llegó ahora fuera de su contexto.');
            } catch (InvalidArgumentException | AccesoDenegado $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $this->expectException(AccesoDenegado::class);
        $this->agenda->reservar($datos);
    }

    public function testFallaDeAuditoriaRevierteCitaYLlegadaJuntas(): void
    {
        $datos = $this->comando();
        $this->db->exec("CREATE TRIGGER rechazar_cita BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='citas' BEGIN SELECT RAISE(ABORT,'Auditoría no disponible'); END");
        try {
            $this->agenda->reservar($datos);
            $this->fail('No puede guardar sin auditoría.');
        } catch (Throwable $error) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
            $this->assertSame('abierta', $this->db->query('SELECT estado FROM solicitudes_agenda')->fetchColumn());
        }
    }

    public function testRojoMantieneDerivacionSinCrearReserva(): void
    {
        $this->entrada['sintomas'] = [['codigo' => 'sintoma.dificultad_respiratoria', 'tiempo' => 'no_se']];
        $evaluada = $this->agenda->evaluar($this->entrada);
        $this->assertSame('rojo', $evaluada['nivel']);
        $this->assertFalse($evaluada['reservable']);
        try {
            $this->agenda->reservar($this->entrada + ['id_solicitud' => $evaluada['id_solicitud'], 'modo_llegada' => 'ahora', 'hora' => '09:00:00']);
        } catch (InvalidArgumentException $error) {
            $this->assertNotSame('', $error->getMessage());
        }
        $this->assertSame('derivada', $this->db->query('SELECT estado FROM solicitudes_agenda')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM citas')->fetchColumn());
    }
}
