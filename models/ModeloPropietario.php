<?php
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/AccesoDenegado.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * C6: modelos del portal del propietario (RN-G01, RN-G02). El contexto
 * propietario no tiene clínica activa: el alcance es la persona. Sus
 * mascotas son globales (RN-110); nada de otro propietario es accesible y
 * cada intento queda en auditoría (sin clínica) con un 403.
 */
abstract class ModeloPropietario
{
    public function __construct(protected PDO $conn) {}

    /** id_usuario del propietario del contexto activo; otro contexto, 403. */
    protected function propietario(): int
    {
        $contexto = Contexto::actual();
        if ($contexto === null || $contexto['tipo'] !== Contexto::PROPIETARIO) {
            throw new AccesoDenegado(403, 'Esta acción es del portal del propietario.');
        }
        return (int) Contexto::idUsuario();
    }

    /** RN-G02: la mascota es del propietario; si no, 403 auditado. */
    protected function exigirMascotaPropia(int $idMascota): array
    {
        $consulta = $this->conn->prepare('SELECT * FROM mascotas WHERE id_mascota = ? AND id_propietario = ?');
        $consulta->execute([$idMascota, $this->propietario()]);
        $mascota = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($mascota === false) {
            $this->denegar('mascotas', $idMascota, 'Mascota de otro propietario (RN-G02)');
        }
        return $mascota;
    }

    protected function denegar(string $tabla, $idRegistro, string $motivo): never
    {
        $auditoria = new Auditoria($this->conn);
        $auditoria->log(Contexto::idUsuario(), 'OTHER', $tabla, $idRegistro, null, null, $motivo, null);
        throw new AccesoDenegado(403, 'No tienes acceso a estos datos.');
    }

    protected function auditar(string $accion, string $tabla, $idRegistro, ?array $antes, ?array $despues, string $descripcion, ?int $idClinica = null): void
    {
        $auditoria = new Auditoria($this->conn);
        $auditoria->log(Contexto::idUsuario(), $accion, $tabla, $idRegistro, $antes, $despues, $descripcion, $idClinica);
    }
}
