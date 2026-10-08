<?php
require_once __DIR__ . '/ModeloPropietario.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/**
 * HU-5.12 y HU-5.13: las clínicas del propietario. Se vincula o se
 * desvincula él mismo y decide qué clínicas ven la historia registrada por
 * otras (RN-113). Cada cambio queda en auditoría con la clínica afectada,
 * para que también lo vea esa clínica.
 */
final class VinculosPropietario extends ModeloPropietario
{
    /** Citas no resueltas que impiden desvincularse (RE-5.13.3, decisión del usuario en C6). */
    private const CITAS_SIN_RESOLVER = ['pendiente', 'confirmada', 'en_curso', 'sin_cerrar'];

    /** Clínicas activas con vínculo activo del propietario, con su autorización de historia. */
    public function clinicas(): array
    {
        $consulta = $this->conn->prepare("SELECT c.id_clinica, c.nombre, c.direccion, c.telefono,
                pc.autoriza_historia_compartida, pc.fecha_autorizacion
            FROM propietario_clinica pc
            JOIN clinicas c ON c.id_clinica = pc.id_clinica
            WHERE pc.id_propietario = ? AND pc.estado = 'activo' AND c.estado = 'activa'
            ORDER BY c.nombre");
        $consulta->execute([$this->propietario()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** RE-5.13.1: clínicas activas de la plataforma a las que todavía no está vinculado. */
    public function disponibles(): array
    {
        $consulta = $this->conn->prepare("SELECT c.id_clinica, c.nombre, c.direccion
            FROM clinicas c
            WHERE c.estado = 'activa' AND NOT EXISTS (
                SELECT 1 FROM propietario_clinica pc
                WHERE pc.id_clinica = c.id_clinica AND pc.id_propietario = ? AND pc.estado = 'activo'
            )
            ORDER BY c.nombre");
        $consulta->execute([$this->propietario()]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Horario de cada clínica vinculada (HU-5.1, RE-5.1.7), indexado por id_clinica. */
    public function horarios(): array
    {
        $porClinica = [];
        $consulta = $this->conn->prepare('SELECT * FROM horarios_clinica WHERE id_clinica = ? ORDER BY dia_semana');
        foreach ($this->clinicas() as $clinica) {
            $consulta->execute([(int) $clinica['id_clinica']]);
            $porClinica[(int) $clinica['id_clinica']] = $consulta->fetchAll(PDO::FETCH_ASSOC);
        }
        return $porClinica;
    }

    /**
     * RE-5.13.1/2: se vincula con un clic, sin repetir la verificación del
     * correo (ya inició sesión). La clínica solo ve sus datos de propietario:
     * las mascotas se le vinculan al agendar o al atenderlas allí.
     */
    public function vincular(int $idClinica): void
    {
        $idPropietario = $this->propietario();
        $estadoClinica = $this->estadoClinica($idClinica);
        if ($estadoClinica !== 'activa') {
            throw new InvalidArgumentException('Esa clínica no está disponible.');
        }

        $vinculo = $this->vinculo($idClinica);
        if ($vinculo === 'activo') {
            throw new InvalidArgumentException('Ya estás vinculado a esa clínica.');
        }

        if ($vinculo === null) {
            $this->conn->prepare("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (?, ?, 'activo')")
                ->execute([$idPropietario, $idClinica]);
        } else {
            $this->conn->prepare("UPDATE propietario_clinica SET estado = 'activo' WHERE id_propietario = ? AND id_clinica = ?")
                ->execute([$idPropietario, $idClinica]);
        }
        $this->auditar('INSERT', 'propietario_clinica', $idPropietario, ['estado' => $vinculo], ['estado' => 'activo'], 'El propietario se vinculó desde el portal (HU-5.13)', $idClinica);
    }

    /**
     * RE-5.13.3 y RN-115: se rechaza con citas sin resolver en esa clínica.
     * Sin ellas, el vínculo pasa a inactivo, pierde la autorización de
     * historia y las mascotas quedan desvinculadas: la clínica conserva sus
     * registros en solo lectura y no registra nada nuevo.
     */
    public function desvincular(int $idClinica): void
    {
        $idPropietario = $this->propietario();
        if ($this->vinculo($idClinica) !== 'activo') {
            throw new InvalidArgumentException('No estás vinculado a esa clínica.');
        }

        $pendientes = $this->citasSinResolver($idClinica);
        if ($pendientes > 0) {
            throw new InvalidArgumentException(sprintf(
                'Tienes %d cita%s sin resolver en esa clínica. Cancela las pendientes o confirmadas; las que están en atención las cierra la clínica.',
                $pendientes,
                $pendientes === 1 ? '' : 's'
            ));
        }

        Transaccion::ejecutar($this->conn, function () use ($idPropietario, $idClinica): void {
            $this->conn->prepare("UPDATE propietario_clinica
                SET estado = 'inactivo', autoriza_historia_compartida = 0, fecha_autorizacion = NULL
                WHERE id_propietario = ? AND id_clinica = ?")->execute([$idPropietario, $idClinica]);

            $mascotas = $this->conn->prepare("UPDATE mascota_clinica SET estado = 'inactivo'
                WHERE id_clinica = ? AND estado = 'activo'
                  AND id_mascota IN (SELECT id_mascota FROM mascotas WHERE id_propietario = ?)");
            $mascotas->execute([$idClinica, $idPropietario]);

            $this->auditar('UPDATE', 'propietario_clinica', $idPropietario, ['estado' => 'activo'], ['estado' => 'inactivo', 'mascotas_desvinculadas' => $mascotas->rowCount()], 'El propietario se desvinculó desde el portal (HU-5.13, RN-115)', $idClinica);
        });
    }

    /** RE-5.12.1/2/4: activa o revoca, de inmediato y con auditoría, la historia compartida para esa clínica. */
    public function autorizarHistoria(int $idClinica, bool $autoriza): void
    {
        $idPropietario = $this->propietario();
        if ($this->vinculo($idClinica) !== 'activo') {
            throw new InvalidArgumentException('Solo puedes autorizar a una clínica a la que estás vinculado.');
        }

        $fecha = $autoriza ? (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s') : null;
        $this->conn->prepare('UPDATE propietario_clinica SET autoriza_historia_compartida = ?, fecha_autorizacion = ?
            WHERE id_propietario = ? AND id_clinica = ?')->execute([$autoriza ? 1 : 0, $fecha, $idPropietario, $idClinica]);

        $descripcion = $autoriza ? 'Autorizó la historia compartida (HU-5.12)' : 'Revocó la historia compartida (HU-5.12)';
        $this->auditar('UPDATE', 'propietario_clinica', $idPropietario, null, ['autoriza_historia_compartida' => $autoriza ? 1 : 0], $descripcion, $idClinica);
    }

    private function vinculo(int $idClinica): ?string
    {
        $consulta = $this->conn->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $consulta->execute([$this->propietario(), $idClinica]);
        $estado = $consulta->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    private function estadoClinica(int $idClinica): ?string
    {
        $consulta = $this->conn->prepare('SELECT estado FROM clinicas WHERE id_clinica = ?');
        $consulta->execute([$idClinica]);
        $estado = $consulta->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    private function citasSinResolver(int $idClinica): int
    {
        $marcas = implode(', ', array_fill(0, count(self::CITAS_SIN_RESOLVER), '?'));
        $consulta = $this->conn->prepare("SELECT COUNT(*) FROM citas c
            JOIN mascotas m ON m.id_mascota = c.id_mascota
            WHERE c.id_clinica = ? AND m.id_propietario = ? AND c.estado IN ($marcas)");
        $consulta->execute([$idClinica, $this->propietario(), ...self::CITAS_SIN_RESOLVER]);
        return (int) $consulta->fetchColumn();
    }
}
