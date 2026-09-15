<?php
class Consulta {
    private $conn;
    private $table_name = "consultas";

    public $id_consulta;
    public $id_mascota;
    public $doc_veterinario;
    public $fecha_hora;
    public $motivo_consulta;
    public $anamnesis;
    public $peso;
    public $temperatura;
    public $frecuencia_cardiaca;
    public $diagnostico;
    public $plan_tratamiento;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Registrar nueva consulta
    public function insert($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_cita, id_mascota, doc_veterinario, fecha_hora, motivo_consulta, anamnesis, peso, temperatura, frecuencia_cardiaca, frecuencia_respiratoria, diagnostico, plan_tratamiento, observaciones)
                  VALUES (:id_cita, :id_mascota, :doc_veterinario, NOW(), :motivo, :anamnesis, :peso, :temperatura, :fc, :fr, :diagnostico, :plan, :observaciones)";
        
        $stmt = $this->conn->prepare($query);

        $idCita = isset($data['id_cita']) && !empty($data['id_cita']) ? $data['id_cita'] : null;
        $stmt->bindParam(':id_cita', $idCita);
        $stmt->bindParam(':id_mascota', $data['id_mascota']);
        $stmt->bindParam(':doc_veterinario', $data['doc_veterinario']);
        $stmt->bindParam(':motivo', $data['motivo_consulta']);
        $stmt->bindParam(':anamnesis', $data['anamnesis']);
        $stmt->bindParam(':peso', $data['peso']);
        $stmt->bindParam(':temperatura', $data['temperatura']);
        $stmt->bindParam(':fc', $data['frecuencia_cardiaca']);
        $stmt->bindValue(':fr', $data['frecuencia_respiratoria'] ?? null);
        $stmt->bindParam(':diagnostico', $data['diagnostico']);
        $stmt->bindParam(':plan', $data['plan_tratamiento']);
        $stmt->bindValue(':observaciones', $data['observaciones'] ?? '');

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Obtener consulta vinculada a una cita
    public function findByCita($id_cita) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_cita = :id_cita LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_cita', $id_cita);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener todas las consultas de la clínica (Global)
    public function findAll() {
        $query = "SELECT c.*, 
                         m.nombre as nombre_mascota, 
                         e.nombre_especie, 
                         p.nombre_completo as nombre_propietario,
                         u.nombre_completo as veterinario 
                  FROM " . $this->table_name . " c
                  JOIN mascotas m ON c.id_mascota = m.id_mascota
                  JOIN especies e ON m.id_especie = e.id_especie
                  JOIN usuarios p ON m.doc_propietario = p.documento
                  JOIN usuarios u ON c.doc_veterinario = u.documento
                  ORDER BY c.fecha_hora DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener historial cronológico de una mascota
    public function findByMascota($id_mascota) {
        $query = "SELECT c.*, u.nombre_completo as veterinario 
                  FROM " . $this->table_name . " c
                  JOIN usuarios u ON c.doc_veterinario = u.documento
                  WHERE c.id_mascota = :id 
                  ORDER BY c.fecha_hora DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_mascota);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Verificar si es la primera consulta para generar HC
    public function countByMascota($id_mascota) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE id_mascota = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_mascota);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    // Guardar metadatos de archivo adjunto
    public function saveArchivo($data) {
        $query = "INSERT INTO archivos_clinicos 
                  (id_consulta, nombre_original, nombre_servidor, ruta_archivo, tipo_archivo, extension, tamano_bytes, descripcion) 
                  VALUES (:id_consulta, :nombre_orig, :nombre_serv, :ruta, :tipo, :ext, :tamano, :desc)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_consulta', $data['id_consulta']);
        $stmt->bindParam(':nombre_orig', $data['nombre_original']);
        $stmt->bindParam(':nombre_serv', $data['nombre_servidor']);
        $stmt->bindParam(':ruta', $data['ruta_archivo']);
        $stmt->bindParam(':tipo', $data['tipo_archivo']);
        $stmt->bindParam(':ext', $data['extension']);
        $stmt->bindParam(':tamano', $data['tamano_bytes']);
        $stmt->bindParam(':desc', $data['descripcion']);
        
        return $stmt->execute();
    }
    public function getArchivosByConsulta($id_consulta) {
        $query = "SELECT * FROM archivos_clinicos WHERE id_consulta = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_consulta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Datos de un adjunto junto con el dueño de la mascota a la que pertenece.
     *
     * M2-04 (RN-204 / RN-G02) — Sirve para decidir si quien pide el archivo
     * tiene derecho a verlo. Antes ver_archivo.php aceptaba un nombre de
     * archivo suelto y lo servía a cualquier sesión válida, sin mirar de quién
     * era la mascota: un propietario podía leer los adjuntos de pacientes
     * ajenos, y los nombres eran adivinables porque seguían el patrón
     * CLI_{id_consulta}_{timestamp}_{i}.
     */
    public function getArchivoConDueno($id_archivo) {
        $query = "SELECT a.id_archivo, a.id_consulta, a.nombre_original, a.nombre_servidor,
                         a.extension, a.tipo_archivo,
                         c.id_mascota, m.doc_propietario
                  FROM archivos_clinicos a
                  JOIN " . $this->table_name . " c ON a.id_consulta = c.id_consulta
                  JOIN mascotas m ON c.id_mascota = m.id_mascota
                  WHERE a.id_archivo = :id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', (int) $id_archivo, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    /**
     * HU-08 / M2-10 — Adjuntos de varias consultas en una sola consulta SQL.
     *
     * El historial los pedía consulta por consulta: con 100 consultas eran 100
     * viajes a la base solo para los archivos, y el criterio pide cargar en
     * menos de 3 segundos.
     */
    public function getArchivosDeConsultas(array $ids) {
        if (empty($ids)) return [];

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare(
            "SELECT * FROM archivos_clinicos WHERE id_consulta IN ($marcas) ORDER BY id_archivo"
        );
        $stmt->execute(array_map('intval', $ids));

        $porConsulta = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porConsulta[(int) $fila['id_consulta']][] = $fila;
        }

        return $porConsulta;
    }
}
?>
