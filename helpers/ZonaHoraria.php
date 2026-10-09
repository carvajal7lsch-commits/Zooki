<?php
/**
 * Zona horaria del sistema, en un solo lugar (revisión de D2.1, D2.2).
 *
 * Sin esto PHP usa la de php.ini (en XAMPP, Europe/Berlin) y MySQL la del
 * servidor: «hoy», los vencimientos de los enlaces y la auditoría quedaban
 * corridos horas. El arranque (public/index.php, ver_archivo.php y los
 * scripts) llama a aplicar(); Database fija la misma zona en cada conexión,
 * así date() y NOW() coinciden.
 */
final class ZonaHoraria
{
    public const ZONA = 'America/Bogota';

    public static function aplicar(): void
    {
        date_default_timezone_set(self::ZONA);
    }

    /**
     * Desplazamiento para la sesión de MySQL (Colombia no tiene horario de
     * verano: siempre -05:00). Se usa el desplazamiento y no el nombre porque
     * MariaDB de XAMPP no trae cargadas las tablas de zonas horarias.
     */
    public static function desplazamiento(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::ZONA)))->format('P');
    }

    public static function aplicarEnConexion(PDO $conexion): void
    {
        self::aplicar();
        if ($conexion->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        $conexion->exec("SET time_zone = '" . self::desplazamiento() . "'");
    }
}
