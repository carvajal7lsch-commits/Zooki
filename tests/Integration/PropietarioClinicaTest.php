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
            'email'=>'nuevo@zooki.test','telefono'=>'3001234567'],$cambios);
    }
    /**
     * RE-1.5.1, RN-109 y RN-111 (decisión del usuario, M5): sin vínculo activo
     * la búsqueda exacta solo dice que la cuenta existe, igual por documento o
     * correo; con el vínculo activo, sus datos y sus mascotas básicas.
     */
    public function testBusquedaExactaSinVinculoSoloDiceQueHayUnaCuenta(): void
    {
        foreach (['Fabio', '10000', 'fabio@', '%', '100000000', 'fabio@zooki.tes'] as $termino) {
            $this->assertNull($this->modelo->buscarExacto($termino));
        }
        $esperado = ['vinculado' => false, 'mensaje' => PropietarioClinica::MENSAJE_SIN_VINCULO];
        $this->assertSame($esperado, $this->modelo->buscarExacto('1000000006'));
        $this->assertSame($esperado, $this->modelo->buscarExacto('fabio@zooki.test'));
        $this->assertSame([9], array_map('intval', array_column($this->modelo->listar(), 'id_usuario')));
        try {
            $this->modelo->obtener(6);
            $this->fail('Debió rechazar');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    public function testBusquedaExactaConVinculoActivoMuestraSusMascotasBasicas(): void
    {
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (6, 2, 'activo')");

        $porDocumento = $this->modelo->buscarExacto('1000000006');

        $this->assertSame($porDocumento, $this->modelo->buscarExacto('fabio@zooki.test'));
        $this->assertTrue($porDocumento['vinculado']);
        $this->assertSame('Fabio Dueño', $porDocumento['nombre_completo']);
        $this->assertSame(['id_mascota', 'nombre', 'nombre_especie', 'nombre_raza', 'raza_indicada', 'url_foto'], array_keys($porDocumento['mascotas'][0]));
        foreach (['password', 'google_uid', 'peso', 'token_carnet', 'es_super_admin'] as $campo) {
            $this->assertArrayNotHasKey($campo, $porDocumento);
        }
    }
    /**
     * RE-T.19.1 (decisión del usuario, M5 C3): sin aceptación presencial. La
     * cuenta queda pendiente, sin contraseña ni consentimiento, con el vínculo
     * de la clínica y un enlace de activación de 72 horas.
     */
    public function testAltaSinAceptacionCreaCuentaPendienteConEnlaceYSinConsentimiento(): void
    {
        $alta=$this->modelo->registrar($this->datos());

        $persona=$this->db->query('SELECT * FROM usuarios WHERE id_usuario=' . $alta['id_usuario'])->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($persona['password']);
        $this->assertSame(0,(int)$persona['estado'],'Pendiente: no entra ni tiene portal.');
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn(),'El personal no acepta por el titular.');
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM password_resets')->fetchColumn());
        $enlace=(new VerificacionEmail($this->db))->buscarPorId($alta['id']);
        $this->assertSame(ActivacionPropietario::PROPOSITO,$enlace['proposito']);
        $this->assertSame(2,(int)$enlace['id_clinica_vinculo']);
        $this->assertTrue(password_verify($alta['token'],$enlace['token_hash']));
        $horas=(strtotime($enlace['expires_at'])-time())/3600;
        $this->assertEqualsWithDelta(72,$horas,0.1);
        $this->assertSame('activo',$this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario=' . $alta['id_usuario'] . ' AND id_clinica=2')->fetchColumn());
        $cliente=array_values(array_filter($this->modelo->listar(),fn ($fila) => (int)$fila['id_usuario'] === $alta['id_usuario']))[0];
        $this->assertSame(1,(int)$cliente['pendiente_activacion']);
        $this->assertTrue($this->modelo->buscarExacto('1000000030')['pendiente'],'La clínica lo encuentra y sabe que aún no activa.');
    }
    public function testControladorExigeDatosObligatoriosYRechazaDuplicados(): void
    {
        $entradas=[];
        foreach (['nombre_completo','tipo_documento','documento','telefono','email'] as $campo) $entradas[]=$this->datos([$campo=>'']);
        foreach ($entradas as $datos) {
            $_POST=$datos; ob_start();
            try { (new PropietarioController($this->db,fn()=>true))->registrarAjax(); $respuesta=json_decode(ob_get_contents(),true); }
            finally { ob_end_clean(); }
            $this->assertFalse($respuesta['success']); $this->assertSame(422,http_response_code());
        }
        // RN-109 (M5 C3): un documento o un correo con cuenta no se duplica; sigue la confirmación del vínculo.
        foreach ([['documento'=>'1000000006'],['email'=>'fabio@zooki.test']] as $cambio) {
            $_POST=$this->datos($cambio); ob_start();
            try { (new PropietarioController($this->db,fn()=>true))->registrarAjax(); $respuesta=json_decode(ob_get_contents(),true); }
            finally { ob_end_clean(); }
            $this->assertSame(409,http_response_code());
            $this->assertTrue($respuesta['existe']);
            $this->assertFalse($respuesta['vinculado']);
            $this->assertSame(PropietarioClinica::MENSAJE_SIN_VINCULO,$respuesta['message']);
            $this->assertSame(array_values($cambio)[0],$respuesta['identificador'],'Con el dato que escribió, sin nombre ni contacto.');
            $this->assertArrayNotHasKey('nombre_completo',$respuesta);
        }
        $this->assertSame(10,(int)$this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $this->assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn());
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
        // M5 C3: el correo lleva el enlace de activación, donde él acepta la política y crea su contraseña.
        $this->assertStringContainsString('action=activar_propietario',$correos[0][2]);
        $this->assertStringContainsString('72 horas',$correos[0][2]);
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
