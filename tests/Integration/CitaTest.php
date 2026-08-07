<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Cita.php';

class CitaTest extends TestCase
{
    private $db;
    private $citaModel;

    protected function setUp(): void
    {
        // Setup SQLite in-memory database
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create schema for citas
        $this->db->exec("
            CREATE TABLE citas (
                id_cita INTEGER PRIMARY KEY AUTOINCREMENT,
                id_mascota INTEGER,
                doc_veterinario TEXT,
                fecha TEXT,
                hora TEXT,
                hora_fin TEXT,
                duracion_minutos INTEGER,
                estado TEXT
            )
        ");

        $this->citaModel = new Cita($this->db);
    }

    public function testCheckMascotaDisponibleReturnsTrueWhenNoCitas()
    {
        $this->assertTrue(
            $this->citaModel->checkMascotaDisponible(1, '2026-08-04', '09:00:00', 30)
        );
    }

    public function testCheckMascotaDisponibleReturnsFalseWhenOverlapExists()
    {
        // Insert an existing appointment from 09:00 to 09:30
        $stmt = $this->db->prepare("
            INSERT INTO citas (id_mascota, doc_veterinario, fecha, hora, hora_fin, duracion_minutos, estado)
            VALUES (1, 'VET001', '2026-08-04', '09:00:00', '09:30:00', 30, 'programada')
        ");
        $stmt->execute();

        // 1. Exact overlap: 09:00 to 09:30
        $this->assertFalse(
            $this->citaModel->checkMascotaDisponible(1, '2026-08-04', '09:00:00', 30)
        );

        // 2. Partial overlap: 09:15 to 09:45
        $this->assertFalse(
            $this->citaModel->checkMascotaDisponible(1, '2026-08-04', '09:15:00', 30)
        );

        // 3. Adjacent but no overlap: 09:30 to 10:00
        $this->assertTrue(
            $this->citaModel->checkMascotaDisponible(1, '2026-08-04', '09:30:00', 30)
        );

        // 4. No overlap (earlier): 08:30 to 09:00
        $this->assertTrue(
            $this->citaModel->checkMascotaDisponible(1, '2026-08-04', '08:30:00', 30)
        );
    }

    public function testCheckVeterinarioDisponibleReturnsFalseWhenOverlapExists()
    {
        // Insert an existing appointment for vet 'VET005' from 10:00 to 10:45
        $stmt = $this->db->prepare("
            INSERT INTO citas (id_mascota, doc_veterinario, fecha, hora, hora_fin, duracion_minutos, estado)
            VALUES (3, 'VET005', '2026-08-04', '10:00:00', '10:45:00', 45, 'programada')
        ");
        $stmt->execute();

        // Overlapping appointment: 10:30 to 11:00
        $this->assertFalse(
            $this->citaModel->checkDisponibilidad('VET005', '2026-08-04', '10:30:00', 30)
        );

        // Available appointment: 10:45 to 11:15
        $this->assertTrue(
            $this->citaModel->checkDisponibilidad('VET005', '2026-08-04', '10:45:00', 30)
        );
    }

}
