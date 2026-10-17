<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../helpers/ValidadorPrescripcion.php';
require_once __DIR__ . '/../helpers/ValidadorTratamiento.php';

/** HU-2.4: tratamientos; heredan la clínica de su consulta (RN-112). */
class Tratamiento extends ModeloHistoria
{
    /**
     * RE-2.4.1: valida un tratamiento antes de guardarlo. fecha_inicio es
     * obligatoria (NOT NULL en v2): el Grafo I la usará para saber si la
     * medicación sigue vigente (RN-211). Cada error dice su fila y su campo
     * (tratamientos.N.campo) para mostrarlo junto a él (M2 C5b).
     */
    public static function validar(array $datos, int $indice = 0): array
    {
        $medicamento = ValidadorClinico::textoRequerido($datos['medicamento'] ?? null, ValidadorTratamiento::MEDICAMENTO_MAX);
        if ($medicamento === null) {
            ValidadorTratamiento::error($indice, 'medicamento', 'Cada tratamiento necesita el medicamento.');
        }
        $dosis = ValidadorClinico::textoRequerido($datos['dosis'] ?? null, ValidadorTratamiento::DOSIS_MAX);
        if ($dosis === null) {
            ValidadorTratamiento::error($indice, 'dosis', sprintf('El tratamiento "%s" necesita la dosis.', $medicamento));
        }
        $via = $datos['via_administracion'] ?? null;
        if (!in_array($via, ValidadorTratamiento::VIAS, true)) {
            ValidadorTratamiento::error($indice, 'via_administracion', sprintf('El tratamiento "%s" necesita una vía de administración de la lista.', $medicamento));
        }
        $duracion = ValidadorClinico::textoRequerido($datos['duracion'] ?? null, ValidadorTratamiento::DURACION_MAX);
        if ($duracion === null) {
            ValidadorTratamiento::error($indice, 'duracion', sprintf('El tratamiento "%s" necesita la duración.', $medicamento));
        }

        $fechaInicio = ValidadorClinico::fecha($datos['fecha_inicio'] ?? null);
        if ($fechaInicio === null) {
            ValidadorTratamiento::error($indice, 'fecha_inicio', sprintf('El tratamiento "%s" necesita una fecha de inicio válida.', $medicamento));
        }
        $fechaFin = $datos['fecha_fin'] ?? null;
        if ($fechaFin === '') {
            $fechaFin = null;
        }
        if ($fechaFin !== null) {
            $fechaFin = ValidadorClinico::fecha($fechaFin);
            if ($fechaFin === null || $fechaFin < $fechaInicio) {
                ValidadorTratamiento::error($indice, 'fecha_fin', 'La fecha de fin del tratamiento debe ser válida y no anterior al inicio.');
            }
        }

        return [
            'medicamento' => $medicamento,
            'dosis' => $dosis,
            'via_administracion' => $via,
            'duracion' => $duracion,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'observaciones' => ValidadorClinico::textoOpcional($datos['observaciones'] ?? null, ValidadorTratamiento::OBSERVACIONES_MAX),
        ] + ValidadorPrescripcion::fila($datos, $indice);
    }

    /**
     * Agrega un tratamiento a una consulta de la clínica activa. Lo llama
     * Consulta::registrar dentro de su transacción.
     */
    public function insertarEnConsulta(int $idConsulta, array $datos, int $indice = 0): int
    {
        $this->exigirConsultaPropia($idConsulta, 'Tratamiento sobre una consulta de otra clínica (RN-112)');
        $limpio = self::validar($datos, $indice);
        $idNodo = null;
        if ($limpio['farmaco'] !== null) {
            $nodo = $this->conn->prepare("SELECT id_nodo FROM grafo_nodos WHERE codigo = ? AND tipo = 'farmaco' AND activo = 1");
            $nodo->execute([$limpio['farmaco']]);
            $idNodo = $nodo->fetchColumn();
            if ($idNodo === false) {
                ValidadorPrescripcion::error($indice, 'farmaco', 'El fármaco debe ser un nodo activo del catálogo.');
            }
        }

        $sql = 'INSERT INTO tratamientos
            (id_consulta, medicamento, dosis, via_administracion, duracion, fecha_inicio, observaciones, id_nodo_farmaco, fecha_fin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->conn->prepare($sql)->execute([
            $idConsulta,
            $limpio['medicamento'],
            $limpio['dosis'],
            $limpio['via_administracion'],
            $limpio['duracion'],
            $limpio['fecha_inicio'],
            $limpio['observaciones'],
            $idNodo,
            $limpio['fecha_fin'],
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /**
     * HU-2.5 (M2-10): tratamientos visibles de varias consultas en una sola
     * consulta SQL, agrupados por consulta. Los de una consulta no visible
     * no salen (RN-113).
     */
    public function findByConsultas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$visible, $parametros] = $this->consultaVisible('c');
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT t.* FROM tratamientos t
            JOIN consultas c ON c.id_consulta = t.id_consulta
            WHERE t.id_consulta IN ($marcas) AND $visible
            ORDER BY t.id_tratamiento";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([...array_map('intval', $ids), ...$parametros]);

        $porConsulta = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porConsulta[(int) $fila['id_consulta']][] = $fila;
        }
        return $porConsulta;
    }

    /** RN-112 / RE-2.10.4: punto único por el que pasa cualquier modificación. */
    public function paraModificar(int $idTratamiento): array
    {
        $sql = 'SELECT t.* FROM tratamientos t
            JOIN consultas c ON c.id_consulta = t.id_consulta
            WHERE t.id_tratamiento = ? AND c.id_clinica = ?';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idTratamiento, $this->clinica()]);
        $tratamiento = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($tratamiento === false) {
            $this->denegarAcceso('tratamientos', $idTratamiento, 'Modificación de un tratamiento de otra clínica (RN-112)');
        }
        return $tratamiento;
    }
}
