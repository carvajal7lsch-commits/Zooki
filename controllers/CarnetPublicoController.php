<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/CarnetPublico.php';
require_once __DIR__ . '/../models/AccesosCarnet.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';

/** RE-5.11: respuesta pública sin depender de una sesión ni de un contexto. */
final class CarnetPublicoController
{
    private PDO $db;
    private CarnetPublico $carnets;
    private Closure $reloj;
    public const MENSAJE_NO_DISPONIBLE = 'Carnet no disponible.';
    public const MENSAJE_UBICACION = 'Solicitud recibida. Si corresponde a un escaneo reciente, el dueño recibirá la ubicación.';

    public function __construct(?PDO $db = null, ?callable $correo = null, ?callable $reloj = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->carnets = new CarnetPublico($this->db, $correo);
        $this->reloj = Closure::fromCallable($reloj ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA)));
    }

    /** Se usan también para 404, 429, errores y recursos; nunca cachear un token revocado. */
    public static function cabeceras(): array
    {
        return ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff'];
    }

    private function protegerRespuesta(): void
    {
        foreach (self::cabeceras() as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
    }

    private function admitir(DateTimeImmutable $ahora): ?string
    {
        $ipHash = PrivacidadCarnet::ipHash();
        if (!(new AccesosCarnet($this->db))->admitir($ipHash, $ahora)) {
            return null;
        }
        return $ipHash;
    }

    public function mostrar(): void
    {
        $this->protegerRespuesta();
        $carnet = null;
        $aviso = null;
        $mensaje = self::MENSAJE_NO_DISPONIBLE;
        http_response_code(404);
        try {
            $ahora = ($this->reloj)();
            $ipHash = $this->admitir($ahora);
            if ($ipHash === null) {
                http_response_code(429);
                $mensaje = 'Demasiadas consultas. Intenta más tarde.';
            } else {
                $token = $_GET['t'] ?? null;
                $resultado = $this->carnets->escanear($token, $ipHash, $ahora, true);
                if ($resultado !== null) {
                    $datos = $resultado['datos'];
                    $datos['foto'] = empty($datos['foto']) ? null : 'index.php?action=carnet_foto&t=' . rawurlencode($token);
                    $carnet = ['disponible' => true, 'datos' => $datos, 'qr_src' => 'data:image/svg+xml;base64,' . base64_encode(QrCarnet::svg($token))];
                    $comprobante = $resultado['comprobante'];
                    $aviso = $resultado['aviso'];
                    http_response_code(200);
                }
            }
        } catch (Throwable $error) {
            http_response_code(503);
            $carnet = null;
            $mensaje = 'El carnet no está disponible temporalmente. Intenta más tarde.';
            error_log('Carnet público no disponible: ' . $error->getMessage());
        }
        require __DIR__ . '/../views/publico/carnet.php';
        // RN-504: quien escanea ve el carnet sin esperar al SMTP (hasta 10 s de espera).
        if ($aviso !== null) {
            $this->liberarRespuesta();
            $this->carnets->enviarAviso($aviso);
        }
    }

    /** Entrega lo ya generado antes del SMTP; las pruebas capturan la salida y no se vacía. */
    private function liberarRespuesta(): void
    {
        if (defined('ZOOKI_PRUEBAS')) {
            return;
        }
        ignore_user_abort(true);
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    public function ubicacion(): void
    {
        $this->protegerRespuesta();
        try {
            $ahora = ($this->reloj)();
            $ipHash = $this->admitir($ahora);
            if ($ipHash === null) {
                RespuestaJson::error(429, 'Demasiadas consultas. Intenta más tarde.');
                return;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $this->carnets->ubicar($_POST['t'] ?? null, $ipHash, $_POST, $ahora);
            }
            http_response_code(200);
            RespuestaJson::enviar(['success' => true, 'message' => self::MENSAJE_UBICACION]);
        } catch (Throwable $error) {
            RespuestaJson::error(503, 'El carnet no está disponible temporalmente. Intenta más tarde.');
            error_log('Ubicación del carnet no disponible.');
        }
    }

    public function foto(): void
    {
        $this->protegerRespuesta();
        $id = $this->carnets->resolver($_GET['t'] ?? null);
        $datos = $id === null ? null : (new CarnetMascota($this->db))->datosEmergencia($id);
        $carpeta = realpath(__DIR__ . '/../public/uploads/mascotas');
        $archivo = $datos !== null && !empty($datos['foto']) && $carpeta !== false ? realpath($carpeta . '/' . basename($datos['foto'])) : false;
        if ($archivo === false || !str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            return;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: ' . $mime);
        readfile($archivo);
    }
}
