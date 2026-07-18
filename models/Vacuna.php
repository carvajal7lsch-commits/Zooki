<?php
class Vacuna {
    private $conn;
    private $table_name = "vacunas";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function insert($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_mascota, nombre_vacuna, laboratorio, lote, fecha_aplicacion, fecha_proxima_dosis, observaciones) 
                  VALUES (:id_mascota, :nombre, :lab, :lote, :fecha_app, :fecha_prox, :obs)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $data['id_mascota']);
        $stmt->bindParam(':nombre', $data['nombre_vacuna']);
        $stmt->bindParam(':lab', $data['laboratorio']);
        $stmt->bindParam(':lote', $data['lote']);
        $stmt->bindParam(':fecha_app', $data['fecha_aplicacion']);
        $stmt->bindParam(':fecha_prox', $data['fecha_proxima_dosis']);
        $stmt->bindParam(':obs', $data['observaciones']);
        
        return $stmt->execute();
    }

    public function findByMascota($id_mascota) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_mascota = :id ORDER BY fecha_aplicacion DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_mascota);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendientesSemana() {
        $query = "SELECT v.*, m.nombre as nombre_mascota, u.nombre_completo as propietario 
                  FROM " . $this->table_name . " v
                  JOIN mascotas m ON v.id_mascota = m.id_mascota
                  JOIN usuarios u ON m.doc_propietario = u.documento
                  WHERE v.fecha_proxima_dosis BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  ORDER BY v.fecha_proxima_dosis ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Para HU-20: Panel de vacunaciones pendientes agrupadas por día y especie
    public function getPendientesPorDiaYEspecie() {
        $query = "SELECT 
                    DATE(v.fecha_proxima_dosis) as fecha,
                    DAYNAME(v.fecha_proxima_dosis) as dia_semana,
                    e.nombre_especie as especie,
                    COUNT(*) as total,
                    GROUP_CONCAT(DISTINCT m.nombre SEPARATOR ', ') as mascotas
                  FROM " . $this->table_name . " v
                  JOIN mascotas m ON v.id_mascota = m.id_mascota
                  JOIN especies e ON m.id_especie = e.id_especie
                  WHERE v.fecha_proxima_dosis BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DATE(v.fecha_proxima_dosis), e.id_especie, e.nombre_especie
                  ORDER BY fecha ASC, e.nombre_especie ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVacunasPorEspecie($id_especie) {
        $query = "SELECT vb.id_vacuna_base, vb.nombre_vacuna, vb.descripcion
                  FROM vacunas_base vb
                  JOIN especie_vacunas ev ON vb.id_vacuna_base = ev.id_vacuna_base
                  WHERE ev.id_especie = :id_especie AND vb.estado = 1
                  ORDER BY vb.nombre_vacuna";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_especie', $id_especie);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertarNuevaVacuna($nombre_vacuna, $descripcion = null) {
        $query = "INSERT INTO vacunas_base (nombre_vacuna, descripcion, estado) VALUES (:nombre, :desc, 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nombre', $nombre_vacuna);
        $stmt->bindParam(':desc', $descripcion);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function relacionarVacunaConEspecie($id_vacuna_base, $id_especie) {
        $query = "INSERT INTO especie_vacunas (id_especie, id_vacuna_base) VALUES (:id_esp, :id_vac)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_esp', $id_especie);
        $stmt->bindParam(':id_vac', $id_vacuna_base);
        return $stmt->execute();
    }

    public function insertarNuevoLaboratorio($nombre_laboratorio) {
        $query = "INSERT INTO laboratorios_base (nombre_laboratorio, estado) VALUES (:nombre, 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nombre', $nombre_laboratorio);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
}
?>
