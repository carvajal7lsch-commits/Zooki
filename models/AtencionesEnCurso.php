<?php
/**
 * RN-409: atenciones en curso de TODAS las clínicas, para la vigilancia.
 *
 * Excepción explícita a RNF-11: la recorre la tarea programada, que no tiene
 * contexto activo, y también el calendario al cargarse. No devuelve nada a
 * ninguna pantalla: cada aviso se escribe en la clínica de su cita y va al
 * veterinario asignado (id_usuario).
 */
final class AtencionesEnCurso
{
    public function __construct(private PDO $conn) {}

    public function listar(): array
    {
        $sql = "SELECT c.id_cita, c.id_clinica, c.id_veterinario, c.fecha, c.hora, c.hora_fin,
                c.duracion_minutos, c.estado, c.aviso_atencion_abierta,
                m.nombre AS mascota_nombre, v.nombre_completo AS veterinario_nombre, v.email AS veterinario_email
            FROM citas c
            LEFT JOIN mascotas m ON m.id_mascota = c.id_mascota
            LEFT JOIN usuarios v ON v.id_usuario = c.id_veterinario
            WHERE c.estado = 'en_curso'";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sella el aviso de atención abierta. Solo escribe si no se había
     * enviado: si la tarea y el calendario revisan a la vez, uno solo lo
     * reclama y lo envía (una sola vez, RN-409).
     */
    public function sellarAviso(int $idCita, string $ahora): bool
    {
        $consulta = $this->conn->prepare("UPDATE citas SET aviso_atencion_abierta = ?
            WHERE id_cita = ? AND estado = 'en_curso' AND aviso_atencion_abierta IS NULL");
        $consulta->execute([$ahora, $idCita]);
        return $consulta->rowCount() === 1;
    }

    /** Terminado el día, una atención que sigue en curso queda "sin cerrar". */
    public function marcarSinCerrar(int $idCita): bool
    {
        $consulta = $this->conn->prepare("UPDATE citas SET estado = 'sin_cerrar' WHERE id_cita = ? AND estado = 'en_curso'");
        $consulta->execute([$idCita]);
        return $consulta->rowCount() === 1;
    }
}
