<?php
require_once __DIR__ . '/ConocimientoPrueba.php';
require_once __DIR__ . '/../../helpers/ConocimientoClinico.php';

final class ConocimientoRealPrueba
{
    public const CARPETA = __DIR__ . '/../../database/conocimiento';

    public static function instantanea(): ConocimientoClinico
    {
        $lectura = ArchivosConocimiento::leer(self::CARPETA);
        $datos = $lectura['datos'];
        $fuentes = array_column($datos['fuentes'], null, 'codigo');
        foreach (['nodos', 'aristas', 'reglas'] as $grupo) {
            foreach ($datos[$grupo] as &$elemento) {
                $elemento['fuentes'] = array_map(static fn (array $ref): array => [...$fuentes[$ref['codigo']], ...$ref], $elemento['fuentes']);
            }
            unset($elemento);
        }
        return new ConocimientoClinico($datos['nodos'], $datos['aristas'], $datos['reglas'], $lectura['version'], $datos['manifiesto']['limitaciones']);
    }

    /** Solo catálogos globales: los IDs y pares salen de la semilla real. */
    public static function taxonomia(PDO $db): void
    {
        $semilla = file_get_contents(__DIR__ . '/../../database/02_semilla.sql');
        foreach (['especies', 'razas'] as $tabla) {
            preg_match('/INSERT IGNORE INTO `' . $tabla . '`.*?;/s', $semilla, $coincidencias);
            $db->exec(str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $coincidencias[0]));
        }
    }
}
