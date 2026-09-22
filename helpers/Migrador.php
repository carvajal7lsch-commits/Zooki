<?php
/**
 * Aplica las migraciones de database/ que falten y anota cada una en
 * schema_migraciones, para que el despliegue no dependa de entrar al servidor
 * a correrlas a mano (lo lanza docker/iniciar.sh al arrancar el contenedor).
 *
 * Qué cuenta como migración: los archivos NN_nombre.sql con NN ≥ 04.
 * 01_schema.sql es el esquema inicial y 03_drawdb_schema.sql un modelo; los dos
 * los carga MySQL solo cuando crea la base (docker-entrypoint-initdb.d).
 *
 * Línea base: las migraciones 04 a 12 se aplicaron a mano antes de que existiera
 * este registro, y algunas no se pueden repetir (07 y 09 redefinen los estados
 * de las citas con la lista de su época y borrarían «sin_cerrar»). Por eso, la
 * primera vez que corre en una base que ya tiene el esquema, las anota como
 * aplicadas sin ejecutarlas. De la 13 en adelante se ejecutan.
 *
 * Regla para las migraciones nuevas: deben poder ejecutarse más de una vez
 * (en una instalación nueva las corre MySQL al crear la base y luego este
 * migrador). Todas las recientes lo cumplen; ver database/13_razas_portal.sql.
 */
final class Migrador
{
    public const PRIMERA = 4;
    public const LINEA_BASE = 12;
    private const CANDADO = 'zooki_migraciones';

    /** @var callable(string): void */
    private $registrar;

    public function __construct(private PDO $db, private string $carpeta, ?callable $registrar = null)
    {
        $this->registrar = $registrar ?? static function (string $mensaje): void {};
    }

    /**
     * @return array{ejecutadas: string[], linea_base: string[]} lo que hizo
     * @throws RuntimeException si una migración falla (las siguientes no se corren)
     */
    public function migrar(bool $soloRevisar = false): array
    {
        $hecho = ['ejecutadas' => [], 'linea_base' => []];

        if (!$this->existeTabla('usuarios')) {
            throw new RuntimeException('La base no tiene el esquema: primero se carga database/01_schema.sql.');
        }

        // Si dos contenedores arrancan a la vez, solo uno migra.
        if ((int) $this->db->query("SELECT GET_LOCK('" . self::CANDADO . "', 60)")->fetchColumn() !== 1) {
            ($this->registrar)('Otro proceso está aplicando las migraciones; se omite.');
            return $hecho;
        }

        try {
            if (!$soloRevisar) {
                $this->db->exec(
                    "CREATE TABLE IF NOT EXISTS schema_migraciones (
                        archivo varchar(150) NOT NULL PRIMARY KEY,
                        modo enum('ejecutada','linea_base') NOT NULL DEFAULT 'ejecutada',
                        aplicada_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            }
            $aplicadas = $this->existeTabla('schema_migraciones')
                ? $this->db->query('SELECT archivo FROM schema_migraciones')->fetchAll(PDO::FETCH_COLUMN)
                : [];
            $archivos = $this->archivos();

            // Primera vez: 04–12 ya estaban aplicadas a mano.
            if (!$aplicadas) {
                foreach ($archivos as $numero => $archivo) {
                    if ($numero <= self::LINEA_BASE) {
                        if (!$soloRevisar) {
                            $this->anotar($archivo, 'linea_base');
                        }
                        $aplicadas[] = $archivo;
                        $hecho['linea_base'][] = $archivo;
                    }
                }
            }

            foreach ($archivos as $archivo) {
                if (in_array($archivo, $aplicadas, true)) {
                    continue;
                }
                if ($soloRevisar) {
                    $hecho['ejecutadas'][] = $archivo;
                    continue;
                }
                ($this->registrar)("Aplicando {$archivo}…");
                $this->ejecutar($archivo);
                $this->anotar($archivo, 'ejecutada');
                $hecho['ejecutadas'][] = $archivo;
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . self::CANDADO . "')");
        }

        return $hecho;
    }

    /** @return array<int, string> número => archivo, en orden */
    private function archivos(): array
    {
        $lista = [];
        foreach (glob(rtrim($this->carpeta, '/\\') . '/*.sql') as $ruta) {
            $nombre = basename($ruta);
            if (preg_match('/^(\d+)_[\w-]+\.sql$/', $nombre, $m) && (int) $m[1] >= self::PRIMERA) {
                if (isset($lista[(int) $m[1]])) {
                    throw new RuntimeException("Dos migraciones con el número {$m[1]}: {$lista[(int) $m[1]]} y $nombre.");
                }
                $lista[(int) $m[1]] = $nombre;
            }
        }
        ksort($lista);
        return $lista;
    }

    private function ejecutar(string $archivo): void
    {
        $sql = file_get_contents(rtrim($this->carpeta, '/\\') . '/' . $archivo);
        try {
            // El archivo trae varias sentencias; nextRowset() hace que un error
            // en cualquiera de ellas se lance, no solo en la primera.
            $st = $this->db->query($sql);
            while ($st->nextRowset()) {
            }
            $st->closeCursor();
        } catch (PDOException $e) {
            throw new RuntimeException("Falló $archivo: " . $e->getMessage(), 0, $e);
        }
    }

    private function anotar(string $archivo, string $modo): void
    {
        $this->db->prepare('INSERT INTO schema_migraciones (archivo, modo) VALUES (?, ?)')->execute([$archivo, $modo]);
    }

    private function existeTabla(string $tabla): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$tabla]);
        return (int) $st->fetchColumn() > 0;
    }
}
