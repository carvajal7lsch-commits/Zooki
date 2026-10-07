<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../helpers/Contexto.php';

/**
 * HU-T.17 — Una persona, varios contextos (RE-T.17.1, RE-T.17.2, RE-T.17.5,
 * RN-G01, RN-G08).
 */
class ContextoTest extends TestCase
{
    private PDO $db;
    private Usuario $usuario;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->db = DosClinicas::sqlite();
        $this->usuario = new Usuario($this->db);
    }

    private function claves(?array $contextos): array
    {
        return array_column($contextos ?? [], 'clave');
    }

    /** RE-T.17.1: una sola identidad con roles de personal en dos clínicas y de propietario. */
    public function testUnaPersonaTieneSusRolesComoContextosSeparados(): void
    {
        $this->assertSame(
            ['clinica:1:2', 'clinica:2:1', 'propietario'],
            $this->claves($this->usuario->contextosDe(DosClinicas::DOBLE))
        );
    }

    /** RE-T.17.2: con un solo contexto no hay nada que elegir. */
    public function testElPersonalDeUnaSolaClinicaTieneUnContexto(): void
    {
        $contextos = $this->usuario->contextosDe(DosClinicas::ADMIN_NORTE);

        $this->assertSame(['clinica:1:1'], $this->claves($contextos));
        $this->assertSame('Clínica Norte', $contextos[0]['clinica']);
        $this->assertSame('admin_panel', Contexto::destino($contextos[0]));
    }

    public function testElPropietarioTieneSoloSuPortal(): void
    {
        $contextos = $this->usuario->contextosDe(DosClinicas::PROPIETARIO);

        $this->assertSame(['propietario'], $this->claves($contextos));
        $this->assertSame('portal_propietario', Contexto::destino($contextos[0]));
    }

    /** RE-T.17.5 / RN-G01: la plataforma no se combina con roles de clínica, aunque existieran por error. */
    public function testElSuperAdministradorSoloTieneLaPlataforma(): void
    {
        $this->db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (7, 1, 1, 'activo')");

        $contextos = $this->usuario->contextosDe(DosClinicas::SUPER_ADMIN);
        $this->assertSame(['plataforma'], $this->claves($contextos));
        $this->assertSame('plataforma_inicio', Contexto::destino($contextos[0]));
    }

    /** RN-G08: inactivar a la persona en una clínica le quita solo ese contexto. */
    public function testInactivarEnUnaClinicaNoTocaLosOtrosRoles(): void
    {
        (new Usuario($this->db))->cambiarEstadoEnClinica(DosClinicas::DOBLE, DosClinicas::SUR, false);

        $this->assertSame(['clinica:1:2', 'propietario'], $this->claves($this->usuario->contextosDe(DosClinicas::DOBLE)));
    }

    public function testUnaClinicaQueNoEstaActivaNoDaContexto(): void
    {
        $this->assertSame([], $this->usuario->contextosDe(DosClinicas::ADMIN_SUSPENDIDA));
    }

    public function testUnaCuentaInactivaOInexistenteNoTieneContextos(): void
    {
        $this->assertNull($this->usuario->contextosDe(DosClinicas::INACTIVO));
        $this->assertNull($this->usuario->contextosDe(999));
    }

    public function testActivarGuardaElContextoYElRolEnLaSesion(): void
    {
        $contextos = $this->usuario->contextosDe(DosClinicas::DOBLE);
        Contexto::iniciarIdentidad(DosClinicas::DOBLE, 'Elena Doble', false, 'password');
        Contexto::activar($contextos[1], count($contextos));

        $this->assertSame(DosClinicas::DOBLE, Contexto::idUsuario());
        $this->assertSame(DosClinicas::SUR, Contexto::clinicaActiva());
        $this->assertSame(1, Contexto::rolActivo());
        $this->assertSame(1, $_SESSION['usuario_id_rol'], 'El espejo para el código de C2–C9 es el rol del contexto');
        $this->assertTrue(Contexto::puedeCambiar());
        $this->assertSame('Clínica Sur · Administrador', Contexto::etiqueta($contextos[1]));
    }

    public function testElPortalNoTieneClinicaActiva(): void
    {
        Contexto::activar(Contexto::dePropietario(), 1);

        $this->assertNull(Contexto::clinicaActiva());
        $this->assertFalse(Contexto::puedeCambiar());
    }

    public function testIniciarOtraIdentidadQuitaElContextoAnterior(): void
    {
        Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', 1), 1);
        Contexto::iniciarIdentidad(DosClinicas::PROPIETARIO, 'Fabio', false, 'password');

        $this->assertNull(Contexto::actual());
        $this->assertArrayNotHasKey('usuario_id_rol', $_SESSION);
    }

    /** El selector solo acepta claves que existen entre los contextos vigentes. */
    public function testBuscarSoloEncuentraClavesDisponibles(): void
    {
        $contextos = $this->usuario->contextosDe(DosClinicas::DOBLE);

        $this->assertNotNull(Contexto::buscar($contextos, 'clinica:2:1'));
        $this->assertNull(Contexto::buscar($contextos, 'clinica:2:2'), 'Un rol que no tiene en esa clínica no se acepta');
        $this->assertNull(Contexto::buscar($contextos, 'plataforma'));
    }
}
