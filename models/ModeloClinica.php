<?php
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/AccesoDenegado.php';
require_once __DIR__ . '/Auditoria.php';

/** RNF-11: resolver el alcance en los modelos y fallar cerrado sin contexto. */
abstract class ModeloClinica
{
    public function __construct(protected PDO $conn) {}

    protected function clinica(): int
    {
        $id = Contexto::clinicaActiva();
        if ($id === null || $id < 1) {
            throw new AccesoDenegado(403, 'Selecciona un contexto de clínica.');
        }
        return $id;
    }

    /**
     * RE-T.15.2: deja el intento en auditoría y responde 403 sin distinguir
     * «no existe» de «es de otra clínica», para no confirmar ids ajenos.
     *
     * Dentro de una transacción la auditoría se deshace con ella: quien
     * llama vuelve a auditar después del rollback.
     */
    protected function denegarAcceso(string $tabla, $idRegistro, string $motivo): never
    {
        $auditoria = new Auditoria($this->conn);
        $auditoria->log(Contexto::idUsuario(), 'OTHER', $tabla, $idRegistro, null, null, $motivo);
        throw new AccesoDenegado(403, 'No tienes acceso a este registro clínico.');
    }

    /**
     * RN-110 / RN-113: la mascota está vinculada (activa) a la clínica activa;
     * si no, 403 auditado. Devuelve su estado y su propietario.
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
            $this->denegarAcceso('mascotas', $idMascota, 'Mascota no vinculada a la clínica activa (RN-110, RN-113)');
        }
        return $mascota;
    }

    /**
     * RN-207: un registro nuevo (acto clínico o cita) solo sobre una mascota
     * vinculada y activa.
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
}
