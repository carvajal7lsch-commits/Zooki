<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ValidadorMascota.php';
require_once __DIR__ . '/../../helpers/FotoMascota.php';

/**
 * Datos de mascota que manda el portal del propietario (RE-15.9).
 */
class ValidadorMascotaTest extends TestCase
{
    private DateTimeImmutable $hoy;

    protected function setUp(): void
    {
        $this->hoy = new DateTimeImmutable('2026-09-21', new DateTimeZone('America/Bogota'));
    }

    private function entrada(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Toby', 'especie' => '1', 'raza' => '3', 'sexo' => 'Macho',
            'peso' => '8.5', 'fecha_nacimiento' => '2021-03-10',
        ];
    }

    private function error(array $cambios): ?string
    {
        return ValidadorMascota::validar($this->entrada($cambios), $this->hoy)['error'];
    }

    public function testDatosCorrectosSeLimpian(): void
    {
        $r = ValidadorMascota::validar($this->entrada(['nombre' => "  Don   Toby  ", 'peso' => '8,5']), $this->hoy);

        $this->assertNull($r['error']);
        $this->assertSame('Don Toby', $r['datos']['nombre']);
        $this->assertSame('8.5', $r['datos']['peso']);
        $this->assertSame(1, $r['datos']['especie']);
    }

    public function testNombreObligatorioLargoYCaracteres(): void
    {
        $this->assertNotNull($this->error(['nombre' => '   ']));
        $this->assertNotNull($this->error(['nombre' => str_repeat('a', 51)]));
        $this->assertNotNull($this->error(['nombre' => '<script>']));
        $this->assertNotNull($this->error(['nombre' => '../../evil']));
        $this->assertNull($this->error(['nombre' => "Ñoño D'Artagnan-2"]));
    }

    public function testEspecieYRazaDebenSerIds(): void
    {
        $this->assertNotNull($this->error(['especie' => '']));
        $this->assertNotNull($this->error(['raza' => 'Otra']));
        $this->assertNotNull($this->error(['raza' => '0']));
        $this->assertNotNull($this->error(['especie' => '1 OR 1=1']));
    }

    /** «Mi raza no está en la lista»: no crea la raza, deja lo que escribió para la clínica. */
    public function testRazaQueNoEstaEnLaLista(): void
    {
        $r = ValidadorMascota::validar($this->entrada(['raza' => 'otra', 'raza_indicada' => '  Shih   Tzu (mini) ']), $this->hoy);
        $this->assertNull($r['error']);
        $this->assertNull($r['datos']['raza']);
        $this->assertSame('Shih Tzu (mini)', $r['datos']['raza_indicada']);

        $this->assertNotNull($this->error(['raza' => 'otra', 'raza_indicada' => '']));
        $this->assertNotNull($this->error(['raza' => 'otra', 'raza_indicada' => '<b>x</b>']));
        $this->assertNotNull($this->error(['raza' => 'otra', 'raza_indicada' => str_repeat('a', 51)]));
        // Con una raza de la lista, lo escrito se ignora.
        $this->assertNull(ValidadorMascota::validar($this->entrada(['raza_indicada' => 'Pomerania']), $this->hoy)['datos']['raza_indicada']);
    }

    public function testSexoSoloMachoOHembra(): void
    {
        $this->assertNotNull($this->error(['sexo' => 'macho']));
        $this->assertNotNull($this->error(['sexo' => '']));
        $this->assertNull($this->error(['sexo' => 'Hembra']));
    }

    public function testPesoPositivoConDosDecimalesComoMaximo(): void
    {
        foreach (['abc', '-5', '0', '151', '8.555', ''] as $peso) {
            $this->assertNotNull($this->error(['peso' => $peso]), "peso «{$peso}» debería rechazarse");
        }
        $this->assertNull($this->error(['peso' => '150']));
        $this->assertNull($this->error(['peso' => '0.35']));
    }

    public function testFechaDeNacimientoOpcionalYRazonable(): void
    {
        $this->assertNull(ValidadorMascota::validar($this->entrada(['fecha_nacimiento' => '']), $this->hoy)['datos']['fecha_nacimiento']);
        $this->assertNotNull($this->error(['fecha_nacimiento' => '2026-02-30']));
        $this->assertNotNull($this->error(['fecha_nacimiento' => '21/03/2021']));
        $this->assertNotNull($this->error(['fecha_nacimiento' => '2026-09-22']));
        $this->assertNotNull($this->error(['fecha_nacimiento' => '1980-01-01']));
        $this->assertNull($this->error(['fecha_nacimiento' => '2026-09-21']));
    }

    /** M1-02: el nombre de la mascota no puede sacar el archivo de la carpeta. */
    public function testLaEtiquetaDelArchivoNoTraeRutas(): void
    {
        $this->assertSame('evil', FotoMascota::etiqueta('../../evil'));
        $this->assertSame('Don_Toby', FotoMascota::etiqueta('Don Toby'));
        $this->assertSame('mascota', FotoMascota::etiqueta('ñ/\\.'));
    }
}
