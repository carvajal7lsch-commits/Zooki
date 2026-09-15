<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Mascota.php';

/**
 * Modulo 1 — Reglas de catalogo y vinculo con el propietario.
 *
 * RN-101 (toda mascota tiene propietario), RN-105 (las inactivas no salen en
 * las busquedas) y RN-106 (la raza corresponde a la especie). Antes ninguna de
 * las tres se hacia cumplir en el backend: los ids llegaban crudos del POST.
 */
class MascotaCatalogoTest extends TestCase
{
    private PDO $db;
    private Mascota $modelo;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("CREATE TABLE especies (id_especie INTEGER PRIMARY KEY AUTOINCREMENT, nombre_especie TEXT)");
        $this->db->exec("CREATE TABLE razas (id_raza INTEGER PRIMARY KEY AUTOINCREMENT, id_especie INTEGER, nombre_raza TEXT)");
        $this->db->exec("CREATE TABLE usuarios (documento TEXT PRIMARY KEY, nombre_completo TEXT, id_rol INTEGER, estado INTEGER)");
        $this->db->exec("
            CREATE TABLE mascotas (
                id_mascota INTEGER PRIMARY KEY AUTOINCREMENT,
                numero_historia_clinica TEXT,
                doc_propietario TEXT,
                nombre TEXT,
                id_especie INTEGER,
                id_raza INTEGER,
                fecha_nacimiento TEXT NULL,
                peso REAL,
                sexo TEXT,
                color TEXT,
                url_foto TEXT NULL,
                estado INTEGER DEFAULT 1
            )
        ");

        $this->db->exec("CREATE TABLE colores_base (id_color INTEGER PRIMARY KEY AUTOINCREMENT, nombre_color TEXT)");
        $this->db->exec("CREATE TABLE mascota_colores (id_mascota INTEGER, id_color INTEGER)");

        $this->db->exec("INSERT INTO especies (id_especie, nombre_especie) VALUES (1,'Perro'), (2,'Gato')");
        $this->db->exec("INSERT INTO razas (id_raza, id_especie, nombre_raza) VALUES (10,1,'Labrador'), (20,2,'Siames')");
        $this->db->exec("
            INSERT INTO usuarios (documento, nombre_completo, id_rol, estado) VALUES
            ('111','Ana Propietaria',4,1),
            ('222','Vet Veterinario',2,1)
        ");

        $this->modelo = new Mascota($this->db);
    }

    // ── RN-106: la raza debe ser de la especie ───────────────────────────

    public function testUnaRazaDeLaEspecieCorrectaSeAcepta(): void
    {
        $this->assertTrue($this->modelo->razaPerteneceAEspecie(10, 1));
    }

    /** El caso que motivo el hallazgo: un gato de raza Labrador. */
    public function testUnaRazaDeOtraEspecieSeRechaza(): void
    {
        $this->assertFalse($this->modelo->razaPerteneceAEspecie(10, 2));
    }

    public function testUnaRazaInexistenteSeRechaza(): void
    {
        $this->assertFalse($this->modelo->razaPerteneceAEspecie(999, 1));
    }

    // ── RN-101: el propietario debe existir y tener ese rol ──────────────

    public function testSeAceptaUnPropietarioReal(): void
    {
        $this->assertTrue($this->modelo->esPropietarioValido('111'));
    }

    public function testUnDocumentoInexistenteNoEsPropietario(): void
    {
        $this->assertFalse($this->modelo->esPropietarioValido('999'));
    }

    /** Un veterinario existe, pero no puede figurar como dueno. */
    public function testUnUsuarioDeOtroRolNoEsPropietario(): void
    {
        $this->assertFalse($this->modelo->esPropietarioValido('222'));
    }

    // ── RN-106: el catalogo de razas no se llena de duplicados ───────────

    public function testObtenerOCrearRazaReutilizaLaExistente(): void
    {
        $id = $this->modelo->obtenerOCrearRaza(1, 'Labrador');

        $this->assertSame(10, $id);
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM razas")->fetchColumn());
    }

    /** La comparacion no distingue mayusculas, como ya hacian especies y colores. */
    public function testObtenerOCrearRazaIgnoraMayusculas(): void
    {
        $this->assertSame(10, $this->modelo->obtenerOCrearRaza(1, 'LABRADOR'));
        $this->assertSame(10, $this->modelo->obtenerOCrearRaza(1, '  labrador  '));
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM razas")->fetchColumn());
    }

    public function testObtenerOCrearRazaCreaLaNuevaSoloUnaVez(): void
    {
        $id = $this->modelo->obtenerOCrearRaza(1, 'Beagle');

        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, $this->modelo->obtenerOCrearRaza(1, 'Beagle'));
        $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM razas")->fetchColumn());
    }

    /** El mismo nombre en otra especie si es una raza distinta. */
    public function testElMismoNombreEnOtraEspecieEsOtraRaza(): void
    {
        $idGato = $this->modelo->obtenerOCrearRaza(2, 'Labrador');

        $this->assertNotSame(10, $idGato);
        $this->assertTrue($this->modelo->razaPerteneceAEspecie($idGato, 2));
    }

    // ── RN-105 y estado ──────────────────────────────────────────────────

    private function crearMascota(string $nombre, int $estado = 1): int
    {
        $this->db->prepare(
            "INSERT INTO mascotas (doc_propietario, nombre, id_especie, id_raza, peso, sexo, color, estado)
             VALUES ('111', :n, 1, 10, 5.5, 'M', '', :e)"
        )->execute([':n' => $nombre, ':e' => $estado]);

        return (int) $this->db->lastInsertId();
    }

    public function testGetEstadoDevuelveElEstadoReal(): void
    {
        $activa = $this->crearMascota('Firulais', 1);
        $baja   = $this->crearMascota('Rex', 0);

        $this->assertSame(1, $this->modelo->getEstado($activa));
        $this->assertSame(0, $this->modelo->getEstado($baja));
    }

    /**
     * Distinguir "no existe" de "existe e inactiva" es lo que permite que
     * cambiar el estado de una mascota inexistente deje de reportarse como
     * exito (M1-08).
     */
    public function testGetEstadoDevuelveNullSiLaMascotaNoExiste(): void
    {
        $this->assertNull($this->modelo->getEstado(4242));
    }

    /** RN-105: las inactivas no aparecen en la busqueda. */
    public function testLaBusquedaOmiteLasMascotasInactivas(): void
    {
        $this->crearMascota('Firulais', 1);
        $this->crearMascota('Firulinda', 0);

        $nombres = array_column($this->modelo->search('Firul'), 'nombre');

        $this->assertContains('Firulais', $nombres);
        $this->assertNotContains('Firulinda', $nombres);
    }

    /** RN-104: la mascota inactiva se conserva, no se borra. */
    public function testLaMascotaInactivaSigueExistiendo(): void
    {
        $id = $this->crearMascota('Rex', 1);

        $this->modelo->updateStatus($id, 0);

        $this->assertSame(0, $this->modelo->getEstado($id));
        $this->assertNotNull($this->modelo->getById($id), 'La ficha debe conservarse');
    }
}
