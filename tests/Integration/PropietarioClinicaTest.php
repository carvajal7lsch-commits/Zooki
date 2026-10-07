<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/VerificacionEmail.php';
require_once __DIR__ . '/../../controllers/PropietarioController.php';
require_once __DIR__ . '/../../helpers/Security.php';

final class PropietarioClinicaTest extends TestCase
{
    private PDO $db;
    private PropietarioClinica $modelo;
    protected function setUp(): void
    {
        $this->db=DosClinicas::sqlite(); DosClinicas::crearMascotasSqlite($this->db);
        $_SESSION=['id_usuario'=>4]; Contexto::activar(Contexto::deClinica(2,'Clínica Sur',Roles::VETERINARIO),1);
        $_POST=[]; $_GET=[]; $_SERVER['REQUEST_METHOD']='POST';
        $this->modelo=new PropietarioClinica($this->db);
    }
    protected function tearDown(): void { $_SESSION=[]; $_POST=[]; $_GET=[]; http_response_code(200); }
    private function datos(array $cambios=[]): array
    {
        return array_replace(['nombre_completo'=>'Nuevo Titular','tipo_documento'=>'CC','documento'=>'1000000030',
            'email'=>'nuevo@zooki.test','telefono'=>'3001234567','titular_presente'=>'1','acepta_politica'=>'1'],$cambios);
    }
    public function testBusquedaGlobalSoloExactaConMascotasBasicas(): void
    {
        foreach (['Fabio','10000','fabio@','%','100000000','fabio@zooki.tes'] as $termino) $this->assertNull($this->modelo->buscarExacto($termino));
        $porDocumento=$this->modelo->buscarExacto('1000000006');
        $porCorreo=$this->modelo->buscarExacto('fabio@zooki.test');
        $this->assertSame($porDocumento,$porCorreo); $this->assertFalse($porCorreo['vinculado']);
        $this->assertSame(['id_mascota','nombre','nombre_especie','nombre_raza','raza_indicada','url_foto'],array_keys($porCorreo['mascotas'][0]));
        foreach (['password','google_uid','peso','token_carnet'] as $campo) $this->assertArrayNotHasKey($campo,$porCorreo);
        $this->assertSame([9],array_map('intval',array_column($this->modelo->listar(),'id_usuario')));
        try { $this->modelo->obtener(6); $this->fail('Debió rechazar'); } catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
    }
    public function testAltaSinAceptacionOPresenciaNoCreaCuentaNiVinculoNiToken(): void
    {
        foreach ([['acepta_politica'=>'0'],['acepta_politica'=>''],['titular_presente'=>'0']] as $datos) {
            try { $this->modelo->registrar($this->datos($datos),'politica-1','127.0.0.1'); $this->fail('Debió rechazar'); }
            catch (InvalidArgumentException $e) { $this->assertStringContainsString('titular',$e->getMessage()); }
        }
        $this->assertSame(10,(int)$this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $this->assertSame(3,(int)$this->db->query('SELECT COUNT(*) FROM propietario_clinica')->fetchColumn());
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM password_resets')->fetchColumn());
    }
    public function testControladorExigeDatosObligatoriosYRechazaDuplicados(): void
    {
        $entradas=[];
        foreach (['nombre_completo','tipo_documento','documento','telefono','email'] as $campo) $entradas[]=$this->datos([$campo=>'']);
        $entradas[]=$this->datos(['documento'=>'1000000006']);
        $entradas[]=$this->datos(['email'=>'fabio@zooki.test']);
        foreach ($entradas as $datos) {
            $_POST=$datos; ob_start();
            try { (new PropietarioController($this->db,fn()=>true))->registrarAjax(); $respuesta=json_decode(ob_get_contents(),true); }
            finally { ob_end_clean(); }
            $this->assertFalse($respuesta['success']); $this->assertSame(422,http_response_code());
        }
        $this->assertSame(10,(int)$this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn());
    }
    public function testAltaConsentidaGuardaPruebaYPasswordResetSinContrasena(): void
    {
        $alta=$this->modelo->registrar($this->datos(),'politica-1','127.0.0.1');
        $persona=$this->db->query('SELECT * FROM usuarios WHERE id_usuario=' . $alta['id_usuario'])->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($persona['password']);
        $prueba=$this->db->query('SELECT * FROM consentimientos_datos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('alta_personal',$prueba['medio']); $this->assertSame('politica-1',$prueba['version_politica']);
        $this->assertSame('127.0.0.1',$prueba['ip_address']); $this->assertNotEmpty($prueba['fecha']);
        $reset=(new PasswordReset($this->db))->findById($alta['id_enlace']);
        $this->assertTrue(password_verify($alta['token'],$reset['token_hash']));
        $this->assertSame($alta['id_usuario'],(int)$reset['id_usuario']);
        $this->assertCount(2,$this->modelo->listar());
    }
    public function testSolicitudNoCambiaIdentidadNiBloqueaLoginYSoloConfirmaLaClinicaGuardada(): void
    {
        $antes=$this->db->query('SELECT * FROM usuarios WHERE id_usuario=6')->fetch(PDO::FETCH_ASSOC);
        $solicitud=$this->modelo->solicitarVinculo('1000000006');
        $this->assertSame($antes,$this->db->query('SELECT * FROM usuarios WHERE id_usuario=6')->fetch(PDO::FETCH_ASSOC));
        $this->assertFalse((new VerificacionEmail($this->db))->hayPendiente(6));
        $this->assertFalse($this->modelo->buscarExacto('1000000006')['vinculado']);
        $_SESSION=[]; $_GET=['id_clinica'=>3];
        $this->assertFalse($this->modelo->confirmarVinculo($solicitud['id_enlace'],'inventado'));
        $this->assertTrue($this->modelo->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
        $this->assertFalse($this->modelo->confirmarVinculo($solicitud['id_enlace'],$solicitud['token']));
        $clinicas=$this->db->query('SELECT id_clinica FROM propietario_clinica WHERE id_propietario=6 ORDER BY id_clinica')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([1,2],array_map('intval',$clinicas));
        $this->assertSame($antes,$this->db->query('SELECT * FROM usuarios WHERE id_usuario=6')->fetch(PDO::FETCH_ASSOC));
    }
    public function testNoConfirmaTokenDeRegistroVencidoCorreoCambiadoNiClinicaSuspendida(): void
    {
        foreach (['registro','vencido','correo','suspendida'] as $caso) {
            $s=$this->modelo->solicitarVinculo('1000000006');
            if ($caso === 'registro') $this->db->exec("UPDATE verificaciones_email SET proposito='registro' WHERE id=" . $s['id_enlace']);
            if ($caso === 'vencido') $this->db->exec("UPDATE verificaciones_email SET expires_at='2000-01-01' WHERE id=" . $s['id_enlace']);
            if ($caso === 'correo') $this->db->exec("UPDATE usuarios SET email='cambiado@zooki.test' WHERE id_usuario=6");
            if ($caso === 'suspendida') $this->db->exec("UPDATE clinicas SET estado='suspendida' WHERE id_clinica=2");
            $this->assertFalse($this->modelo->confirmarVinculo($s['id_enlace'],$s['token']));
        }
        $this->assertSame(3,(int)$this->db->query('SELECT COUNT(*) FROM propietario_clinica')->fetchColumn());
    }
    public function testControladorEnviaEnlaceSinExponerloAlPersonalNiEnviarPassword(): void
    {
        $correos=[];
        $controller=new PropietarioController($this->db,function ($email,$nombre,$asunto,$html) use (&$correos) { $correos[]=[$email,$asunto,$html]; return true; });
        $_POST=$this->datos(); ob_start();
        try { $controller->registrarAjax(); $res=json_decode(ob_get_contents(),true); } finally { ob_end_clean(); }
        $this->assertTrue($res['success']); $this->assertArrayNotHasKey('token',$res); $this->assertArrayNotHasKey('id_enlace',$res);
        $this->assertStringContainsString('action=reset_password',$correos[0][2]);
        $this->assertNull($this->db->query('SELECT password FROM usuarios WHERE id_usuario=' . $res['id_usuario'])->fetchColumn());
        $_POST=['identificador'=>'fabio@zooki.test']; ob_start();
        try { $controller->solicitarVinculoAjax(); $res=json_decode(ob_get_contents(),true); } finally { ob_end_clean(); }
        $this->assertTrue($res['success']); $this->assertArrayNotHasKey('token',$res); $this->assertArrayNotHasKey('enlace',$res);
        $this->assertStringContainsString('action=confirmar_vinculo_propietario',$correos[1][2]);
        $this->assertSame('fabio@zooki.test',$correos[1][0]);
    }
    public function testConfirmacionPublicaPostExigeCsrf(): void
    {
        $_SESSION=[]; $_POST=[];
        try { Security::autorizar('confirmar_vinculo_propietario'); $this->fail('Debió rechazar'); }
        catch (AccesoDenegado $e) { $this->assertSame(403,$e->codigo()); }
    }
}
