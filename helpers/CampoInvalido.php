<?php
/**
 * M5 C2b: un dato de formulario inválido, con el campo y el aviso corto para
 * mostrar bajo él (una línea, M0.1). El mensaje completo va al resumen del
 * formulario. RespuestaJson lo devuelve como 422 con `campo` y `aviso`.
 */
final class CampoInvalido extends InvalidArgumentException
{
    public function __construct(public readonly string $campo, public readonly string $aviso, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
