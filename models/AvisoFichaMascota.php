<?php
require_once __DIR__ . '/ModeloClinica.php';

/** RE-1.4.5: entrega y estado del aviso, siempre desde la clínica activa. */
final class AvisoFichaMascota extends ModeloClinica
{
    public function enviarPendientes(int $idMascota,?callable $enviar=null): void
    {
        if ($enviar === null) {
            require_once __DIR__ . '/../config/EmailService.php';
            $correo = new EmailService();
            $enviar = function (array $aviso) use ($correo): bool {
                $correo->limpiarDirecciones();
                return $correo->enviarCorreoPersonalizado($aviso['destinatario_email'],'Propietario',
                    $aviso['asunto'],'<p>' . htmlspecialchars($aviso['mensaje'],ENT_QUOTES,'UTF-8') . '</p>');
            };
        }
        $stmt=$this->conn->prepare("SELECT * FROM notificaciones WHERE id_clinica=? AND id_entidad=?
            AND tipo_entidad='mascota' AND tipo_notificacion='cambio_ficha' AND estado IN ('pendiente','error')");
        $stmt->execute([$this->clinica(),$idMascota]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $aviso) {
            try { $estado=$enviar($aviso) ? 'enviado' : 'error'; }
            catch (Throwable $e) { error_log('Aviso de ficha: ' . $e->getMessage()); $estado='error'; }
            $this->conn->prepare('UPDATE notificaciones SET estado=? WHERE id_notificacion=? AND id_clinica=?')
                ->execute([$estado,$aviso['id_notificacion'],$this->clinica()]);
        }
    }
}
