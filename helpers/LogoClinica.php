<?php
require_once __DIR__ . '/CampoInvalido.php';

/** RE-7.4.1/5: imagen real, ruta generada y limpieza exclusiva de la subida que aún no se confirmó. */
final class LogoClinica
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const FORMATOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private string $carpeta;
    private mixed $mover;
    private array $pendientes = [];

    public function __construct(?string $carpeta = null, ?callable $mover = null)
    {
        $this->carpeta = $carpeta ?? __DIR__ . '/../public/uploads/logos';
        $this->mover = $mover ?? 'move_uploaded_file';
    }

    public function guardar(?array $archivo, int $clinica): ?string
    {
        if ($archivo === null || ($archivo['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($archivo['error'] ?? null) !== UPLOAD_ERR_OK) {
            $this->rechazar('No se pudo recibir el logo. Elige una imagen de hasta 5 MB.');
        }
        $temporal = $archivo['tmp_name'] ?? null;
        if (!is_string($temporal) || !is_file($temporal)) {
            $this->rechazar('El archivo del logo no es válido.');
        }
        $bytes = filesize($temporal);
        if ($bytes === false || $bytes < 1 || $bytes > self::MAX_BYTES) {
            $this->rechazar('El logo debe pesar como máximo 5 MB.');
        }
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);
        $imagen = @getimagesize($temporal);
        if (!isset(self::FORMATOS[$tipo]) || !$imagen || ($imagen['mime'] ?? null) !== $tipo) {
            $this->rechazar('Elige una imagen JPG, PNG o WebP válida.');
        }
        if ($clinica < 1) {
            throw new LogicException('El logo requiere una clínica autorizada.');
        }
        if (!is_dir($this->carpeta) && !mkdir($this->carpeta, 0755, true) && !is_dir($this->carpeta)) {
            throw new RuntimeException('No se pudo preparar la carpeta de logos.');
        }
        $nombre = 'clinica-' . $clinica . '-' . bin2hex(random_bytes(16)) . '.' . self::FORMATOS[$tipo];
        $destino = $this->carpeta . DIRECTORY_SEPARATOR . $nombre;
        $ruta = 'uploads/logos/' . $nombre;
        $this->pendientes[$ruta] = $destino;
        try {
            if (!($this->mover)($temporal, $destino)) {
                throw new RuntimeException('No se pudo guardar el logo.');
            }
        } catch (Throwable $error) {
            $this->descartar($ruta);
            throw $error;
        }
        return $ruta;
    }

    public function confirmar(?string $ruta): void
    {
        unset($this->pendientes[$ruta ?? '']);
    }

    public function descartar(?string $ruta): void
    {
        // Solo conoce archivos creados por esta instancia; nunca elimina el logo anterior ni una ruta del cliente.
        $destino = $this->pendientes[$ruta ?? ''] ?? null;
        if ($destino !== null && is_file($destino) && !unlink($destino)) {
            error_log('No se pudo retirar el logo nuevo tras fallar el guardado.');
        }
        unset($this->pendientes[$ruta ?? '']);
    }

    private function rechazar(string $mensaje): never
    {
        throw new CampoInvalido('logo_archivo', $mensaje, $mensaje);
    }
}
