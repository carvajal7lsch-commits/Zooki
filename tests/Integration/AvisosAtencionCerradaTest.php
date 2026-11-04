<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/NotificacionInterna.php';

/** RN-409: el aviso operativo se retira con el cierre, sin borrar historia ni avisos ajenos. */
final class AvisosAtencionCerradaTest extends TestCase
{
    private const CIERRE = '2030-01-07 09:30:00';
    private PDO $db;
    private NotificacionInterna $avisos;
    private int $cita;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Norte', Roles::VETERINARIO), 1);
        $this->db->exec("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,hora_inicio_real,duracion_minutos,motivo,estado)
            VALUES (1,1,2,1,'2030-01-07','09:00:00','09:30:00','2030-01-07 09:00:00',30,'Control','en_curso')");
        $this->cita = (int) $this->db->lastInsertId();
        $this->avisos = new NotificacionInterna($this->db);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function crearAvisos(int $clinica = 1, ?int $cita = null, int $usuario = 2): void
    {
        foreach (['ATENCION_ABIERTA', 'ATENCION_SIN_CERRAR'] as $tipo) {
            $this->avisos->crearParaUsuario($clinica, $usuario, $tipo, $tipo, 'Aviso de prueba', 'index.php?action=vet_atencion', $cita ?? $this->cita);
        }
    }

    private function guardarConsulta(): int
    {
        $consulta = new Consulta($this->db, static fn () => IntervalosAgenda::fecha(self::CIERRE));
        return $consulta->registrar(['id_cita' => $this->cita, 'id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], static fn () => []);
    }

    public function testGuardarConsultaExpiraAmbosAvisosSinTocarOtrasCitasClinicasOTipos(): void
    {
        $this->crearAvisos();
        $this->crearAvisos(2, $this->cita, 4);
        $this->crearAvisos(1, $this->cita + 1);
        $this->avisos->crearParaUsuario(1, 2, 'OTRO_AVISO', 'Conservar', 'Otro motivo', null, $this->cita);
        $this->assertSame(5, $this->avisos->contarNoLeidas(1, 2, Roles::VETERINARIO));
        $this->assertGreaterThan(0, $this->guardarConsulta());
        $filas = $this->db->query('SELECT id_clinica,id_cita,tipo,vigente_hasta,leida FROM notificaciones_internas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(7, $filas, 'Conserva todas las filas');
        foreach ($filas as $indice => $fila) {
            $this->assertSame($indice < 2 ? self::CIERRE : null, $fila['vigente_hasta']);
            $this->assertSame(0, (int) $fila['leida'], 'Retirar no simula una lectura');
        }
        $this->assertSame(3, $this->avisos->contarNoLeidas(1, 2, Roles::VETERINARIO));
        $this->assertCount(3, $this->avisos->obtenerParaUsuario(1, 2, Roles::VETERINARIO));
        $this->assertSame(2, $this->avisos->contarNoLeidas(2, 4, Roles::VETERINARIO));
        $this->assertSame('completada', $this->db->query('SELECT estado FROM citas')->fetchColumn());
    }

    public function testElCierreDesdeCitaTambienRetiraLosAvisos(): void
    {
        $this->crearAvisos();
        $this->db->exec("UPDATE citas SET estado='sin_cerrar'");
        $citas = new Cita($this->db);
        $this->assertTrue($citas->completarAtencion($citas->getById($this->cita), self::CIERRE));
        $this->assertSame([self::CIERRE, self::CIERRE], $this->db->query('SELECT vigente_hasta FROM notificaciones_internas')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame([], $this->avisos->obtenerParaUsuario(1, 2, Roles::VETERINARIO));
        $this->assertSame(0, $this->avisos->contarNoLeidas(1, 2, Roles::VETERINARIO));
    }

    public function testAvisosAnterioresYPublicadosTardeNoReaparecenTrasCompletar(): void
    {
        $this->crearAvisos();
        foreach (['en_curso', 'sin_cerrar', 'pausada'] as $estado) {
            $this->db->prepare('UPDATE citas SET estado=?')->execute([$estado]);
            $this->assertCount(2, $this->avisos->obtenerParaUsuario(1, 2, Roles::VETERINARIO));
        }
        $this->db->exec("UPDATE citas SET estado='completada'");
        $this->crearAvisos();
        $this->avisos->crearParaUsuario(1, 2, 'OTRO_AVISO', 'Conservar', 'Otro motivo', null, $this->cita);
        $this->assertSame(['OTRO_AVISO'], array_column($this->avisos->obtenerParaUsuario(1, 2, Roles::VETERINARIO), 'tipo'));
        $this->assertSame(1, $this->avisos->contarNoLeidas(1, 2, Roles::VETERINARIO));
        $this->assertSame(4, (int) $this->db->query('SELECT COUNT(*) FROM notificaciones_internas WHERE vigente_hasta IS NULL AND tipo<>\'OTRO_AVISO\'')->fetchColumn());
    }

    public function testFalloDeCierreRevierteTambienLaExpiracionDeAvisos(): void
    {
        $this->crearAvisos();
        $this->db->exec("CREATE TRIGGER falla_cierre BEFORE INSERT ON auditoria_sistema WHEN NEW.tabla_afectada='citas' AND NEW.accion='UPDATE' BEGIN SELECT RAISE(ABORT,'Fallo de auditoría'); END");
        $fallo = null;
        try {
            $this->guardarConsulta();
        } catch (Throwable $error) {
            $fallo = $error;
        }
        $this->assertInstanceOf(RuntimeException::class, $fallo);
        $this->assertSame('en_curso', $this->db->query('SELECT estado FROM citas')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
        $this->assertSame([null, null], $this->db->query('SELECT vigente_hasta FROM notificaciones_internas')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(2, $this->avisos->contarNoLeidas(1, 2, Roles::VETERINARIO));
    }
}
