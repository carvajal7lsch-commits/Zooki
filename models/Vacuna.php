<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/CatalogoClinica.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/**
 * HU-3.1: vacunas aplicadas. Cada una guarda su clínica y su veterinario
 * (RN-112); toda clínica vinculada a la mascota ve las de todas (RN-113).
 */
class Vacuna extends ModeloHistoria
{
    /** RE-3.1.1 y RN-207: registra una vacuna sobre una mascota activa y vinculada. */
    public function registrar(array $entrada): int
    {
        $idMascota = ValidadorClinico::id($entrada['id_mascota'] ?? null);
        if ($idMascota === null) {
            throw new InvalidArgumentException('La mascota indicada no es válida.');
        }
        $this->exigirMascotaActiva($idMascota);

        $nombre = ValidadorClinico::textoRequerido($entrada['nombre_vacuna'] ?? null, 150);
        if ($nombre === null) {
            throw new InvalidArgumentException('El nombre de la vacuna es obligatorio.');
        }

        // No se puede aplicar una vacuna en el futuro.
        $fechaAplicacion = ValidadorClinico::fechaNoFutura($entrada['fecha_aplicacion'] ?? null);
        if ($fechaAplicacion === null) {
            throw new InvalidArgumentException('La fecha de aplicación no es válida o está en el futuro.');
        }

        // La próxima dosis es futura, pero nunca anterior a la aplicación.
        $fechaProxima = null;
        if (!empty($entrada['fecha_proxima'])) {
            $fechaProxima = ValidadorClinico::fecha($entrada['fecha_proxima']);
            if ($fechaProxima === null || $fechaProxima < $fechaAplicacion) {
                throw new InvalidArgumentException('La fecha de próxima dosis no es válida o es anterior a la aplicación.');
            }
        }

        $sql = 'INSERT INTO vacunas
            (id_clinica, id_mascota, id_veterinario, nombre_vacuna, laboratorio, lote, fecha_aplicacion, fecha_proxima_dosis, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->conn->prepare($sql)->execute([
            $this->clinica(),
            $idMascota,
            $this->veterinarioActual(),
            $nombre,
            ValidadorClinico::textoOpcional($entrada['laboratorio'] ?? null, 150),
            ValidadorClinico::textoOpcional($entrada['lote'] ?? null, 100),
            $fechaAplicacion,
            $fechaProxima,
            ValidadorClinico::textoOpcional($entrada['observaciones'] ?? null, 5000),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /** RE-2.10.1: vacunas de todas las clínicas, con la clínica que las aplicó. */
    public function findByMascota($idMascota): array
    {
        $idMascota = (int) $idMascota;
        $this->exigirMascotaVinculada($idMascota);

        $sql = 'SELECT v.*, cl.nombre AS clinica_nombre, u.nombre_completo AS veterinario
            FROM vacunas v
            JOIN clinicas cl ON cl.id_clinica = v.id_clinica
            LEFT JOIN usuarios u ON u.id_usuario = v.id_veterinario
            WHERE v.id_mascota = ?
            ORDER BY v.fecha_aplicacion DESC, v.id_vacuna DESC';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idMascota]);
        return $this->marcarOrigen($consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * RE-3.5.1: próximas dosis de los 7 días siguientes, solo de las vacunas
     * que aplicó la clínica activa y de mascotas activas que siguen
     * vinculadas a ella. Los recordatorios por correo son de C8.
     */
    public function pendientesSemana(?DateTimeImmutable $hoy = null): array
    {
        $hoy ??= new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
        $limite = $hoy->modify('+7 days');
        return $this->pendientesEntre($hoy->format('Y-m-d'), $limite->format('Y-m-d'));
    }

    /**
     * Próximas dosis entre dos fechas, solo de lo que aplicó la clínica
     * activa y de mascotas activas vinculadas a ella. Las usan el panel de
     * la semana y los eventos del calendario (C5).
     */
    public function pendientesEntre(string $inicio, string $fin): array
    {

        $sql = "SELECT v.*, m.nombre AS nombre_mascota, e.nombre_especie, u.nombre_completo AS propietario
            FROM vacunas v
            JOIN mascotas m ON m.id_mascota = v.id_mascota
            JOIN mascota_clinica mc ON mc.id_mascota = v.id_mascota AND mc.id_clinica = v.id_clinica
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            LEFT JOIN usuarios u ON u.id_usuario = m.id_propietario
            WHERE v.id_clinica = ? AND mc.estado = 'activo' AND m.estado = 1
              AND v.fecha_proxima_dosis BETWEEN ? AND ?
            ORDER BY v.fecha_proxima_dosis ASC, m.nombre ASC";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica(), $inicio, $fin]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** RN-112 / RE-2.10.4: punto único por el que pasa cualquier modificación. */
    public function paraModificar(int $idVacuna): array
    {
        $consulta = $this->conn->prepare('SELECT * FROM vacunas WHERE id_vacuna = ? AND id_clinica = ?');
        $consulta->execute([$idVacuna, $this->clinica()]);
        $vacuna = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($vacuna === false) {
            $this->denegarAcceso('vacunas', $idVacuna, 'Modificación de una vacuna de otra clínica (RN-112)');
        }
        return $vacuna;
    }

    public function getVacunasPorEspecie($idEspecie): array
    {
        return (new CatalogoClinica($this->conn))->vacunasPorEspecie((int) $idEspecie);
    }

    public function getLaboratorios(): array
    {
        return (new CatalogoClinica($this->conn))->laboratorios();
    }
}
