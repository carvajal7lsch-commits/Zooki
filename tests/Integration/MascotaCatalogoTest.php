<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Mascota.php';

/** A.3 / HU-7.2: taxonomía global de lectura, sin los cuatro casos de crear razas. */
final class MascotaCatalogoTest extends TestCase
{
    private PDO $db;
    private Mascota $modelo;
    protected function setUp(): void
    {
        $this->db=DosClinicas::sqlite(); DosClinicas::crearMascotasSqlite($this->db);
        $_SESSION=['id_usuario'=>2]; Contexto::activar(Contexto::deClinica(1,'Norte',Roles::VETERINARIO),1);
        $this->modelo=new Mascota($this->db);
    }
    protected function tearDown(): void { $_SESSION=[]; }
    private function datos(array $cambios=[]): array
    {
        return array_replace(['id_propietario'=>6,'nombre'=>'Toby','id_especie'=>1,'id_raza'=>49,
            'fecha_nacimiento'=>'2022-01-01','peso'=>'5','sexo'=>'Macho','colores'=>[1]],$cambios);
    }
    public function testLaTaxonomiaGlobalSeLeeIgualEnAmbasClinicas(): void
    {
        $antes=$this->modelo->getRazasByEspecie(1);
        Contexto::activar(Contexto::deClinica(2,'Sur',Roles::VETERINARIO),1);
        $this->assertSame($antes,$this->modelo->getRazasByEspecie(1));
        $this->assertCount(2,$this->modelo->getEspecies()); $this->assertCount(2,$this->modelo->getColoresBase());
        $this->assertTrue($this->modelo->razaPerteneceAEspecie(49,1));
        $this->assertFalse($this->modelo->razaPerteneceAEspecie(49,2));
    }
    public function testRazaIndicadaNoCreaUnaRazaGlobal(): void
    {
        $id=$this->modelo->insert($this->datos(['id_raza'=>null,'raza_indicada'=>'Raza por confirmar']));
        $m=$this->modelo->getById($id); $this->assertNull($m['id_raza']);
        $this->assertSame('Raza por confirmar',$m['raza_indicada']);
        $this->assertSame(2,(int)$this->db->query('SELECT COUNT(*) FROM razas')->fetchColumn());
        foreach (['obtenerOCrearRaza','insertRaza','insertEspecie','insertColor'] as $metodo) $this->assertFalse(method_exists($this->modelo,$metodo));
    }
    public function testRazaDeOtraEspecieYColoresInventadosSeRechazanSinAltaParcial(): void
    {
        foreach ([['id_raza'=>50],['colores'=>[999]],['raza_indicada'=>str_repeat('A',51),'id_raza'=>null]] as $datos) {
            try { $this->modelo->insert($this->datos($datos)); $this->fail('Debió rechazar'); }
            catch (InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
    }
    public function testCamposObligatoriosYLargoSeValidanEnServidor(): void
    {
        foreach (['nombre'=>'','fecha_nacimiento'=>'','peso'=>'-1','sexo'=>'X','colores'=>[],'nombre_largo'=>str_repeat('A',51)] as $campo=>$valor) {
            try { $this->modelo->insert($this->datos([$campo === 'nombre_largo' ? 'nombre' : $campo=>$valor])); $this->fail('Debió rechazar'); }
            catch (InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
    }
}
