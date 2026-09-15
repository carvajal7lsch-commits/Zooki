<?php
class Tratamiento {
    private $conn;
    private $table_name = "tratamientos";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function insert($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_consulta, medicamento, dosis, via_administracion, duracion, observaciones) 
                  VALUES (:id_consulta, :medicamento, :dosis, :via, :duracion, :obs)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_consulta', $data['id_consulta']);
        $stmt->bindParam(':medicamento', $data['medicamento']);
        $stmt->bindParam(':dosis', $data['dosis']);
        $stmt->bindParam(':via', $data['via_administracion']);
        $stmt->bindParam(':duracion', $data['duracion']);
        $stmt->bindParam(':obs', $data['observaciones']);
        
        return $stmt->execute();
    }

    public function findByConsulta($id_consulta) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_consulta = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_consulta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * HU-08 / M2-10 — Tratamientos de varias consultas de una sola vez, para
     * que el historial no dispare una consulta por cada entrada.
     */
    public function findByConsultas(array $ids) {
        if (empty($ids)) return [];

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . $this->table_name . " WHERE id_consulta IN ($marcas) ORDER BY id_tratamiento"
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
