<?php
require_once __DIR__ . '/../helpers/Transaccion.php';

/**
 * Casos que revisa el super-administrador (HU-0.3). Un solo lugar para
 * abrirlos, sin repetir un caso que sigue abierto.
 */
final class CasoSoporte
{
    public function __construct(private PDO $db) {}

    /**
     * RN-G24 y Modelos §13.1 (nodo D2): el titular pidió un documento que ya
     * usa otra cuenta. El caso guarda quién lo pidió y qué documento, para
     * que soporte resuelva el posible duplicado; al titular nunca se le dice
     * de quién es. $regla dice desde dónde llegó (cambio de documento o
     * completar el perfil).
     */
    public function documentoDuplicado(int $idUsuario, string $documento, string $regla): void
    {
        $descripcion = 'El titular solicitó el documento ' . $documento . ' ya asociado a otra cuenta (' . $regla . ').';
        Transaccion::ejecutar($this->db, function () use ($idUsuario, $descripcion): void {
            $this->bloquearUsuario($idUsuario);
            $this->db->prepare("INSERT INTO casos_soporte (tipo, id_usuario, descripcion)
                SELECT 'documento_duplicado', ?, ? WHERE NOT EXISTS
                (SELECT 1 FROM casos_soporte WHERE tipo = 'documento_duplicado' AND id_usuario = ? AND descripcion = ? AND estado = 'abierto')")
                ->execute([$idUsuario, $descripcion, $idUsuario, $descripcion]);
        });
    }

    /** Serializa dos solicitudes del mismo titular: así el caso no se duplica. */
    private function bloquearUsuario(int $idUsuario): void
    {
        $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?' . $bloqueo);
        $stmt->execute([$idUsuario]);
        $stmt->fetchColumn();
    }
}
