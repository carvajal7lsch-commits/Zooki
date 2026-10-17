<?php

/**
 * RE-2.1.5 — Reglas de una alerta médica, compartidas por el servidor y el HTML.
 * La descripción cabe en alertas_medicas.descripcion (VARCHAR(255)).
 */
final class ValidadorAlertaMedica
{
    public const DESCRIPCION_MAX = 255;
    public const BUSQUEDA_MIN = 2;
    public const BUSQUEDA_MAX = 60;
    public const CARACTERES = '\p{L}\p{M}\p{N}\p{P}\p{S} ';

    public const TIPOS = [
        'alergia' => 'Alergia',
        'condicion_cronica' => 'Condición crónica',
        'medicacion_continua' => 'Medicación continua',
        'otra' => 'Otra alerta',
    ];

    /**
     * Tipo de nodo del Grafo I que admite cada tipo de alerta: una alergia o una medicación
     * se cruzan con un fármaco (y la alergia también con una sustancia); «otra» no se enlaza.
     */
    public const NODOS_POR_TIPO = [
        'alergia' => ['farmaco', 'sustancia'],
        'medicacion_continua' => ['farmaco'],
        'condicion_cronica' => ['condicion'],
        'otra' => [],
    ];

    public static function tipo(mixed $valor): string
    {
        if (!is_string($valor) || !isset(self::TIPOS[$valor])) {
            throw new InvalidArgumentException('Elige el tipo de alerta.');
        }
        return $valor;
    }

    public static function descripcion(mixed $valor): string
    {
        $texto = is_string($valor) ? trim(preg_replace('/\s+/u', ' ', $valor) ?? '') : '';
        if ($texto === '') {
            throw new InvalidArgumentException('Describe la alergia o la alerta.');
        }
        if (mb_strlen($texto) > self::DESCRIPCION_MAX) {
            throw new InvalidArgumentException('La descripción admite hasta ' . self::DESCRIPCION_MAX . ' caracteres.');
        }
        if (preg_match('/^[' . self::CARACTERES . ']+$/uD', $texto) !== 1) {
            throw new InvalidArgumentException('La descripción tiene caracteres no permitidos.');
        }
        return $texto;
    }

    /** Tipos de nodo que se pueden enlazar a este tipo de alerta. */
    public static function tiposDeNodo(string $tipo): array
    {
        return self::NODOS_POR_TIPO[$tipo] ?? [];
    }

    public static function busqueda(mixed $valor): string
    {
        $texto = is_string($valor) ? trim(preg_replace('/\s+/u', ' ', $valor) ?? '') : '';
        return mb_substr($texto, 0, self::BUSQUEDA_MAX);
    }

    /** Atributos del campo de descripción: la misma regla que el servidor (validación y restricción de entrada). */
    public static function atributosDescripcion(): string
    {
        return 'required maxlength="' . self::DESCRIPCION_MAX . '" data-caracteres="' . self::CARACTERES . '" data-espacios="normalizar"';
    }
}
