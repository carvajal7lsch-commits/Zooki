<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Usuario.php';

class AuthTest extends TestCase
{
    private $db;
    private $userModel;

    protected function setUp(): void
    {
        // Setup SQLite in-memory database
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create schemas
        $this->db->exec("
            CREATE TABLE roles (
                id_rol INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre_rol TEXT
            );
            
            CREATE TABLE usuarios (
                documento TEXT PRIMARY KEY,
                tipo_documento TEXT,
                nombre_completo TEXT,
                password TEXT,
                estado INTEGER,
                id_rol INTEGER,
                debe_cambiar_password INTEGER DEFAULT 0,
                email TEXT,
                telefono TEXT,
                FOREIGN KEY(id_rol) REFERENCES roles(id_rol)
            );
        ");

        // Seed roles
        $this->db->exec("INSERT INTO roles (id_rol, nombre_rol) VALUES (1, 'administrador')");
        $this->db->exec("INSERT INTO roles (id_rol, nombre_rol) VALUES (2, 'veterinario')");
        $this->db->exec("INSERT INTO roles (id_rol, nombre_rol) VALUES (3, 'recepcionista')");
        $this->db->exec("INSERT INTO roles (id_rol, nombre_rol) VALUES (4, 'propietario')");

        $this->userModel = new Usuario($this->db);
    }

    public function testRegisterAndRetrieveUser()
    {
        // Insert a new user (simulate registration)
        $passwordHash = password_hash('ZookiSecPass123', PASSWORD_BCRYPT);
        
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (documento, tipo_documento, nombre_completo, password, estado, id_rol, email, telefono)
            VALUES (:doc, 'CC', 'Juan Perez', :pass, 1, 4, 'juan.perez@example.com', '5551234')
        ");
        $stmt->execute([
            ':doc' => '100200300',
            ':pass' => $passwordHash
        ]);

        // Get user by document
        $user = $this->userModel->getUserByDocumento('100200300');
        
        $this->assertNotEmpty($user);
        $this->assertEquals('Juan Perez', $user['nombre_completo']);
        $this->assertEquals('CC', $user['tipo_documento']);
        $this->assertEquals('propietario', $user['rol']); // joined from roles table
        $this->assertEquals(1, $user['estado']);
        $this->assertTrue(password_verify('ZookiSecPass123', $user['password']));
    }

    public function testCheckEmailAvailability()
    {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (documento, tipo_documento, nombre_completo, password, estado, id_rol, email, telefono)
            VALUES ('100200300', 'CC', 'Juan Perez', 'hash', 1, 4, 'juan.perez@example.com', '5551234')
        ");
        $stmt->execute();

        // Email should exist
        $exists = $this->userModel->getUserByEmail('juan.perez@example.com');
        $this->assertNotEmpty($exists);

        // Another email should not exist
        $notExists = $this->userModel->getUserByEmail('another@example.com');
        $this->assertFalse($notExists);
    }
}
