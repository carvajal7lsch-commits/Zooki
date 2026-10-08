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
            direccion TEXT, telefono TEXT, id_plan INTEGER, estado TEXT NOT NULL DEFAULT 'pendiente_verificacion')");
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
            fecha_nacimiento TEXT, peso NUMERIC, sexo TEXT DEFAULT 'Desconocido', esterilizado INTEGER,
            raza_indicada TEXT, url_foto TEXT, carnet_activo INTEGER DEFAULT 1,
            ficha_por_completar INTEGER DEFAULT 0,
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
            id_clinica_vinculo INTEGER, proposito TEXT NOT NULL DEFAULT 'registro',
            token_hash TEXT NOT NULL, expires_at TEXT NOT NULL, used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    }

    /** C2: mismas columnas de los catálogos reales, con FK para probar rollback. */
    public static function crearCatalogosSqlite(PDO $db): void
    {
        $db->exec('PRAGMA foreign_keys = ON');
        // IF NOT EXISTS: C4 también carga la taxonomía de C3 en la misma base.
        $db->exec('CREATE TABLE IF NOT EXISTS especies (id_especie INTEGER PRIMARY KEY)');
        $db->exec('INSERT OR IGNORE INTO especies (id_especie) VALUES (1),(2),(3),(4),(5),(6)');
        $db->exec('CREATE TABLE tipos_cita (id_tipo_cita INTEGER PRIMARY KEY AUTOINCREMENT,
            id_clinica INTEGER NOT NULL REFERENCES clinicas, nombre_tipo TEXT, duracion_minutos INTEGER,
            margen_minutos INTEGER DEFAULT 10, pausable INTEGER DEFAULT 1, descripcion TEXT, color TEXT, activo INTEGER)');
        $db->exec('CREATE TABLE horarios_clinica (id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_clinica INTEGER NOT NULL REFERENCES clinicas, dia_semana INTEGER, activo INTEGER,
            bloque_morning_activo INTEGER, bloque_afternoon_activo INTEGER, bloque_morning_inicio TEXT,
            bloque_morning_fin TEXT, bloque_afternoon_inicio TEXT, bloque_afternoon_fin TEXT,
            UNIQUE(id_clinica,dia_semana))');
        $db->exec('CREATE TABLE vacunas_base (id_vacuna_base INTEGER PRIMARY KEY AUTOINCREMENT,
            id_clinica INTEGER NOT NULL REFERENCES clinicas, nombre_vacuna TEXT, descripcion TEXT, estado INTEGER)');
        $db->exec('CREATE TABLE especie_vacunas (id_especie_vacuna INTEGER PRIMARY KEY AUTOINCREMENT,
            id_especie INTEGER REFERENCES especies, id_vacuna_base INTEGER REFERENCES vacunas_base,
            UNIQUE(id_especie,id_vacuna_base))');
        $db->exec('CREATE TABLE laboratorios_base (id_laboratorio INTEGER PRIMARY KEY AUTOINCREMENT,
            id_clinica INTEGER NOT NULL REFERENCES clinicas, nombre_laboratorio TEXT, estado INTEGER)');
        $db->exec('CREATE TABLE productos_desparasitacion_base (id_producto INTEGER PRIMARY KEY AUTOINCREMENT,
            id_clinica INTEGER NOT NULL REFERENCES clinicas, nombre_producto TEXT, tipo TEXT, estado INTEGER)');
    }

    /** C3: taxonomía global de lectura y auditoría de la ficha. */
    public static function crearMascotasSqlite(PDO $db): void
    {
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('CREATE TABLE especies (id_especie INTEGER PRIMARY KEY, nombre_especie TEXT)');
        $db->exec("INSERT INTO especies VALUES (1,'Canino'),(2,'Felino')");
        $db->exec('CREATE TABLE razas (id_raza INTEGER PRIMARY KEY, id_especie INTEGER REFERENCES especies, nombre_raza TEXT)');
        $db->exec("INSERT INTO razas VALUES (49,1,'Sin raza definida'),(50,2,'Sin raza definida')");
        $db->exec('CREATE TABLE colores_base (id_color INTEGER PRIMARY KEY, nombre_color TEXT)');
        $db->exec("INSERT INTO colores_base VALUES (1,'Negro'),(2,'Blanco')");
        $db->exec('CREATE TABLE mascota_colores (id_mascota INTEGER REFERENCES mascotas, id_color INTEGER REFERENCES colores_base, PRIMARY KEY(id_mascota,id_color))');
        $db->exec('CREATE TABLE auditoria_mascotas (id_auditoria INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER REFERENCES clinicas,
            id_mascota INTEGER REFERENCES mascotas, id_usuario INTEGER REFERENCES usuarios, campo_modificado TEXT,
            valor_anterior TEXT,valor_nuevo TEXT,fecha_cambio TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec("CREATE TABLE notificaciones (id_notificacion INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER REFERENCES clinicas,
            id_usuario INTEGER REFERENCES usuarios,tipo_entidad TEXT,id_entidad INTEGER,destinatario_email TEXT,
            tipo_notificacion TEXT,asunto TEXT,mensaje TEXT,fecha_envio TEXT DEFAULT CURRENT_TIMESTAMP,estado TEXT DEFAULT 'pendiente')");
        $db->exec('CREATE TABLE consentimientos_datos (id_consentimiento INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER REFERENCES usuarios,
            version_politica TEXT NOT NULL,medio TEXT NOT NULL,ip_address TEXT,fecha TEXT DEFAULT CURRENT_TIMESTAMP)');
        self::completarMascota($db);
    }

    public static function completarMascota(PDO $db): void
    {
        $db->exec("UPDATE mascotas SET id_especie=1,id_raza=49,fecha_nacimiento='2022-01-01',peso=8.50,sexo='Hembra' WHERE id_mascota=1");
        $db->exec('INSERT INTO mascota_colores (id_mascota,id_color) VALUES (1,1)');
    }

    /**
     * C4: historia clínica y prevención, con las columnas de 01_schema.sql.
     * Llamar después de crearMascotasSqlite() (usa sus especies y colores).
     */
    public static function crearHistoriaSqlite(PDO $db): void
    {
        $db->exec('PRAGMA foreign_keys = ON');
        // C5: misma protección contra la doble reserva que 01_schema.sql (D-2):
        // ocupa_horario es NULL si la cita está libre o es sobrecupo, y el
        // índice único no lleva id_clinica.
        $db->exec("CREATE TABLE citas (id_cita INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER NOT NULL REFERENCES clinicas,
            id_mascota INTEGER NOT NULL REFERENCES mascotas, id_veterinario INTEGER NOT NULL REFERENCES usuarios,
            id_tipo_cita INTEGER, fecha TEXT NOT NULL, hora TEXT NOT NULL, hora_fin TEXT, motivo TEXT NOT NULL,
            duracion_minutos INTEGER, margen_minutos INTEGER NOT NULL DEFAULT 0, prioridad TEXT NOT NULL DEFAULT 'verde',
            prioridad_calculada TEXT, motivo_ajuste_prioridad TEXT, es_sobrecupo INTEGER NOT NULL DEFAULT 0,
            orden_sobrecupo INTEGER, sintomas_texto TEXT, inicio_sintomas TEXT, estado TEXT NOT NULL DEFAULT 'pendiente',
            ocupa_horario INTEGER GENERATED ALWAYS AS (CASE WHEN estado IN ('cancelada', 'no_asistio') OR es_sobrecupo = 1 THEN NULL ELSE 1 END) STORED,
            hora_llegada TEXT, hora_inicio_real TEXT, hora_fin_real TEXT, aviso_atencion_abierta TEXT, observaciones TEXT,
            fecha_registro TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec('CREATE UNIQUE INDEX uq_cita_veterinario_horario ON citas (id_veterinario, fecha, hora, ocupa_horario)');
        $db->exec('CREATE TABLE consultas (id_consulta INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER NOT NULL REFERENCES clinicas,
            id_cita INTEGER UNIQUE REFERENCES citas, id_mascota INTEGER NOT NULL REFERENCES mascotas,
            id_veterinario INTEGER NOT NULL REFERENCES usuarios, fecha_hora TEXT NOT NULL, motivo_consulta TEXT NOT NULL,
            anamnesis TEXT NOT NULL, peso NUMERIC, temperatura NUMERIC, frecuencia_cardiaca INTEGER,
            frecuencia_respiratoria INTEGER, diagnostico TEXT NOT NULL, plan_tratamiento TEXT NOT NULL, observaciones TEXT)');
        $db->exec('CREATE TABLE tratamientos (id_tratamiento INTEGER PRIMARY KEY AUTOINCREMENT, id_consulta INTEGER NOT NULL REFERENCES consultas,
            id_nodo_farmaco INTEGER, medicamento TEXT NOT NULL, dosis TEXT NOT NULL, via_administracion TEXT NOT NULL,
            duracion TEXT NOT NULL, fecha_inicio TEXT NOT NULL, fecha_fin TEXT, observaciones TEXT,
            fecha_registro TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE TABLE archivos_clinicos (id_archivo INTEGER PRIMARY KEY AUTOINCREMENT, id_consulta INTEGER NOT NULL REFERENCES consultas,
            nombre_original TEXT NOT NULL, nombre_servidor TEXT NOT NULL, ruta_archivo TEXT NOT NULL, tipo_archivo TEXT NOT NULL,
            extension TEXT NOT NULL, tamano_bytes INTEGER NOT NULL, descripcion TEXT, fecha_subida TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE TABLE vacunas (id_vacuna INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER NOT NULL REFERENCES clinicas,
            id_mascota INTEGER NOT NULL REFERENCES mascotas, id_veterinario INTEGER REFERENCES usuarios, nombre_vacuna TEXT NOT NULL,
            laboratorio TEXT, lote TEXT, fecha_aplicacion TEXT NOT NULL, fecha_proxima_dosis TEXT, observaciones TEXT,
            fecha_registro TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE TABLE desparasitaciones (id_desparasitacion INTEGER PRIMARY KEY AUTOINCREMENT, id_clinica INTEGER NOT NULL REFERENCES clinicas,
            id_mascota INTEGER NOT NULL REFERENCES mascotas, id_veterinario INTEGER REFERENCES usuarios, tipo TEXT NOT NULL,
            producto TEXT NOT NULL, periodicidad TEXT NOT NULL, fecha_aplicacion TEXT NOT NULL, fecha_proxima TEXT NOT NULL,
            observaciones TEXT, fecha_registro TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE UNIQUE INDEX uq_mascota_clinica_numero_hc ON mascota_clinica (id_clinica, numero_historia_clinica)');
    }

    /**
     * C4: Fabio también es propietario en Sur y Luna queda vinculada a Sur.
     * $autoriza es propietario_clinica.autoriza_historia_compartida de Sur
     * (RN-113). SQL portable: también corre sobre el esquema real.
     */
    public static function vincularLunaASur(PDO $db, int $autoriza = 0): void
    {
        $db->prepare("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado, autoriza_historia_compartida) VALUES (6, 2, 'activo', ?)")
            ->execute([$autoriza]);
        $db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (1, 2, 'activo')");
    }

    /**
     * C5: un tipo de cita por clínica (duración y margen distintos) y el
     * horario de lunes a domingo, 08:00–12:00 y 14:00–18:00. SQL portable:
     * también corre sobre el esquema real.
     */
    public static function poblarAgenda(PDO $db): void
    {
        $db->exec("INSERT INTO tipos_cita (id_tipo_cita, id_clinica, nombre_tipo, duracion_minutos, margen_minutos, pausable, activo) VALUES
            (1, 1, 'Consulta Norte', 30, 10, 1, 1),
            (2, 2, 'Control Sur', 20, 5, 1, 1)");
        $horario = $db->prepare("INSERT INTO horarios_clinica (id_clinica, dia_semana, activo, bloque_morning_activo, bloque_afternoon_activo,
            bloque_morning_inicio, bloque_morning_fin, bloque_afternoon_inicio, bloque_afternoon_fin)
            VALUES (?, ?, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00')");
        foreach ([self::NORTE, self::SUR] as $clinica) {
            for ($dia = 1; $dia <= 7; $dia++) {
                $horario->execute([$clinica, $dia]);
            }
        }
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
