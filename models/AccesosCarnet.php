<?php
require_once __DIR__ . '/../helpers/Transaccion.php';

/** RN-505: límite global por IP, incluso para tokens inválidos y ubicaciones. */
final class AccesosCarnet
{
    public const MAXIMO_POR_HORA = 30;

    public function __construct(private PDO $db)
    {
    }

    public function admitir(string $ipHash, DateTimeImmutable $ahora): bool
    {
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $nombre = 'carnet_ip_' . substr($ipHash, 0, 54);
        if ($mysql) {
            // La primera visita tampoco tiene una fila que bloquear: candado por IP.
            $candado = $this->db->prepare('SELECT GET_LOCK(?, 5)');
            $candado->execute([$nombre]);
            if ((int) $candado->fetchColumn() !== 1) {
                throw new RuntimeException('No se pudo reservar el contador del carnet.');
            }
        }
        try {
            return Transaccion::ejecutar($this->db, function () use ($ipHash, $ahora): bool {
                $this->db->prepare('DELETE FROM carnet_accesos WHERE fecha < ?')->execute([$ahora->modify('-24 hours')->format('Y-m-d H:i:s')]);
                $consulta = $this->db->prepare('SELECT COUNT(*) FROM carnet_accesos WHERE ip_hash = ? AND fecha > ?');
                $consulta->execute([$ipHash, $ahora->modify('-1 hour')->format('Y-m-d H:i:s')]);
                // Por encima del límite no se registra: una inundación no hace crecer la tabla.
                if ((int) $consulta->fetchColumn() >= self::MAXIMO_POR_HORA) {
                    return false;
                }
                $this->db->prepare('INSERT INTO carnet_accesos (ip_hash, fecha) VALUES (?, ?)')->execute([$ipHash, $ahora->format('Y-m-d H:i:s')]);
                return true;
            });
        } finally {
            if ($mysql) {
                $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$nombre]);
            }
        }
    }
}
