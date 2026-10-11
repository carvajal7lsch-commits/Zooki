<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/AvisoFichaMascota.php';
require_once __DIR__ . '/../../controllers/MascotaController.php';

final class MascotaAccesoTest extends TestCase
{
    private PDO $db;
    private Mascota $modelo;
    protected function setUp(): void
    {
        $this->db=DosClinicas::sqlite(); DosClinicas::crearMascotasSqlite($this->db);
        $this->modelo=new Mascota($this->db); $this->comoVet(1);
        $_POST=[]; $_GET=[]; $_FILES=[]; $_SERVER['REQUEST_METHOD']='POST';
    }
    protected function tearDown(): void { $_SESSION=[]; $_POST=[]; $_GET=[]; $_FILES=[]; }
    private function comoVet(int $clinica): void
    {
        $_SESSION=['id_usuario'=>$clinica === 1 ? 2 : 4];
        Contexto::activar(Contexto::deClinica($clinica,'Clínica',Roles::VETERINARIO),1);
    }
    private function rechazo(callable $accion): void
    {
        try { $accion(); $this->fail('Debió rechazar el acceso.'); }
        catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
    }
    public function testLaMascotaDeANoSeVeNiSeEditaDesdeB(): void
    {
        $this->assertCount(1,$this->modelo->getAll()); $this->comoVet(2);
        $this->assertSame([],$this->modelo->getAll()); $this->assertSame([],$this->modelo->search('Luna'));
        $this->assertSame([],$this->modelo->getByPropietario(6));
        foreach ([fn()=>$this->modelo->getById(1),fn()=>$this->modelo->update(['id_mascota'=>1,'peso'=>10]),
            fn()=>$this->modelo->updateStatus(1,0),fn()=>$this->modelo->getPropietarioSiActiva(1)] as $accion) $this->rechazo($accion);
        $this->assertSame(4,(int)$this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica=2 AND id_usuario=4 AND accion='OTHER'")->fetchColumn());
        $this->assertSame('8.5',(string)$this->db->query('SELECT peso FROM mascotas WHERE id_mascota=1')->fetchColumn());
    }
    public function testConfirmarPropietarioYVincularMascotaNoDuplicaNiAsignaHC(): void
    {
        $this->comoVet(2); $propietario=new PropietarioClinica($this->db);
        $this->rechazo(fn()=>$this->modelo->vincular(1,6));
        $solicitud=$propietario->solicitarVinculo('fabio@zooki.test');
        $this->assertFalse($this->modelo->esPropietarioValido(6));
        $this->assertTrue($propietario->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
        $this->modelo->vincular(1,6); $this->modelo->vincular(1,6);
        $m=$this->modelo->getById(1);
        $this->assertSame(str_repeat('a',43),$m['token_carnet']); $this->assertNull($m['numero_historia_clinica']);
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
        $this->assertSame(2,(int)$this->db->query('SELECT COUNT(*) FROM mascota_clinica')->fetchColumn());
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM auditoria_mascotas WHERE campo_modificado='vinculo_clinica' AND id_clinica=2 AND id_usuario=4")->fetchColumn());
    }
    public function testBSoloEditaDatosNoProtegidosYSeAuditaYNotifica(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica) VALUES (6,2)");
        $this->comoVet(2); $this->modelo->vincular(1,6);
        foreach (['id_especie'=>2,'id_raza'=>50,'raza_indicada'=>'Nueva raza','sexo'=>'Macho','fecha_nacimiento'=>'2023-01-01'] as $campo=>$valor) {
            $this->rechazo(fn()=>$this->modelo->update(['id_mascota'=>1,$campo=>$valor]));
        }
        $this->assertTrue($this->modelo->update(['id_mascota'=>1,'peso'=>'10.25','nombre'=>'Luna Sur','colores'=>[1,2]]));
        $this->assertSame(1,(int)$this->modelo->getById(1)['id_especie']);
        $this->assertFalse($this->modelo->getById(1)['identidad_editable']);
        $auditoria=$this->db->query("SELECT campo_modificado FROM auditoria_mascotas WHERE campo_modificado <> 'vinculo_clinica'")->fetchAll(PDO::FETCH_COLUMN);
        sort($auditoria); $this->assertSame(['colores','nombre','peso'],$auditoria);
        $avisos=[]; (new AvisoFichaMascota($this->db))->enviarPendientes(1,function ($aviso) use (&$avisos) { $avisos[]=$aviso; return true; });
        $this->assertCount(1,$avisos); $this->assertStringContainsString('Clínica Sur',$avisos[0]['mensaje']);
        $this->assertStringContainsString('peso',$avisos[0]['mensaje']); $this->assertSame('fabio@zooki.test',$avisos[0]['destinatario_email']);
        $this->assertSame('enviado',$this->db->query('SELECT estado FROM notificaciones')->fetchColumn());
    }
    public function testEdicionDePesoNoEscribeIdentidadNiColores(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario,id_clinica) VALUES (6,2)");
        $this->comoVet(2); $this->modelo->vincular(1,6);
        $this->db->exec("CREATE TRIGGER proteger_identidad BEFORE UPDATE OF id_especie,id_raza,raza_indicada,sexo,fecha_nacimiento ON mascotas BEGIN SELECT RAISE(ABORT,'identidad sobrescrita'); END");
        $this->db->exec("CREATE TRIGGER proteger_colores BEFORE DELETE ON mascota_colores BEGIN SELECT RAISE(ABORT,'colores sobrescritos'); END");
        $this->assertTrue($this->modelo->update(['id_mascota'=>1,'peso'=>'11']));
        $this->assertSame('11',(string)$this->modelo->getById(1)['peso']);
    }
    public function testVinculoConPropietarioAjenoDejaAuditoriaTrasRollback(): void
    {
        $this->rechazo(fn()=>$this->modelo->vincular(1,5));
        $this->assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE accion='OTHER'")->fetchColumn());
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM mascota_clinica')->fetchColumn());
    }
    public function testRegistrarTokenAleatorioUnicoYColoresSinColumnasV1(): void
    {
        $tokens=[];
        for ($i=0;$i<12;$i++) {
            $id=$this->modelo->insert(['id_propietario'=>6,'nombre'=>'Nueva ' . $i,'id_especie'=>1,'id_raza'=>49,
                'fecha_nacimiento'=>'2023-01-01','peso'=>'3.50','sexo'=>'Macho','colores'=>[1]]);
            $m=$this->modelo->getById($id); $tokens[]=$m['token_carnet'];
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D',$m['token_carnet']);
            $this->assertNull($m['numero_historia_clinica']); $this->assertSame(1,(int)$m['id_clinica_registro']);
            $this->assertSame('1',$m['colores_ids']);
        }
        $this->assertCount(12,array_unique($tokens));
        $columnas=array_column($this->db->query('PRAGMA table_info(mascotas)')->fetchAll(PDO::FETCH_ASSOC),'name');
        $this->assertNotContains('color',$columnas); $this->assertNotContains('numero_historia_clinica',$columnas);
    }
    public function testBusquedaPorMascotaPropietarioYDocumentoYMinimoTresCaracteres(): void
    {
        $inicio=microtime(true);
        foreach (['Luna','Fabio','1000000006'] as $termino) {
            $resultado=$this->modelo->search($termino);
            $this->assertSame([1],array_map('intval',array_column($resultado,'id_mascota')));
            foreach (['nombre','nombre_especie','propietario_nombre','url_foto'] as $campo) $this->assertArrayHasKey($campo,$resultado[0]);
        }
        $this->assertLessThan(2,microtime(true)-$inicio);
        $this->assertSame([],$this->modelo->search('Lu'));
        $this->assertCount(1,$this->modelo->getByPropietario(6));
        $this->assertFalse(method_exists($this->modelo,'delete'));
    }
    public function testAltaSinPropietarioYFotoInvalidaNoGuardaMascota(): void
    {
        $this->rechazo(fn()=>$this->modelo->insert(['nombre'=>'Toby']));
        $temporal=tempnam(sys_get_temp_dir(),'foto_c3_');
        try {
            file_put_contents($temporal,base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='));
            foreach ([42,FotoMascota::MAX_BYTES+1] as $tamano) {
                $_FILES=['foto'=>['error'=>UPLOAD_ERR_OK,'size'=>$tamano,'tmp_name'=>$temporal,'name'=>'foto.jpg']];
                $_POST=['nombre'=>'Toby'];
                ob_start();
                try { (new MascotaController($this->db))->registrarAjax(); $respuesta=json_decode(ob_get_contents(),true); }
                finally { ob_end_clean(); }
                $this->assertFalse($respuesta['success']); $this->assertSame(422,http_response_code());
            }
        } finally { unlink($temporal); http_response_code(200); }
        $this->assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
    }
    public function testInactivarConservaFichaYNoSaleEnBusquedaActiva(): void
    {
        $this->modelo->updateStatus(1,0);
        $this->assertSame([],$this->modelo->search('Luna')); $this->assertSame([],$this->modelo->getAll());
        $this->assertSame(0,$this->modelo->getEstado(1)); $this->assertCount(1,$this->modelo->getAll(true));
        $this->assertNull($this->modelo->getPropietarioSiActiva(1));
    }
    public function testSinContextoFallaCerrado(): void
    {
        Contexto::salir(); $this->rechazo(fn()=>$this->modelo->getAll()); $this->rechazo(fn()=>$this->modelo->search('Luna'));
    }
    public function testPeticionDirectaDelControladorNoOcultaUn403(): void
    {
        $this->comoVet(2); $_POST=['id_mascota'=>'1','peso'=>'10'];
        ob_start(); try { $this->rechazo(fn()=>(new MascotaController($this->db))->actualizarAjax()); } finally { ob_end_clean(); }
        $this->assertSame('8.5',(string)$this->db->query('SELECT peso FROM mascotas WHERE id_mascota=1')->fetchColumn());
    }
    public function testUnaAuditoriaFallidaDeshaceFichaColoresYNotificacion(): void
    {
        $this->db->exec("CREATE TRIGGER fallo_auditoria BEFORE INSERT ON auditoria_mascotas BEGIN SELECT RAISE(ABORT,'fallo'); END");
        try { $this->modelo->update(['id_mascota'=>1,'peso'=>'9','colores'=>[2]]); $this->fail('Debió fallar'); }
        catch (PDOException $e) { $this->assertStringContainsString('fallo',$e->getMessage()); }
        $m=$this->modelo->getById(1); $this->assertSame('8.5',(string)$m['peso']); $this->assertSame('1',$m['colores_ids']);
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM notificaciones')->fetchColumn());
    }
}
