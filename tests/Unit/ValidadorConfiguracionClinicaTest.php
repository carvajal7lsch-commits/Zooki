<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/ValidadorConfiguracionClinica.php';

final class ValidadorConfiguracionClinicaTest extends TestCase
{
    public function testDuracionYMargenConTopesDelDueno(): void
    {
        foreach ([['duracion_minutos', '1'], ['duracion_minutos', '720'], ['margen_minutos', '0'], ['margen_minutos', '120']] as [$campo, $valor]) {
            $this->assertNull(ValidadorConfiguracionClinica::campo($campo, $valor));
        }
        foreach ([['duracion_minutos', '0'], ['duracion_minutos', '721'], ['duracion_minutos', '150000'], ['margen_minutos', '121'], ['margen_minutos', '-1'], ['margen_minutos', '1.5']] as [$campo, $valor]) {
            $this->assertNotNull(ValidadorConfiguracionClinica::campo($campo, $valor));
        }
    }

    public function testCorreoDeContactoNoEsUnaIdentidadYSeValidaSuFormato(): void
    {
        $this->assertNull(ValidadorConfiguracionClinica::campo('email_contacto', 'contacto@mailinator.com'));
        $this->assertNotNull(ValidadorConfiguracionClinica::campo('email_contacto', 'sin-arroba'));
    }
}
