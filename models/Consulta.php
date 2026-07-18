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
                  (id_cita, id_mascota, doc_veterinario, fecha_hora, motivo_consulta, anamnesis, peso, temperatura, frecuencia_cardiaca, diagnostico, plan_tratamiento) 
                  VALUES (:id_cita, :id_mascota, :doc_veterinario, NOW(), :motivo, :anamnesis, :peso, :temperatura, :fc, :diagnostico, :plan)";
        
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
        $stmt->bindParam(':diagnostico', $data['diagnostico']);
        $stmt->bindParam(':plan', $data['plan_tratamiento']);

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
}
?>
