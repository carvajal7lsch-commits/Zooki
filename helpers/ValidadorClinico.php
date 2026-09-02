<?php
/**
 * Validaciones de entrada para los registros clinicos (HU-35, VD-VAC-01 /
 * VD-HC-03). Antes de esto, ConsultaController, VacunaController y
 * DesparasitacionController tomaban $_POST tal cual y lo insertaban.
 *
 * Son funciones puras a proposito: los metodos registrarAjax() terminan en
 * exit(), asi que la unica forma de cubrir estas reglas con PHPUnit es
 * mantener la logica de validacion fuera del controlador.
 *
 * Convencion: cada validador devuelve el valor ya normalizado (listo para
 * guardar) o null si la entrada no es aceptable. Asi el controlador
 * distingue "no valido" de "vacio opcional" comparando contra null.
 */
class ValidadorClinico
{
    /** Rangos fisiologicos plausibles, acotados ademas por el tipo de columna. */
    public const PESO_MIN = 0.01;
    public const PESO_MAX = 200.0;      // decimal(5,2) admite hasta 999.99
    public const TEMP_MIN = 25.0;
    public const TEMP_MAX = 45.0;       // decimal(4,1)
    public const FC_MIN   = 10;
    public const FC_MAX   = 400;

    /**
     * Identificador de fila: entero estrictamente positivo.
     * Rechaza "3abc", "0", "-1", arreglos y cadenas vacias.
     */
    public static function id($valor): ?int
    {
        if (is_array($valor) || $valor === null || $valor === '') return null;
        if (!is_numeric($valor)) return null;

        $num = $valor + 0;
        if (!is_int($num) && floor($num) != $num) return null;

        $entero = (int) $num;

        return $entero > 0 ? $entero : null;
    }

    /** Fecha real en formato Y-m-d. "2026-02-31" se rechaza. */
    public static function fecha($valor): ?string
    {
        if (!is_string($valor) || trim($valor) === '') return null;

        $valor = trim($valor);
        $d = DateTime::createFromFormat('Y-m-d', $valor);

        // createFromFormat es permisivo: hay que confirmar que la fecha
        // reconstruida sea identica a la recibida.
        if (!$d || $d->format('Y-m-d') !== $valor) return null;

        return $valor;
    }

    /** Fecha valida que ademas no este en el futuro (no se aplica manana). */
    public static function fechaNoFutura($valor): ?string
    {
        $fecha = self::fecha($valor);
        if ($fecha === null) return null;

        return ($fecha <= date('Y-m-d')) ? $fecha : null;
    }

    /**
     * Texto obligatorio: se recorta y se rechaza si queda vacio o si excede
     * el limite de la columna (evita truncado silencioso de MySQL).
     */
    public static function textoRequerido($valor, int $maximo): ?string
    {
        if (!is_string($valor)) return null;

        $texto = trim($valor);
        if ($texto === '') return null;
        if (mb_strlen($texto) > $maximo) return null;

        return $texto;
    }

    /**
     * Texto opcional: devuelve siempre una cadena (vacia si no vino nada) y
     * recorta al maximo de la columna en vez de rechazar, porque son campos
     * de observaciones donde perder el registro completo seria peor.
     */
    public static function textoOpcional($valor, int $maximo): string
    {
        if (!is_string($valor)) return '';

        return mb_substr(trim($valor), 0, $maximo);
    }

    /** Numero decimal dentro de un rango clinico. */
    public static function decimal($valor, float $min, float $max): ?float
    {
        if (is_array($valor) || $valor === null || $valor === '') return null;
        if (!is_numeric($valor)) return null;

        $num = (float) $valor;

        return ($num >= $min && $num <= $max) ? $num : null;
    }

    /** Numero entero dentro de un rango clinico. */
    public static function entero($valor, int $min, int $max): ?int
    {
        if (is_array($valor) || $valor === null || $valor === '') return null;
        if (!is_numeric($valor)) return null;

        $num = $valor + 0;
        if (floor($num) != $num) return null;

        $entero = (int) $num;

        return ($entero >= $min && $entero <= $max) ? $entero : null;
    }

    /** Valor que debe pertenecer a un ENUM de la base de datos. */
    public static function opcion($valor, array $permitidas): ?string
    {
        if (!is_string($valor)) return null;

        $texto = strtolower(trim($valor));

        return in_array($texto, $permitidas, true) ? $texto : null;
    }
}
