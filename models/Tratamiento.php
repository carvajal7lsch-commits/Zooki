<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/** HU-2.4: tratamientos; heredan la clínica de su consulta (RN-112). */
class Tratamiento extends ModeloHistoria
{
    /**
     * RE-2.4.1: valida un tratamiento antes de guardarlo. fecha_inicio es
     * obligatoria (NOT NULL en v2): el Grafo I la usará para saber si la
     * medicación sigue vigente (RN-211).
     */
    public static function validar(array $datos): array
    {
        $medicamento = ValidadorClinico::textoRequerido($datos['medicamento'] ?? null, 150);
        $dosis = ValidadorClinico::textoRequerido($datos['dosis'] ?? null, 100);
        $via = ValidadorClinico::textoRequerido($datos['via_administracion'] ?? null, 50);
        $duracion = ValidadorClinico::textoRequerido($datos['duracion'] ?? null, 100);

        if ($medicamento === null) {
            throw new InvalidArgumentException('Cada tratamiento necesita el medicamento.');
        }
        if ($dosis === null || $via === null || $duracion === null) {
            throw new InvalidArgumentException(sprintf('El tratamiento "%s" necesita dosis, vía de administración y duración.', $medicamento));
        }

        $fechaInicio = ValidadorClinico::fecha($datos['fecha_inicio'] ?? null);
        if ($fechaInicio === null) {
            throw new InvalidArgumentException(sprintf('El tratamiento "%s" necesita una fecha de inicio válida.', $medicamento));
        }

        return [
            'medicamento' => $medicamento,
            'dosis' => $dosis,
            'via_administracion' => $via,
            'duracion' => $duracion,
            'fecha_inicio' => $fechaInicio,
            'observaciones' => ValidadorClinico::textoOpcional($datos['observaciones'] ?? null, 500),
        ];
    }

    /**
     * Agrega un tratamiento a una consulta de la clínica activa. Lo llama
     * Consulta::registrar dentro de su transacción.
     */
    public function insertarEnConsulta(int $idConsulta, array $datos): int
    {
        $this->exigirConsultaPropia($idConsulta);
        $limpio = self::validar($datos);

        $sql = 'INSERT INTO tratamientos
            (id_consulta, medicamento, dosis, via_administracion, duracion, fecha_inicio, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?)';
        $this->conn->prepare($sql)->execute([
            $idConsulta,
            $limpio['medicamento'],
            $limpio['dosis'],
            $limpio['via_administracion'],
            $limpio['duracion'],
            $limpio['fecha_inicio'],
            $limpio['observaciones'],
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

    private function exigirConsultaPropia(int $idConsulta): void
    {
        $consulta = $this->conn->prepare('SELECT 1 FROM consultas WHERE id_consulta = ? AND id_clinica = ?');
        $consulta->execute([$idConsulta, $this->clinica()]);
        if (!$consulta->fetchColumn()) {
            $this->denegarAcceso('consultas', $idConsulta, 'Tratamiento sobre una consulta de otra clínica (RN-112)');
        }
    }
}
