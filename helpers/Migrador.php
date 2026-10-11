<?php
/**
 * Aplica las migraciones de database/ que falten y la semilla, para que el
 * despliegue no dependa de entrar al servidor (lo lanza docker/iniciar.sh al
 * arrancar el contenedor web).
 *
 * La base v2 nace de dos archivos que no son migraciones (plan M0, etapa B):
 * · 01_schema.sql   el esquema completo. MySQL lo carga al crear el volumen;
 *                   en local se importa a mano.
 * · 02_semilla.sql  roles, planes y catálogos globales. Este migrador la
 *                   aplica en CADA arranque (D-5): es repetible, solo inserta
 *                   lo que falta y nunca sobrescribe, así que un catálogo
 *                   nuevo llega a producción sin entrar al servidor.
 *
 * Migraciones: los archivos NN_nombre.sql con NN ≥ 03, en orden, una sola vez
 * cada una (quedan en schema_migraciones). Deben poder ejecutarse más de una
 * vez, porque en una instalación nueva MySQL las corre al crear la base y
 * luego este migrador las vuelve a correr.
 *
 * Se niega a trabajar sobre una base v1 (sin la tabla clinicas o sin
 * usuarios.id_usuario): la v2 no migra datos, y aplicar migraciones v2 sobre
 * el esquema viejo lo dejaría a medias.
 */
final class Migrador
{
    public const PRIMERA = 3;
    public const SEMILLA = '02_semilla.sql';
    private const CANDADO = 'zooki_migraciones';

    /** @var callable(string): void */
    private $registrar;

    public function __construct(private PDO $db, private string $carpeta, ?callable $registrar = null)
    {
        $this->registrar = $registrar ?? static function (string $mensaje): void {};
    }

    /**
     * @return array{ejecutadas: string[], semilla: bool} lo que hizo (o, al
     *         solo revisar, lo que haría)
     * @throws RuntimeException si la base no es v2 o si un archivo falla
     *         (las migraciones siguientes no se corren)
     */
    public function migrar(bool $soloRevisar = false): array
    {
        $this->exigirEsquemaV2();

        $hecho = ['ejecutadas' => [], 'semilla' => false];

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
                        aplicada_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
                );
            }
            $aplicadas = $this->existeTabla('schema_migraciones')
                ? $this->db->query('SELECT archivo FROM schema_migraciones')->fetchAll(PDO::FETCH_COLUMN)
                : [];

            foreach ($this->archivos() as $archivo) {
                if (in_array($archivo, $aplicadas, true)) {
                    continue;
                }
                $hecho['ejecutadas'][] = $archivo;
                if ($soloRevisar) {
                    continue;
                }
                ($this->registrar)("Aplicando {$archivo}…");
                $this->ejecutar($archivo);
                $this->db->prepare('INSERT INTO schema_migraciones (archivo) VALUES (?)')->execute([$archivo]);
            }

            // La semilla va después de las migraciones: una migración nueva
            // puede crear la columna o la tabla que la semilla llena.
            if (is_file($this->ruta(self::SEMILLA))) {
                $hecho['semilla'] = true;
                if (!$soloRevisar) {
                    $this->ejecutar(self::SEMILLA);
                }
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . self::CANDADO . "')");
        }

        return $hecho;
    }

    /**
     * @throws RuntimeException con un mensaje que dice qué hacer
     */
    private function exigirEsquemaV2(): void
    {
        if (!$this->existeTabla('usuarios')) {
            throw new RuntimeException(
                'La base no tiene el esquema: primero se cargan database/01_schema.sql y database/02_semilla.sql.'
            );
        }
        if (!$this->existeTabla('clinicas') || !$this->existeColumna('usuarios', 'id_usuario')) {
            throw new RuntimeException(
                'La base tiene el esquema v1 (falta la tabla clinicas o la columna usuarios.id_usuario). '
                . 'La v2 no migra datos: respalda la base y créala de nuevo con database/01_schema.sql '
                . 'y database/02_semilla.sql (plan M0, etapa F).'
            );
        }
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
        $sql = file_get_contents($this->ruta($archivo));
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

    private function ruta(string $archivo): string
    {
        return rtrim($this->carpeta, '/\\') . '/' . $archivo;
    }

    private function existeTabla(string $tabla): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$tabla]);
        return (int) $st->fetchColumn() > 0;
    }

    private function existeColumna(string $tabla, string $columna): bool
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute([$tabla, $columna]);
        return (int) $st->fetchColumn() > 0;
    }
}
