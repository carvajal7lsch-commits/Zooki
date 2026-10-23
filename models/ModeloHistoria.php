<?php
require_once __DIR__ . '/ModeloClinica.php';

/**
 * Regla única de visibilidad de la historia clínica (RN-112, RN-113, RN-115,
 * RF-2.6).
 *
 * - La mascota tiene que estar o haber estado vinculada a la clínica activa;
 *   nunca vinculada, no se muestra nada y la petición da 403 auditado.
 * - Con el vínculo activo: vacunas y desparasitaciones de todas las
 *   clínicas, con la que las aplicó; consultas, tratamientos y archivos
 *   propios siempre y los de otra clínica solo si el propietario autorizó a
 *   la clínica activa (propietario_clinica.autoriza_historia_compartida).
 *   Sin autorización ni siquiera se revela que existen.
 * - Con el vínculo inactivo (el propietario se desvinculó, RN-115): solo
 *   los registros propios, en solo lectura; nunca los de otras clínicas.
 * - Solo la clínica que creó un registro lo modifica (paraModificar en cada
 *   modelo), y nadie registra nada nuevo sin vínculo activo
 *   (exigirMascotaActiva).
 */
abstract class ModeloHistoria extends ModeloClinica
{
    /** B1.1: una conciliación no puede dejar que un alta clínica tardía escriba sobre P. */
    protected function registrarEnPacienteActual(int $mascota, callable $escribir): mixed
    {
        require_once __DIR__ . '/../helpers/CoordinadorAgenda.php';
        $this->exigirMascotaActiva($mascota);
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), fn () => ['usuarios' => [Contexto::idUsuario()], 'mascotas' => [$mascota]], function () use ($mascota,$escribir): mixed {
                $this->exigirMascotaActiva($mascota);
                $q = $this->conn->prepare("SELECT 1 FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario JOIN clinicas c ON c.id_clinica=uc.id_clinica WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1 AND c.estado='activa'");
                $q->execute([$this->clinica(),Contexto::idUsuario()]);
                if (Contexto::rolActivo() !== Roles::VETERINARIO || !$q->fetchColumn()) {
                    throw new AccesoDenegado(403,'No tienes acceso a esta acción clínica.');
                }
                return $escribir();
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('mascotas',$mascota,'Alta clínica denegada después de esperar o conciliar (RN-207)');
        }
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
        // Lo propio: con cualquier vínculo (activo, o inactivo tras desvincularse).
        // Lo de otra clínica: vínculo activo y autorización del propietario.
        $condicion = "(
                $alias.id_clinica = ?
                AND EXISTS (
                    SELECT 1 FROM mascota_clinica mcp
                    WHERE mcp.id_mascota = $alias.id_mascota AND mcp.id_clinica = ?
                )
            ) OR (
                EXISTS (
                    SELECT 1 FROM mascota_clinica mcv
                    WHERE mcv.id_mascota = $alias.id_mascota AND mcv.id_clinica = ? AND mcv.estado = 'activo'
                )
                AND EXISTS (
                    SELECT 1 FROM mascotas mv
                    JOIN propietario_clinica pc ON pc.id_propietario = mv.id_propietario
                    WHERE mv.id_mascota = $alias.id_mascota
                      AND pc.id_clinica = ?
                      AND pc.estado = 'activo'
                      AND pc.autoriza_historia_compartida = 1
                )
            )";
        return ['(' . $condicion . ')', [$clinica, $clinica, $clinica, $clinica]];
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

    /** RN-112: lo que cuelga de una consulta solo se escribe en una consulta de la clínica activa. */
    protected function exigirConsultaPropia(int $idConsulta, string $motivo): void
    {
        $consulta = $this->conn->prepare('SELECT 1 FROM consultas WHERE id_consulta = ? AND id_clinica = ?');
        $consulta->execute([$idConsulta, $this->clinica()]);
        if (!$consulta->fetchColumn()) {
            $this->denegarAcceso('consultas', $idConsulta, $motivo);
        }
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
