<?php
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../models/MarcaClinica.php';

/** C4: el worker recibe datos reconstruidos y autorizados, nunca un correo del cliente. */
final class CorreoAgenda
{
    public static function enviar(array $cita): bool
    {
        $h = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $titulos = ['NUEVA_CITA' => 'Cita confirmada', 'CITA_REPROGRAMADA' => 'Cita reprogramada', 'CITA_CANCELADA' => 'Cita cancelada'];
        $titulo = $titulos[$cita['tipo']] ?? 'Actualización de cita';
        $contenido = '<p>' . $h($titulo) . '</p><dl><dt>Mascota</dt><dd>' . $h($cita['mascota_nombre'])
            . '</dd><dt>Fecha</dt><dd>' . $h($cita['fecha']) . '</dd><dt>Hora</dt><dd>' . $h($cita['hora'])
            . '</dd><dt>Veterinario</dt><dd>' . $h($cita['veterinario_nombre']) . '</dd></dl>';
        $correo = new EmailService();
        $html = $correo->obtenerPlantillaBaseHTML($cita['propietario_nombre'], $titulo, $contenido, null, null, MarcaClinica::delRegistro($cita));
        return $correo->enviarCorreoPersonalizado($cita['email'], $cita['propietario_nombre'], $titulo, $html);
    }
}
