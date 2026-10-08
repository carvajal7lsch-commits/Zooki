<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/CatalogoClinica.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/**
 * HU-3.4: desparasitaciones. Igual que las vacunas: guardan su clínica y su
 * veterinario (RN-112) y las ve toda clínica vinculada (RN-113).
 */
class Desparasitacion extends ModeloHistoria
{
    /** Valores de los ENUM de la tabla `desparasitaciones`. */
    public const TIPOS = ['interna', 'externa'];
    public const PERIODICIDADES = ['mensual' => '+1 month', 'trimestral' => '+3 months', 'semestral' => '+6 months'];

    /** RE-3.4.1, RE-3.4.2 y RN-207: registra y calcula la próxima aplicación. */
    public function registrar(array $entrada): int
    {
        $idMascota = ValidadorClinico::id($entrada['id_mascota'] ?? null);
        if ($idMascota === null) {
            throw new InvalidArgumentException('La mascota indicada no es válida.');
        }
        $this->exigirMascotaActiva($idMascota);

        // Los ENUM de MySQL sin modo estricto guardan '' en silencio, así que
        // el valor se valida aquí contra la lista real de la columna.
        $tipo = ValidadorClinico::opcion($entrada['tipo'] ?? null, self::TIPOS);
        if ($tipo === null) {
            throw new InvalidArgumentException('El tipo de desparasitación debe ser interna o externa.');
        }

        $periodicidad = ValidadorClinico::opcion($entrada['periodicidad'] ?? null, array_keys(self::PERIODICIDADES));
        if ($periodicidad === null) {
            throw new InvalidArgumentException('La periodicidad debe ser mensual, trimestral o semestral.');
        }

        $producto = ValidadorClinico::textoRequerido($entrada['producto'] ?? null, 150);
        if ($producto === null) {
            throw new InvalidArgumentException('El producto es obligatorio.');
        }

        $fechaAplicacion = ValidadorClinico::fechaNoFutura($entrada['fecha_aplicacion'] ?? null);
        if ($fechaAplicacion === null) {
            throw new InvalidArgumentException('La fecha de aplicación no es válida o está en el futuro.');
        }
        $fechaProxima = (new DateTimeImmutable($fechaAplicacion))->modify(self::PERIODICIDADES[$periodicidad])->format('Y-m-d');

        $sql = 'INSERT INTO desparasitaciones
            (id_clinica, id_mascota, id_veterinario, tipo, producto, periodicidad, fecha_aplicacion, fecha_proxima, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->conn->prepare($sql)->execute([
            $this->clinica(),
            $idMascota,
            $this->veterinarioActual(),
            $tipo,
            $producto,
            $periodicidad,
            $fechaAplicacion,
            $fechaProxima,
            ValidadorClinico::textoOpcional($entrada['observaciones'] ?? null, 5000),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /** RE-2.10.1: desparasitaciones de todas las clínicas, con la que las aplicó. */
    public function findByMascota($idMascota): array
    {
        $idMascota = (int) $idMascota;
        $this->exigirMascotaVinculada($idMascota);

        $sql = 'SELECT d.*, cl.nombre AS clinica_nombre, u.nombre_completo AS veterinario
            FROM desparasitaciones d
            JOIN clinicas cl ON cl.id_clinica = d.id_clinica
            LEFT JOIN usuarios u ON u.id_usuario = d.id_veterinario
            WHERE d.id_mascota = ?
            ORDER BY d.fecha_aplicacion DESC, d.id_desparasitacion DESC';
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$idMascota]);
        return $this->marcarOrigen($consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Próximas aplicaciones de los 7 días siguientes, solo de la clínica activa. */
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

        $sql = "SELECT d.*, m.nombre AS nombre_mascota, e.nombre_especie, u.nombre_completo AS propietario
            FROM desparasitaciones d
            JOIN mascotas m ON m.id_mascota = d.id_mascota
            JOIN mascota_clinica mc ON mc.id_mascota = d.id_mascota AND mc.id_clinica = d.id_clinica
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            LEFT JOIN usuarios u ON u.id_usuario = m.id_propietario
            WHERE d.id_clinica = ? AND mc.estado = 'activo' AND m.estado = 1
              AND d.fecha_proxima BETWEEN ? AND ?
            ORDER BY d.fecha_proxima ASC, m.nombre ASC";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([$this->clinica(), $inicio, $fin]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /** RN-112 / RE-2.10.4: punto único por el que pasa cualquier modificación. */
    public function paraModificar(int $idDesparasitacion): array
    {
        $consulta = $this->conn->prepare('SELECT * FROM desparasitaciones WHERE id_desparasitacion = ? AND id_clinica = ?');
        $consulta->execute([$idDesparasitacion, $this->clinica()]);
        $desparasitacion = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($desparasitacion === false) {
            $this->denegarAcceso('desparasitaciones', $idDesparasitacion, 'Modificación de una desparasitación de otra clínica (RN-112)');
        }
        return $desparasitacion;
    }

    public function getProductos(): array
    {
        return (new CatalogoClinica($this->conn))->productos();
    }
}
