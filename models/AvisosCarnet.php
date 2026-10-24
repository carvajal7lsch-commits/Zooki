<?php
require_once __DIR__ . '/../helpers/CorreoCarnet.php';

/** Reserva bajo el bloqueo de mascota; el SMTP se realiza después de confirmar. */
final class AvisosCarnet
{
    private Closure $correo;

    public function __construct(private PDO $db, ?callable $correo = null)
    {
        $this->correo = Closure::fromCallable($correo ?? [CorreoCarnet::class, 'enviar']);
    }

    public function reservar(int $idMascota, string $tipo, DateTimeImmutable $ahora, ?string $mapa = null): array
    {
        $consulta = $this->db->prepare('SELECT u.id_usuario, u.email, u.nombre_completo, m.nombre FROM mascotas m JOIN usuarios u ON u.id_usuario = m.id_propietario WHERE m.id_mascota = ?');
        $consulta->execute([$idMascota]);
        $titular = $consulta->fetch(PDO::FETCH_ASSOC);
        $escapar = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $asunto = $tipo === 'carnet_ubicacion' ? 'Ubicación compartida desde el carnet' : 'Alguien consultó el carnet de tu mascota';
        $mensaje = '<p>Mascota: ' . $escapar($titular['nombre']) . '</p><p>Fecha y hora: ' . $ahora->format('d/m/Y H:i:s') . ' (America/Bogota)</p>';
        if ($mapa !== null) {
            $mensaje .= '<p><a href="' . $escapar($mapa) . '">Ver ubicación en OpenStreetMap</a></p>';
        }
        $this->db->prepare("INSERT INTO notificaciones (id_clinica, id_usuario, tipo_entidad, id_entidad, destinatario_email, tipo_notificacion, asunto, mensaje, fecha_envio, estado)
            VALUES (NULL, ?, 'mascota', ?, ?, ?, ?, ?, ?, 'pendiente')")->execute([$titular['id_usuario'], $idMascota, $titular['email'], $tipo, $asunto, $mensaje, $ahora->format('Y-m-d H:i:s')]);
        return ['id' => (int) $this->db->lastInsertId(), 'email' => $titular['email'], 'nombre' => $titular['nombre_completo'], 'asunto' => $asunto, 'mensaje' => $mensaje];
    }

    public function enviar(?array $aviso): void
    {
        if ($aviso === null) {
            return;
        }
        try {
            $enviado = (bool) ($this->correo)($aviso);
        } catch (Throwable $error) {
            $enviado = false;
            error_log('Correo del carnet: fallo de transporte.');
        }
        try {
            $this->db->prepare('UPDATE notificaciones SET estado = ? WHERE id_notificacion = ?')->execute([$enviado ? 'enviado' : 'error', $aviso['id']]);
        } catch (Throwable $error) {
            // El registro reservado queda pendiente; nunca romper la información de emergencia.
            error_log('Correo del carnet: no se pudo actualizar el estado del envío.');
        }
    }
}
