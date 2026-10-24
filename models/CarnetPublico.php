<?php
require_once __DIR__ . '/CarnetMascota.php';
require_once __DIR__ . '/AvisosCarnet.php';
require_once __DIR__ . '/../helpers/QrCarnet.php';
require_once __DIR__ . '/../helpers/PrivacidadCarnet.php';
require_once __DIR__ . '/../helpers/UbicacionCarnet.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/** HU-5.11: el único permiso público es poseer un token vigente, nunca un id. */
final class CarnetPublico
{
    public const VENTANA_MINUTOS = 30;
    private AvisosCarnet $avisos;

    public function __construct(private PDO $db, ?callable $correo = null)
    {
        $this->avisos = new AvisosCarnet($db, $correo);
    }

    public function resolver(mixed $token, bool $bloquear = false): ?int
    {
        $formatoValido = true;
        try {
            $token = QrCarnet::validarToken($token);
        } catch (InvalidArgumentException $error) {
            // RE-5.11.2: también el mal formado hace exactamente una búsqueda de índice.
            $token = str_repeat('!', 43);
            $formatoValido = false;
        }
        $candado = $bloquear && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $consulta = $this->db->prepare('SELECT id_mascota, token_carnet FROM mascotas WHERE token_carnet = ? AND estado = 1 AND carnet_activo = 1 AND id_propietario IS NOT NULL' . $candado);
        $consulta->execute([$token]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        // MySQL usa general_ci: el token base64url debe seguir siendo sensible a mayúsculas.
        return !$formatoValido || $fila === false || !hash_equals($fila['token_carnet'], $token) ? null : (int) $fila['id_mascota'];
    }

    /** Con $diferirAviso el llamador envía el aviso con enviarAviso() después de responder. */
    public function escanear(mixed $token, string $ipHash, DateTimeImmutable $ahora, bool $diferirAviso = false): ?array
    {
        $ahora = $ahora->setTimezone(new DateTimeZone(ReglaAtencion::ZONA));
        $resultado = Transaccion::ejecutar($this->db, function () use ($token, $ipHash, $ahora): ?array {
            $id = $this->resolver($token, true);
            if ($id === null) {
                return null;
            }
            $datos = (new CarnetMascota($this->db))->datosEmergencia($id);
            if ($datos === null) {
                return null;
            }
            $this->db->prepare('INSERT INTO carnet_escaneos (id_mascota, fecha, ip_hash, notificado) VALUES (?, ?, ?, 1)')->execute([$id, $ahora->format('Y-m-d H:i:s'), $ipHash]);
            $idEscaneo = (int) $this->db->lastInsertId();
            $candado = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $consulta = $this->db->prepare("SELECT id_notificacion FROM notificaciones WHERE tipo_entidad = 'mascota' AND id_entidad = ? AND tipo_notificacion = 'carnet_escaneo' AND fecha_envio > ? ORDER BY fecha_envio DESC LIMIT 1" . $candado);
            $consulta->execute([$id, $ahora->modify('-' . self::VENTANA_MINUTOS . ' minutes')->format('Y-m-d H:i:s')]);
            $aviso = $consulta->fetchColumn() === false ? $this->avisos->reservar($id, 'carnet_escaneo', $ahora) : null;
            return ['datos' => $datos, 'comprobante' => PrivacidadCarnet::comprobante($token, $ipHash, $idEscaneo), 'aviso' => $aviso];
        });
        if ($resultado !== null && !$diferirAviso) {
            $this->avisos->enviar($resultado['aviso']);
            unset($resultado['aviso']);
        }
        return $resultado;
    }

    public function enviarAviso(?array $aviso): void
    {
        $this->avisos->enviar($aviso);
    }

    public function ubicar(mixed $token, string $ipHash, array $entrada, DateTimeImmutable $ahora): void
    {
        $ahora = $ahora->setTimezone(new DateTimeZone(ReglaAtencion::ZONA));
        $latitud = UbicacionCarnet::coordenada($entrada['latitud'] ?? null, 90);
        $longitud = UbicacionCarnet::coordenada($entrada['longitud'] ?? null, 180);
        $comprobante = $entrada['comprobante'] ?? null;
        if ($latitud === null || $longitud === null || !is_string($comprobante)) {
            return;
        }
        $aviso = Transaccion::ejecutar($this->db, function () use ($token, $ipHash, $comprobante, $latitud, $longitud, $ahora): ?array {
            $id = $this->resolver($token, true);
            if ($id === null) {
                return null;
            }
            $candado = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $consulta = $this->db->prepare('SELECT id_escaneo, latitud, longitud, fecha FROM carnet_escaneos WHERE id_mascota = ? AND ip_hash = ? ORDER BY fecha DESC, id_escaneo DESC LIMIT 1' . $candado);
            $consulta->execute([$id, $ipHash]);
            $escaneo = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($escaneo === false || $escaneo['latitud'] !== null || $escaneo['longitud'] !== null
                || $escaneo['fecha'] < $ahora->modify('-' . self::VENTANA_MINUTOS . ' minutes')->format('Y-m-d H:i:s')
                || $escaneo['fecha'] > $ahora->format('Y-m-d H:i:s')
                || !hash_equals(PrivacidadCarnet::comprobante($token, $ipHash, (int) $escaneo['id_escaneo']), $comprobante)) {
                return null;
            }
            $this->db->prepare('UPDATE carnet_escaneos SET latitud = ?, longitud = ? WHERE id_escaneo = ?')->execute([$latitud, $longitud, $escaneo['id_escaneo']]);
            return $this->avisos->reservar($id, 'carnet_ubicacion', $ahora, UbicacionCarnet::mapa($latitud, $longitud));
        });
        $this->avisos->enviar($aviso);
    }
}
