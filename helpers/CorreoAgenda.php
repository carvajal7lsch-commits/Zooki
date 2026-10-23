<?php
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../models/MarcaClinica.php';

/** C4: el worker recibe datos reconstruidos y autorizados, nunca un correo del cliente. */
final class CorreoAgenda
{
    public static function enviar(array $cita): bool
    {
        $titulos = ['NUEVA_CITA' => 'Cita confirmada', 'CITA_REPROGRAMADA' => 'Cita reprogramada', 'CITA_CANCELADA' => 'Cita cancelada', 'CITA_REASIGNADA' => 'Cambio de veterinario confirmado', 'ESTIMACION_CITA' => 'Actualización de hora estimada', 'REPROGRAMAR_NECESARIO' => 'Tu cita requiere reprogramación'];
        $titulo = $titulos[$cita['tipo']] ?? 'Actualización de cita';
        $contenido = self::contenido($cita, $titulo);
        $correo = new EmailService();
        $html = $correo->obtenerPlantillaBaseHTML($cita['propietario_nombre'], $titulo, $contenido, null, null, MarcaClinica::delRegistro($cita));
        return $correo->enviarCorreoPersonalizado($cita['email'], $cita['propietario_nombre'], $titulo, $html);
    }

    public static function contenido(array $cita, string $titulo): string
    {
        $h = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $estimacion = empty($cita['inicio_estimado']) ? '' : '<dt>Inicio estimado</dt><dd>' . $h($cita['inicio_estimado']) . '</dd>';
        $alternativas = ($cita['tipo'] ?? '') === 'REPROGRAMAR_NECESARIO' ? '<p>En Mis citas, elige Reprogramar para consultar las alternativas vigentes de tu clínica y confirmar un nuevo horario. Si no encuentras un espacio compatible, contacta a la clínica. Tu cita conserva su hora pactada mientras se acuerda el cambio.</p>' : '';
        return $alternativas . '<p>' . $h($titulo) . '</p><dl><dt>Mascota</dt><dd>' . $h($cita['mascota_nombre'])
            . '</dd><dt>Fecha</dt><dd>' . $h($cita['fecha']) . '</dd><dt>Hora pactada</dt><dd>' . $h($cita['hora_pactada'] ?? $cita['hora'])
            . '</dd>' . $estimacion . '<dt>Veterinario</dt><dd>' . $h($cita['veterinario_nombre']) . '</dd></dl>';
    }
}
