<?php
require_once __DIR__ . '/../models/Recordatorio.php';

/** HU-3.2/3.6: correo inyectable para verificar el cron sin contactar SMTP. */
final class EnviadorRecordatorios
{
    public function __construct(private Recordatorio $recordatorios, private object $correo)
    {
    }

    public function ejecutar(DateTimeImmutable $ahora, string $appUrl): int
    {
        $hoy = VentanaRecordatorio::hoy($ahora);
        $manana = (new DateTimeImmutable($hoy))->modify('+1 day')->format('Y-m-d');
        $avisos = $this->recordatorios->dosisPorRecordar($hoy);
        foreach ($this->recordatorios->citasDeManana($manana) as $cita) {
            $avisos[] = $cita + [
                'tipo_entidad' => 'cita',
                'id_entidad' => $cita['id_cita'],
                'tipo_notificacion' => 'recordatorio_cita_24h',
            ];
        }
        $intentos = 0;
        foreach ($avisos as $aviso) {
            if (!$this->recordatorios->puedeEnviar((int) $aviso['id_clinica'], $aviso['tipo_entidad'], (int) $aviso['id_entidad'], $aviso['tipo_notificacion'])) {
                continue;
            }
            [$asunto, $contenido, $cta] = $this->contenido($aviso);
            $cuerpo = $this->correo->obtenerPlantillaBaseHTML($aviso['prop_nombre'], $asunto, $contenido, $cta, $appUrl);
            try {
                $this->correo->limpiarDirecciones();
                $enviado = $this->correo->enviarCorreoPersonalizado($aviso['email'], $aviso['prop_nombre'], $asunto, $cuerpo);
            } catch (Throwable $error) {
                // RE-3.6.2: un fallo de transporte también consume un intento.
                $enviado = false;
                error_log('Recordatorio: fallo de transporte: ' . $error->getMessage());
            }
            $this->recordatorios->registrar($aviso + [
                'asunto' => $asunto,
                'mensaje' => $cuerpo,
                'enviado' => $enviado,
            ]);
            $intentos++;
            $estado = $enviado ? 'enviado' : 'error';
            echo "Clínica {$aviso['id_clinica']}: {$aviso['tipo_entidad']} {$aviso['id_entidad']} — $estado\n";
        }
        return $intentos;
    }

    /** RE-3.2.2: la clínica original identifica cada correo, con texto escapado. */
    private function contenido(array $aviso): array
    {
        $clinica = htmlspecialchars($aviso['clinica_nombre'], ENT_QUOTES, 'UTF-8');
        $mascota = htmlspecialchars($aviso['mascota_nombre'], ENT_QUOTES, 'UTF-8');
        if ($aviso['tipo_entidad'] === 'cita') {
            $fecha = (new DateTimeImmutable($aviso['fecha']))->format('d/m/Y');
            $hora = htmlspecialchars(substr($aviso['hora'], 0, 5), ENT_QUOTES, 'UTF-8');
            $vet = htmlspecialchars($aviso['vet_nombre'], ENT_QUOTES, 'UTF-8');
            $motivo = htmlspecialchars($aviso['motivo'], ENT_QUOTES, 'UTF-8');
            $asunto = "{$aviso['clinica_nombre']} — Recordatorio de cita: {$aviso['mascota_nombre']}";
            $contenido = "<p><strong>$clinica</strong> te recuerda la cita de <strong>$mascota</strong>.</p>"
                . "<p>Fecha: $fecha · Hora: $hora · Veterinario: $vet</p><p>Motivo: $motivo</p>"
                . '<p>Si no puedes asistir, comunícate con la clínica para reprogramar o cancelar.</p>';
            return [$asunto, $contenido, 'Ver mis citas'];
        }
        $fecha = (new DateTimeImmutable($aviso['fecha_proxima']))->format('d/m/Y');
        $tipo = $aviso['tipo_entidad'] === 'vacuna' ? 'vacunación' : 'desparasitación';
        $item = htmlspecialchars($aviso['nombre_item'], ENT_QUOTES, 'UTF-8');
        $asunto = "{$aviso['clinica_nombre']} — Recordatorio de $tipo: {$aviso['mascota_nombre']}";
        $contenido = "<p><strong>$clinica</strong> te recuerda la próxima $tipo de <strong>$mascota</strong>.</p>"
            . "<p>$item · Próxima aplicación: $fecha</p>"
            . '<p>Agenda una cita con la clínica para mantener al día la prevención.</p>';
        return [$asunto, $contenido, 'Agendar cita'];
    }
}
