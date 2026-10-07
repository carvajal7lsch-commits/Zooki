<?php
require_once __DIR__ . '/ModeloClinica.php';

/**
 * Regla única de visibilidad de la historia clínica (RN-112, RN-113, RF-2.6).
 *
 * - La mascota tiene que estar vinculada (activa) a la clínica activa; si no,
 *   no se muestra nada y la petición da 403 auditado.
 * - Vacunas y desparasitaciones: toda clínica vinculada ve las de todas las
 *   clínicas, con la que las aplicó.
 * - Consultas, tratamientos y archivos: los propios siempre; los de otra
 *   clínica solo si el propietario autorizó a la clínica activa
 *   (propietario_clinica.autoriza_historia_compartida). Sin autorización ni
 *   siquiera se revela que existen.
 * - Solo la clínica que creó un registro lo modifica (paraModificar en cada
 *   modelo).
 */
abstract class ModeloHistoria extends ModeloClinica
{
    /**
     * RN-113: la mascota está vinculada a la clínica activa. Devuelve su
     * estado y su propietario.
     */
    protected function exigirMascotaVinculada(int $idMascota): array
    {
        $sql = "SELECT m.id_mascota, m.estado, m.id_propietario
            FROM mascotas m
            JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota
            WHERE m.id_mascota = ? AND mc.id_clinica = ? AND mc.estado = 'activo'";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idMascota, $this->clinica()]);
        $mascota = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($mascota === false) {
            $this->denegarAcceso('mascotas', $idMascota, 'Historia clínica de una mascota no vinculada a la clínica (RN-113)');
        }
        return $mascota;
    }

    /**
     * RN-207: un acto clínico nuevo solo sobre una mascota vinculada y activa.
     * Una mascota dada de baja conserva su historia, pero no admite registros.
     */
    protected function exigirMascotaActiva(int $idMascota): array
    {
        $mascota = $this->exigirMascotaVinculada($idMascota);
        if ((int) $mascota['estado'] !== 1) {
            throw new InvalidArgumentException('La mascota está inactiva: conserva su historia, pero no admite registros nuevos.');
        }
        return $mascota;
    }

    /**
     * RN-113: condición SQL para que la consulta con alias $alias sea visible
     * en la clínica activa. Devuelve la condición y sus parámetros.
     *
     * @return array{0: string, 1: list<int>}
     */
    protected function consultaVisible(string $alias): array
    {
        $clinica = $this->clinica();
        $condicion = "EXISTS (
                SELECT 1 FROM mascota_clinica mcv
                WHERE mcv.id_mascota = $alias.id_mascota AND mcv.id_clinica = ? AND mcv.estado = 'activo'
            ) AND (
                $alias.id_clinica = ?
                OR EXISTS (
                    SELECT 1 FROM mascotas mv
                    JOIN propietario_clinica pc ON pc.id_propietario = mv.id_propietario
                    WHERE mv.id_mascota = $alias.id_mascota
                      AND pc.id_clinica = ?
                      AND pc.estado = 'activo'
                      AND pc.autoriza_historia_compartida = 1
                )
            )";
        return [$condicion, [$clinica, $clinica, $clinica]];
    }

    /** Marca en cada fila si es de la clínica activa (lo demás es de solo lectura). */
    protected function marcarOrigen(array $filas): array
    {
        $clinica = $this->clinica();
        foreach ($filas as &$fila) {
            $fila['es_propia'] = (int) $fila['id_clinica'] === $clinica;
        }
        unset($fila);
        return $filas;
    }

    /** RN-112: el veterinario que registra es la persona del contexto activo. */
    protected function veterinarioActual(): int
    {
        $idUsuario = Contexto::idUsuario();
        if ($idUsuario === null) {
            throw new AccesoDenegado(401, 'Sesion expirada. Inicia sesion nuevamente.');
        }
        return $idUsuario;
    }
}
