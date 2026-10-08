<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';

/** C9/C1.7: la vista restaurada conserva el aislamiento y la identidad global. */
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

    public function testTarjetasPorDefectoYTablaDisponibleSoloConPersonalDeLaClinica(): void
    {
        $vista = $this->renderizar();
        $this->assertSame('tarjetas', $vista->query('//*[@id="usuariosModulo"]')->item(0)->getAttribute('data-vista'));
        $this->assertSame(4, $vista->query('//article[contains(@class,"person-card")]')->length);
        $this->assertSame(2, $vista->query('//*[@data-vista-contenido="tabla"][@hidden]')->length);
        $this->assertSame(0, $vista->query('//*[@data-id-usuario="4"]')->length);
        $this->assertSame(2, $vista->query('//button[@data-vista-usuarios]')->length);
    }

    public function testCuentaExclusivaHabilitaResetYCuentaCompartidaLoBloqueaEnAmbasVistas(): void
    {
        $vista = $this->renderizar();
        $this->assertSame(2, $vista->query('//button[@data-accion="restablecer"][@data-id="2"][not(@disabled)]')->length);
        $this->assertSame(2, $vista->query('//button[@data-accion="restablecer"][@data-id="5"][@disabled]')->length);
        $this->assertSame('0', $vista->query('//article[@data-id-usuario="5"]')->item(0)->getAttribute('data-identidad-editable'));
    }

    public function testOtroVinculoPersonalInactivoTambienBloqueaLaTarjetaYLaTabla(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario,id_clinica,id_rol,estado) VALUES (2,2,2,'inactivo')");
        $vista = $this->renderizar();
        $this->assertSame(2, $vista->query('//button[@data-accion="restablecer"][@data-id="2"][@disabled]')->length);
        $this->assertSame(2, $vista->query('//button[@data-accion="editar"][@data-id="2"][not(@disabled)]')->length);
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
