<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../scripts/dev/DatosPrueba.php';

/** E2: la revisión del script no necesita la base de trabajo del usuario. */
final class DatosPruebaClinicasTest extends TestCase
{
    private function base(): PDO
    {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE clinicas (id_clinica INTEGER PRIMARY KEY, nombre TEXT, nit TEXT)');
        return $db;
    }

    private function esperadas(): array
    {
        return ['norte' => ['nombre' => 'Clínica Norte (prueba)', 'nit' => '9001234568']];
    }

    public function testReconoceLaClinicaPropiaConGuionYConservaLasAjenas(): void
    {
        $db = $this->base();
        $db->exec("INSERT INTO clinicas VALUES (1, 'Clínica Norte (prueba)', '900123456-8'), (2, 'Clínica ajena', '9012345678')");
        $antes = $db->query('SELECT * FROM clinicas')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(['propias' => ['norte' => 1], 'ajenas' => ['9012345678']], DatosPrueba::revisarClinicas($db, $this->esperadas()));
        $this->assertSame($antes, $db->query('SELECT * FROM clinicas')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testNitDeOtraClinicaDetieneLaRevisionSinModificarDatos(): void
    {
        foreach (['9001234568', '900123456-8'] as $nit) {
            $db = $this->base();
            $db->prepare('INSERT INTO clinicas VALUES (1, ?, ?)')->execute(['Clínica del usuario', $nit]);
            $antes = $db->query('SELECT * FROM clinicas')->fetchAll(PDO::FETCH_ASSOC);
            try {
                DatosPrueba::revisarClinicas($db, $this->esperadas());
                $this->fail('El NIT ajeno no puede convertirse en una clínica de prueba.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('otra clínica local', $error->getMessage());
                $this->assertSame($antes, $db->query('SELECT * FROM clinicas')->fetchAll(PDO::FETCH_ASSOC));
            }
        }
    }

    public function testDosFilasDelMismoNitNoSeFusionanNiSeSobrescriben(): void
    {
        $db = $this->base();
        $db->exec("INSERT INTO clinicas VALUES (1, 'Clínica Norte (prueba)', '900123456-8'), (2, 'Clínica Norte (prueba)', '9001234568')");
        $this->expectException(InvalidArgumentException::class);
        DatosPrueba::revisarClinicas($db, $this->esperadas());
    }

    /** F0: las clínicas de prueba quedan en Profesional con una sola suscripción vigente. */
    public function testLasClinicasDePruebaQuedanEnProfesionalConSuscripcionActiva(): void
    {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec("CREATE TABLE planes (id_plan INTEGER PRIMARY KEY, nombre TEXT)");
        $db->exec("INSERT INTO planes VALUES (1, 'Gratuito'), (2, 'Profesional')");
        $db->exec('CREATE TABLE clinicas (id_clinica INTEGER PRIMARY KEY, nombre TEXT, nit TEXT, id_plan INTEGER)');
        $db->exec("INSERT INTO clinicas VALUES (1, 'Clínica Norte (prueba)', '9001234568', 1), (2, 'Clínica ajena', '9012345678', 1)");
        $db->exec("CREATE TABLE suscripciones (id_suscripcion INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER, id_plan INTEGER,
            estado TEXT, ciclo TEXT, al_dia INTEGER, fecha_inicio TEXT, fecha_fin TEXT)");
        $db->exec("INSERT INTO suscripciones (id_clinica, id_plan, estado, ciclo, al_dia, fecha_inicio) VALUES (1, 1, 'activa', 'mensual', 1, '2026-10-01')");

        $this->assertTrue(DatosPrueba::planProfesional($db, 1));
        $this->assertFalse(DatosPrueba::planProfesional($db, 1), 'Repetible: no crea otra suscripción.');

        $this->assertSame(2, (int) $db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 1')->fetchColumn());
        $vigentes = $db->query("SELECT id_plan, al_dia FROM suscripciones WHERE id_clinica = 1 AND estado = 'activa'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([['id_plan' => 2, 'al_dia' => 1]], array_map(fn ($f) => array_map('intval', $f), $vigentes));
        $this->assertSame('cancelada', $db->query('SELECT estado FROM suscripciones WHERE id_plan = 1')->fetchColumn());
        $this->assertSame(1, (int) $db->query('SELECT id_plan FROM clinicas WHERE id_clinica = 2')->fetchColumn(), 'Una clínica ajena no cambia.');
    }

    public function testElScriptDePruebaPasaSusClinicasAProfesional(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../../scripts/dev/datos_prueba.php');
        $this->assertStringContainsString('DatosPrueba::planProfesional($db, (int) $id);', $script);
    }
}
