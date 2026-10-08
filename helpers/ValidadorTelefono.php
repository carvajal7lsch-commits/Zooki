<?php
/**
 * Formato del teléfono de contacto (C9.1).
 *
 * El servidor y el formulario usan los mismos límites: el HTML los toma de
 * aquí (maxlength, pattern y los caracteres que filtra el JS al escribir).
 */
final class ValidadorTelefono
{
    public const MIN = 7;
    public const MAX = 20;

    /** Caracteres admitidos, en el formato de una clase de expresión regular. */
    public const CARACTERES = '0-9+\s-';

    public const MENSAJE = 'El teléfono solo puede tener números, espacios, + y guiones (de ' . self::MIN . ' a ' . self::MAX . ' caracteres).';

    /** Espacios repetidos fuera, como se guarda. */
    public static function normalizar(string $telefono): string
    {
        return preg_replace('/\s+/', ' ', trim($telefono));
    }

    public static function esValido(string $telefono): bool
    {
        return preg_match('/^[' . self::CARACTERES . ']{' . self::MIN . ',' . self::MAX . '}$/D', $telefono) === 1;
    }

    /**
     * D1: atributos de todo campo de teléfono, ya escapados. El filtro de
     * public/js/interacciones.js lee data-caracteres mientras se escribe.
     */
    public static function atributosHtml(): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return 'inputmode="tel" maxlength="' . self::MAX . '" pattern="' . $e(self::patronHtml()) . '"'
            . ' data-caracteres="' . $e(self::CARACTERES) . '" title="' . $e(self::MENSAJE) . '"';
    }

    /** Patrón para el atributo pattern del HTML (sin anclas: el navegador las agrega). */
    public static function patronHtml(): string
    {
        return '[0-9+\s\-]{' . self::MIN . ',' . self::MAX . '}';
    }
}
