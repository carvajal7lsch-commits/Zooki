<?php
/**
 * Una petición que no se puede atender por falta de sesión, de contexto o de
 * permiso. Security la lanza y el front controller la convierte en la
 * respuesta (JSON o redirección). Que sea una excepción y no un exit() es lo
 * que permite probar la autorización desde PHPUnit.
 */
final class AccesoDenegado extends RuntimeException
{
    /**
     * @param int    $codigo    401 sin sesión, 403 sin permiso
     * @param string $redirigir acción a la que se lleva a una navegación normal
     */
    public function __construct(int $codigo, string $mensaje, private string $redirigir = '')
    {
        parent::__construct($mensaje, $codigo);
        if ($this->redirigir === '') {
            $this->redirigir = $codigo === 401 ? 'login' : 'dashboard';
        }
    }

    public function codigo(): int
    {
        return $this->getCode();
    }

    public function redirigir(): string
    {
        return $this->redirigir;
    }
}
