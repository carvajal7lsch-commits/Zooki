<?php
require_once __DIR__ . '/ReglaAtencion.php';
require_once __DIR__ . '/../models/AtencionesEnCurso.php';
require_once __DIR__ . '/../models/NotificacionInterna.php';

/**
 * RN-409 — Vigila las atenciones que se quedan abiertas.
 *
 * · 10 minutos después de la hora de fin, si la atención sigue en curso, avisa
 *   al veterinario una sola vez, en sus notificaciones y por correo.
 * · Terminado el día de la cita, la pasa a "sin cerrar" y le avisa de nuevo.
 *
 * Lo ejecutan la tarea programada (scripts/vigilar_atenciones.php, cada 5
 * minutos) y la agenda del calendario al cargarse, para que funcione aunque la
 * tarea no esté programada. Cada aviso se reclama con una escritura atómica
 * (sello o cambio de estado), así que si los dos revisan a la vez solo uno lo
 * envía.
 *
 * Recorre todas las clínicas (AtencionesEnCurso); cada aviso queda en la
 * clínica de su cita y va al veterinario asignado por id_usuario.
 */
final class VigilanteAtenciones
{
    private AtencionesEnCurso $atenciones;
    private NotificacionInterna $notificaciones;
    private $correo = null;

    /** @var callable(array, string, string): void|null envío del correo; las pruebas lo reemplazan */
    private $enviarCorreo;

    public function __construct(PDO $db, ?callable $enviarCorreo = null)
    {
        $this->atenciones = new AtencionesEnCurso($db);
        $this->notificaciones = new NotificacionInterna($db);
        $this->enviarCorreo = $enviarCorreo;
    }

    /** @return array{avisadas: int, sin_cerrar: int} */
    public function revisar(DateTimeImmutable $ahora): array
    {
        $resultado = ['avisadas' => 0, 'sin_cerrar' => 0];

        foreach ($this->atenciones->listar() as $cita) {
            try {
                $mascota = $cita['mascota_nombre'] ?: 'la mascota';

                if (ReglaAtencion::quedaSinCerrar($cita, $ahora)) {
                    if ($this->atenciones->marcarSinCerrar((int) $cita['id_cita'])) {
                        $resultado['sin_cerrar']++;
                        $this->avisar($cita, 'ATENCION_SIN_CERRAR', "Atención sin cerrar: $mascota",
                            "La atención de $mascota del " . date('d/m/Y', strtotime($cita['fecha']))
                            . ' terminó el día sin cerrarse. Registra su consulta para completarla.');
                    }
                } elseif (ReglaAtencion::debeAvisarAbierta($cita, $ahora)) {
                    if ($this->atenciones->sellarAviso((int) $cita['id_cita'], $ahora->format('Y-m-d H:i:s'))) {
                        $resultado['avisadas']++;
                        $fin = ReglaAtencion::fin($cita['fecha'], $cita['hora'], $cita['hora_fin'] ?? null, $cita['duracion_minutos'] ?? null);
                        $this->avisar($cita, 'ATENCION_ABIERTA', "Atención abierta: $mascota",
                            "La atención de $mascota debía terminar a las " . $fin->format('g:i A')
                            . ' y sigue abierta. Registra su consulta para completarla.');
                    }
                }
            } catch (Throwable $e) {
                error_log('Vigilancia de la atención de la cita ' . $cita['id_cita'] . ': ' . $e->getMessage());
            }
        }

        return $resultado;
    }

    /** Notificación interna y correo al veterinario. Un fallo del correo no deshace el aviso. */
    private function avisar(array $cita, string $tipo, string $titulo, string $mensaje): void
    {
        $enlace = 'index.php?action=vet_atencion&id_cita=' . (int) $cita['id_cita'];
        $this->notificaciones->crearParaUsuario(
            (int) $cita['id_clinica'],
            (int) $cita['id_veterinario'],
            $tipo,
            $titulo,
            $mensaje,
            $enlace,
            (int) $cita['id_cita']
        );

        if (empty($cita['veterinario_email'])) {
            return;
        }
        if ($this->enviarCorreo !== null) {
            ($this->enviarCorreo)($cita, $titulo, $mensaje);
            return;
        }

        try {
            if ($this->correo === null) {
                require_once __DIR__ . '/../config/EmailService.php';
                $this->correo = new EmailService();
            }
            $base = $this->urlApp();
            $html = $this->correo->obtenerPlantillaBaseHTML(
                $cita['veterinario_nombre'] ?? '',
                $titulo,
                '<p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">' . htmlspecialchars($mensaje) . '</p>',
                $base !== '' ? 'Abrir la atención' : null,
                $base !== '' ? "$base/$enlace" : null
            );
            $this->correo->limpiarDirecciones();
            $this->correo->enviarCorreoPersonalizado($cita['veterinario_email'], $cita['veterinario_nombre'] ?? '', $titulo, $html);
        } catch (Throwable $e) {
            error_log('No se pudo enviar el correo de atención abierta (cita ' . $cita['id_cita'] . '): ' . $e->getMessage());
        }
    }

    private function urlApp(): string
    {
        $archivo = __DIR__ . '/../.env';
        $env = file_exists($archivo) ? parse_ini_file($archivo) : [];
        return rtrim((string) ($env['APP_URL'] ?? ''), '/');
    }
}
