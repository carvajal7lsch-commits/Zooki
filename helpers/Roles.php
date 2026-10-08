<?php
/**
 * Roles de la v2 tal como están en la tabla `roles` (02_semilla.sql).
 *
 * El identificador 3 está retirado. Los roles de clínica son solo el 1 y el
 * 2, y se asignan en usuario_clinica; el 4 lo da el vínculo en
 * propietario_clinica y el 5 la marca usuarios.es_super_admin (MER §2).
 */
final class Roles
{
    public const ADMIN = 1;
    public const VETERINARIO = 2;
    public const PROPIETARIO = 4;
    public const SUPER_ADMIN = 5;

    /** Los únicos que se asignan en usuario_clinica (B.5, RE-T.17.5). */
    public const DE_CLINICA = [self::ADMIN, self::VETERINARIO];

    public static function esDeClinica(int $idRol): bool
    {
        return in_array($idRol, self::DE_CLINICA, true);
    }

    public static function nombre(int $idRol): string
    {
        return [
            self::ADMIN => 'Administrador',
            self::VETERINARIO => 'Veterinario',
            self::PROPIETARIO => 'Propietario',
            self::SUPER_ADMIN => 'Super-administrador',
        ][$idRol] ?? 'Sin rol';
    }
}
