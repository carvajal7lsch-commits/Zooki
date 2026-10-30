<?php
require_once __DIR__ . '/../../models/CargadorConocimiento.php';

/** Cambios de un conjunto sintético aislado; nunca modifica los archivos de producción. */
final class ConocimientoPrueba
{
    public static function copiar(?string $origen = null): string
    {
        $carpeta = sys_get_temp_dir() . '/zooki_conocimiento_' . bin2hex(random_bytes(6));
        mkdir($carpeta);
        $origen ??= __DIR__ . '/../Fixtures/conocimiento';
        foreach (glob($origen . '/*.json') as $archivo) {
            copy($archivo, $carpeta . '/' . basename($archivo));
        }
        return $carpeta;
    }

    public static function cambiar(string $carpeta, string $archivo, callable $cambio): void
    {
        $ruta = $carpeta . '/' . $archivo . '.json';
        $datos = json_decode(file_get_contents($ruta), true, 64, JSON_THROW_ON_ERROR);
        $cambio($datos);
        file_put_contents($ruta, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public static function borrar(string $carpeta): void
    {
        foreach (glob($carpeta . '/*.json') as $archivo) {
            unlink($archivo);
        }
        rmdir($carpeta);
    }

    public static function sqlite(): PDO
    {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('CREATE TABLE especies (id_especie INTEGER PRIMARY KEY, nombre_especie TEXT UNIQUE)');
        $db->exec('CREATE TABLE razas (id_raza INTEGER PRIMARY KEY, id_especie INTEGER REFERENCES especies, nombre_raza TEXT)');
        $db->exec("INSERT INTO especies VALUES (1, 'Canino'), (2, 'Felino')");
        $db->exec("INSERT INTO razas VALUES (55, 1, 'Gran danés')");
        self::crearGrafo($db);
        $db->exec('CREATE TABLE auditoria_sistema (id_auditoria INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER, id_usuario INTEGER, accion TEXT, tabla_afectada TEXT, registro_id TEXT, datos_nuevos TEXT, descripcion TEXT)');
        return $db;
    }

    public static function crearGrafo(PDO $db): void
    {
        $comunes = "codigo TEXT UNIQUE NOT NULL, activo INTEGER, version_conocimiento TEXT, fuentes TEXT, estado_validacion TEXT";
        $db->exec("CREATE TABLE grafo_nodos (id_nodo INTEGER PRIMARY KEY AUTOINCREMENT, $comunes, tipo TEXT, etiqueta TEXT, nivel_triage TEXT, alarma_universal INTEGER, moderado INTEGER, metadatos TEXT, id_especie INTEGER UNIQUE REFERENCES especies, id_raza INTEGER UNIQUE REFERENCES razas)");
        $db->exec("CREATE TABLE grafo_aristas (id_arista INTEGER PRIMARY KEY AUTOINCREMENT, $comunes, id_origen INTEGER REFERENCES grafo_nodos, id_destino INTEGER REFERENCES grafo_nodos, tipo_relacion TEXT, signo TEXT, peso NUMERIC, efecto TEXT, condiciones TEXT, pauta TEXT)");
        $db->exec("CREATE TABLE grafo_sinonimos (id_sinonimo INTEGER PRIMARY KEY AUTOINCREMENT, $comunes, id_nodo INTEGER REFERENCES grafo_nodos, texto TEXT, normalizado TEXT)");
        $db->exec("CREATE TABLE grafo_reglas (id_regla INTEGER PRIMARY KEY AUTOINCREMENT, $comunes, motor TEXT, condiciones TEXT, resultado TEXT)");
    }

    public static function instantanea(PDO $db): array
    {
        $filas = [];
        foreach (['grafo_nodos', 'grafo_aristas', 'grafo_sinonimos', 'grafo_reglas', 'auditoria_sistema'] as $tabla) {
            $filas[$tabla] = $db->query('SELECT * FROM ' . $tabla)->fetchAll(PDO::FETCH_ASSOC);
        }
        return $filas;
    }
}
