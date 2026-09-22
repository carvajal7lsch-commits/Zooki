<?php
/**
 * Volcado SQL de la base de datos con PDO (HU-23, RN-504).
 *
 * Reemplaza a mysqldump: la imagen php:8.2-apache no lo trae y el cliente de
 * Debian es el de MariaDB, que no garantiza autenticarse contra MySQL 8
 * (caching_sha2_password sin TLS). Con PDO el respaldo usa la misma conexión
 * que la aplicación y funciona igual en Docker y en XAMPP.
 *
 * El esquema no tiene vistas, triggers ni rutinas; si se agregan, este
 * volcado debe ampliarse para incluirlos.
 */
class Respaldo
{
    private const FILAS_POR_INSERT = 100;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Escribe el volcado completo en un archivo .sql.gz y devuelve cuántas
     * tablas incluyó. Todo se lee dentro de una misma instantánea para que las
     * tablas sean coherentes entre sí aunque la aplicación siga escribiendo.
     */
    public function volcar(string $archivoGz): int
    {
        $gz = gzopen($archivoGz, 'wb6');
        if ($gz === false) {
            throw new RuntimeException("No se pudo crear el archivo $archivoGz");
        }

        try {
            $this->db->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            gzwrite($gz, "-- Respaldo de Zooki · " . date('Y-m-d H:i:s') . "\n");
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

            $tablas = $this->db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tablas as $tabla) {
                $this->volcarTabla($gz, $tabla);
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
            $this->db->exec('COMMIT');
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        } finally {
            gzclose($gz);
        }

        return count($tablas);
    }

    private function volcarTabla($gz, string $tabla): void
    {
        $nombre = '`' . str_replace('`', '``', $tabla) . '`';
        $crear = $this->db->query("SHOW CREATE TABLE $nombre")->fetch(PDO::FETCH_NUM)[1];
        gzwrite($gz, "DROP TABLE IF EXISTS $nombre;\n$crear;\n\n");

        // Las columnas generadas (citas.slot_activo) se calculan solas: si el
        // INSERT las incluye, MySQL rechaza la restauración.
        $columnas = [];
        foreach ($this->db->query("SHOW COLUMNS FROM $nombre")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (stripos($col['Extra'], 'GENERATED') === false) {
                $columnas[] = '`' . str_replace('`', '``', $col['Field']) . '`';
            }
        }
        $lista = implode(', ', $columnas);

        $stmt = $this->db->query("SELECT $lista FROM $nombre");
        $lote = [];
        while ($fila = $stmt->fetch(PDO::FETCH_NUM)) {
            $lote[] = '(' . implode(', ', array_map([$this, 'literal'], $fila)) . ')';
            if (count($lote) === self::FILAS_POR_INSERT) {
                gzwrite($gz, "INSERT INTO $nombre ($lista) VALUES\n" . implode(",\n", $lote) . ";\n");
                $lote = [];
            }
        }
        if ($lote) {
            gzwrite($gz, "INSERT INTO $nombre ($lista) VALUES\n" . implode(",\n", $lote) . ";\n");
        }
        gzwrite($gz, "\n");
    }

    private function literal($valor): string
    {
        return $valor === null ? 'NULL' : $this->db->quote((string) $valor);
    }
}
