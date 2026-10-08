<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/RecordatoriosDosClinicas.php';
require_once __DIR__ . '/../../helpers/EnviadorRecordatorios.php';

/** HU-3.2/3.6: aislamiento, ventana y reintentos del proceso del sistema. */
class RecordatorioTest extends TestCase
{
    private const HOY = '2026-09-22';
    private PDO $db;
    private Recordatorio $recordatorio;
    private CorreoRecordatorioSimulado $correo;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        RecordatoriosDosClinicas::poblar($this->db);
        $this->recordatorio = new Recordatorio($this->db);
        $this->correo = new CorreoRecordatorioSimulado();
    }

    private function ejecutar(): int
    {
        ob_start();
        try {
            return (new EnviadorRecordatorios($this->recordatorio, $this->correo))->ejecutar(
                new DateTimeImmutable('2026-09-23 01:00:00 UTC'), 'https://zooki.test/index.php'
            );
        } finally {
            ob_end_clean();
        }
    }

    public function testSeleccionaVentanaConClinicaOriginalYPropietarioGlobal(): void
    {
        $dosis = $this->recordatorio->dosisPorRecordar(self::HOY);
        $this->assertSame([1,2,3,1,2], array_map('intval', array_column($dosis, 'id_entidad')));
        $this->assertSame([1,1,2,1,2], array_map('intval', array_column($dosis, 'id_clinica')));
        $this->assertSame(array_fill(0,5,6), array_map('intval', array_column($dosis, 'id_usuario')));
        $this->assertSame([
            VentanaRecordatorio::PRIMER_AVISO, VentanaRecordatorio::ULTIMO_AVISO,
            VentanaRecordatorio::PRIMER_AVISO, VentanaRecordatorio::ULTIMO_AVISO,
            VentanaRecordatorio::PRIMER_AVISO,
        ], array_column($dosis, 'tipo_notificacion'));
        $this->assertSame('Desparasitación interna (Drontal)', $dosis[3]['nombre_item']);
    }

    public function testCorreosYBitacoraConservanClinicaYNoSeRepiten(): void
    {
        $this->assertSame(7, $this->ejecutar());
        $filas = $this->db->query('SELECT * FROM notificaciones ORDER BY id_notificacion')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(7, $filas);
        foreach ($filas as $indice => $fila) {
            $nombre = (int) $fila['id_clinica'] === 1 ? 'Clínica Norte' : 'Clínica Sur';
            $this->assertSame(6, (int) $fila['id_usuario']);
            $this->assertSame('enviado', $fila['estado']);
            $this->assertStringContainsString($nombre, $this->correo->envios[$indice]['asunto']);
            $this->assertStringContainsString($nombre, $fila['mensaje']);
            $this->assertStringContainsString('Luna', $fila['mensaje']);
        }
        $this->assertSame(0, $this->ejecutar());
        $this->assertCount(7, $this->correo->envios);
    }

    public function testClinicaDesvinculadaNoEnviaAunqueOtraSigaActiva(): void
    {
        $this->db->exec("UPDATE mascota_clinica SET estado='inactivo' WHERE id_clinica=2");
        $this->assertSame(4, $this->ejecutar());
        $this->assertSame([1], array_map('intval', $this->db->query('SELECT DISTINCT id_clinica FROM notificaciones')->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testMascotaInactivaNoGeneraDosisNiCitas(): void
    {
        $this->db->exec('UPDATE mascotas SET estado=0');
        $this->assertSame(0, $this->ejecutar());
    }

    public function testCuentaInactivaNoGeneraDosisNiCitas(): void
    {
        $this->db->exec('UPDATE usuarios SET estado=0 WHERE id_usuario=6');
        $this->assertSame(0, $this->ejecutar());
    }

    public function testSinCorreoNoGeneraDosisNiCitas(): void
    {
        $this->db->exec("UPDATE usuarios SET email='' WHERE id_usuario=6");
        $this->assertSame(0, $this->ejecutar());
    }

    public function testRenovacionEnOtraClinicaSuprimeLaDosisAntigua(): void
    {
        $this->db->exec("INSERT INTO vacunas (id_clinica,id_mascota,nombre_vacuna,fecha_aplicacion,fecha_proxima_dosis)
            VALUES (2,1,'Rabia','2026-09-20','2027-09-20')");
        $this->db->exec("INSERT INTO desparasitaciones (id_clinica,id_mascota,tipo,producto,periodicidad,fecha_aplicacion,fecha_proxima)
            VALUES (2,1,'interna','Nuevo','trimestral','2026-09-20','2026-12-20')");
        $this->assertSame(5, $this->ejecutar());
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones WHERE id_entidad=1 AND tipo_entidad IN ('vacuna','desparasitacion')")->fetchColumn());
    }

    public function testErrorSeReintentaTresVecesYLuegoSeDetiene(): void
    {
        $this->correo->resultado = false;
        for ($intento = 0; $intento < VentanaRecordatorio::MAX_INTENTOS; $intento++) {
            $this->assertSame(7, $this->ejecutar());
        }
        $this->assertSame(0, $this->ejecutar());
        $this->assertSame(21, (int) $this->db->query("SELECT COUNT(*) FROM notificaciones WHERE estado='error'")->fetchColumn());
    }

    public function testExcepcionDeTransporteQuedaRegistradaParaReintento(): void
    {
        $this->correo->lanzarError = true;
        $this->assertSame(7, $this->ejecutar());
        $this->correo->lanzarError = false;
        $this->assertSame(7, $this->ejecutar());
        $this->assertSame(0, $this->ejecutar());
    }

    public function testErrorDeOtraClinicaNoConsumeIntentosDeLaOriginal(): void
    {
        for ($intento = 0; $intento < 3; $intento++) {
            $this->recordatorio->registrar([
                'id_clinica'=>2, 'id_usuario'=>6, 'tipo_entidad'=>'vacuna', 'id_entidad'=>1,
                'email'=>'fabio@zooki.test', 'tipo_notificacion'=>VentanaRecordatorio::PRIMER_AVISO,
                'asunto'=>'Error ajeno', 'mensaje'=>'Simulado', 'enviado'=>false,
            ]);
        }
        $this->assertTrue($this->recordatorio->puedeEnviar(1, 'vacuna', 1, VentanaRecordatorio::PRIMER_AVISO));
        $this->assertFalse($this->recordatorio->puedeEnviar(2, 'vacuna', 1, VentanaRecordatorio::PRIMER_AVISO));
    }

    public function testElCronRealUsaConexionYCorreoSimulados(): void
    {
        $db = $this->db;
        $emailService = $this->correo;
        $ahora = new DateTimeImmutable('2026-09-23 01:00:00 UTC');
        $appUrl = 'https://zooki.test/index.php';
        ob_start();
        try {
            include __DIR__ . '/../../scripts/send_reminders.php';
            $this->assertSame(7, $intentos);
            include __DIR__ . '/../../scripts/send_reminders.php';
            $this->assertSame(0, $intentos);
        } finally {
            ob_end_clean();
        }
        $this->assertCount(7, $emailService->envios);
    }
}
