<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/Security.php';

/**
 * HU-T.10 — Autorizacion central por rol (RBAC), sobre el rol del contexto
 * activo (RN-G18).
 *
 * Se prueba la matriz accion -> roles, que es donde vive la regla. La
 * decision completa (sesion, contexto, clinica y CSRF) se prueba en
 * tests/Integration/SeguridadClinicaTest.php.
 */
class AutorizacionRolTest extends TestCase
{
    private const ADMIN       = Roles::ADMIN;
    private const VETERINARIO = Roles::VETERINARIO;
    private const PROPIETARIO = Roles::PROPIETARIO;
    private const SUPER_ADMIN = Roles::SUPER_ADMIN;

    /** Matriz real, leida por reflexion (es privada a proposito). */
    private static function matriz(): array
    {
        $metodo = new ReflectionMethod('Security', 'actionRoles');
        $metodo->setAccessible(true);

        return $metodo->invoke(null);
    }

    private static function lista(string $propiedad): array
    {
        $prop = new ReflectionProperty('Security', $propiedad);
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
     * Si alguien agrega una ruta al enrutador y olvida decidir quien la usa,
     * esto falla: toda accion es publica, sin contexto o de la matriz.
     */
    public function testTodaAccionDelEnrutadorEstaCubierta()
    {
        $cubiertas = array_merge(self::lista('publicActions'), self::lista('accionesSinContexto'), array_keys(self::matriz()));
        $sinCubrir = array_values(array_diff(self::accionesDelEnrutador(), $cubiertas));

        $this->assertSame([], $sinCubrir, 'Acciones sin roles asignados: ' . implode(', ', $sinCubrir));
    }

    /** Una accion no puede estar en dos listas a la vez: seria ambiguo. */
    public function testNingunaAccionEstaEnDosListas()
    {
        $publicas = self::lista('publicActions');
        $sinContexto = self::lista('accionesSinContexto');
        $matriz = array_keys(self::matriz());

        $this->assertSame([], array_values(array_intersect($publicas, $matriz)));
        $this->assertSame([], array_values(array_intersect($publicas, $sinContexto)));
        $this->assertSame([], array_values(array_intersect($sinContexto, $matriz)));
    }

    /** La matriz no debe acumular rutas que ya no existen. */
    public function testLaMatrizNoTieneAccionesFantasma()
    {
        $fantasma = array_diff(array_merge(array_keys(self::matriz()), self::lista('accionesSinContexto')), self::accionesDelEnrutador());

        $this->assertSame([], array_values($fantasma), 'Acciones inexistentes: ' . implode(', ', $fantasma));
    }

    /** B.5 / RE-T.11.2: en la matriz solo existen los roles 1, 2, 4 y 5; el 3 (recepcionista) ya no. */
    public function testLaMatrizSoloUsaLosRolesDeLaV2()
    {
        $roles = array_unique(array_merge(...array_values(self::matriz())));
        sort($roles);

        $this->assertSame([1, 2, 4, 5], array_values($roles));
    }

    public function testNoQuedanRutasDelRecepcionista()
    {
        $rutas = array_filter(self::accionesDelEnrutador(), fn ($a) => str_starts_with($a, 'reception_'));
        $this->assertSame([], array_values($rutas));
        $this->assertFalse(defined('Security::ROL_RECEPCIONISTA'));
    }

    public function testPropietarioNoAccedeAAdministracion()
    {
        foreach (['admin_usuarios', 'registrar_usuario_ajax', 'cambiar_estado_usuario_ajax', 'get_auditoria_ajax', 'guardar_horarios_clinica_ajax'] as $accion) {
            $this->assertFalse($this->permite($accion, self::PROPIETARIO), $accion);
        }
    }

    public function testPropietarioNoAccedeADatosClinicosNiDeOtrosDuenos()
    {
        foreach (['registrar_consulta_ajax', 'registrar_vacuna_ajax', 'listar_historial_ajax', 'listar_propietarios_ajax', 'listar_mascotas_ajax'] as $accion) {
            $this->assertFalse($this->permite($accion, self::PROPIETARIO), $accion);
        }
    }

    /** RN-201: solo el veterinario registra actos clinicos; ni el administrador. */
    public function testSoloElVeterinarioRegistraActosClinicos()
    {
        foreach ([
            'registrar_consulta_ajax', 'registrar_vacuna_ajax', 'registrar_desparasitacion_ajax',
            'registrar_nueva_vacuna_ajax', 'registrar_nuevo_laboratorio_ajax',
            'registrar_nuevo_producto_desparasitacion_ajax',
        ] as $accion) {
            $this->assertTrue($this->permite($accion, self::VETERINARIO), "$accion deberia permitir al veterinario");
            foreach ([self::ADMIN, self::PROPIETARIO, self::SUPER_ADMIN] as $rol) {
                $this->assertFalse($this->permite($accion, $rol), "$accion no deberia permitir al rol $rol");
            }
        }
    }

    /** RN-407: atender una cita es del veterinario. */
    public function testSoloElVeterinarioAtiendeLasCitas()
    {
        foreach (['iniciar_cita_ajax', 'completar_cita_ajax', 'marcar_no_asistio_ajax', 'cerrar_sin_consulta_ajax', 'vet_atencion'] as $accion) {
            $this->assertTrue($this->permite($accion, self::VETERINARIO), "$accion deberia permitir al veterinario");
            foreach ([self::ADMIN, self::PROPIETARIO, self::SUPER_ADMIN] as $rol) {
                $this->assertFalse($this->permite($accion, $rol), "$accion no deberia permitir al rol $rol");
            }
        }
    }

    /** RE-T.7.2 / RE-T.11.1: solo el administrador de la clinica gestiona su personal. */
    public function testSoloElAdministradorGestionaUsuarios()
    {
        foreach ([
            'registrar_usuario_ajax', 'actualizar_usuario_ajax', 'cambiar_estado_usuario_ajax',
            'get_usuario_ajax', 'resetear_password_usuario_ajax', 'verificar_documento_ajax', 'verificar_email_ajax',
        ] as $accion) {
            $this->assertTrue($this->permite($accion, self::ADMIN), "$accion deberia permitir al admin");
            foreach ([self::VETERINARIO, self::PROPIETARIO, self::SUPER_ADMIN] as $rol) {
                $this->assertFalse($this->permite($accion, $rol), "$accion no deberia permitir al rol $rol");
            }
        }
    }

    /** El portal es del dueno: el personal no entra por ahi (RN-G18). */
    public function testElPersonalNoEntraAlPortalDelPropietario()
    {
        foreach (['portal_propietario', 'portal_agendar_cita_ajax', 'ver_detalle_mascota_propietario_ajax'] as $accion) {
            $this->assertTrue($this->permite($accion, self::PROPIETARIO));
            foreach ([self::ADMIN, self::VETERINARIO, self::SUPER_ADMIN] as $rol) {
                $this->assertFalse($this->permite($accion, $rol), "$accion no deberia permitir al rol $rol");
            }
        }
    }

    /** RE-T.15.4 / RN-004: el super-administrador solo tiene la plataforma y su inicio. */
    public function testElSuperAdministradorSoloTieneLaPlataforma()
    {
        $permitidas = array_keys(array_filter(self::matriz(), fn ($roles) => in_array(self::SUPER_ADMIN, $roles, true)));
        sort($permitidas);

        $this->assertSame(['dashboard', 'plataforma_inicio'], $permitidas);
    }

    /** Contrapeso: cada rol conserva lo que usa a diario. */
    public function testCadaRolConservaSusAccionesHabituales()
    {
        $this->assertTrue($this->permite('admin_usuarios', self::ADMIN));
        $this->assertTrue($this->permite('get_auditoria_ajax', self::ADMIN));
        $this->assertTrue($this->permite('registrar_cita_ajax', self::ADMIN));

        $this->assertTrue($this->permite('vet_agenda', self::VETERINARIO));
        $this->assertTrue($this->permite('registrar_consulta_ajax', self::VETERINARIO));
        $this->assertTrue($this->permite('guardar_mascota_ajax', self::VETERINARIO));

        $this->assertTrue($this->permite('portal_propietario', self::PROPIETARIO));
        $this->assertTrue($this->permite('portal_agendar_cita_ajax', self::PROPIETARIO));
    }

    /** HU-T.5: "Mi perfil" es del personal; el propietario usa el suyo en el portal. */
    public function testElPanelMiPerfilEsDelPersonal()
    {
        $this->assertTrue($this->permite('mi_perfil', self::ADMIN));
        $this->assertTrue($this->permite('mi_perfil', self::VETERINARIO));
        $this->assertFalse($this->permite('mi_perfil', self::PROPIETARIO));
    }

    /** Endpoints que el portal comparte con el personal (catalogos, agenda, avisos). */
    public function testLosEndpointsCompartidosSiguenAbiertosAlPersonalYAlPortal()
    {
        foreach ([
            'listar_especies_ajax', 'listar_razas_ajax', 'listar_colores_ajax',
            'get_horas_disponibles_ajax', 'get_sugerencias_horario_ajax',
            'cancelar_cita_ajax', 'enviar_email_ajax', 'get_notificaciones_ajax', 'dashboard',
        ] as $accion) {
            foreach ([self::ADMIN, self::VETERINARIO, self::PROPIETARIO] as $rol) {
                $this->assertTrue($this->permite($accion, $rol), "$accion deberia permitir al rol $rol");
            }
        }
    }

    /** HU-T.17: elegir el contexto, salir y cambiar la contrasena solo exigen identidad. */
    public function testLasAccionesSinContextoSonLasMinimas()
    {
        $sinContexto = self::lista('accionesSinContexto');
        sort($sinContexto);

        $this->assertSame(['cambiar_contexto', 'cambiar_password', 'cambiar_password_ajax', 'logout', 'seleccionar_contexto'], $sinContexto);
    }

    /** El login y el registro no pueden quedar detras del control de rol. */
    public function testElFlujoPublicoNoQuedaRestringido()
    {
        $publicas = self::lista('publicActions');

        foreach (['login', 'register', 'process_register', 'google_login_ajax', 'check_document_ajax', 'solicitar_reset_password_ajax'] as $accion) {
            $this->assertContains($accion, $publicas, "$accion deberia seguir siendo publica");
        }
    }

    /** T-05: con una contrasena temporal pendiente solo se puede cambiarla o salir. */
    public function testConPasswordTemporalSoloSePuedeCambiarlaOSalir()
    {
        $permitidas = self::lista('accionesConPasswordTemporal');
        sort($permitidas);

        $this->assertSame(['cambiar_password', 'cambiar_password_ajax', 'logout'], $permitidas);
    }
}
