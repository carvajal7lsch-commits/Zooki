<?php
require_once __DIR__ . '/ModeloPropietario.php';
require_once __DIR__ . '/CarnetMascota.php';
require_once __DIR__ . '/../helpers/QrCarnet.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/UbicacionCarnet.php';

/** RN-G02: único acceso privado al carnet, también para SVG e impresión. */
final class CarnetPropietario extends ModeloPropietario
{
    public function obtener(int $idMascota): array
    {
        return $this->presentar($this->exigirMascotaPropia($idMascota), $idMascota);
    }

    private function presentar(array $mascota, int $idMascota): array
    {
        $motivo = CarnetMascota::motivoNoDisponible($mascota);
        if ($motivo !== null) {
            return ['disponible' => false, 'motivo' => $motivo];
        }
        $datos = (new CarnetMascota($this->conn))->datosEmergencia($idMascota);
        // La clínica pudo inactivarla entre la autorización y esta lectura.
        if ($datos === null) {
            return ['disponible' => false, 'motivo' => 'El carnet ya no está disponible. Vuelve a abrir la ficha.'];
        }
        return [
            'disponible' => true,
            'token' => $mascota['token_carnet'],
            'enlace' => QrCarnet::enlace($mascota['token_carnet']),
            'datos' => $datos,
            'escaneos' => $this->ultimosEscaneos($idMascota),
        ];
    }

    public function ultimosEscaneos(int $idMascota): array
    {
        $this->exigirMascotaPropia($idMascota);
        $consulta = $this->conn->prepare('SELECT fecha, latitud, longitud FROM carnet_escaneos WHERE id_mascota = ? ORDER BY fecha DESC, id_escaneo DESC LIMIT 10');
        $consulta->execute([$idMascota]);
        return array_map(static fn (array $fila): array => [
            'fecha' => $fila['fecha'],
            'mapa' => $fila['latitud'] !== null && $fila['longitud'] !== null ? UbicacionCarnet::mapa($fila['latitud'], $fila['longitud']) : null,
        ], $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    public function regenerar(int $idMascota, mixed $tokenVisto): array
    {
        $tokenVisto = QrCarnet::validarToken($tokenVisto);
        $this->exigirMascotaPropia($idMascota);
        return Transaccion::ejecutar($this->conn, function () use ($idMascota, $tokenVisto): array {
            // RE-5.10.5: lectura actual bajo bloqueo, también en transacción exterior MySQL.
            $bloqueo = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $consulta = $this->conn->prepare('SELECT id_propietario, estado, carnet_activo, token_carnet FROM mascotas WHERE id_mascota = ? AND id_propietario = ?' . $bloqueo);
            $consulta->execute([$idMascota, $this->propietario()]);
            $mascota = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($mascota === false) {
                $this->denegar('mascotas', $idMascota, 'Carnet ajeno (RN-G02)');
            }
            $motivo = CarnetMascota::motivoNoDisponible($mascota);
            if ($motivo !== null) {
                throw new InvalidArgumentException($motivo);
            }
            if (!hash_equals($mascota['token_carnet'], $tokenVisto)) {
                return $this->presentar($mascota, $idMascota) + ['regenerado' => false];
            }
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $fecha = (new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA)))->format('Y-m-d H:i:s');
            $this->conn->prepare('UPDATE mascotas SET token_carnet = ?, token_carnet_fecha = ? WHERE id_mascota = ? AND id_propietario = ?')->execute([$token, $fecha, $idMascota, $this->propietario()]);
            // No guardar tokens de acceso en el registro de auditoría (RN-502).
            $auditoria = new Auditoria($this->conn);
            if (!$auditoria->log(Contexto::idUsuario(), 'UPDATE', 'mascotas', $idMascota, null, ['token_carnet_fecha' => $fecha], 'QR del carnet regenerado por el propietario (RE-5.10.5)', null)) {
                throw new RuntimeException('No se pudo auditar la regeneración.');
            }
            $mascota['token_carnet'] = $token;
            return $this->presentar($mascota, $idMascota) + ['regenerado' => true];
        });
    }
}
