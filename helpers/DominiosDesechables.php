<?php

/** RE-0.1.5: lista mantenida en el repositorio, sin servicios de terceros. */
final class DominiosDesechables
{
    public static function validar(string $email): ?string
    {
        $dominio = mb_strtolower(substr($email, strrpos($email, '@') + 1));
        $lineas = file(__DIR__ . '/../config/dominios_desechables.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false) {
            throw new RuntimeException('No se pudo cargar la lista de dominios desechables.');
        }
        foreach ($lineas as $linea) {
            $rechazado = mb_strtolower(trim($linea));
            if ($rechazado !== '' && ($dominio === $rechazado || str_ends_with($dominio, '.' . $rechazado))) {
                return 'Usa un correo permanente; no se admiten dominios desechables.';
            }
        }
        return null;
    }
}
