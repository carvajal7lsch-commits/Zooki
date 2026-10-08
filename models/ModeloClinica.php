<?php
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/AccesoDenegado.php';
require_once __DIR__ . '/Auditoria.php';

/** RNF-11: resolver el alcance en los modelos y fallar cerrado sin contexto. */
abstract class ModeloClinica
{
    /**
     * C6: clínica elegida por el propietario en el portal, ya validada por
     * enClinicaDelPropietario(). null = la del contexto activo.
     */
    private ?int $clinicaDelPortal = null;

    public function __construct(protected PDO $conn) {}

    protected function clinica(): int
    {
        if ($this->clinicaDelPortal !== null) {
            return $this->clinicaDelPortal;
        }
        $id = Contexto::clinicaActiva();
        if ($id === null || $id < 1) {
            throw new AccesoDenegado(403, 'Selecciona un contexto de clínica.');
        }
        return $id;
    }

    /**
     * C6 / RE-5.3.5: en el portal no hay clínica activa; el propietario elige
     * una. Se valida aquí, y no en quien llama, que sea de un vínculo activo
     * suyo con una clínica activa; si no, 403 auditado. Devuelve una copia
     * del modelo acotada a esa clínica.
     */
    public function enClinicaDelPropietario(int $idClinica): static
    {
        $contexto = Contexto::actual();
        if ($contexto === null || $contexto['tipo'] !== Contexto::PROPIETARIO) {
            throw new AccesoDenegado(403, 'Esta acción es del portal del propietario.');
        }

        $sql = "SELECT 1 FROM propietario_clinica pc
            JOIN clinicas c ON c.id_clinica = pc.id_clinica
            WHERE pc.id_propietario = ? AND pc.id_clinica = ? AND pc.estado = 'activo' AND c.estado = 'activa'";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([Contexto::idUsuario(), $idClinica]);
        if (!$consulta->fetchColumn()) {
            $this->denegarAcceso('clinicas', $idClinica, 'Clínica sin vínculo activo del propietario (RE-5.3.5)');
        }

        $copia = clone $this;
        $copia->clinicaDelPortal = $idClinica;
        return $copia;
    }

    /** Un modelo que este usa por dentro queda con la misma clínica (la del portal, si la hay). */
    protected function conMismoAlcance(ModeloClinica $modelo): ModeloClinica
    {
        $modelo->clinicaDelPortal = $this->clinicaDelPortal;
        return $modelo;
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

    /** Estado del vínculo de la mascota con la clínica ('activo', 'inactivo') o null si nunca se vinculó. */
    protected function vinculoDeLaMascota(int $idMascota): ?string
    {
        $consulta = $this->conn->prepare('SELECT estado FROM mascota_clinica WHERE id_mascota = ? AND id_clinica = ?');
        $consulta->execute([$idMascota, $this->clinica()]);
        $estado = $consulta->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    /**
     * Lectura de la historia (RN-113, RN-115): la mascota está o estuvo
     * vinculada a la clínica. Con el vínculo inactivo (el propietario se
     * desvinculó) la clínica solo lee sus propios registros; nunca vinculada,
     * 403 auditado. Devuelve estado, propietario y el estado del vínculo.
     */
    protected function exigirMascotaVinculada(int $idMascota): array
    {
        $sql = 'SELECT m.id_mascota, m.estado, m.id_propietario, mc.estado AS vinculo
            FROM mascotas m
            JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota
            WHERE m.id_mascota = ? AND mc.id_clinica = ?';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idMascota, $this->clinica()]);
        $mascota = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($mascota === false) {
            $this->denegarAcceso('mascotas', $idMascota, 'Mascota no vinculada a la clínica activa (RN-110, RN-113)');
        }
        return $mascota;
    }

    /**
     * RN-207 y RN-115: un registro nuevo (acto clínico o cita) solo sobre una
     * mascota con vínculo activo y activa. Con el vínculo inactivo la clínica
     * ya no registra nada (403 auditado); una mascota dada de baja conserva
     * su historia, pero no admite registros.
     */
    protected function exigirMascotaActiva(int $idMascota): array
    {
        $mascota = $this->exigirMascotaVinculada($idMascota);
        if ($mascota['vinculo'] !== 'activo') {
            $this->denegarAcceso('mascotas', $idMascota, 'Registro nuevo sobre una mascota desvinculada: solo lectura (RN-115)');
        }
        if ((int) $mascota['estado'] !== 1) {
            throw new InvalidArgumentException('La mascota está inactiva: conserva su historia, pero no admite registros nuevos.');
        }
        return $mascota;
    }
}
