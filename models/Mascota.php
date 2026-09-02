<?php

class Mascota {
    private $conn;
    private $table_name = "mascotas";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Insertar mascota (usando los nuevos nombres de columna)
    public function insert($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                   (numero_historia_clinica, doc_propietario, nombre, id_especie, id_raza, fecha_nacimiento, peso, sexo, color, url_foto) 
                   VALUES (:numero_historia_clinica, :doc_propietario, :nombre, :id_especie, :id_raza, :fecha_nacimiento, :peso, :sexo, :color, :url_foto)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':numero_historia_clinica', $data['numero_historia_clinica']);
        $stmt->bindParam(':doc_propietario', $data['doc_propietario']);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':id_especie', $data['id_especie']);
        $stmt->bindParam(':id_raza', $data['id_raza']);
        $stmt->bindParam(':fecha_nacimiento', $data['fecha_nacimiento']);
        $stmt->bindParam(':peso', $data['peso']);
        $stmt->bindParam(':sexo', $data['sexo']);
        $stmt->bindParam(':color', $data['color']);
        $stmt->bindParam(':url_foto', $data['url_foto']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Guardar relación de colores
    public function saveColores($id_mascota, $colores) {
        $this->conn->prepare("DELETE FROM mascota_colores WHERE id_mascota = ?")->execute([$id_mascota]);
        if (!empty($colores)) {
            $stmt = $this->conn->prepare("INSERT INTO mascota_colores (id_mascota, id_color) VALUES (?, ?)");
            foreach ($colores as $id_color) {
                $stmt->execute([$id_mascota, $id_color]);
            }
        }
    }

    // Obtener todas las mascotas con nombres (Permisivo)
    public function getAll() {
        $query = "SELECT m.*, u.nombre_completo as propietario_nombre, 
                         e.nombre_especie, r.nombre_raza,
                         GROUP_CONCAT(cb.nombre_color SEPARATOR ', ') as colores_nombres,
                         GROUP_CONCAT(cb.id_color) as colores_ids
                  FROM " . $this->table_name . " m
                  LEFT JOIN usuarios u ON m.doc_propietario = u.documento
                  LEFT JOIN especies e ON m.id_especie = e.id_especie
                  LEFT JOIN razas r ON m.id_raza = r.id_raza
                  LEFT JOIN mascota_colores mc ON m.id_mascota = mc.id_mascota
                  LEFT JOIN colores_base cb ON mc.id_color = cb.id_color
                  GROUP BY m.id_mascota";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener una con nombres (Permisivo)
    public function getById($id) {
        $query = "SELECT m.*, u.nombre_completo as propietario_nombre, 
                         e.nombre_especie, r.nombre_raza,
                         GROUP_CONCAT(cb.id_color) as colores_ids
                  FROM " . $this->table_name . " m
                  LEFT JOIN usuarios u ON m.doc_propietario = u.documento
                  LEFT JOIN especies e ON m.id_especie = e.id_especie
                  LEFT JOIN razas r ON m.id_raza = r.id_raza
                  LEFT JOIN mascota_colores mc ON m.id_mascota = mc.id_mascota
                  LEFT JOIN colores_base cb ON mc.id_color = cb.id_color
                  WHERE m.id_mascota = :id
                  GROUP BY m.id_mascota";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar (usando los nuevos nombres de columna)
    public function update($data) {
        $fields = [
            "nombre = :nombre",
            "id_especie = :id_especie",
            "id_raza = :id_raza",
            "sexo = :sexo",
            "peso = :peso",
            "color = :color",
            "estado = :estado"
        ];

        if (!empty($data['fecha_nacimiento'])) {
            $fields[] = "fecha_nacimiento = :fecha_nacimiento";
        }
        if (!empty($data['url_foto'])) {
            $fields[] = "url_foto = :url_foto";
        }
        if (!empty($data['doc_propietario'])) {
            $fields[] = "doc_propietario = :doc_propietario";
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $fields) . " WHERE id_mascota = :id_mascota";
        $stmt = $this->conn->prepare($query);

        // Bind common fields
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':id_especie', $data['id_especie']);
        $stmt->bindParam(':id_raza', $data['id_raza']);
        $stmt->bindParam(':sexo', $data['sexo']);
        $stmt->bindParam(':peso', $data['peso']);
        $stmt->bindParam(':color', $data['color']);
        $stmt->bindParam(':estado', $data['estado']);
        $stmt->bindParam(':id_mascota', $data['id_mascota']);

        // Bind optional fields
        if (!empty($data['fecha_nacimiento'])) $stmt->bindParam(':fecha_nacimiento', $data['fecha_nacimiento']);
        if (!empty($data['url_foto'])) $stmt->bindParam(':url_foto', $data['url_foto']);
        if (!empty($data['doc_propietario'])) $stmt->bindParam(':doc_propietario', $data['doc_propietario']);

        return $stmt->execute();
    }

    // Actualizar Número de Historia Clínica
    public function actualizarHC($id, $hc) {
        $query = "UPDATE " . $this->table_name . " SET numero_historia_clinica = :hc WHERE id_mascota = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':hc', $hc);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Registro de auditoría
    public function registrarAuditoria($id_mascota, $usuario_doc, $campo, $anterior, $nuevo) {
        $query = "INSERT INTO auditoria_mascotas (id_mascota, usuario_doc, campo_modificado, valor_anterior, valor_nuevo) 
                  VALUES (:id_mascota, :usuario_doc, :campo, :anterior, :nuevo)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $id_mascota);
        $stmt->bindParam(':usuario_doc', $usuario_doc);
        $stmt->bindParam(':campo', $campo);
        $stmt->bindParam(':anterior', $anterior);
        $stmt->bindParam(':nuevo', $nuevo);
        return $stmt->execute();
    }

    // Obtener todas las especies
    public function getEspecies() {
        $query = "SELECT * FROM especies ORDER BY nombre_especie";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Insertar nueva especie
    public function insertEspecie($nombre_especie) {
        $query = "INSERT INTO especies (nombre_especie) VALUES (:nom)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $nombre_especie);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Obtener razas por especie
    public function getRazasByEspecie($id_especie) {
        $query = "SELECT * FROM razas WHERE id_especie = :id ORDER BY nombre_raza";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_especie);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Insertar nueva raza dinámicamente
    public function insertRaza($id_especie, $nombre_raza) {
        $query = "INSERT INTO razas (id_especie, nombre_raza) VALUES (:id_esp, :nom)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_esp', $id_especie);
        $stmt->bindParam(':nom', $nombre_raza);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function search($term) {
        $query = "SELECT m.*, u.nombre_completo as propietario_nombre,
                         e.nombre_especie, r.nombre_raza,
                         GROUP_CONCAT(cb.nombre_color SEPARATOR ', ') as colores_nombres
                  FROM " . $this->table_name . " m
                  LEFT JOIN usuarios u ON m.doc_propietario = u.documento
                  LEFT JOIN especies e ON m.id_especie = e.id_especie
                  LEFT JOIN razas r ON m.id_raza = r.id_raza
                  LEFT JOIN mascota_colores mc ON m.id_mascota = mc.id_mascota
                  LEFT JOIN colores_base cb ON mc.id_color = cb.id_color
                  WHERE (m.nombre LIKE :term1 
                     OR u.nombre_completo LIKE :term2 
                     OR u.documento LIKE :term3)
                  AND m.estado = 1
                  GROUP BY m.id_mascota
                  LIMIT 10";
        
        $stmt = $this->conn->prepare($query);
        $likeTerm = "%$term%";
        $stmt->bindParam(':term1', $likeTerm);
        $stmt->bindParam(':term2', $likeTerm);
        $stmt->bindParam(':term3', $likeTerm);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getColoresBase() {
        $query = "SELECT * FROM colores_base ORDER BY nombre_color";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertColor($nombre_color) {
        $query = "INSERT INTO colores_base (nombre_color) VALUES (:nom)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $nombre_color);
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getByPropietario($doc_propietario) {
        $query = "SELECT m.*, e.nombre_especie as especie, r.nombre_raza as raza 
                  FROM " . $this->table_name . " m
                  JOIN especies e ON m.id_especie = e.id_especie
                  JOIN razas r ON m.id_raza = r.id_raza
                  WHERE m.doc_propietario = :doc AND m.estado = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc', $doc_propietario);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET estado = :status WHERE id_mascota = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * HU-35: confirma que la mascota exista y siga activa antes de colgarle un
     * registro clinico. Devuelve el documento del propietario (lo necesita
     * RN-G02 para el portal) o null si la mascota no existe o esta inactiva.
     *
     * Es una consulta propia y no getById() porque aqui solo interesa la
     * existencia: getById() hace cinco JOIN y un GROUP BY para armar la ficha
     * completa, y ademas no filtra por estado.
     */
    public function getPropietarioSiActiva($id_mascota) {
        // Se rechaza lo que no sea un entero positivo en vez de dejar que el
        // cast lo convierta: (int)"1 OR 1=1" da 1, asi que un identificador
        // basura devolveria los datos de la mascota 1 como si nada. No es una
        // via de inyeccion (la consulta va preparada), pero si un acierto por
        // accidente que el llamador no espera.
        if (!is_numeric($id_mascota) || floor($id_mascota + 0) != $id_mascota + 0) {
            return null;
        }
        $id_mascota = (int) $id_mascota;
        if ($id_mascota <= 0) {
            return null;
        }

        $query = "SELECT doc_propietario FROM " . $this->table_name . "
                  WHERE id_mascota = :id AND estado = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id_mascota, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $fila['doc_propietario'] : null;
    }
}
?>
