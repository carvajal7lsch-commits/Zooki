<?php

/** RE-0.1.3: módulo 11 de la DIAN, con pesos alineados a la derecha. */
final class ValidadorNit
{
    public const MENSAJE = 'Escribe un NIT válido, incluido su dígito de verificación.';

    public static function normalizar(string $valor): string
    {
        $valor = str_replace(['.', '-', ' '], '', trim($valor));
        return ltrim($valor, '0');
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
        if (!preg_match('/^[\d. ]+(?:-\d)?$/D', trim($valor))) {
            return self::MENSAJE;
        }
        $nit = self::normalizar($valor);
        if (!preg_match('/^\d{2,15}$/D', $nit)) {
            return self::MENSAJE;
        }
        return self::digito(substr($nit, 0, -1)) === (int) substr($nit, -1) ? null : self::MENSAJE;
    }
}
