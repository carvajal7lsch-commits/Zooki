<?php
require_once __DIR__ . '/ModeloHistoria.php';

/**
 * HU-2.3: adjuntos de una consulta. Heredan la clínica de su consulta
 * (RN-112) y se ven con la misma regla que ella (RN-113).
 */
class ArchivoClinico extends ModeloHistoria
{
    /** Lo llama Consulta::registrar dentro de su transacción. */
    public function insertarEnConsulta(int $idConsulta, array $datos): int
    {
        $this->exigirConsultaPropia($idConsulta);

        $sql = 'INSERT INTO archivos_clinicos
            (id_consulta, nombre_original, nombre_servidor, ruta_archivo, tipo_archivo, extension, tamano_bytes, descripcion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $this->conn->prepare($sql)->execute([
            $idConsulta,
            $datos['nombre_original'],
            $datos['nombre_servidor'],
            $datos['ruta_archivo'],
            $datos['tipo_archivo'],
            $datos['extension'],
            $datos['tamano_bytes'],
            $datos['descripcion'] ?? null,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /** HU-2.5 (M2-10): adjuntos visibles de varias consultas, agrupados. */
    public function deConsultas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$visible, $parametros] = $this->consultaVisible('c');
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT a.id_archivo, a.id_consulta, a.nombre_original, a.extension, a.tipo_archivo,
                a.tamano_bytes, a.descripcion, a.fecha_subida
            FROM archivos_clinicos a
            JOIN consultas c ON c.id_consulta = a.id_consulta
            WHERE a.id_consulta IN ($marcas) AND $visible
            ORDER BY a.id_archivo";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([...array_map('intval', $ids), ...$parametros]);

        $porConsulta = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porConsulta[(int) $fila['id_consulta']][] = $fila;
        }
        return $porConsulta;
    }

    /**
     * RE-2.3.3: el adjunto que pide public/ver_archivo.php, según el contexto
     * activo. Si no corresponde, 403 auditado; un id inexistente responde
     * igual, para no confirmar que existe en otra clínica.
     */
    public function paraDescargar(int $idArchivo): array
    {
        $contexto = Contexto::actual();
        if ($contexto !== null && $contexto['tipo'] === Contexto::CLINICA) {
            return $this->paraClinica($idArchivo);
        }
        if ($contexto !== null && $contexto['tipo'] === Contexto::PROPIETARIO) {
            return $this->paraPropietario($idArchivo);
        }
        throw new AccesoDenegado(403, 'No tienes acceso a este registro clínico.');
    }

    /** RN-112 / RE-2.10.4: punto único por el que pasa cualquier modificación. */
    public function paraModificar(int $idArchivo): array
    {
        $sql = 'SELECT a.* FROM archivos_clinicos a
            JOIN consultas c ON c.id_consulta = a.id_consulta
            WHERE a.id_archivo = ? AND c.id_clinica = ?';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idArchivo, $this->clinica()]);
        $archivo = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($archivo === false) {
            $this->denegarAcceso('archivos_clinicos', $idArchivo, 'Modificación de un adjunto de otra clínica (RN-112)');
        }
        return $archivo;
    }

    /** RN-113: la misma regla que la consulta a la que pertenece. */
    private function paraClinica(int $idArchivo): array
    {
        [$visible, $parametros] = $this->consultaVisible('c');
        $sql = "SELECT a.id_archivo, a.nombre_original, a.nombre_servidor, a.extension
            FROM archivos_clinicos a
            JOIN consultas c ON c.id_consulta = a.id_consulta
            WHERE a.id_archivo = ? AND $visible";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idArchivo, ...$parametros]);
        $archivo = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($archivo === false) {
            $this->denegarAcceso('archivos_clinicos', $idArchivo, 'Adjunto clínico fuera de la historia visible (RN-113)');
        }
        return $archivo;
    }

    /**
     * RN-G02: el propietario solo ve los adjuntos de sus mascotas. El resto
     * del portal (qué clínicas, qué consultas) se completa en C6.
     */
    private function paraPropietario(int $idArchivo): array
    {
        $sql = 'SELECT a.id_archivo, a.nombre_original, a.nombre_servidor, a.extension
            FROM archivos_clinicos a
            JOIN consultas c ON c.id_consulta = a.id_consulta
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            WHERE a.id_archivo = ? AND m.id_propietario = ?';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idArchivo, Contexto::idUsuario()]);
        $archivo = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($archivo === false) {
            $auditoria = new Auditoria($this->conn);
            $auditoria->log(Contexto::idUsuario(), 'OTHER', 'archivos_clinicos', $idArchivo, null, null, 'Adjunto clínico de una mascota ajena (RN-G02)', null);
            throw new AccesoDenegado(403, 'No tienes acceso a este registro clínico.');
        }
        return $archivo;
    }

    private function exigirConsultaPropia(int $idConsulta): void
    {
        $consulta = $this->conn->prepare('SELECT 1 FROM consultas WHERE id_consulta = ? AND id_clinica = ?');
        $consulta->execute([$idConsulta, $this->clinica()]);
        if (!$consulta->fetchColumn()) {
            $this->denegarAcceso('consultas', $idConsulta, 'Adjunto sobre una consulta de otra clínica (RN-112)');
        }
    }
}
