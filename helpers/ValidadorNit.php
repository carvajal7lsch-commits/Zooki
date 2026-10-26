<?php

/** RE-0.1.3: módulo 11 de la DIAN, con pesos alineados a la derecha. */
final class ValidadorNit
{
    public const MENSAJE = 'El dígito de verificación no corresponde. Revísalo en tu RUT.';
    public const NUMERO_MAX = 14;
    public const PRESENTACION_MAX = self::NUMERO_MAX + 2;

    /** RE-0.1.3, rectificación del dueño 2026-10-26: sin guion es el número completo; con guion incluye DV. */
    public static function separar(string $valor): ?array
    {
        $valor = str_replace(['.', ' '], '', trim($valor));
        if (preg_match('/^[0-9]{2,' . self::NUMERO_MAX . '}$/D', $valor) && ltrim($valor, '0') !== '') {
            return ['numero' => $valor, 'dv' => (string) self::digito($valor)];
        }
        if (!preg_match('/^([0-9]{1,' . self::NUMERO_MAX . '})-([0-9])$/D', $valor, $partes)) return null;
        if (ltrim($partes[1], '0') === '') return null;
        return ['numero' => $partes[1], 'dv' => $partes[2]];
    }

    /** Formato ya compuesto por número y DV: idempotente para los valores guardados. */
    public static function normalizar(string $valor): string
    {
        $valor = str_replace(['.', '-', ' '], '', trim($valor));
        return ltrim($valor, '0');
    }

    /** RE-0.1.2/3: convertir la entrada una sola vez antes de guardar o buscar duplicados. */
    public static function normalizarEntrada(string $valor): string
    {
        $error = self::validar($valor);
        if ($error !== null) throw new InvalidArgumentException($error);
        $partes = self::separar($valor);
        return self::normalizar($partes['numero'] . '-' . $partes['dv']);
    }

    public static function digito(string $numero): int
    {
        if (!preg_match('/^\d{1,14}$/D', $numero)) {
            throw new InvalidArgumentException(self::MENSAJE);
        }
        $pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67];
        $suma = 0;
        foreach (str_split(strrev($numero)) as $indice => $digito) {
            $suma += (int) $digito * $pesos[$indice];
        }
        $residuo = $suma % 11;
        return $residuo < 2 ? $residuo : 11 - $residuo;
    }

    public static function validar(string $valor): ?string
    {
        $partes = self::separar($valor);
        return $partes !== null && self::digito($partes['numero']) === (int) $partes['dv'] ? null : self::MENSAJE;
    }
}
