<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../models/InvitacionPersonal.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';

/**
 * F0 — Preparación del corte de producción.
 *
 *   RN-G15 / RE-T.13.4  la IP real atraviesa nginx del host → Traefik → Apache
 *   B.5                 Apache sirve solo public/ y la imagen no lleva credenciales ni pruebas
 *   Modelos §10.1       un enlace auténtico de una invitación reemplazada lo dice; los demás, mensaje genérico
 */
final class F0CorteTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../..';
    private const REEMPLAZADA = 'Esta invitación se reemplazó por una más reciente. Usa el enlace del último correo.';
    private const GENERICO_INVITACION = 'La invitación no es válida, venció o ya fue utilizada.';
    private const GENERICO_ACTIVACION = 'El enlace no es válido, venció o ya fue utilizado.';

    private PDO $db;
    private object $correo;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $this->db->exec('CREATE TABLE intentos_login (id_intento INTEGER PRIMARY KEY AUTOINCREMENT, identificador TEXT UNIQUE, intentos INTEGER, bloqueado_hasta TEXT, primer_intento TEXT, ultimo_intento TEXT)');
        Security::definirAuditoria(new Auditoria($this->db));
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_POST = [];
        $_GET = [];
        $this->correo = new class {
            public array $enviados = [];
            public function limpiarDirecciones(): void {}
            public function enviarInvitacionPersonal(...$d) { $this->enviados[] = ['activacion', ...$d]; return true; }
            public function enviarInvitacionClinica(...$d) { $this->enviados[] = ['invitacion', ...$d]; return true; }
            public function enviarRestablecimientoPorAdministrador(...$d) { $this->enviados[] = ['restablecimiento', ...$d]; return true; }
        };
    }

    protected function tearDown(): void
    {
        Security::definirAuditoria(false);
        Security::definirAlmacenDeIntentos(null);
        Auditoria::definirProxiesConfiables(null);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    // ── RN-G15: IP real detrás de nginx → Traefik → Apache ──────────────

    /**
     * El cliente 203.0.113.7 inventa «6.6.6.6»; nginx agrega la IP real; Traefik
     * agrega la de nginx tal como la ve (10.0.1.1, la puerta de enlace de
     * dokploy-network) y Apache recibe la conexión desde Traefik (10.0.1.5).
     */
    public function testLaIpRealAtraviesaLosDosSaltosDeProduccion(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.1.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 203.0.113.7, 10.0.1.1';

        Auditoria::definirProxiesConfiables(['10.0.1.0/24']);
        $this->assertSame('203.0.113.7', Auditoria::ipCliente(), 'Con el rango de dokploy-network vale la IP del cliente.');

        Auditoria::definirProxiesConfiables(['10.0.1.5']);
        $this->assertSame('10.0.1.1', Auditoria::ipCliente(), 'Confiando solo en Traefik, todos se verían con la IP de nginx.');

        Auditoria::definirProxiesConfiables([]);
        $this->assertSame('10.0.1.5', Auditoria::ipCliente(), 'Sin TRUSTED_PROXIES todos se verían con la IP de Traefik.');

        // Si nginx no llega desde el rango de Traefik, se confía también en su dirección exacta.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 172.17.0.1';
        Auditoria::definirProxiesConfiables(['10.0.1.0/24', '172.17.0.1']);
        $this->assertSame('203.0.113.7', Auditoria::ipCliente());
    }

    // ── B.5: Apache y la imagen ─────────────────────────────────────────

    public function testApacheSirveSoloPublicSinCambiarLasUrl(): void
    {
        $conf = (string) file_get_contents(self::RAIZ . '/docker/apache-zooki.conf');
        $this->assertMatchesRegularExpression('/^\s*DocumentRoot \/var\/www\/html\/Zooki\/public\s*$/m', $conf);
        $this->assertMatchesRegularExpression('/^\s*Alias \/Zooki\/public \/var\/www\/html\/Zooki\/public\s*$/m', $conf, 'Un proxy que apunte a /Zooki/public/ sigue sirviendo.');
        $this->assertMatchesRegularExpression('/<Directory \/var\/www\/html>\s*Options None\s*AllowOverride None\s*Require all denied/', $conf);
        $this->assertMatchesRegularExpression('/<Directory \/var\/www\/html\/Zooki\/public>\s*Options -Indexes/', $conf);
        $this->assertStringContainsString('<FilesMatch "^\.">', $conf);
        $this->assertStringContainsString("<Directory /var/www/html/Zooki/public/uploads/clinicos>\n        Require all denied", str_replace("\r\n", "\n", $conf), 'Revisión F0: los adjuntos no dependen de un .htaccess del volumen.');

        $dockerfile = (string) file_get_contents(self::RAIZ . '/Dockerfile');
        $this->assertStringContainsString('COPY docker/apache-zooki.conf /etc/apache2/sites-available/000-default.conf', $dockerfile);
        $this->assertFileExists(self::RAIZ . '/public/index.php');
        $this->assertFileExists(self::RAIZ . '/public/ver_archivo.php');
        $this->assertFileExists(self::RAIZ . '/public/docs/index.php');
    }

    public function testLaImagenNoLlevaCredencialesNiPruebasYConservaElPortal(): void
    {
        $lineas = array_map('trim', file(self::RAIZ . '/.dockerignore', FILE_IGNORE_NEW_LINES));
        foreach (['.env', '.git', 'tests', 'scratch', 'logs', 'Claude outputs', 'node_modules', 'vendor', 'public/uploads/*',
            'documentacion/AnalisisVaciosDiseno.md', 'specs', 'agentes'] as $fuera) {
            $this->assertContains($fuera, $lineas, "$fuera no debe entrar en la imagen");
        }
        foreach (['documentacion', 'README.md', 'database', 'scripts', 'docker', 'public', 'config', 'helpers', 'models', 'controllers', 'views', 'composer.json'] as $dentro) {
            $this->assertNotContains($dentro, $lineas, "$dentro lo necesita la aplicación o el portal");
        }
        // La aplicación lee el .env como archivo: fuera de la imagen, se monta al arrancar (decisión del usuario, 2026-10-11).
        $compose = (string) file_get_contents(self::RAIZ . '/docker-compose.yml');
        $this->assertStringContainsString('- ./.env:/var/www/html/Zooki/.env:ro', $compose);
        $this->assertStringContainsString("\$envFile = __DIR__ . '/../.env';", (string) file_get_contents(self::RAIZ . '/config/Database.php'));
        // scripts/algolia_index.php y el portal leen README.md y documentacion/ (DocsIndex).
        $this->assertStringContainsString("'/README.md'", (string) file_get_contents(self::RAIZ . '/helpers/DocsIndex.php'));
    }

    // ── Invitación reemplazada (decisión del usuario, 2026-10-11) ──────

    private function comoAdminNorte(): UsuarioController
    {
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::ADMIN), 1);
        return new UsuarioController($this->db, $this->correo);
    }

    private function json(callable $accion): array
    {
        ob_start();
        try {
            $accion();
        } finally {
            $salida = (string) ob_get_clean();
        }
        return json_decode($salida, true) ?? [];
    }

    private function invitar(array $datos): void
    {
        $controlador = $this->comoAdminNorte();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $datos + ['tipo_documento' => 'CC', 'documento' => '1000000077', 'nombre_completo' => 'Persona Escrita',
            'email' => 'escrita@zooki.test', 'telefono' => '3001112233', 'id_rol' => '2', 'estado' => '1'];
        $this->assertTrue($this->json(fn () => $controlador->registrarAjax())['success']);
    }

    private function reenviarPendientes(): void
    {
        $controlador = $this->comoAdminNorte();
        foreach (array_column($controlador->listarInvitaciones(), 'id_invitacion') as $id) {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['id_invitacion' => (string) $id];
            $this->assertTrue($this->json(fn () => $controlador->reenviarInvitacionAjax())['success']);
        }
    }

    /** id y token de cada enlace enviado de esa clase, en orden. */
    private function enlaces(string $clase): array
    {
        $enlaces = [];
        foreach ($this->correo->enviados as $envio) {
            if ($envio[0] !== $clase) {
                continue;
            }
            parse_str((string) parse_url($envio[5], PHP_URL_QUERY), $consulta);
            $enlaces[] = ['id' => (int) $consulta['id'], 'token' => (string) $consulta['token']];
        }
        return $enlaces;
    }

    /** Abre la página del enlace; GET por defecto (solo mira). */
    private function abrir(string $accion, array $enlace, string $metodo = 'GET', array $extra = []): string
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = $metodo;
        $_GET = $metodo === 'GET' ? ['id' => (string) $enlace['id'], 'token' => $enlace['token']] : [];
        $_POST = $metodo === 'POST' ? ['id' => (string) $enlace['id'], 'token' => $enlace['token']] + $extra : [];
        ob_start();
        try {
            $controlador = new IdentidadController($this->db, $this->correo);
            $accion === 'invitacion_personal' ? $controlador->invitacion() : $controlador->enlace('activacion_personal');
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    private function filas(): array
    {
        return $this->db->query('SELECT id, used, expires_at FROM verificaciones_email ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testUnaInvitacionReemplazadaLoDiceSinCambiarNada(): void
    {
        $this->invitar(['documento' => '1000000006', 'email' => 'fabio@zooki.test']);
        $this->reenviarPendientes();
        [$vieja, $nueva] = $this->enlaces('invitacion');
        $antes = $this->filas();

        $html = $this->abrir('invitacion_personal', $vieja);

        $this->assertStringContainsString(self::REEMPLAZADA, $html);
        $this->assertStringNotContainsString('Aceptar la invitación', $html);
        $this->assertSame($antes, $this->filas(), 'El GET no cambia nada.');
        $this->assertStringContainsString('Aceptar la invitación', $this->abrir('invitacion_personal', $nueva), 'El último correo sigue sirviendo.');
    }

    public function testElPostSobreUnaInvitacionReemplazadaNoDecideNada(): void
    {
        $this->invitar(['documento' => '1000000006', 'email' => 'fabio@zooki.test']);
        $this->reenviarPendientes();
        [$vieja] = $this->enlaces('invitacion');

        $html = $this->abrir('invitacion_personal', $vieja, 'POST', ['decision' => 'aceptar']);

        $this->assertStringContainsString(self::REEMPLAZADA, $html);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM usuario_clinica WHERE id_usuario = 6')->fetchColumn());
    }

    public function testUnTokenIncorrectoDaElMensajeGenericoSinRevelarNada(): void
    {
        $this->invitar(['documento' => '1000000006', 'email' => 'fabio@zooki.test']);
        $this->reenviarPendientes();
        [$vieja] = $this->enlaces('invitacion');

        $html = $this->abrir('invitacion_personal', ['id' => $vieja['id'], 'token' => str_repeat('0', 64)]);

        $this->assertStringContainsString(self::GENERICO_INVITACION, $html);
        $this->assertStringNotContainsString('reemplazó', $html);
    }

    public function testUnaInvitacionVencidaDaElMensajeGenerico(): void
    {
        $this->invitar(['documento' => '1000000006', 'email' => 'fabio@zooki.test']);
        $this->reenviarPendientes();
        [$vieja] = $this->enlaces('invitacion');
        // La más reciente también venció: ya no hay otra vigente que ofrecer.
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00'");

        $this->assertStringContainsString(self::GENERICO_INVITACION, $this->abrir('invitacion_personal', $vieja));
        $nueva = $this->enlaces('invitacion')[1];
        $this->assertStringContainsString(self::GENERICO_INVITACION, $this->abrir('invitacion_personal', $nueva));
    }

    public function testUnaInvitacionAceptadaDaElMensajeGenerico(): void
    {
        $this->invitar(['documento' => '1000000006', 'email' => 'fabio@zooki.test']);
        $this->reenviarPendientes();
        [$vieja, $nueva] = $this->enlaces('invitacion');
        $this->assertStringContainsString('Bienvenido al equipo', $this->abrir('invitacion_personal', $nueva, 'POST', ['decision' => 'aceptar']));

        $this->assertStringContainsString(self::GENERICO_INVITACION, $this->abrir('invitacion_personal', $nueva));
        $this->assertStringContainsString(self::GENERICO_INVITACION, $this->abrir('invitacion_personal', $vieja), 'Ya no hay otra vigente.');
    }

    /** La misma regla en la activación de una cuenta nueva reenviada. */
    public function testUnaActivacionReemplazadaTambienLoDice(): void
    {
        $this->invitar([]);
        $this->reenviarPendientes();
        [$vieja, $nueva] = $this->enlaces('activacion');
        $antes = $this->filas();

        $this->assertStringContainsString(self::REEMPLAZADA, $this->abrir('activar_personal', $vieja));
        $this->assertSame($antes, $this->filas());
        $this->assertStringContainsString(self::GENERICO_ACTIVACION, $this->abrir('activar_personal', ['id' => $vieja['id'], 'token' => str_repeat('0', 64)]));
        $this->assertStringContainsString('Aceptar y activar mi cuenta', $this->abrir('activar_personal', $nueva));
    }

    /** Otra persona u otra clínica no cuentan como «la más reciente». */
    public function testSoloCuentaUnaInvitacionDeLaMismaPersonaYClinica(): void
    {
        $this->invitar(['documento' => '1000000006', 'email' => 'fabio@zooki.test']);
        [$fabio] = $this->enlaces('invitacion');
        $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id = ?')->execute([$fabio['id']]);
        $this->invitar(['documento' => '1000000009', 'email' => 'iris@zooki.test']);

        $this->assertFalse((new InvitacionPersonal($this->db))->reemplazada($fabio['id'], $fabio['token'], InvitacionPersonal::PROPOSITO));
        $this->assertStringContainsString(self::GENERICO_INVITACION, $this->abrir('invitacion_personal', $fabio));
    }
}
