<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';

/** C9/C9.1/C1.7: la vista de v1.12.0 con los datos v2, aislada y sin ceder identidades compartidas. */
final class UsuariosVistaTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN), 1);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function renderizar(): DOMXPath
    {
        $controlador = new UsuarioController($this->db);
        $personal = $controlador->listar();
        $propietarios = $controlador->listarPropietarios();
        ob_start();
        try {
            include __DIR__ . '/../../views/admin/usuarios.php';
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $documento = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        return new DOMXPath($documento);
    }

    private const PERSONAL = '//section[@data-panel="personal"]';
    private const CLIENTES = '//section[@data-panel="clientes"]';

    public function testTarjetasPorDefectoYTablaDisponibleSoloConPersonalDeLaClinica(): void
    {
        $vista = $this->renderizar();
        $this->assertSame('tarjetas', $vista->query('//*[@id="usuariosModulo"]')->item(0)->getAttribute('data-vista'));
        $this->assertSame(4, $vista->query(self::PERSONAL . '//article[contains(@class,"person-card")]')->length);
        $this->assertSame(2, $vista->query('//*[@data-vista-contenido="tabla"][@hidden]')->length);
        $this->assertSame(0, $vista->query('//*[@data-id-usuario="4"]')->length);
        $this->assertSame(2, $vista->query('//button[@data-vista-usuarios]')->length);
    }

    public function testCabeceraConPestanasNuevoUsuarioBuscadorRolYEstado(): void
    {
        $vista = $this->renderizar();
        $this->assertSame(['personal', 'clientes'], array_map(fn ($b) => $b->getAttribute('data-tab'), iterator_to_array($vista->query('//button[contains(@class,"tab-btn")]'))));
        $this->assertStringContainsString('Nuevo Usuario', $vista->query('//button[@data-accion="nuevo"]')->item(0)->textContent);
        $this->assertSame(2, $vista->query('//input[@data-filtro-texto]')->length);
        $roles = array_map(fn ($o) => $o->getAttribute('value'), iterator_to_array($vista->query('//*[@data-filtros="personal"]//select[@data-filtro-rol]/option')));
        $this->assertSame(['', (string) Roles::ADMIN, (string) Roles::VETERINARIO], $roles);
        $this->assertSame(0, $vista->query('//*[@data-filtros="clientes"]//select[@data-filtro-rol]')->length);
        foreach (['personal', 'clientes'] as $pestana) {
            $estados = array_map(fn ($i) => $i->getAttribute('value'), iterator_to_array($vista->query('//*[@data-filtros="' . $pestana . '"]//input[@data-filtro-estado]')));
            $this->assertSame(['', '1', '0'], $estados);
        }
    }

    public function testTarjetaDePersonalConAvatarInterruptorChipsDocumentoYTelefono(): void
    {
        $this->db->exec("UPDATE usuarios SET telefono='3001234567' WHERE id_usuario=2");
        $this->db->exec("UPDATE usuario_clinica SET estado='inactivo' WHERE id_usuario=8 AND id_clinica=1");
        $vista = $this->renderizar();
        $beto = $vista->query('//article[@data-id-usuario="2"]')->item(0);
        $this->assertSame('BN', trim($vista->query('.//*[contains(@class,"avatar-iniciales")]', $beto)->item(0)->textContent));
        $this->assertSame(1, $vista->query('.//input[@type="checkbox"][@data-accion="estado"][@checked]', $beto)->length);
        $this->assertStringContainsString('Veterinario', $beto->textContent);
        $this->assertStringContainsString('1000000002', $beto->textContent);
        $this->assertStringContainsString('3001234567', $beto->textContent);
        $this->assertStringNotContainsString('Inactivo', $beto->textContent);

        $hugo = $vista->query('//article[@data-id-usuario="8"]')->item(0);
        $this->assertSame('0', $hugo->getAttribute('data-estado'));
        $this->assertStringContainsString('Inactivo', $hugo->textContent);
        $this->assertSame(0, $vista->query('.//input[@data-accion="estado"][@checked]', $hugo)->length);
    }

    public function testNadieSeDesactivaNiSeRestableceASiMismo(): void
    {
        $vista = $this->renderizar();
        $this->assertSame(2, $vista->query('//input[@data-accion="estado"][@data-id="1"][@disabled]')->length);
        $this->assertSame(0, $vista->query('//button[@data-accion="restablecer"][@data-id="1"]')->length);
        $this->assertStringContainsString('(Tú)', $vista->query('//article[@data-id-usuario="1"]')->item(0)->textContent);
        $this->assertSame(2, $vista->query('//input[@data-accion="estado"][@data-id="2"][not(@disabled)]')->length);
    }

    public function testCuentaExclusivaOfreceResetYCuentaCompartidaNoLoMuestraEnAmbasVistas(): void
    {
        $vista = $this->renderizar();
        $this->assertSame(2, $vista->query('//button[@data-accion="restablecer"][@data-id="2"]')->length);
        $this->assertSame(0, $vista->query('//button[@data-accion="restablecer"][@data-id="5"]')->length);
        $this->assertSame(2, $vista->query('//button[@data-accion="editar"][@data-id="5"]')->length);
        $this->assertSame('0', $vista->query('//article[@data-id-usuario="5"]')->item(0)->getAttribute('data-identidad-editable'));
    }

    public function testOtroVinculoPersonalInactivoTambienQuitaElResetDeTarjetaYTabla(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario,id_clinica,id_rol,estado) VALUES (2,2,2,'inactivo')");
        $vista = $this->renderizar();
        $this->assertSame(0, $vista->query('//button[@data-accion="restablecer"][@data-id="2"]')->length);
        $this->assertSame(2, $vista->query('//button[@data-accion="editar"][@data-id="2"]')->length);
    }

    public function testClientesDeLaClinicaConInterruptorEditarYVer(): void
    {
        $this->db->exec("UPDATE usuarios SET telefono='3115550000' WHERE id_usuario=6");
        $this->db->exec("UPDATE propietario_clinica SET estado='inactivo' WHERE id_propietario=5 AND id_clinica=1");
        $vista = $this->renderizar();
        $tarjetas = $vista->query(self::CLIENTES . '//article[contains(@class,"client-card")]');
        $this->assertSame(['5', '6'], array_map(fn ($t) => $t->getAttribute('data-id-usuario'), iterator_to_array($tarjetas)));
        // Iris es cliente de Sur: no aparece en Norte.
        $this->assertSame(0, $vista->query(self::CLIENTES . '//*[@data-id-usuario="9"]')->length);

        $fabio = $vista->query(self::CLIENTES . '//article[@data-id-usuario="6"]')->item(0);
        $this->assertSame('FD', trim($vista->query('.//*[contains(@class,"avatar-iniciales")]', $fabio)->item(0)->textContent));
        $this->assertStringContainsString('3115550000', $fabio->textContent);
        $this->assertStringContainsString('1000000006', $fabio->textContent);
        $this->assertSame(1, $vista->query('.//input[@data-accion="estado-cliente"][@checked]', $fabio)->length);
        $this->assertSame(1, $vista->query('.//button[@data-accion="editar-cliente"]', $fabio)->length);
        $this->assertSame(1, $vista->query('.//button[@data-accion="ver-cliente"]', $fabio)->length);
        $this->assertSame(0, $vista->query(self::CLIENTES . '//button[@data-accion="restablecer"]')->length);

        $elena = $vista->query(self::CLIENTES . '//article[@data-id-usuario="5"]')->item(0);
        $this->assertSame('0', $elena->getAttribute('data-estado'));
        $this->assertStringContainsString('Inactivo', $elena->textContent);
        $this->assertSame(0, $vista->query('.//input[@data-accion="estado-cliente"][@checked]', $elena)->length);
    }

    public function testElTelefonoDeLosFormulariosUsaLaReglaDelServidor(): void
    {
        $vista = $this->renderizar();
        foreach (['usuarioTelefono'] as $id) {
            $campo = $vista->query('//input[@id="' . $id . '"]')->item(0);
            $this->assertSame((string) ValidadorTelefono::MAX, $campo->getAttribute('maxlength'));
            $this->assertSame(ValidadorTelefono::patronHtml(), $campo->getAttribute('pattern'));
            $this->assertSame(ValidadorTelefono::CARACTERES, $campo->getAttribute('data-caracteres'));
        }
    }

    public function testSinJsNiCssEnLinea(): void
    {
        $vista = $this->renderizar();
        $this->assertSame(0, $vista->query('//*[@style or @onclick or @onchange or @onsubmit or @oninput]')->length);
        $this->assertSame(0, $vista->query('//script[not(@src)]')->length);
    }

    public function testTarjetaMuestraElRolDelContextoYEscapaLosDatos(): void
    {
        $this->db->exec("UPDATE usuarios SET nombre_completo='<script>alert(1)</script>' WHERE id_usuario=5");
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_SUR];
        Contexto::activar(Contexto::deClinica(2, 'Clínica Sur', Roles::ADMIN), 1);
        $vista = $this->renderizar();
        $tarjeta = $vista->query('//article[@data-id-usuario="5"]')->item(0);
        $this->assertStringContainsString('Administrador', $tarjeta->textContent);
        $this->assertStringContainsString('<script>alert(1)</script>', $tarjeta->textContent);
        $this->assertSame(0, $vista->query('.//script', $tarjeta)->length);
    }
}
