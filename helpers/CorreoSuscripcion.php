<?php
require_once __DIR__ . '/../config/EmailService.php';

final class CorreoSuscripcion
{
    public static function enviar(array $aviso): bool
    {
        $correo = new EmailService();
        $contenido = '<p>' . htmlspecialchars($aviso['mensaje'], ENT_QUOTES, 'UTF-8') . '</p>';
        $html = $correo->obtenerPlantillaBaseHTML($aviso['nombre_completo'], $aviso['asunto'], $contenido);
        return $correo->enviarCorreoPersonalizado($aviso['email'], $aviso['nombre_completo'], $aviso['asunto'], $html);
    }
}
