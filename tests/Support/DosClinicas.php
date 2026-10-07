<?php
/**
 * Fixture compartido de dos clínicas (RE-T.15.5), para las pruebas de
 * identidad, contexto y aislamiento.
 *
 * · crearEsquemaSqlite(): las tablas que tocan esas pruebas, con los mismos
 *   nombres de columna que database/01_schema.sql, para SQLite en memoria.
 * · poblar(): los datos, con SQL portable. BaseV2MysqlTest lo carga también
 *   sobre el esquema real de MySQL, así que si 01_schema.sql cambia una
 *   columna que el fixture usa, esa prueba lo detecta.
 *
 * Personas (id_usuario):
 *   1 Ana Norte      administradora de Norte
 *   2 Beto Norte     veterinario de Norte
 *   3 Carla Sur      administradora de Sur
 *   4 Diego Sur      veterinario de Sur
 *   5 Elena Doble    veterinaria en Norte, administradora en Sur y propietaria en Norte
 *   6 Fabio Dueño    propietario en Norte
 *   7 Gina Plataforma super-administradora
 *   8 Hugo Inactivo  veterinario de Norte con la cuenta inactiva
 *   9 Iris Google    propietaria en Sur, cuenta de Google sin contraseña
 *  10 Juan Pausa     administrador de una clínica suspendida
 */
final class DosClinicas
{
    public const NORTE = 1;
    public const SUR = 2;
    public const SUSPENDIDA = 3;

    public const ADMIN_NORTE = 1;
    public const VET_NORTE = 2;
    public const ADMIN_SUR = 3;
    public const VET_SUR = 4;
    public const DOBLE = 5;
    public const PROPIETARIO = 6;
    public const SUPER_ADMIN = 7;
    public const INACTIVO = 8;
    public const GOOGLE = 9;
    public const ADMIN_SUSPENDIDA = 10;

    /** Contraseña de todas las cuentas que la tienen. */
    public const PASSWORD = 'Clave#Prueba2026';

    public static function sqlite(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::crearEsquemaSqlite($db);
        self::poblar($db);
        return $db;
    }

    public static function crearEsquemaSqlite(PDO $db): void
    {
        $db->exec("CREATE TABLE roles (id_rol INTEGER PRIMARY KEY, nombre_rol TEXT NOT NULL UNIQUE)");
        $db->exec("INSERT INTO roles (id_rol, nombre_rol) VALUES (1,'administrador'), (2,'veterinario'), (4,'propietario'), (5,'super-administrador')");
        $db->exec("CREATE TABLE clinicas (
            id_clinica INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, nit TEXT NOT NULL UNIQUE,
            id_plan INTEGER, estado TEXT NOT NULL DEFAULT 'pendiente_verificacion')");
        $db->exec("CREATE TABLE usuarios (
            id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, documento TEXT UNIQUE, tipo_documento TEXT,
            nombre_completo TEXT, telefono TEXT, email TEXT UNIQUE, password TEXT, google_uid TEXT UNIQUE,
            perfil_completo INTEGER NOT NULL DEFAULT 1, es_super_admin INTEGER NOT NULL DEFAULT 0,
            estado INTEGER NOT NULL DEFAULT 1, debe_cambiar_password INTEGER NOT NULL DEFAULT 0,
            fecha_registro TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE usuario_clinica (
            id_usuario INTEGER NOT NULL, id_clinica INTEGER NOT NULL, id_rol INTEGER NOT NULL,
            estado TEXT NOT NULL DEFAULT 'activo', fecha_vinculo TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_usuario, id_clinica))");
        $db->exec("CREATE TABLE propietario_clinica (
            id_propietario INTEGER NOT NULL, id_clinica INTEGER NOT NULL, estado TEXT NOT NULL DEFAULT 'activo',
            fecha_vinculo TEXT DEFAULT CURRENT_TIMESTAMP, autoriza_historia_compartida INTEGER NOT NULL DEFAULT 0,
            fecha_autorizacion TEXT, PRIMARY KEY (id_propietario, id_clinica))");
        $db->exec("CREATE TABLE mascotas (
            id_mascota INTEGER PRIMARY KEY AUTOINCREMENT, id_propietario INTEGER, id_clinica_registro INTEGER,
            token_carnet TEXT NOT NULL UNIQUE, nombre TEXT, id_especie INTEGER, id_raza INTEGER,
            estado INTEGER NOT NULL DEFAULT 1)");
        $db->exec("CREATE TABLE mascota_clinica (
            id_mascota INTEGER NOT NULL, id_clinica INTEGER NOT NULL, numero_historia_clinica TEXT,
            estado TEXT NOT NULL DEFAULT 'activo', PRIMARY KEY (id_mascota, id_clinica))");
        $db->exec("CREATE TABLE auditoria_sistema (
            id_auditoria INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER, id_usuario INTEGER,
            ip_address TEXT, fecha_hora TEXT DEFAULT CURRENT_TIMESTAMP, accion TEXT NOT NULL,
            tabla_afectada TEXT, registro_id TEXT, datos_anteriores TEXT, datos_nuevos TEXT, descripcion TEXT)");
        $db->exec("CREATE TABLE notificaciones_internas (
            id INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER NOT NULL, id_usuario INTEGER,
            id_rol_destino INTEGER, tipo TEXT NOT NULL, titulo TEXT NOT NULL, mensaje TEXT NOT NULL,
            enlace TEXT, id_cita INTEGER, vigente_hasta TEXT, leida INTEGER NOT NULL DEFAULT 0,
            fecha_creacion TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER, email TEXT NOT NULL, token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL, used INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE verificaciones_email (
            id INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER NOT NULL, email TEXT NOT NULL,
            token_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    }

    /** Datos portables (SQLite y MySQL). Los roles los trae el esquema o la semilla. */
    public static function poblar(PDO $db): void
    {
        $db->exec("INSERT INTO clinicas (id_clinica, nombre, nit, estado) VALUES
            (1, 'Clínica Norte', '900123456-8', 'activa'),
            (2, 'Clínica Sur', '900654321-0', 'activa'),
            (3, 'Clínica Pausada', '900111222-1', 'suspendida')");

        // Costo bajo a propósito: las pruebas crean este fixture muchas veces.
        $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $personas = [
            [1, '1000000001', 'Ana Norte', 'ana@zooki.test', $hash, null, 0, 1],
            [2, '1000000002', 'Beto Norte', 'beto@zooki.test', $hash, null, 0, 1],
            [3, '1000000003', 'Carla Sur', 'carla@zooki.test', $hash, null, 0, 1],
            [4, '1000000004', 'Diego Sur', 'diego@zooki.test', $hash, null, 0, 1],
            [5, '1000000005', 'Elena Doble', 'elena@zooki.test', $hash, null, 0, 1],
            [6, '1000000006', 'Fabio Dueño', 'fabio@zooki.test', $hash, null, 0, 1],
            [7, null, 'Gina Plataforma', 'gina@zooki.test', $hash, null, 1, 1],
            [8, '1000000008', 'Hugo Inactivo', 'hugo@zooki.test', $hash, null, 0, 0],
            [9, '1000000009', 'Iris Google', 'iris@zooki.test', null, 'google-sub-iris', 0, 1],
            [10, '1000000010', 'Juan Pausa', 'juan@zooki.test', $hash, null, 0, 1],
        ];
        $stmt = $db->prepare("INSERT INTO usuarios (id_usuario, documento, tipo_documento, nombre_completo, email, password, google_uid, es_super_admin, estado)
                              VALUES (?, ?, 'CC', ?, ?, ?, ?, ?, ?)");
        foreach ($personas as $p) {
            $stmt->execute($p);
        }

        $db->exec("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES
            (1, 1, 1, 'activo'), (2, 1, 2, 'activo'), (3, 2, 1, 'activo'), (4, 2, 2, 'activo'),
            (5, 1, 2, 'activo'), (5, 2, 1, 'activo'), (8, 1, 2, 'activo'), (10, 3, 1, 'activo')");
        $db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES
            (5, 1, 'activo'), (6, 1, 'activo'), (9, 2, 'activo')");
        $db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, estado) VALUES
            (1, 6, 1, '" . str_repeat('a', 43) . "', 'Luna', 1)");
        $db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (1, 1, 'activo')");
    }
}
