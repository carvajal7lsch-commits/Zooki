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
}
