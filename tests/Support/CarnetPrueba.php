<?php
require_once __DIR__ . '/DosClinicas.php';

/** RN-503: datos privados reconocibles y registros de dos clínicas. */
final class CarnetPrueba
{
    public static function crear(): PDO
    {
        $db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($db);
        DosClinicas::crearHistoriaSqlite($db);
        $db->exec('CREATE TABLE carnet_escaneos (id_escaneo INTEGER PRIMARY KEY AUTOINCREMENT, id_mascota INTEGER NOT NULL, fecha TEXT, ip_hash TEXT NOT NULL, latitud NUMERIC, longitud NUMERIC, notificado INTEGER DEFAULT 0)');
        $db->exec('CREATE TABLE carnet_accesos (id_acceso INTEGER PRIMARY KEY AUTOINCREMENT, ip_hash TEXT NOT NULL, fecha TEXT NOT NULL)');
        $db->exec('ALTER TABLE mascotas ADD COLUMN token_carnet_fecha TEXT DEFAULT NULL');
        $db->exec('ALTER TABLE usuarios ADD COLUMN direccion TEXT');
        $db->exec("UPDATE mascotas SET id_especie = 1, id_raza = 49, raza_indicada = 'Mestiza', token_carnet_fecha = '2020-01-01 00:00:00' WHERE id_mascota = 1");
        $db->exec("UPDATE usuarios SET telefono = '3101234567', email = 'privado@example.org', documento = 'DOCUMENTO-PRIVADO', direccion = 'DIRECCION-PRIVADA' WHERE id_usuario = 6");
        $db->exec("UPDATE clinicas SET telefono = '6011111111', telefono_urgencias = '3112222222' WHERE id_clinica = 1");
        $db->exec("UPDATE clinicas SET telefono = '6023333333', telefono_urgencias = '' WHERE id_clinica = 2");
        DosClinicas::vincularLunaASur($db);
        $db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, tipo, descripcion, activa) VALUES
            (1, 1, 'alergia', 'Penicilina <urgente>', 1),
            (1, 2, 'condicion_cronica', 'Diabetes', 1),
            (1, 2, 'otra', 'ALERTA-INACTIVA-PRIVADA', 0)");
        $hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
        $insertar = $db->prepare('INSERT INTO vacunas (id_clinica, id_mascota, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis, observaciones) VALUES (?, 1, ?, ?, ?, ?)');
        foreach ([
            [1, 'Rabia', '2024-01-01', '2099-01-01'],
            [2, 'Rabia', '2025-01-01', '2099-02-01'],
            [1, 'Vencida', '2025-01-01', '2025-02-01'],
            [1, 'Sin fecha', '2025-01-01', null],
            [2, 'Hoy', '2025-01-01', $hoy],
            [1, 'Renovada sin fecha', '2024-01-01', '2099-01-01'],
            [2, 'Renovada sin fecha', '2025-01-01', null],
            [1, 'Futura', '2098-01-01', '2099-01-01'],
        ] as $fila) {
            $insertar->execute([...$fila, 'HISTORIA-PRIVADA']);
        }
        $db->exec("INSERT INTO mascotas (id_mascota, id_propietario, token_carnet, nombre) VALUES (2, 9, '" . str_repeat('m', 43) . "', 'Milo')");
        return $db;
    }
}
