<?php
require_once __DIR__ . '/DosClinicas.php';

/** E1: complemento del fixture de dos clínicas; mismas columnas que el MER. */
final class PlataformaFixture
{
    public static function sqlite(): PDO
    {
        $db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($db);
        DosClinicas::crearHistoriaSqlite($db);
        $db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto', fecha TEXT DEFAULT CURRENT_TIMESTAMP, fecha_cierre TEXT)");
        $db->exec('CREATE TABLE intentos_login (identificador TEXT PRIMARY KEY, intentos INTEGER DEFAULT 0, primer_intento TEXT, ultimo_intento TEXT, bloqueado_hasta TEXT)');
        return $db;
    }

    public static function datos(PDO $db): void
    {
        $db->exec('DELETE FROM suscripciones');
        $db->exec("UPDATE clinicas SET id_plan = 1 WHERE id_clinica IN (1,2,3)");
        $db->exec("INSERT INTO clinicas (id_clinica, nombre, nit, estado) VALUES (4, 'Pendiente', '9001234568', 'pendiente_verificacion')");
        $db->exec("INSERT INTO casos_soporte (tipo, id_clinica, descripcion) VALUES ('clinica_duplicada', 1, 'Reporte de NIT'), ('documento_duplicado', 2, 'Reporte de documento')");
        $db->prepare("INSERT INTO intentos_login (identificador, intentos, primer_intento, ultimo_intento, bloqueado_hasta) VALUES (?, 20, ?, ?, ?)")
            ->execute(['ip:192.0.2.8', date('Y-m-d H:i:s', time() - 120), date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() + 900)]);
        $stmt = $db->prepare("INSERT INTO auditoria_sistema (id_usuario, ip_address, accion, fecha_hora) VALUES (?, '192.0.2.8', 'LOGIN_FAIL', ?)");
        foreach ([2, 4, null] as $id) {
            $stmt->execute([$id, date('Y-m-d H:i:s')]);
        }
        // Fallo anterior a la ventana: no debe sumar una tercera clínica ni destinatarios.
        $stmt->execute([10, date('Y-m-d H:i:s', time() - 3600)]);
    }

    public static function plataforma(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::SUPER_ADMIN, 'usuario_nombre' => 'Gina Plataforma'];
        Contexto::activar(Contexto::dePlataforma(), 1);
    }
}
