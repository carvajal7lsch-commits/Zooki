<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../models/Cita.php';

/** RN-408/426: esperar durante el hueco, sin confundir presencia con inicio clínico. */
final class InasistenciaAutomaticaTest extends TestCase
{
    private PDO $db;
    private string $ahora = '2030-01-07 07:00:00';

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Norte', Roles::VETERINARIO), 1);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function cita(string $hora, int $clinica = 1, int $vet = 2): int
    {
        $this->db->prepare("INSERT INTO citas(id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,estado,duracion_minutos,margen_minutos,motivo) VALUES (?,1,?,1,'2030-01-07',?,'confirmada',30,10,'Control de prueba')")->execute([$clinica, $vet, $hora]);
        return (int) $this->db->lastInsertId();
    }

    private function revisar(string $hora): int
    {
        $this->ahora = '2030-01-07 ' . $hora;
        return (new CascadaAgenda($this->db, fn () => IntervalosAgenda::fecha($this->ahora)))->marcarInasistencias('2030-01-07');
    }

    private function estado(int $id): string
    {
        return (string) $this->db->query('SELECT estado FROM citas WHERE id_cita=' . $id)->fetchColumn();
    }

    public function testEsperaEnElHuecoHastaLaSiguienteCitaYRecalculaUnaSolaVez(): void
    {
        $primera = $this->cita('09:00:00');
        $segunda = $this->cita('10:00:00');
        $this->assertSame(0, $this->revisar('09:30:00'));
        $this->assertSame('confirmada', $this->estado($primera));
        $this->assertSame(1, $this->revisar('10:00:00'));
        $this->assertSame('no_asistio', $this->estado($primera));
        $this->assertSame('confirmada', $this->estado($segunda));
        $this->assertSame(0, $this->revisar('10:00:00'));
        $this->assertNotNull($this->db->query('SELECT fecha_ultimo_calculo FROM citas WHERE id_cita=' . $segunda)->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Inasistencia automática%'")->fetchColumn());
    }

    public function testUltimaReservaEsperaHastaElCierreYRespetaToleranciaConfigurada(): void
    {
        $id = $this->cita('09:00:00');
        $this->assertSame(0, $this->revisar('17:59:59'));
        $this->assertSame(1, $this->revisar('18:00:00'));
        $this->assertSame('no_asistio', $this->estado($id));
        $this->db->exec('UPDATE clinicas SET tolerancia_llegada_min=60 WHERE id_clinica=1');
        $tardia = $this->cita('17:00:00');
        $this->assertSame(0, $this->revisar('18:00:00'));
        $this->assertSame(1, $this->revisar('18:00:01'));
        $this->assertSame('no_asistio', $this->estado($tardia));
    }

    public function testNoAfectaPresenciasIniciosNiOtraClinica(): void
    {
        $presente = $this->cita('08:00:00');
        $iniciada = $this->cita('09:00:00');
        $ajena = $this->cita('08:00:00', DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->db->prepare('UPDATE citas SET hora_llegada=? WHERE id_cita=?')->execute(['2030-01-07 08:05:00', $presente]);
        $this->db->prepare("UPDATE citas SET estado='en_curso',hora_inicio_real=?,duracion_activa_objetivo_segundos=1800 WHERE id_cita=?")->execute(['2030-01-07 09:00:00', $iniciada]);
        $this->assertSame(0, $this->revisar('23:00:00'));
        $this->assertSame('confirmada', $this->estado($presente));
        $this->assertSame('en_curso', $this->estado($iniciada));
        $this->assertSame('confirmada', $this->estado($ajena));
    }

    public function testLaCitaDeOtroVeterinarioNoAcortaLaEspera(): void
    {
        $id = $this->cita('09:00:00');
        $otro = (int) $this->db->query('SELECT id_usuario FROM usuario_clinica WHERE id_clinica=1 AND id_rol=2 AND id_usuario<>2 LIMIT 1')->fetchColumn();
        $this->assertGreaterThan(0, $otro);
        $this->cita('10:00:00', 1, $otro);
        $this->assertSame(0, $this->revisar('10:30:00'));
        $this->assertSame('confirmada', $this->estado($id));
    }

    public function testOtraCitaCanceladaNoAcortaElHuecoYAuditoriaFallidaRevierte(): void
    {
        $id = $this->cita('09:00:00');
        $cancelada = $this->cita('10:00:00');
        $this->db->exec("UPDATE citas SET estado='cancelada' WHERE id_cita=" . $cancelada);
        $this->assertSame(0, $this->revisar('10:30:00'));
        $this->db->exec("CREATE TRIGGER impedir_ausencia BEFORE INSERT ON auditoria_sistema WHEN NEW.descripcion LIKE 'Inasistencia automática%' BEGIN SELECT RAISE(ABORT,'Sin auditoría'); END");
        try {
            $this->revisar('18:00:00');
            $this->fail('La auditoría debe ser atómica con la inasistencia');
        } catch (RuntimeException $error) {
            $this->assertSame('confirmada', $this->estado($id));
        }
    }
}
