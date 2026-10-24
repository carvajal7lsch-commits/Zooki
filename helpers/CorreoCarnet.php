<?php
require_once __DIR__ . '/../config/EmailService.php';

/** RN-504: mismo transporte de CorreoAgenda, sin token ni enlaces del carnet. */
final class CorreoCarnet
{
    public static function enviar(array $aviso): bool
    {
        $correo = new EmailService();
        $html = $correo->obtenerPlantillaBaseHTML($aviso['nombre'], $aviso['asunto'], $aviso['mensaje']);
        return $correo->enviarCorreoPersonalizado($aviso['email'], $aviso['nombre'], $aviso['asunto'], $html);
    }
}
