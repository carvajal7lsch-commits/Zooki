<?php

class Usuario
{
    private $conn;
    private $table_name = "usuarios";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Obtener usuario con su nombre de rol
    public function getUserByDocumento($documento)
    {
        $query =
            "SELECT u.documento, u.tipo_documento, u.nombre_completo, u.password, u.estado, u.id_rol, r.nombre_rol as rol, u.debe_cambiar_password, u.email, u.telefono
                  FROM " .
            $this->table_name .
            " u
                  JOIN roles r ON u.id_rol = r.id_rol
                  WHERE u.documento = :documento LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":documento", $documento);
        $stmt->execute();

        return $stmt->fetch();
    }

    // Verificar si un email ya existe
    public function getUserByEmail($email)
    {
        $query =
            "SELECT documento FROM " .
            $this->table_name .
            " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getUserDetailsByEmail($email)
    {
        $query =
            "SELECT documento, nombre_completo, email, estado, id_rol FROM " .
            $this->table_name .
            " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Verificar si un email ya existe (excluyendo un documento específico)
    public function getUserByEmailExcluding($email, $exclude_documento)
    {
        $query =
            "SELECT documento FROM " .
            $this->table_name .
            " WHERE email = :email AND documento != :exclude_doc LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":exclude_doc", $exclude_documento);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Obtener todos los propietarios con el conteo de sus mascotas activas
    public function getAllOwnersWithPetCount()
    {
        $query =
            "SELECT u.documento, u.tipo_documento, u.nombre_completo, u.telefono, u.email, u.estado,
                         COUNT(m.id_mascota) as total_mascotas
                  FROM " .
            $this->table_name .
            " u
                  LEFT JOIN mascotas m ON u.documento = m.doc_propietario AND m.estado = 1
                  WHERE u.id_rol = 4
                  GROUP BY u.documento
                  ORDER BY u.nombre_completo";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener todos los propietarios (rol id_rol = 4) - Legacy (usado en buscadores)
    public function getAllOwners()
    {
        $query =
            "SELECT documento, tipo_documento, nombre_completo, telefono, email, estado FROM " .
            $this->table_name .
            " WHERE id_rol = 4 ORDER BY nombre_completo";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener un usuario por documento de forma simple
    public function getById($documento)
    {
        $query =
            "SELECT * FROM " . $this->table_name . " WHERE documento = :doc";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":doc", $documento);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar datos del usuario
    public function update($data)
    {
        // Debug: Log de los datos recibidos
        error_log("Update usuario - documento: " . $data["documento"] . ", original_doc: " . $data["original_doc"]);
        error_log("Comparación documento == original_doc: " . ($data["documento"] == $data["original_doc"] ? "true" : "false"));
        error_log("Comparación documento === original_doc: " . ($data["documento"] === $data["original_doc"] ? "true" : "false"));
        
        // Si el documento no cambió, no intentar actualizarlo (evita error de clave foránea)
        if ($data["documento"] == $data["original_doc"]) {
            error_log("Documento no cambió, actualizando sin cambiar documento");
            $query =
                "UPDATE " .
                $this->table_name .
                "
                  SET tipo_documento = :tipo_doc, nombre_completo = :nombre,
                      telefono = :tel, email = :email, id_rol = :id_rol, estado = :est
                  WHERE documento = :doc";
        } else {
            // Si el documento cambió, intentar actualizarlo (podría fallar por restricciones de clave foránea)
            error_log("Documento cambió, intentando actualizar documento también");
            $query =
                "UPDATE " .
                $this->table_name .
                "
                  SET documento = :new_doc, tipo_documento = :tipo_doc, nombre_completo = :nombre,
                      telefono = :tel, email = :email, id_rol = :id_rol, estado = :est
                  WHERE documento = :old_doc";
        }

        $stmt = $this->conn->prepare($query);
        
        if ($data["documento"] == $data["original_doc"]) {
            $stmt->bindParam(":doc", $data["documento"]);
        } else {
            $stmt->bindParam(":new_doc", $data["documento"]);
            $stmt->bindParam(":old_doc", $data["original_doc"]);
        }
        
        $stmt->bindParam(":tipo_doc", $data["tipo_documento"]);
        $stmt->bindParam(":nombre", $data["nombre_completo"]);
        $stmt->bindParam(":tel", $data["telefono"]);
        $stmt->bindParam(":email", $data["email"]);
        $stmt->bindParam(":id_rol", $data["id_rol"]);
        $stmt->bindParam(":est", $data["estado"]);

        $result = $stmt->execute();
        error_log("Resultado de update: " . ($result ? "true" : "false"));
        return $result;
    }

    // Crear nuevo usuario (con id_rol y nombre_completo)
    public function create($data)
    {
        $query =
            "INSERT INTO " .
            $this->table_name .
            " (documento, tipo_documento, nombre_completo, telefono, email, password, id_rol, estado, debe_cambiar_password)
                  VALUES (:documento, :tipo_documento, :nombre_completo, :telefono, :email, :password, :id_rol, :estado, :debe_cambiar_password)";

        $stmt = $this->conn->prepare($query);

        // Limpieza
        $documento = htmlspecialchars(strip_tags($data["documento"]));
        $tipo_doc = htmlspecialchars(
            strip_tags($data["tipo_documento"] ?? "CC"),
        );
        $nombre_completo = htmlspecialchars(
            strip_tags($data["nombre_completo"]),
        );
        $telefono = htmlspecialchars(strip_tags($data["telefono"]));
        $email = htmlspecialchars(strip_tags($data["email"]));
        $password = $data["password"];
        $id_rol = $data["id_rol"];
        $estado = isset($data["estado"]) ? $data["estado"] : 1;
        $debe_cambiar_password = isset($data["debe_cambiar_password"]) ? $data["debe_cambiar_password"] : 0;

        // Bind
        $stmt->bindParam(":documento", $documento);
        $stmt->bindParam(":tipo_documento", $tipo_doc);
        $stmt->bindParam(":nombre_completo", $nombre_completo);
        $stmt->bindParam(":telefono", $telefono);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $password);
        $stmt->bindParam(":id_rol", $id_rol);
        $stmt->bindParam(":estado", $estado);
        $stmt->bindParam(":debe_cambiar_password", $debe_cambiar_password);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Actualizar contraseña del usuario
    public function updatePassword($documento, $passwordHash) {
        $query = "UPDATE " . $this->table_name . " SET password = :password WHERE documento = :documento";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password", $passwordHash);
        $stmt->bindParam(":documento", $documento);
        return $stmt->execute();
    }

    // Actualizar debe_cambiar_password
    public function updateDebeCambiarPassword($documento, $valor) {
        $query = "UPDATE " . $this->table_name . " SET debe_cambiar_password = :valor WHERE documento = :documento";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":valor", $valor);
        $stmt->bindParam(":documento", $documento);
        return $stmt->execute();
    }

    // Obtener todos los usuarios del sistema con sus roles
    public function getAll()
    {
        $query =
            "SELECT u.documento, u.tipo_documento, u.nombre_completo, u.telefono, u.email, u.estado, u.id_rol, r.nombre_rol
                  FROM " .
            $this->table_name .
            " u
                  JOIN roles r ON u.id_rol = r.id_rol
                  WHERE u.id_rol != 4
                  ORDER BY u.nombre_completo";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener listado de roles (excepto propietario tal vez)
    public function getRoles()
    {
        $query =
            "SELECT id_rol, nombre_rol FROM roles WHERE id_rol != 4 ORDER BY id_rol";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Actualizar solo el estado del usuario
    public function updateStatus($documento, $estado)
    {
        $query =
            "UPDATE " .
            $this->table_name .
            " SET estado = :est WHERE documento = :doc";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":est", $estado);
        $stmt->bindParam(":doc", $documento);
        return $stmt->execute();
    }
}
?>
