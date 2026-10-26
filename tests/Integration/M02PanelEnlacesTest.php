<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/PlataformaFixture.php';
require_once __DIR__ . '/../../models/Plataforma.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../controllers/PlataformaController.php';
require_once __DIR__ . '/../../controllers/RegistroClinicaController.php';
require_once __DIR__ . '/../../helpers/PresentacionPlataforma.php';
require_once __DIR__ . '/../../config/EmailService.php';
require_once __DIR__ . '/RegistroClinicaTest.php';

/**
 * M0.2 — Panel del super-administrador (HU-0.3), páginas de los enlaces de
 * correo con el marco del acceso y mensaje del super-administrador al
 * activar una clínica (bitácora de F1).
 */
final class M02PanelEnlacesTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = PlataformaFixture::sqlite();
        PlataformaFixture::datos($this->db);
        PlataformaFixture::plataforma();
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.8';
        Auditoria::definirProxiesConfiables([]);
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        putenv('APP_URL=http://localhost/Zooki/public');
    }

    protected function tearDown(): void
    {
        Security::definirAlmacenDeIntentos(null);
        Auditoria::definirProxiesConfiables(null);
        putenv('APP_URL');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    private function panel(array $consulta = []): string
    {
        $_GET = $consulta;
        ob_start();
        try {
            (new PlataformaController($this->db))->inicio();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    // ── Panel del super-administrador ───────────────────────────────────

    public function testElPanelOrdenaClinicasSoporteYSeguridadSinDatosClinicos(): void
    {
        $this->db->exec("UPDATE clinicas SET nit = '9012345677', fecha_registro = '2026-10-11 18:35:45' WHERE id_clinica = 1");
        $html = $this->panel();
        // Revisión del usuario (2026-10-12): sin franja de cifras; los contadores van en las pestañas.
        $this->assertStringNotContainsString('data-indicador', $html);
        $this->assertStringContainsString('data-contador-soporte>2<', $html);
        $this->assertStringContainsString('data-contador-seguridad>1<', $html);
        foreach (['data-pestana="clinicas"', 'data-pestana="soporte"', 'data-pestana="seguridad"', 'data-panel="soporte" hidden', 'data-panel="seguridad" hidden'] as $parte) {
            $this->assertStringContainsString($parte, $html);
        }
        // La tarjeta de Usuarios: NIT con DV, estado como etiqueta, plan, suscripción, fecha sin hora y barras de uso.
        foreach (['card-header-mini', 'avatar-iniciales', 'card-title-mini', 'card-tags-mini', 'card-contact-mini', 'card-footer-mini', 'action-btn-mini plataforma-gestionar'] as $componente) {
            $this->assertStringContainsString($componente, $html);
        }
        $this->assertStringContainsString('NIT 901234567-7', $html);
        $this->assertStringContainsString('<span class="tag-mini plataforma-estado plataforma-estado--activa"><i class="bi bi-check-circle" aria-hidden="true"></i> Activa</span>', $html);
        $this->assertStringContainsString('<span class="tag-mini plataforma-estado plataforma-estado--pendiente"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Pendiente de verificación</span>', $html);
        $this->assertStringContainsString('<i class="bi bi-gem" aria-hidden="true"></i> Gratuito</span>', $html);
        $this->assertStringContainsString('Sin pago', $html);
        $this->assertStringContainsString('Gratuito · sin pago', $html, 'En la tabla.');
        $this->assertStringContainsString('11 oct 2026', $html);
        $this->assertStringNotContainsString('2026-10-11 18:35:45', $html);
        $this->assertStringNotContainsString('<i class="bi bi-credit-card" aria-hidden="true"></i> Sin suscripción', $html, 'La tarjeta no dice «Sin suscripción» de un gratuito.');
        $this->assertMatchesRegularExpression('/<progress class="plataforma-uso__barra" max="2" value="\d">/', $html);
        // S4 añade finanzas sin mover el aviso de Seguridad ni rediseñar el modal.
        $this->assertSame(3, substr_count($html, 'data-modal-pestana='));
        $this->assertStringContainsString('data-modal-pestana="pagos"', $html);
        $this->assertStringNotContainsString('Aviso al administrador', $html);
        // RN-G13: ningún dato clínico individual ni enlace a pantallas clínicas.
        $this->assertStringNotContainsString('Luna', $html);
        $this->assertStringNotContainsString('Fabio', $html);
        $this->assertDoesNotMatchRegularExpression('/action=(vet_pacientes|ver_consulta|portal_propietario|admin_citas|admin_usuarios)/', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=|<script(?![^>]*src=)/', $html);
        // Cabecera con los componentes del sistema, no enlaces sueltos.
        $this->assertStringContainsString('class="contexto-actual"', $html);
        $this->assertStringContainsString('header-user__role">Super-administrador<', $html);
        $this->assertStringNotContainsString('>Cerrar sesión</a> ', $html);
    }

    public function testSinJavascriptFiltraPorEstadoPlanYNitConGuion(): void
    {
        $this->db->exec("UPDATE clinicas SET nit = '9012345677' WHERE id_clinica = 1");
        $this->db->exec('UPDATE clinicas SET id_plan = 2 WHERE id_clinica = 2');
        // Una clínica pendiente aún no tiene plan (se asigna al verificar, HU-0.2).
        $this->db->exec('UPDATE clinicas SET id_plan = NULL WHERE id_clinica = 4');
        $porNit = $this->panel(['buscar' => '901234567-7']);
        $this->assertSame(1, substr_count($porNit, '<article class="person-card plataforma-tarjeta" data-clinica-fila'));
        $this->assertStringContainsString('Clínica Norte', $porNit);
        $porPlan = $this->panel(['plan' => '2']);
        $this->assertSame(1, substr_count($porPlan, '<article class="person-card plataforma-tarjeta" data-clinica-fila'));
        $this->assertStringContainsString('Clínica Sur', $porPlan);
        $vacio = $this->panel(['estado' => 'baja']);
        $this->assertMatchesRegularExpression('/data-sin-resultados >/', $vacio, 'El estado vacío se ve.');
        $this->assertStringContainsString('No hay clínicas que coincidan con la búsqueda o los filtros.', $vacio);
        $this->panel(['plan' => 'x']);
        $this->assertSame(422, http_response_code());
    }

    public function testPresentacionDelPanel(): void
    {
        $this->assertSame('901234567-7', PresentacionPlataforma::nit('9012345677'));
        $this->assertSame('900123456-8', PresentacionPlataforma::nit('900123456-8'));
        $this->assertSame('Gratuito · sin pago', PresentacionPlataforma::plan('Gratuito', null));
        $this->assertSame('Profesional · suscripción activa', PresentacionPlataforma::plan('Profesional', 'activa'));
        $this->assertSame('Sin plan hasta verificar', PresentacionPlataforma::plan(null, null));
        $this->assertSame('Sin pago', PresentacionPlataforma::suscripcion('Gratuito', null));
        $this->assertSame('Suscripción activa', PresentacionPlataforma::suscripcion('Profesional', 'activa'));
        $this->assertSame('bi-hourglass-split', PresentacionPlataforma::iconoEstado('pendiente_verificacion'));
        $this->assertSame(['casos' => 1, 'ips' => 2], PresentacionPlataforma::contadores([['estado' => 'abierto'], ['estado' => 'resuelto']], [[], []]));
        $this->assertSame('3 oct 2026', PresentacionPlataforma::fecha('2026-10-03 08:00:00'));
        $uso = PresentacionPlataforma::uso([
            'personal' => ['uso' => 2, 'limite' => 2],
            'mascotas' => ['uso' => 41, 'limite' => 50],
            'citas' => ['uso' => 7, 'limite' => null],
        ]);
        $this->assertSame(['lleno', 'alto', 'libre'], array_column($uso, 'nivel'));
        $this->assertSame(['2 / 2', '41 / 50', '7 · sin límite'], array_column($uso, 'texto'));
        $this->assertSame('admin@x.test (por confirmar)', PresentacionPlataforma::administrador(['administrador' => null, 'administrador_pendiente' => 'admin@x.test']));
    }

    // ── Páginas de los enlaces de correo ────────────────────────────────

    /** Pinta una vista con sus variables, como lo hace su controlador. */
    private function vista(string $archivo, array $variables): string
    {
        extract($variables);
        ob_start();
        try {
            require __DIR__ . '/../../views/auth/' . $archivo . '.php';
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    public static function paginasDeEnlace(): array
    {
        $enlace = ['id' => 1, 'token' => 'abc', 'error' => null];
        return [
            'activar_clinica' => ['activar_clinica', $enlace + ['fila' => ['clinica' => 'Clínica Nueva', 'proposito' => 'registro_clinica'], 'terminado' => false]],
            'aceptar_politica' => ['aceptar_politica', ['version' => '2026-10', 'vigencia' => '1 de octubre de 2026', 'error' => null]],
            'confirmar_vinculo' => ['confirmar_vinculo', ['id' => 1, 'token' => 'abc', 'resultado' => null]],
            'activacion_personal' => ['enlace_identidad', $enlace + ['proposito' => 'activacion_personal', 'fila' => ['email' => 'nuevo@zooki.test'], 'terminado' => false]],
            'cambio_correo' => ['enlace_identidad', $enlace + ['proposito' => 'cambio_correo', 'fila' => ['email' => 'nuevo@zooki.test'], 'terminado' => false]],
            'invitacion_personal' => ['invitacion_personal', $enlace + ['fila' => ['nombre_clinica' => 'Clínica Norte'], 'rol' => 'Veterinario', 'resultado' => null]],
            'verificar_email' => ['verificar_email', ['id' => 1, 'token' => 'abc']],
            'reset_password' => ['reset_password', ['tokenId' => 1, 'tokenPlano' => 'abc', 'tokenValido' => true]],
            'cambiar_password' => ['cambiar_password', ['cuentaPassword' => ['tiene_password' => 1]]],
            'completar_perfil' => ['completar_perfil', ['error' => null]],
            'seleccionar_contexto' => ['seleccionar_contexto', ['disponibles' => [Contexto::dePlataforma()], 'actual' => null, 'nombre' => 'Gina Plataforma', 'error' => null]],
        ];
    }

    /** @dataProvider paginasDeEnlace */
    public function testLasPaginasDeLosCorreosUsanElMarcoDelAcceso(string $archivo, array $variables): void
    {
        $html = $this->vista($archivo, $variables);
        $this->assertStringContainsString('class="login-container"', $html);
        $this->assertStringContainsString('class="form-wrapper registro-marco acceso-pagina"', $html);
        $this->assertStringContainsString('class="acceso-estado acceso-estado--', $html);
        $this->assertStringContainsString('class="login-hero', $html);
        $this->assertStringContainsString('css/registro-cuenta.css?v=' . App::VERSION, $html);
        $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', $html);
        foreach (['reset-card', 'contexto-tarjeta', 'cuenta-check', 'reset-check', 'registro-consentimiento', 'change-password'] as $viejo) {
            $this->assertStringNotContainsString($viejo, $html, 'Sin el diseño anterior: ' . $viejo);
        }
        $this->assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=|<script(?![^>]*src=)/', $html);
        // Ningún enlace sin estilo: todos los <a> del contenido llevan su clase.
        // La casilla de la política tiene su propio estilo para su enlace (.consent-check a).
        $principal = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));
        $principal = preg_replace('/<label class="consent-check".*?<\/label>/s', '', $principal);
        $this->assertDoesNotMatchRegularExpression('/<a (?![^>]*class=)[^>]*>/', $principal);
        $this->assertDoesNotMatchRegularExpression('/<p>(?!<\/p>)/', $principal, 'Ningún párrafo sin clase.');
    }

    public function testLasCasillasDeLaPoliticaSonLaComun(): void
    {
        foreach (['activar_clinica', 'aceptar_politica', 'activacion_personal'] as $clave) {
            [$archivo, $variables] = self::paginasDeEnlace()[$clave];
            $html = $this->vista($archivo, $variables);
            $this->assertStringContainsString('<label class="consent-check"', $html, $clave);
            $this->assertStringContainsString('Autorizo el tratamiento de mis datos personales conforme a la', $html, $clave);
        }
    }

    public function testExitoErrorYEnlaceNoDisponibleConElMismoComponente(): void
    {
        $exito = $this->vista('confirmar_vinculo', ['id' => 1, 'token' => 'abc', 'resultado' => true]);
        $error = $this->vista('confirmar_vinculo', ['id' => 1, 'token' => 'abc', 'resultado' => false]);
        $vencido = $this->vista('reset_password', ['tokenValido' => false, 'errorMessage' => '']);
        $this->assertStringContainsString('acceso-estado--exito', $exito);
        $this->assertStringContainsString('acceso-estado--error', $error);
        $this->assertStringContainsString('<h1>Enlace no disponible</h1>', $error);
        $this->assertStringContainsString('acceso-estado--error', $vencido);
        $this->assertStringContainsString('<h1>Enlace no disponible</h1>', $vencido);
        $this->assertStringNotContainsString('<form', $error);
    }

    // ── Super-administrador que intenta administrar una clínica ─────────

    public function testActivarClinicaExplicaQueLaCuentaEsDeSuperAdministrador(): void
    {
        DosClinicas::crearCatalogosSqlite($this->db);
        $_SESSION = [];
        $registro = new RegistroClinica($this->db);
        $alta = $registro->registrar(RegistroClinicaTest::datos(9, ['email' => 'gina@zooki.test']), '192.0.2.40');
        $controlador = new RegistroClinicaController($this->db);
        $_GET = $alta['enlace'];
        ob_start();
        $controlador->activar();
        $get = (string) ob_get_clean();
        $this->assertStringContainsString(RegistroClinica::MENSAJE_SUPER_ADMIN, $get);
        $this->assertStringContainsString('Esta cuenta administra la plataforma y no puede administrar una clínica. Regístrala con otro correo', $get);
        $this->assertStringNotContainsString('Confirmar y activar clínica', $get, 'Sin botón.');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $alta['enlace'] + ['csrf_token' => Csrf::token(), 'acepta_datos' => '1'];
        ob_start();
        $controlador->activar();
        $post = (string) ob_get_clean();
        $this->assertStringContainsString(RegistroClinica::MENSAJE_SUPER_ADMIN, $post);
        $this->assertSame('pendiente_verificacion', $this->db->query("SELECT estado FROM clinicas WHERE nombre = 'Clínica Nueva 9'")->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM usuario_clinica WHERE id_usuario = 7')->fetchColumn());
        // Aunque se llame al modelo directo, el motivo es el mismo y no cambia nada.
        try {
            $registro->activar($alta['enlace']['id'], $alta['enlace']['token'], ['acepta_datos' => '1'], '192.0.2.40');
            $this->fail('Activó con la cuenta del super-administrador.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(RegistroClinica::MENSAJE_SUPER_ADMIN, $e->getMessage());
        }
    }

    // ── Correos: Gmail claro y oscuro ───────────────────────────────────

    public function testLaPlantillaDeCorreoEscapaYSeLeeEnGmail(): void
    {
        $correo = new EmailService(['SMTP_FROM' => 'no-reply@zooki.test']);
        $html = $correo->obtenerPlantillaBaseHTML("O'Neill <b>", 'Activa tu clínica <script>x</script>', '<p>Contenido</p>', 'Confirmar', 'https://zooki.test/index.php?action=a&id=1&token=t');
        $this->assertStringContainsString('Activa tu clínica &lt;script&gt;x&lt;/script&gt;', $html, 'El título lleva texto del público.');
        $this->assertStringContainsString('Hola, O&#039;Neill &lt;b&gt;.', $html);
        $this->assertStringContainsString('href="https://zooki.test/index.php?action=a&amp;id=1&amp;token=t"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html, 'Sin doble escape del enlace.');
        $this->assertStringContainsString('<meta name="color-scheme" content="light" />', $html);
        $this->assertStringContainsString('bgcolor="#0052ff"', $html, 'El botón es una celda con su color.');
        $this->assertStringContainsString('max-width:600px;width:100%', $html, 'Ancho fluido en el celular.');
        $this->assertStringNotContainsString('margin:-15px', $html, 'Gmail no respeta márgenes negativos.');
        $this->assertStringContainsString('Si el botón no funciona, copia este enlace', $html);
    }
}
