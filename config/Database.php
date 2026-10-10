<?php

require_once __DIR__ . '/../helpers/ZonaHoraria.php';

class Database {
    // Parámetros de la base de datos
    private $host;
    private $db_name;
    private $username;
    private $password;
    private int $port = 3306;
    public $conn;

    public function __construct(?array $configuracion = null) {
        // D3: antes de leer .env o abrir PDO, bloquear conexiones implícitas de PHPUnit.
        if (self::enPruebas()) {
            $permitida = self::configuracionPruebas();
            if ($configuracion === null || $configuracion !== $permitida) {
                throw new RuntimeException('PHPUnit no puede abrir la base de trabajo; inyecta su conexión de pruebas.');
            }
            $this->host = $permitida['host'];
            $this->port = $permitida['port'];
            $this->db_name = $permitida['base'];
            $this->username = $permitida['usuario'];
            $this->password = $permitida['clave'];
            return;
        }
        // Cargar credenciales desde .env si existe
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $this->host = $env['DB_HOST'] ?? 'localhost';
            $this->db_name = $env['DB_NAME'] ?? 'zooki_db';
            $this->username = $env['DB_USER'] ?? 'root';
            $this->password = $env['DB_PASS'] ?? '';
        }
        
        $this->host = self::hostEfectivo((string) $this->host);
    }

    public static function enPruebas(): bool {
        return defined('ZOOKI_PRUEBAS') || getenv('ZOOKI_ENTORNO_PRUEBAS') === '1'
            || class_exists('PHPUnit\\Framework\\TestCase', false);
    }

    /** La conexión permitida se solicita expresamente, nunca usando .env. */
    public static function configuracionPruebas(): array {
        $host = getenv('ZOOKI_TEST_MYSQL_HOST');
        $base = getenv('ZOOKI_TEST_MYSQL_DB') ?: 'zooki_test_base_v2';
        if (!$host || $base !== 'zooki_test_base_v2') {
            throw new RuntimeException('La conexión de pruebas requiere ZOOKI_TEST_MYSQL_HOST y zooki_test_base_v2.');
        }
        return [
            'host' => $host,
            'port' => (int) (getenv('ZOOKI_TEST_MYSQL_PORT') ?: 3306),
            'base' => $base,
            'usuario' => getenv('ZOOKI_TEST_MYSQL_USER') ?: 'root',
            'clave' => getenv('ZOOKI_TEST_MYSQL_PASS') ?: '',
        ];
    }

    /**
     * El host al que de verdad se conecta. Si el configurado es 'db' pero no
     * se puede resolver (por ejemplo, al ejecutar localmente en Windows sin
     * Docker), se usa 127.0.0.1. Lo usa también EmailService para decidir si
     * es local (D2.2): con DB_HOST=db en Windows la base es local.
     */
    public static function hostEfectivo(string $host, string $sistema = PHP_OS_FAMILY): string {
        if ($host !== 'db') {
            return $host;
        }
        if ($sistema === 'Windows' || gethostbyname('db') === 'db') {
            return '127.0.0.1';
        }
        return $host;
    }

    // Método para obtener la conexión
    public function getConnection() {
        $this->conn = null;

        try {
            // Usamos PDO que es el estándar más seguro y profesional hoy en día
            $this->conn = new PDO("mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            
            // Configuramos PDO para que lance excepciones si hay errores (muy útil para depurar)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Hacemos que los resultados se devuelvan como un array asociativo por defecto
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            ZonaHoraria::aplicarEnConexion($this->conn);
            
        } catch(PDOException $exception) {
            if (self::enPruebas()) {
                throw $exception;
            }
            // Si falla la conexión local en XAMPP y no estamos usando 'root', intentamos con las credenciales por defecto de XAMPP (root y sin contraseña)
            if (($this->host === '127.0.0.1' || $this->host === 'localhost') && $this->username !== 'root') {
                try {
                    $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 'root', '');
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    ZonaHoraria::aplicarEnConexion($this->conn);
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
