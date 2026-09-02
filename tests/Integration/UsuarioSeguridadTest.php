<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Usuario.php';

/**
 * Cubre los dos hallazgos criticos de seguridad del analisis de vacios:
 *   VD-SEG-01  escalada de privilegios por id_rol sin validar
 *   VD-SEG-02  bloqueo total al desactivar/degradar al ultimo administrador
 */
class UsuarioSeguridadTest extends TestCase
{
    private $db;
    private $usuario;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("
            CREATE TABLE roles (
                id_rol INTEGER PRIMARY KEY,
                nombre_rol TEXT
            )
        ");
        $this->db->exec("
            INSERT INTO roles (id_rol, nombre_rol) VALUES
            (1,'administrador'), (2,'veterinario'), (3,'recepcionista'), (4,'propietario')
        ");
        $this->db->exec("
            CREATE TABLE usuarios (
                documento TEXT PRIMARY KEY,
                nombre_completo TEXT,
                email TEXT,
                id_rol INTEGER,
                estado INTEGER
            )
        ");

        $this->usuario = new Usuario($this->db);
    }

    private function crearUsuario(string $doc, int $rol, int $estado): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (documento, nombre_completo, email, id_rol, estado)
             VALUES (:d, :n, :e, :r, :s)"
        );
        $stmt->execute([
            ':d' => $doc, ':n' => 'Usuario ' . $doc, ':e' => $doc . '@zooki.test',
            ':r' => $rol, ':s' => $estado,
        ]);
    }

    // ── VD-SEG-01 : lista blanca de roles ──────────────────────────────

    public function testRolesExistentesSonValidos(): void
    {
        foreach ([1, 2, 3, 4] as $rol) {
            $this->assertTrue($this->usuario->esRolValido($rol), "El rol $rol deberia ser valido");
        }
    }

    public function testRolInexistenteEsRechazado(): void
    {
        $this->assertFalse($this->usuario->esRolValido(99));
        $this->assertFalse($this->usuario->esRolValido(0));
        $this->assertFalse($this->usuario->esRolValido(-1));
    }

    public function testRolNoNumericoONuloEsRechazado(): void
    {
        $this->assertFalse($this->usuario->esRolValido(null));
        $this->assertFalse($this->usuario->esRolValido('administrador'));
        $this->assertFalse($this->usuario->esRolValido(''));
    }

    // ── VD-SEG-02 : proteccion del ultimo administrador ────────────────

    public function testUnicoAdminActivoEstaProtegido(): void
    {
        $this->crearUsuario('admin1', 1, 1);
        $this->crearUsuario('vet1', 2, 1);

        $this->assertTrue($this->usuario->esUltimoAdminActivo('admin1'));
    }

    public function testConDosAdminsActivosNingunoEsElUltimo(): void
    {
        $this->crearUsuario('admin1', 1, 1);
        $this->crearUsuario('admin2', 1, 1);

        $this->assertFalse($this->usuario->esUltimoAdminActivo('admin1'));
        $this->assertFalse($this->usuario->esUltimoAdminActivo('admin2'));
    }

    public function testAdminInactivoNoCuentaComoRespaldo(): void
    {
        $this->crearUsuario('admin1', 1, 1);
        $this->crearUsuario('admin2', 1, 0); // desactivado: no puede entrar

        $this->assertTrue(
            $this->usuario->esUltimoAdminActivo('admin1'),
            'Un admin desactivado no debe contar como administrador disponible'
        );
    }

    public function testUsuarioNoAdminNuncaEstaProtegido(): void
    {
        $this->crearUsuario('admin1', 1, 1);
        $this->crearUsuario('vet1', 2, 1);

        $this->assertFalse($this->usuario->esUltimoAdminActivo('vet1'));
    }

    public function testAdminYaInactivoNoBloqueaLaOperacion(): void
    {
        $this->crearUsuario('admin1', 1, 0);

        $this->assertFalse($this->usuario->esUltimoAdminActivo('admin1'));
    }

    public function testDocumentoInexistenteNoRompe(): void
    {
        $this->assertFalse($this->usuario->esUltimoAdminActivo('no-existe'));
    }

    public function testContarAdminsActivosSoloCuentaAdminsHabilitados(): void
    {
        $this->crearUsuario('admin1', 1, 1);
        $this->crearUsuario('admin2', 1, 1);
        $this->crearUsuario('admin3', 1, 0);
        $this->crearUsuario('vet1', 2, 1);

        $this->assertSame(2, $this->usuario->contarAdminsActivos());
    }
}
