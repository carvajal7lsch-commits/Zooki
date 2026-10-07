<?php
require_once __DIR__ . '/CatalogoClinica.php';
class Desparasitacion {
    private $conn;
    private $table_name = "desparasitaciones";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function insert($data) {
        // Calculate next date based on periodicity
        $fecha_app = new DateTime($data['fecha_aplicacion']);
        if ($data['periodicidad'] == 'mensual') {
            $fecha_app->modify('+1 month');
        } elseif ($data['periodicidad'] == 'trimestral') {
            $fecha_app->modify('+3 months');
        } elseif ($data['periodicidad'] == 'semestral') {
            $fecha_app->modify('+6 months');
        }
        $fecha_proxima = $fecha_app->format('Y-m-d');

        $query = "INSERT INTO " . $this->table_name . " 
                  (id_mascota, tipo, producto, periodicidad, fecha_aplicacion, fecha_proxima, observaciones) 
                  VALUES (:id_mascota, :tipo, :producto, :periodicidad, :fecha_app, :fecha_prox, :obs)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $data['id_mascota']);
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':producto', $data['producto']);
        $stmt->bindParam(':periodicidad', $data['periodicidad']);
        $stmt->bindParam(':fecha_app', $data['fecha_aplicacion']);
        $stmt->bindParam(':fecha_prox', $fecha_proxima);
        $stmt->bindParam(':obs', $data['observaciones']);
        
        return $stmt->execute();
    }

    public function getPendientesSemana() {
        $query = "SELECT d.*, m.nombre as nombre_mascota, u.nombre_completo as propietario 
                  FROM " . $this->table_name . " d
                  JOIN mascotas m ON d.id_mascota = m.id_mascota
                  JOIN usuarios u ON m.doc_propietario = u.documento
                  WHERE d.fecha_proxima BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  ORDER BY d.fecha_proxima ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByMascota($id_mascota) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_mascota = :id ORDER BY fecha_aplicacion DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_mascota);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductos(): array {
        return (new CatalogoClinica($this->conn))->productos();
    }

    public function insertarNuevoProducto($nombre_producto, $tipo = 'interna') {
        $query = "INSERT INTO productos_desparasitacion_base (nombre_producto, tipo, estado) VALUES (:nombre, :tipo, 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nombre', $nombre_producto);
        $stmt->bindParam(':tipo', $tipo);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
}
?>
