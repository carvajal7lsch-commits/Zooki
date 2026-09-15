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
            "SELECT u.documento, u.tipo_documento, u.nombre_completo, u.password, u.estado, u.id_rol, r.nombre_rol as rol, u.debe_cambiar_password, u.password_definida, u.email, u.telefono
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
    /**
     * T-03 — Columnas explicitas en vez de `SELECT *`. El comodin arrastraba
     * la columna `password`, y como el resultado se serializa tal cual en
     * getUsuarioAjax, el hash bcrypt de cualquier usuario terminaba viajando
     * al navegador. Quien necesite verificar la contrasena debe usar
     * getUserByDocumento(), que si la trae y no se expone nunca.
     */
    public function getById($documento)
    {
        $query =
            "SELECT documento, tipo_documento, nombre_completo, telefono, email,
                    estado, id_rol, debe_cambiar_password, password_definida
             FROM " . $this->table_name . " WHERE documento = :doc";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":doc", $documento);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar datos del usuario
    public function update($data)
    {
        // Si el documento no cambió, no intentar actualizarlo (evita error de clave foránea)
        if ($data["documento"] == $data["original_doc"]) {
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
            " (documento, tipo_documento, nombre_completo, telefono, email, password, id_rol, estado, debe_cambiar_password, password_definida)
                  VALUES (:documento, :tipo_documento, :nombre_completo, :telefono, :email, :password, :id_rol, :estado, :debe_cambiar_password, :password_definida)";

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
        $password_definida = isset($data["password_definida"]) ? (int) $data["password_definida"] : 1;

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
        $stmt->bindParam(":password_definida", $password_definida);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Actualizar contraseña del usuario
    public function updatePassword($documento, $passwordHash) {
        $query = "UPDATE " . $this->table_name . " SET password = :password, password_definida = 1 WHERE documento = :documento";
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

    /**
     * IDs de todos los roles existentes. Se usa como lista blanca para validar
     * el id_rol que llega por POST (VD-SEG-01): sin esto se podia grabar
     * cualquier valor, incluido un rol inexistente.
     */
    public function getAllRoleIds()
    {
        $query = "SELECT id_rol FROM roles";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Cuenta los administradores activos del sistema. Sirve para impedir que
     * se desactive o degrade al ultimo (VD-SEG-02), lo que dejaria el sistema
     * sin ningun acceso administrativo.
     */
    public function contarAdminsActivos()
    {
        $query =
            "SELECT COUNT(*) FROM " .
            $this->table_name .
            " WHERE id_rol = 1 AND estado = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * VD-SEG-01: el id_rol recibido debe corresponder a un rol existente.
     */
    public function esRolValido($idRol): bool
    {
        return in_array((int) $idRol, $this->getAllRoleIds(), true);
    }

    /**
     * VD-SEG-02: indica si este documento es el unico administrador activo
     * que queda. Desactivarlo o quitarle el rol dejaria el sistema sin
     * ningun acceso administrativo.
     */
    public function esUltimoAdminActivo($documento): bool
    {
        $actual = $this->getById($documento);
        if (!$actual) {
            return false;
        }
        $esAdminActivo = (int) $actual["id_rol"] === 1 && (int) $actual["estado"] === 1;
        if (!$esAdminActivo) {
            return false;
        }
        return $this->contarAdminsActivos() <= 1;
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

    public function updateContactInfo($documento, $email, $telefono)
    {
        $query = "UPDATE " . $this->table_name . " SET email = :email, telefono = :tel WHERE documento = :doc";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":tel", $telefono);
        $stmt->bindParam(":doc", $documento);
        return $stmt->execute();
    }
}
?>
