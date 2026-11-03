<?php
require_once __DIR__ . '/../models/IntencionProfesional.php';

/** Conserva las puertas de identidad; se llama después de aceptar política y cambiar clave. */
final class DestinoInicial
{
    public static function resolver(PDO $db, array $contexto): string
    {
        if ($contexto['tipo'] === Contexto::CLINICA && (int) $contexto['id_rol'] === Roles::ADMIN) {
            $ciclo = (new IntencionProfesional($db))->primerAcceso();
            if ($ciclo !== null) return 'admin_suscripcion&ciclo=' . rawurlencode($ciclo);
        }
        return Contexto::destino($contexto);
    }
}
