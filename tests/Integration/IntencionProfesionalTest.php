<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ClienteWompiFalso.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../helpers/InicioSesion.php';
require_once __DIR__ . '/../../helpers/EleccionPlanRegistro.php';
require_once __DIR__ . '/../../controllers/SuscripcionController.php';

/** HU-0.1/0.5: intención persistida, destino único y Gratis hasta el pago; sin HTTP a Wompi. */
final class IntencionProfesionalTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        $this->db->exec('CREATE TABLE intentos_login (identificador TEXT PRIMARY KEY,intentos INTEGER,primer_intento TEXT,ultimo_intento TEXT,bloqueado_hasta TEXT)');
        $this->db->exec('UPDATE planes SET precio_mensual=40000,precio_anual=400000 WHERE id_plan=2');
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Security::definirFuenteDeContextos(null);
        putenv('APP_URL=http://localhost/Zooki/public');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Security::definirFuenteDeContextos(null);
        putenv('APP_URL');
    }

    private function datos(array $eleccion = []): array
    {
        return $eleccion + ['nombre_clinica' => 'Clínica Elección', 'nit' => '901555123', 'direccion' => 'Carrera 10 número 20',
            'telefono_clinica' => '3001112233', 'email_clinica' => 'eleccion@zooki.test', 'nombre_completo' => 'Olga Nueva',
            'tipo_documento' => 'CC', 'documento' => '1030001234', 'telefono' => '3001112233', 'email' => 'olga@zooki.test',
            'password' => 'Huellita#Segura2026', 'confirm_password' => 'Huellita#Segura2026', 'acepta_datos' => '1'];
    }

    private function activar(array $eleccion): array
    {
        $registro = new RegistroClinica($this->db);
        $alta = $registro->registrar($this->datos($eleccion), '192.0.2.123');
        $fila = $registro->leer($alta['enlace']['id'], $alta['enlace']['token']);
        $clinica = (int) $fila['id_clinica_vinculo'];
        $this->assertSame('pendiente_verificacion', $this->db->query('SELECT estado FROM clinicas WHERE id_clinica=' . $clinica)->fetchColumn());
        $this->assertSame($eleccion['ciclo'] ?? null, $this->db->query('SELECT intencion_profesional_ciclo FROM clinicas WHERE id_clinica=' . $clinica)->fetchColumn());
        $registro->activar($alta['enlace']['id'], $alta['enlace']['token'], ['acepta_datos' => '1'], '192.0.2.123');
        $this->assertSame(1, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica=' . $clinica)->fetchColumn());
        return [$clinica, (new Usuario($this->db))->buscarPorId((int) $fila['id_usuario'])];
    }

    public function testRegistroProfesionalMensualYAnualSeConservanYRedirigenUnaSolaVez(): void
    {
        foreach (['mensual', 'anual'] as $ciclo) {
            $this->setUp();
            [$clinica, $usuario] = $this->activar(['plan' => 'profesional', 'ciclo' => $ciclo]);
            $sesion = new InicioSesion($this->db);
            $this->assertSame('admin_suscripcion&ciclo=' . $ciclo, $sesion->abrir($usuario, 'password'));
            $this->assertNotNull($this->db->query('SELECT intencion_profesional_mostrada_en FROM clinicas WHERE id_clinica=' . $clinica)->fetchColumn());
            $this->assertSame('admin_panel', $sesion->abrir($usuario, 'password'));
            $this->assertSame($ciclo, (new IntencionProfesional($this->db))->actual());
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM pagos_suscripcion')->fetchColumn());
        }
    }

    public function testSinEleccionMantieneElAccesoGratis(): void
    {
        [, $usuario] = $this->activar([]);
        $this->assertSame('admin_panel', (new InicioSesion($this->db))->abrir($usuario, 'password'));
        $this->assertNull((new IntencionProfesional($this->db))->actual());
    }

    public function testPlanYCicloInvalidosSeRechazanAntesDeCrearRegistros(): void
    {
        foreach ([['plan' => 'gratuito'], ['plan' => 'otro'], ['plan' => ['profesional']], ['plan' => 'profesional', 'ciclo' => 'semanal'], ['plan' => 'profesional', 'ciclo' => ['anual']], ['ciclo' => 'anual']] as $entrada) {
            try {
                (new RegistroClinica($this->db))->registrar($this->datos($entrada), '192.0.2.123');
                $this->fail('La elección inválida no puede crear la clínica.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame(3, (int) $this->db->query('SELECT COUNT(*) FROM clinicas')->fetchColumn());
                $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM verificaciones_email')->fetchColumn());
            }
        }
        $this->assertSame('mensual', EleccionPlanRegistro::ciclo(['plan' => 'profesional']));
    }

    private function contexto(int $clinica = 1, int $usuario = 1): void
    {
        $_SESSION = ['id_usuario' => $usuario];
        $contexto = Contexto::deClinica($clinica, 'Clínica', Roles::ADMIN);
        Contexto::activar($contexto, 1);
        Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
    }

    public function testDescartarEsIdempotenteYAisladoSinCambiarElPlan(): void
    {
        $this->db->exec("UPDATE clinicas SET intencion_profesional_ciclo='anual' WHERE id_clinica IN (1,2)");
        $this->contexto();
        $plan = $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica=1')->fetchColumn();
        $modelo = new IntencionProfesional($this->db);
        $modelo->descartar();
        $modelo->descartar();
        $this->assertNull($modelo->actual());
        $this->assertSame($plan, $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica=1')->fetchColumn());
        $this->assertSame('anual', $this->db->query('SELECT intencion_profesional_ciclo FROM clinicas WHERE id_clinica=2')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Continuar con el plan Gratis:%'")->fetchColumn());
    }

    public function testPagoAprobadoSuprimeLaIntencionYLaOferta(): void
    {
        $this->contexto();
        $this->db->exec("UPDATE clinicas SET intencion_profesional_ciclo='mensual' WHERE id_clinica=1");
        $this->db->exec("UPDATE clinicas SET id_plan=1 WHERE id_clinica=1");
        $this->db->exec("UPDATE suscripciones SET id_plan=1,gestion_cobro='gestionada' WHERE id_clinica=1");
        $pago = (new PagoSuscripcion($this->db, 1, ClienteWompiFalso::configuracion()))->reservar('mensual', 0, 1);
        $this->db->exec("UPDATE pagos_suscripcion SET estado='confirmado' WHERE id_clinica=1");
        $modelo = new IntencionProfesional($this->db);
        $this->assertNull($modelo->actual());
        $this->assertNull($modelo->primerAcceso());
        $this->assertTrue((new Suscripcion($this->db, 1))->tienePagosAprobados());
        $this->assertNull($this->db->query('SELECT intencion_profesional_mostrada_en FROM clinicas WHERE id_clinica=1')->fetchColumn());
    }

    public function testMultiplesContextosConservanLaEleccionHastaEntrarALaClinicaCorrecta(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica(id_usuario,id_clinica,id_rol,estado) VALUES (1,2,1,'activo')");
        $this->db->exec("UPDATE clinicas SET intencion_profesional_ciclo='mensual' WHERE id_clinica=1");
        $this->db->exec("UPDATE clinicas SET intencion_profesional_ciclo='anual' WHERE id_clinica=2");
        (new ConsentimientoDatos($this->db))->registrar(1, PoliticaDatos::MEDIO_FORMULARIO, '192.0.2.123');
        $usuario = (new Usuario($this->db))->buscarPorId(1);
        $this->assertSame('seleccionar_contexto', (new InicioSesion($this->db))->abrir($usuario, 'password'));
        $clave = Contexto::deClinica(2, 'Sur', Roles::ADMIN)['clave'];
        $this->assertSame('admin_suscripcion&ciclo=anual', (new ContextoController($this->db))->aplicarCambio($clave));
        $this->assertNull($this->db->query('SELECT intencion_profesional_mostrada_en FROM clinicas WHERE id_clinica=1')->fetchColumn());
    }

    public function testDescartarRechazaCsrfInvalidoSinCambiarLaIntencion(): void
    {
        $this->contexto();
        $this->db->exec("UPDATE clinicas SET intencion_profesional_ciclo='anual' WHERE id_clinica=1");
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'invalido'];
        try {
            (new SuscripcionController($this->db, null, new ConfiguracionWompi([])))->descartarIntencion();
            $this->fail('El servidor debe rechazar el token.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $this->assertSame('anual', $this->db->query('SELECT intencion_profesional_ciclo FROM clinicas WHERE id_clinica=1')->fetchColumn());
        http_response_code(200);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testSuscripcionPreseleccionaLaIntencionYSoloAdministradorPuedeDescartarla(): void
    {
        $this->contexto();
        $this->db->exec("UPDATE clinicas SET intencion_profesional_ciclo='anual' WHERE id_clinica=1");
        $datos = (new SuscripcionController($this->db, null, new ConfiguracionWompi([]), new ClienteWompiFalso()))->datosVista();
        $this->assertSame('anual', $datos['cicloPreseleccionado']);
        $this->assertSame('anual', $datos['intencionProfesional']);
        $this->assertSame('index.php?action=admin_configuracion&seccion=suscripcion&ciclo=anual#suscripcion', EnlaceAjustes::suscripcion(['ciclo' => 'anual']));
        Contexto::activar(Contexto::deClinica(1, 'Clínica', Roles::VETERINARIO), 1);
        $this->expectException(AccesoDenegado::class);
        (new IntencionProfesional($this->db))->descartar();
    }
}
