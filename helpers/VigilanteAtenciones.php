<?php
require_once __DIR__ . '/ReglaAtencion.php';
require_once __DIR__ . '/../models/Cita.php';
require_once __DIR__ . '/../models/NotificacionInterna.php';

/**
 * RN-410 — Vigila las atenciones que se quedan abiertas.
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
 */
final class VigilanteAtenciones
{
    private Cita $citas;
    private NotificacionInterna $notificaciones;
    private $correo = null;

    public function __construct(PDO $db)
    {
        $this->citas = new Cita($db);
        $this->notificaciones = new NotificacionInterna($db);
    }

    /** @return array{avisadas: int, sin_cerrar: int} */
    public function revisar(DateTimeImmutable $ahora): array
    {
        $resultado = ['avisadas' => 0, 'sin_cerrar' => 0];

        foreach ($this->citas->getAtencionesEnCurso() as $cita) {
            try {
                $mascota = $cita['mascota_nombre'] ?: 'la mascota';

                if (ReglaAtencion::quedaSinCerrar($cita, $ahora)) {
                    if ($this->citas->marcarSinCerrar($cita['id_cita'])) {
                        $resultado['sin_cerrar']++;
                        $this->avisar($cita, 'ATENCION_SIN_CERRAR', "Atención sin cerrar: $mascota",
                            "La atención de $mascota del " . date('d/m/Y', strtotime($cita['fecha']))
                            . ' terminó el día sin cerrarse. Registra la consulta o ciérrala sin consulta.');
                    }
                } elseif (ReglaAtencion::debeAvisarAbierta($cita, $ahora)) {
                    if ($this->citas->sellarAvisoAtencionAbierta($cita['id_cita'], $ahora->format('Y-m-d H:i:s'))) {
                        $resultado['avisadas']++;
                        $fin = ReglaAtencion::fin($cita['fecha'], $cita['hora'], $cita['hora_fin'] ?? null, $cita['duracion_minutos'] ?? null);
                        $this->avisar($cita, 'ATENCION_ABIERTA', "Atención abierta: $mascota",
                            "La atención de $mascota debía terminar a las " . $fin->format('g:i A')
                            . ' y sigue abierta. Si ya terminaste, registra la consulta; si no se atendió, ciérrala sin consulta.');
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
        $this->notificaciones->crearParaUsuario($cita['doc_veterinario'], $tipo, $titulo, $mensaje, $enlace, (int) $cita['id_cita']);

        if (empty($cita['veterinario_email'])) {
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
