<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Mascota.php';

/**
 * HU-35 — "Se valida que la mascota exista" y "se verifica que el actor tenga
 * permiso sobre esa mascota" (VD-VAC-01, VD-HC-03).
 *
 * getPropietarioSiActiva() es la comprobacion que usan los tres registros
 * clinicos antes de insertar: responde en una sola consulta si la mascota
 * existe, si sigue activa y de quien es (lo que necesita RN-G02 en el portal).
 */
class MascotaAccesoTest extends TestCase
{
    private $db;
    private $mascota;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("
            CREATE TABLE mascotas (
                id_mascota INTEGER PRIMARY KEY,
                numero_historia_clinica TEXT,
                doc_propietario TEXT,
                id_especie INTEGER,
                id_raza INTEGER,
                nombre TEXT,
                fecha_nacimiento TEXT,
                peso REAL,
                sexo TEXT,
                color TEXT,
                estado INTEGER DEFAULT 1,
                url_foto TEXT,
                patron TEXT
            )
        ");

        $this->crearMascota(1, '1080361993', 1);  // activa
        $this->crearMascota(2, '12345678', 1);    // activa, de otro dueno
        $this->crearMascota(3, '1080361993', 0);  // inactiva (baja logica)

        $this->mascota = new Mascota($this->db);
    }

    private function crearMascota(int $id, string $doc, int $estado): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mascotas (id_mascota, doc_propietario, nombre, peso, estado)
             VALUES (:id, :doc, 'Firulais', 5.0, :estado)"
        );
        $stmt->execute([':id' => $id, ':doc' => $doc, ':estado' => $estado]);
    }

    public function testDevuelveElPropietarioDeUnaMascotaActiva()
    {
        $this->assertSame('1080361993', $this->mascota->getPropietarioSiActiva(1));
        $this->assertSame('12345678', $this->mascota->getPropietarioSiActiva(2));
    }

    /** El caso que abria el hueco: un id_mascota que no existe. */
    public function testDevuelveNullSiLaMascotaNoExiste()
    {
        $this->assertNull($this->mascota->getPropietarioSiActiva(999));
        $this->assertNull($this->mascota->getPropietarioSiActiva(0));
        $this->assertNull($this->mascota->getPropietarioSiActiva(-1));
    }

    /**
     * RN-206 deja la historia clinica intacta, pero una mascota dada de baja
     * no debe seguir recibiendo registros nuevos.
     */
    public function testDevuelveNullSiLaMascotaEstaInactiva()
    {
        $this->assertNull($this->mascota->getPropietarioSiActiva(3));
    }

    /** El parametro va tipado a entero, asi que no hay via de inyeccion. */
    public function testNoSeDejaInyectarPorElIdentificador()
    {
        $this->assertNull($this->mascota->getPropietarioSiActiva("1 OR 1=1"));
        $this->assertNull($this->mascota->getPropietarioSiActiva("999 UNION SELECT doc_propietario FROM mascotas"));

        // La tabla sigue intacta despues de los intentos.
        $this->assertSame(3, (int) $this->db->query("SELECT COUNT(*) FROM mascotas")->fetchColumn());
    }

    /**
     * RN-G02: el dueno de una mascota no es el de otra. La comprobacion de
     * propiedad del portal se apoya en comparar este documento con el de la
     * sesion, asi que tienen que ser distinguibles.
     */
    public function testDistingueAlPropietarioDeCadaMascota()
    {
        $duenoUno = $this->mascota->getPropietarioSiActiva(1);
        $duenoDos = $this->mascota->getPropietarioSiActiva(2);

        $this->assertNotSame($duenoUno, $duenoDos);
    }
}
