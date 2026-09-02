<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Security.php';

/**
 * HU-32 — Autorizacion central por rol (RBAC real), origen VD-SEG-03/04.
 *
 * Se prueba la decision de autorizacion (la matriz accion -> roles), no el
 * corte de la peticion: validateRole() termina en exit() cuando deniega, y
 * exit() no se puede atrapar desde PHPUnit sin matar al propio runner.
 * La matriz es donde vive la regla, asi que es lo que hay que blindar.
 */
class AutorizacionRolTest extends TestCase
{
    private const ADMIN         = 1;
    private const VETERINARIO   = 2;
    private const RECEPCIONISTA = 3;
    private const PROPIETARIO   = 4;

    /** Matriz real, leida por reflexion (es privada a proposito). */
    private static function matriz(): array
    {
        $metodo = new ReflectionMethod('Security', 'actionRoles');
        $metodo->setAccessible(true);

        return $metodo->invoke(null);
    }

    private static function publicas(): array
    {
        $prop = new ReflectionProperty('Security', 'publicActions');
        $prop->setAccessible(true);

        return $prop->getValue();
    }

    /** Acciones declaradas en el switch del front controller. */
    private static function accionesDelEnrutador(): array
    {
        $router = file_get_contents(__DIR__ . '/../../public/index.php');
        preg_match_all('/^\s*case\s+"([a-z_0-9]+)"/m', $router, $m);

        return array_values(array_unique($m[1]));
    }

    private function permite(string $accion, int $rol): bool
    {
        $matriz = self::matriz();
        $this->assertArrayHasKey($accion, $matriz, "La accion '$accion' no esta en la matriz");

        return in_array($rol, $matriz[$accion], true);
    }

    /**
     * Criterio de aceptacion: "Se cubren todos los endpoints AJAX".
     * Este es el test que importa a largo plazo: si alguien agrega una ruta
     * al enrutador y olvida asignarle roles, esto falla.
     */
    public function testTodaAccionDelEnrutadorEstaCubierta()
    {
        $matriz = self::matriz();
        $publicas = self::publicas();
        $sinCubrir = [];

        foreach (self::accionesDelEnrutador() as $accion) {
            if (in_array($accion, $publicas, true)) continue;
            if (isset($matriz[$accion])) continue;
            $sinCubrir[] = $accion;
        }

        $this->assertSame([], $sinCubrir, 'Acciones sin roles asignados: ' . implode(', ', $sinCubrir));
    }

    /** Una accion publica no puede estar tambien en la matriz: seria ambiguo. */
    public function testNingunaAccionEsPublicaYRestringidaALaVez()
    {
        $ambas = array_intersect(self::publicas(), array_keys(self::matriz()));

        $this->assertSame([], array_values($ambas), 'Acciones contradictorias: ' . implode(', ', $ambas));
    }

    /** La matriz no debe acumular rutas que ya no existen. */
    public function testLaMatrizNoTieneAccionesFantasma()
    {
        $fantasma = array_diff(array_keys(self::matriz()), self::accionesDelEnrutador());

        $this->assertSame([], array_values($fantasma), 'Acciones inexistentes: ' . implode(', ', $fantasma));
    }

    public function testPropietarioNoAccedeAAdministracion()
    {
        $this->assertFalse($this->permite('admin_usuarios', self::PROPIETARIO));
        $this->assertFalse($this->permite('registrar_usuario_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('cambiar_estado_usuario_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('get_auditoria_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('guardar_horarios_clinica_ajax', self::PROPIETARIO));
    }

    public function testPropietarioNoAccedeADatosClinicosNiDeOtrosDuenos()
    {
        $this->assertFalse($this->permite('registrar_consulta_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('registrar_vacuna_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('listar_historial_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('listar_propietarios_ajax', self::PROPIETARIO));
        $this->assertFalse($this->permite('listar_mascotas_ajax', self::PROPIETARIO));
    }

    /**
     * RN-201: "Solo el rol Veterinario puede registrar consultas clinicas".
     * Ni recepcion ni el administrador: la regla no admite excepciones, y la
     * matriz debe decir lo mismo que ya hacia la comprobacion del enrutador.
     */
    public function testSoloElVeterinarioRegistraActosClinicos()
    {
        foreach ([
            'registrar_consulta_ajax', 'registrar_vacuna_ajax', 'registrar_desparasitacion_ajax',
            'registrar_nueva_vacuna_ajax', 'registrar_nuevo_laboratorio_ajax',
            'registrar_nuevo_producto_desparasitacion_ajax',
        ] as $accion) {
            $this->assertTrue($this->permite($accion, self::VETERINARIO), "$accion deberia permitir al veterinario");
            $this->assertFalse($this->permite($accion, self::ADMIN), "$accion no deberia permitir al admin (RN-201)");
            $this->assertFalse($this->permite($accion, self::RECEPCIONISTA), "$accion no deberia permitir a recepcion");
            $this->assertFalse($this->permite($accion, self::PROPIETARIO), "$accion no deberia permitir al propietario");
        }
    }

    public function testSoloElAdministradorGestionaUsuarios()
    {
        foreach (['registrar_usuario_ajax', 'actualizar_usuario_ajax', 'cambiar_estado_usuario_ajax', 'get_usuario_ajax'] as $accion) {
            $this->assertTrue($this->permite($accion, self::ADMIN), "$accion deberia permitir al admin");
            $this->assertFalse($this->permite($accion, self::VETERINARIO), "$accion no deberia permitir al veterinario");
            $this->assertFalse($this->permite($accion, self::RECEPCIONISTA), "$accion no deberia permitir a recepcion");
            $this->assertFalse($this->permite($accion, self::PROPIETARIO), "$accion no deberia permitir al propietario");
        }
    }

    /** El portal es del dueno: el personal de la clinica no entra por ahi. */
    public function testElPersonalNoEntraAlPortalDelPropietario()
    {
        foreach (['portal_propietario', 'portal_agendar_cita_ajax', 'ver_detalle_mascota_propietario_ajax'] as $accion) {
            $this->assertTrue($this->permite($accion, self::PROPIETARIO));
            $this->assertFalse($this->permite($accion, self::ADMIN));
            $this->assertFalse($this->permite($accion, self::VETERINARIO));
            $this->assertFalse($this->permite($accion, self::RECEPCIONISTA));
        }
    }

    /**
     * Contrapeso de los tests anteriores: la matriz tiene que seguir dejando
     * pasar lo que cada rol usa a diario. Un RBAC que rompe la operacion no
     * sirve de nada.
     */
    public function testCadaRolConservaSusAccionesHabituales()
    {
        $this->assertTrue($this->permite('admin_usuarios', self::ADMIN));
        $this->assertTrue($this->permite('get_auditoria_ajax', self::ADMIN));

        $this->assertTrue($this->permite('vet_agenda', self::VETERINARIO));
        $this->assertTrue($this->permite('registrar_consulta_ajax', self::VETERINARIO));
        $this->assertTrue($this->permite('listar_historial_ajax', self::VETERINARIO));

        $this->assertTrue($this->permite('reception_agenda', self::RECEPCIONISTA));
        $this->assertTrue($this->permite('registrar_cita_ajax', self::RECEPCIONISTA));
        $this->assertTrue($this->permite('guardar_mascota_ajax', self::RECEPCIONISTA));

        $this->assertTrue($this->permite('portal_propietario', self::PROPIETARIO));
        $this->assertTrue($this->permite('portal_agendar_cita_ajax', self::PROPIETARIO));
    }

    /**
     * Endpoints que el portal comparte con el personal (catalogos y agenda).
     * Cerrarlos por rol romperia el agendamiento del propietario en produccion.
     */
    public function testLosEndpointsCompartidosSiguenAbiertosATodoRolConSesion()
    {
        foreach ([
            'listar_especies_ajax', 'listar_razas_ajax', 'listar_colores_ajax',
            'get_horas_disponibles_ajax', 'get_sugerencias_horario_ajax',
            'cancelar_cita_ajax', 'enviar_email_ajax', 'get_notificaciones_ajax',
            'cambiar_password_ajax', 'dashboard', 'logout',
        ] as $accion) {
            foreach ([self::ADMIN, self::VETERINARIO, self::RECEPCIONISTA, self::PROPIETARIO] as $rol) {
                $this->assertTrue($this->permite($accion, $rol), "$accion deberia permitir al rol $rol");
            }
        }
    }

    /** El login y el registro no pueden quedar detras del control de rol. */
    public function testElFlujoPublicoNoQuedaRestringido()
    {
        $publicas = self::publicas();

        foreach (['login', 'register', 'process_register', 'google_login_ajax', 'check_document_ajax', 'solicitar_reset_password_ajax'] as $accion) {
            $this->assertContains($accion, $publicas, "$accion deberia seguir siendo publica");
        }
    }

    /**
     * Sesiones abiertas antes de este despliegue pueden no tener el id de rol
     * guardado; el respaldo por nombre evita expulsarlas a todas.
     */
    public function testElRolSeResuelvePorNombreCuandoFaltaElId()
    {
        $metodo = new ReflectionMethod('Security', 'rolActual');
        $metodo->setAccessible(true);

        $_SESSION = ['usuario_rol' => 'veterinario'];
        $this->assertSame(self::VETERINARIO, $metodo->invoke(null));

        $_SESSION = ['usuario_id_rol' => 3, 'usuario_rol' => 'veterinario'];
        $this->assertSame(self::RECEPCIONISTA, $metodo->invoke(null), 'El id debe ganarle al nombre');

        $_SESSION = [];
        $this->assertNull($metodo->invoke(null), 'Sin sesion no hay rol');

        $_SESSION = ['usuario_rol' => 'inventado'];
        $this->assertNull($metodo->invoke(null), 'Un rol desconocido no se resuelve');
    }
}
