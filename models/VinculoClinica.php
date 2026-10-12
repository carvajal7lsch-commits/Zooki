<?php
/**
 * RN-115 y RE-5.13.3: el vínculo de un propietario con una clínica se
 * desactiva igual lo pida el propietario (portal, HU-5.13) o la clínica
 * (Clientes, decisión del usuario en «C1 — Revisión» de M5).
 *
 * - No se desactiva con citas sin resolver en esa clínica (decisión del
 *   usuario en C6 y, para la clínica, en C1.1).
 * - Al desactivarlo se quita la autorización de historia compartida y sus
 *   mascotas quedan inactivas en esa clínica: la clínica solo lee lo suyo,
 *   no registra nada nuevo y no salen recordatorios de ella. Se reactivan
 *   cuando la mascota se agenda o se atiende allí con el vínculo confirmado
 *   (RN-110, Mascota::vincular).
 *
 * Quien llama abre la transacción y deja la auditoría con su descripción.
 */
final class VinculoClinica
{
    /** Citas que impiden desactivar el vínculo. */
    public const CITAS_SIN_RESOLVER = ['pendiente', 'confirmada', 'en_curso', 'sin_cerrar'];

    public function __construct(private PDO $db) {}

    public function citasSinResolver(int $idPropietario, int $idClinica): int
    {
        $marcas = implode(', ', array_fill(0, count(self::CITAS_SIN_RESOLVER), '?'));
        $consulta = $this->db->prepare("SELECT COUNT(*) FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            WHERE c.id_clinica = ? AND m.id_propietario = ? AND c.estado IN ($marcas)");
        $consulta->execute([$idClinica, $idPropietario, ...self::CITAS_SIN_RESOLVER]);
        return (int) $consulta->fetchColumn();
    }

    /** Desactiva el vínculo y las mascotas del propietario en la clínica; devuelve cuántas mascotas quedaron inactivas. */
    public function desactivar(int $idPropietario, int $idClinica): int
    {
        $this->db->prepare("UPDATE propietario_clinica
            SET estado = 'inactivo', autoriza_historia_compartida = 0, fecha_autorizacion = NULL
            WHERE id_propietario = ? AND id_clinica = ?")->execute([$idPropietario, $idClinica]);

        $mascotas = $this->db->prepare("UPDATE mascota_clinica SET estado = 'inactivo'
            WHERE id_clinica = ? AND estado = 'activo'
              AND id_mascota IN (SELECT id_mascota FROM mascotas WHERE id_propietario = ?)");
        $mascotas->execute([$idClinica, $idPropietario]);
        return $mascotas->rowCount();
    }
}
