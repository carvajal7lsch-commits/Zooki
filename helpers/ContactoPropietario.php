<?php
/**
 * RN-115 y RE-5.12.3 (decisión del usuario, M5 «A — Revisión»): la clínica ve
 * el documento, el correo y el teléfono de un propietario solo mientras su
 * vínculo (propietario_clinica) con ella está activo. Con el vínculo
 * inactivo, lo haya desactivado el propietario o la clínica, ve solo el
 * nombre y la marca «Desvinculado».
 *
 * Toda consulta del personal toma de aquí esas columnas, para que la regla
 * viva en un solo lugar. Sin fila de vínculo (LEFT JOIN vacío) tampoco se
 * entrega nada.
 */
final class ContactoPropietario
{
    public const MARCA_DESVINCULADO = 'Desvinculado';

    /** Datos de contacto que se ocultan con el vínculo inactivo. */
    private const CAMPOS = ['tipo_documento', 'documento', 'email', 'telefono'];

    /** Condición SQL: el vínculo con alias $aliasVinculo está activo. */
    public static function vinculoActivo(string $aliasVinculo): string
    {
        return "$aliasVinculo.estado = 'activo'";
    }

    /** Una columna que solo se entrega con el vínculo activo (NULL si no). */
    public static function columna(string $columna, string $aliasVinculo, string $nombre): string
    {
        return 'CASE WHEN ' . self::vinculoActivo($aliasVinculo) . " THEN $columna END AS $nombre";
    }

    /** Tipo y número de documento, correo y teléfono del usuario $aliasUsuario. */
    public static function columnas(string $aliasUsuario, string $aliasVinculo): string
    {
        $columnas = [];
        foreach (self::CAMPOS as $campo) {
            $columnas[] = self::columna("$aliasUsuario.$campo", $aliasVinculo, $campo);
        }
        return implode(', ', $columnas);
    }

    /** 1 si el vínculo no está activo; la vista muestra entonces la marca «Desvinculado». */
    public static function marca(string $aliasVinculo): string
    {
        return 'CASE WHEN ' . self::vinculoActivo($aliasVinculo) . ' THEN 0 ELSE 1 END AS desvinculado';
    }
}
