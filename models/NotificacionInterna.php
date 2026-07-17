<?php
require_once __DIR__ . '/../config/Database.php';

class NotificacionInterna {
    private $conn;
    private $table_name = "notificaciones_internas";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Crear notificación para un usuario específico (ej. un veterinario)
    public function crearParaUsuario($doc_usuario, $tipo, $titulo, $mensaje, $enlace = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (doc_usuario, tipo, titulo, mensaje, enlace) 
                  VALUES (:doc_usuario, :tipo, :titulo, :mensaje, :enlace)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':doc_usuario', $doc_usuario);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':mensaje', $mensaje);
        $stmt->bindParam(':enlace', $enlace);
        
        return $stmt->execute();
    }

    // Crear notificación para todo un rol (ej. todos los admins = rol 1)
    public function crearParaRol($id_rol_destino, $tipo, $titulo, $mensaje, $enlace = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_rol_destino, tipo, titulo, mensaje, enlace) 
                  VALUES (:id_rol_destino, :tipo, :titulo, :mensaje, :enlace)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id_rol_destino', $id_rol_destino);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':mensaje', $mensaje);
        $stmt->bindParam(':enlace', $enlace);
        
        return $stmt->execute();
    }

    // Obtener notificaciones para un usuario (incluyendo las dirigidas a su rol)
    public function obtenerParaUsuario($doc_usuario, $id_rol, $limit = 10) {
        // Obtenemos las de este usuario específico + las dirigidas a su rol
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE doc_usuario = :doc_usuario 
                     OR id_rol_destino = :id_rol 
                  ORDER BY fecha_creacion DESC 
                  LIMIT :limit";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc_usuario', $doc_usuario);
        $stmt->bindParam(':id_rol', $id_rol);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener número de notificaciones no leídas
    public function contarNoLeidas($doc_usuario, $id_rol) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                  WHERE leida = 0 
                    AND (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)";
                    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc_usuario', $doc_usuario);
        $stmt->bindParam(':id_rol', $id_rol);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Marcar como leída
    public function marcarLeida($id_notificacion) {
        $query = "UPDATE " . $this->table_name . " 
                  SET leida = 1 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_notificacion);
        
        return $stmt->execute();
    }
    
    // Marcar todas como leídas para un usuario/rol
    public function marcarTodasLeidas($doc_usuario, $id_rol) {
        $query = "UPDATE " . $this->table_name . " 
                  SET leida = 1 
                  WHERE leida = 0 
                    AND (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc_usuario', $doc_usuario);
        $stmt->bindParam(':id_rol', $id_rol);
        
        return $stmt->execute();
    }
}
?>
