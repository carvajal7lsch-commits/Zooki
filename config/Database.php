<?php

class Database {
    // Parámetros de la base de datos
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Cargar credenciales desde .env si existe
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $this->host = $env['DB_HOST'] ?? 'localhost';
            $this->db_name = $env['DB_NAME'] ?? 'zooki_db';
            $this->username = $env['DB_USER'] ?? 'root';
            $this->password = $env['DB_PASS'] ?? '';
        }
        
        // Si el host configurado es 'db' pero no se puede resolver (por ejemplo, al ejecutar localmente en Windows sin Docker)
        if ($this->host === 'db') {
            if (PHP_OS_FAMILY === 'Windows' || gethostbyname('db') === 'db') {
                $this->host = '127.0.0.1';
            }
        }
    }

    // Método para obtener la conexión
    public function getConnection() {
        $this->conn = null;

        try {
            // Usamos PDO que es el estándar más seguro y profesional hoy en día
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            
            // Configuramos PDO para que lance excepciones si hay errores (muy útil para depurar)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Hacemos que los resultados se devuelvan como un array asociativo por defecto
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $exception) {
            // Si falla la conexión local en XAMPP y no estamos usando 'root', intentamos con las credenciales por defecto de XAMPP (root y sin contraseña)
            if (($this->host === '127.0.0.1' || $this->host === 'localhost') && $this->username !== 'root') {
                try {
                    $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 'root', '');
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    $this->username = 'root';
                    $this->password = '';
                    return $this->conn;
                } catch(PDOException $fallbackException) {
                    // Si el fallback también falla, dejamos que muestre el error original
                }
            }
            echo "Error de conexión a la base de datos: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>
